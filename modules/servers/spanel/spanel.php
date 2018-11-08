<?php

if (!defined("WHMCS")) {
  die("This file cannot be accessed directly");
}

if (!extension_loaded('yaml')) {
  die('This module requires the "yaml" PHP extension, please enable it first in your php.ini');
}

require_once "HTTP/Client.php";
require_once __DIR__ . "/../../../includes/phi_access_http_client.inc.php";

function detect_spanel($serverip) {
  $cli = new HTTP_Client();
  $respcode = $cli->get("http://$serverip/?info=1");
  if ($respcode == 200) {
    $resp = $cli->currentResponse();
    if (preg_match('#SPANEL_URL=(.+)#', $resp['body'], $m)) {
      $data = array('is_spanel' => true, 'spanel_url' => $m[1]);
      if (preg_match('#SPANEL_API_VERSION=(.+)#', $resp['body'], $m)) {
        $data['spanel_api_version'] = $m[1];
      } else {
        $data['spanel_api_version'] = 0;
      }
      return array(200, "OK", $data);
    } else {
      return array(200, "OK", array('is_spanel' => false));
    }
  } else {
    return array($respcode, "Network error");
  }
}

function _spanel_api($ip, $user, $pass, $module, $func, $args=array()) {
	if (!$ip) return array(400, "BUG: no server IP address, please check your WHMCS servers configuration");
        $res = phi_http_request("call",
                                "https://$ip:1010/api/$module/$func",
                                array("args"=>$args),
                                array("ssl_verify_peer"=>0, "user"=>$user, "password"=>$pass));
        $res[3]['spanel.ip'] = $ip;
        return $res;
}

### BEGIN OLD CODE

# given a server IP, returns the spanel hostname for it. or, returns a negative
# number on error

function _spanelold_gethostname($serverip) {
	$cli = new HTTP_Client();

        # assume spanel is in http://IP/
        $respcode = $cli->get("http://$serverip/?info=1");
	if ($respcode == 200) {
          $resp = $cli->currentResponse();
          if (preg_match('#SPANEL_URL=https?://(.+?)/#', $resp['body'], $m)) return $m[1];
        }

	# try getting hostname from IP/spanel/spanel.cgi script
        $respcode = $cli->get("http://$serverip/spanel/spanel.cgi?info=1");
	if ($respcode != 200) return -$respcode;
	$resp = $cli->currentResponse();
	if (preg_match('#<FRAME SRC="https?://(.+?)/">#', $resp['body'], $m)) return $m[1];
	if (preg_match('#SPANEL_URL=https?://(.+?)/#', $resp['body'], $m)) return $m[1];
        return -1;
}

# invokes API function on an spanel panel. returns API response structure. see
# doc/api0.pod for API response examples. or, returns a negative number on
# error.

function _spanelold_api0_hostname($hostname, $secure, $user, $pass, $func, $args=array()) {
	$cli = new HTTP_Client();
	$params = "user=".urlencode($user).
		"&pass=".urlencode($pass).
		"&func=".urlencode($func);
	foreach ($args as $k => $v) $params .= "&".urlencode($k)."=".urlencode($v);
	$respcode = $cli->post(($secure ? "https" : "http") . "://$hostname/api0.cgi", $params, true);
	$resp = $cli->currentResponse();
	#echo $resp['body'];
	if ($respcode != 200) return array('status'=>$respcode, 'message'=>"Cannot invoke /api0.cgi successfully", 'raw_output'=>$resp['body']);
	$yaml_result = yaml_parse($resp['body']);
	#print_r($yaml_result); # jika mati di sini, berarti yaml parser gagal
	if (preg_match('/^Error at/', $yaml_result)) {
		return array('status'=>500, 'message'=>"API response returns invalid YAML", 'raw_output'=>$resp['body']);
	}
	$yaml_result['_spanelold_hostname'] = $hostname;
        return $yaml_result;
}

# invokes API function on an spanel panel. returns API response structure. see
# doc/api0.pod for API response examples.

function _spanelold_api0_ip($ip, $secure, $user, $pass, $func, $args=array()) {
	if (!$ip) return array('status'=>400, 'message'=>"No IP supplied");
	$hostname = _spanelold_gethostname($ip);
	if ($hostname < 0) return array('status'=>-$hostname, 'message'=>"Cannot find Spanel hostname, please check whether https://$ip/ or https://$ip/spanel/spanel.cgi is an Spanel login page and accessible");
	#print($hostname);
	return _spanelold_api0_hostname($hostname, $secure, $user, $pass, $func, $args);
}

### END OLD CODE

###

function spanel_MetaData() {
  return array(
               'DisplayName' => 'Spanel',
               'APIVersion' => '1.1',
               'RequiresServer' => true,
               'DefaultNonSSLPort' => '0',
               'DefaultSSLPort' => '1010',
               'ServiceSingleSignOnLabel' => 'Login to Spanel as User',
               'AdminSingleSignOnLabel' => 'Login to Spanel as Admin',
               );
}

function spanel_ConfigOptions() {
	$configarray = array(
	 "Package Name" => array( "Type" => "text", "Size" => "25", ),
	 "Web Space Quota" => array( "Type" => "text", "Size" => "5", "Description" => "MB" ),
	 "Shell Access" => array( "Type" => "yesno", "Description" => "Tick to grant access" ),
	);
	return $configarray;
}

function _fmt_rinci_err($res) {
  $msg = "ERROR: API function did not return success: $res[0] - $res[1]";
  $prev = $res;
  while (isset($prev[3]) && $prev[3]['prev']) {
    $prev = $res[3]['prev'];
    $msg .= ": $prev[0] - $prev[1]";
  }
  $msg .= " (spanel ip=".$res[3]['spanel.ip'].")";
  return $msg;
}

function spanel_CreateAccount($params) {
	$serverip = $params["serverip"];
	$serverusername = $params["serverusername"];
	$serverpassword = $params["serverpassword"];
	$secure = $params["serversecure"];
	$domain = strtolower($params["domain"]);
	$packagetype = $params["type"]; # hostingaccount or reselleraccount
	$username = $params["username"];
	$password = $params["password"];
	$accountid = $params["accountid"];
	$packageid = $params["packageid"];
	$clientsdetails = $params["clientsdetails"];
	# Code to perform action goes here...

        $res = detect_spanel($serverip);
        if ($res[0] != 200) {
          $result = "ERROR: Can't detect spanel at $serverip: $res[0] - $res[1]";
        } elseif ($res[2]['spanel_api_version']) { # new

        #print_r($params);
        $res = _spanel_api($serverip, $serverusername, $serverpassword, "account.shared.modify", "create_account",
                           array(
                                 'domain' => $domain,
                                 'plan' => $params['configoption1'],
                                 'account' => $username,
                                 'password' => $password,
                                 'note' => "[Account created by WHMCS Spanel module on ".date("D M j G:i:s T Y")." for client $clientsdetails[firstname] $clientsdetails[lastname] <$clientsdetails[email]> (ID $clientsdetails[userid]) from IP $_SERVER[REMOTE_ADDR] browser $_SERVER[HTTP_USER_AGENT]]",
                                 'extra_info' => $params,
                                )
                          );
        #print_r($res);
        if ($res[0] != 200) {
          $result = _fmt_rinci_err($res);
        } else {
          $result = "success";
        }
  	return $result;

        } else {

	$result = "";
	do {
		#print_r($params);
		$res = _spanelold_api0_ip($serverip, $secure, $serverusername, $serverpassword, "create_account",
				       array(
					     'clientname' => preg_replace('/^\s+/', '', "$clientsdetails[firstname] $clientsdetails[lastname]"),
					     'clientorganization' => ($clientsdetails['companyname'] ? $clientsdetails['companyname'] : "(no company)"),
					     'clientstreet' => $clientsdetails['address1'] . ($clientsdetails['address2'] ? "; $clientsdetails[address2]" : ""),
					     'clientcity' => $clientsdetails['city'],
					     'clientprovince' => $clientsdetails['state'],
					     'clientcountry' => $clientsdetails['country'],
					     'clientzipcode' => $clientsdetails['postcode'],
					     'clientphone' => $clientsdetails['phonenumber'],
					     'clientemail' => $clientsdetails['email'],
					     'domain' => $domain,
					     'plan' => $params['configoption1'],
					     'username' => $username,
					     'clientpassword' => $password,
					     'note' => "[Account created by WHMCS Spanel module on ".date("D M j G:i:s T Y")." for client $clientsdetails[firstname] $clientsdetails[lastname] <$clientsdetails[email]> (ID $clientsdetails[userid]) from IP $_SERVER[REMOTE_ADDR] browser $_SERVER[HTTP_USER_AGENT]]",
					     'dont_email_client' => 1,
                                             'extra_info' => yaml_emit($params),
					     )
				       );
		#print_r($res);
		if (!$res || !$res['status']) {
			$result = "BUG: bug in spanelold.php: invalid return value from spanelold_api0_ip() function: res=$res";
			break;
		}
		if ($res['status'] != 200) {
			$result = "ERROR: API function did not return success: status=$res[status], message=$res[message], raw output=$res[raw_output]";
			break;
		}
		else {
			$result = "success";
		}

	} while (0);

	return $result;

        }
}

function spanel_TerminateAccount($params) {
	$serverip = $params["serverip"];
	$serverusername = $params["serverusername"];
	$serverpassword = $params["serverpassword"];
	$secure = $params["serversecure"];
	$domain = strtolower($params["domain"]);
	$packagetype = $params["type"]; # hostingaccount or reselleraccount
	$username = $params["username"];
	$accountid = $params["accountid"];
	$packageid = $params["packageid"];

        $res = detect_spanel($serverip);
        if ($res[0] != 200) {
          $result = "ERROR: Can't detect spanel at $serverip: $res[0] - $res[1]";
        } elseif ($res[2]['spanel_api_version']) { # new

        #print_r($params);
        $res = _spanel_api($serverip, $serverusername, $serverpassword, "account.shared.modify", "delete_account",
                           array(
                                 'account' => $username,
                                 'note' => "[Account deleted by WHMCS Spanel module on ".date("D M j G:i:s T Y")." for account ID $accountid from IP $_SERVER[REMOTE_ADDR] browser $_SERVER[HTTP_USER_AGENT]]",
                                 )
                           );
        #print_r($res);
        if ($res[0] != 200) {
          $result = _fmt_rinci_err($res);
        } else {
          $result = "success";
        }
	return $result;

        } else {

	$result = "";
	do {
		#print_r($params);
		$res = _spanelold_api0_ip($serverip, $secure, $serverusername, $serverpassword, "delete_account",
				       array(
					     'username' => $username,
					     'reason' => "[Account deleted by WHMCS Spanel module on ".date("D M j G:i:s T Y")." for account ID $accountid from IP $_SERVER[REMOTE_ADDR] browser $_SERVER[HTTP_USER_AGENT]]",
					     'dont_email_client' => 1,
					     )
				       );
		#print_r($res);
		if (!$res || !$res['status']) {
			$result = "BUG: bug in spanelold.php: invalid return value from spanelold_api0_ip() function: res=$res";
			break;
		}
		elseif ($res['status'] != 200) {
			$result = "ERROR: API function did not return success: status=$res[status], message=$res[message], raw output=$res[raw_output]";
			break;
		}
		else {
			$result = "success";
		}

	} while (0);

	return $result;

        }
}

function spanel_SuspendAccount($params) {
	$serverip = $params["serverip"];
	$serverusername = $params["serverusername"];
	$serverpassword = $params["serverpassword"];
	$secure = $params["serversecure"];
	$domain = strtolower($params["domain"]);
	$packagetype = $params["type"]; # hostingaccount or reselleraccount
	$username = $params["username"];
	$accountid = $params["accountid"];
	$packageid = $params["packageid"];

        $res = detect_spanel($serverip);
        if ($res[0] != 200) {
          $result = "ERROR: Can't detect spanel at $serverip: $res[0] - $res[1]";
        } elseif ($res[2]['spanel_api_version']) { # new

        #print_r($params);
        $res = _spanel_api($serverip, $serverusername, $serverpassword, "account.shared.modify", "disable_account",
                           array(
                                 'account' => $username,
                                 'note' => "[Account suspended by WHMCS Spanel module on ".date("D M j G:i:s T Y")." for account ID $accountid from IP $_SERVER[REMOTE_ADDR] browser $_SERVER[HTTP_USER_AGENT]]",
                                 )
                           );
        #print_r($res);
        if ($res[0] != 200) {
          $result = _fmt_rinci_err($res);
        } else {
          $result = "success";
        }
	return $result;

        } else {

	$result = "";
	do {
		#print_r($params);
		$res = _spanelold_api0_ip($serverip, $secure, $serverusername, $serverpassword, "suspend_account",
				       array(
					     'username' => $username,
					     'reason' => "[Account suspended by WHMCS Spanel module on ".date("D M j G:i:s T Y")." for account ID $accountid from IP $_SERVER[REMOTE_ADDR] browser $_SERVER[HTTP_USER_AGENT]]",
					     'dont_email_client' => 1,
					     )
				       );
		#print_r($res);
		if (!$res || !$res['status']) {
			$result = "BUG: bug in spanelold.php: invalid return value from spanelold_api0_ip() function: res=$res";
			break;
		}
		elseif ($res['status'] != 200) {
			$result = "ERROR: API function did not return success: status=$res[status], message=$res[message], raw output=$res[raw_output]";
			break;
		}
		else {
			$result = "success";
		}

	} while (0);

	return $result;

        }
}

function spanel_UnsuspendAccount($params) {
	$serverip = $params["serverip"];
	$serverusername = $params["serverusername"];
	$serverpassword = $params["serverpassword"];
	$secure = $params["serversecure"];
	$domain = strtolower($params["domain"]);
	$packagetype = $params["type"]; # hostingaccount or reselleraccount
	$username = $params["username"];
	$accountid = $params["accountid"];
	$packageid = $params["packageid"];

        $res = detect_spanel($serverip);
        if ($res[0] != 200) {
          $result = "ERROR: Can't detect spanel at $serverip: $res[0] - $res[1]";
        } elseif ($res[2]['spanel_api_version']) { # new

        #print_r($params);
        $res = _spanel_api($serverip, $serverusername, $serverpassword, "account.shared.modify", "enable_account",
                           array(
                                 'account' => $username,
                                 #'note' => "[Account unsuspended by WHMCS Spanel module on ".date("D M j G:i:s T Y")." for account ID $accountid from IP $_SERVER[REMOTE_ADDR] browser $_SERVER[HTTP_USER_AGENT]]",
                                 )
                           );
        #print_r($res);
        if ($res[0] != 200) {
          $result = _fmt_rinci_err($res);
        } else {
          $result = "success";
        }
	return $result;

        } else {

	$result = "";
	do {
		#print_r($params);
		$res = _spanelold_api0_ip($serverip, $secure, $serverusername, $serverpassword, "unsuspend_account",
				       array(
					     'username' => $username,
					     'reason' => "[Account unsuspended by WHMCS Spanel module on ".date("D M j G:i:s T Y")." for account ID $accountid from IP $_SERVER[REMOTE_ADDR] browser $_SERVER[HTTP_USER_AGENT]]",
					     'dont_email_client' => 1,
					     )
				       );
		#print_r($res);
		if (!$res || !$res['status']) {
			$result = "BUG: bug in spanelold.php: invalid return value from spanelold_api0_ip() function: res=$res";
			break;
		}
		elseif ($res['status'] != 200) {
			$result = "ERROR: API function did not return success: status=$res[status], message=$res[message], raw output=$res[raw_output]";
			break;
		}
		else {
			$result = "success";
		}

	} while (0);

	return $result;

        }
}

function spanel_ChangePassword($params) {
	$serverip = $params["serverip"];
	$serverusername = $params["serverusername"];
	$serverpassword = $params["serverpassword"];
	$secure = $params["serversecure"];
	$domain = strtolower($params["domain"]);
	$username = $params["username"];
	$password = $params["password"];
	$accountid = $params["accountid"];
	$packageid = $params["packageid"];

        $res = detect_spanel($serverip);
        if ($res[0] != 200) {
          $result = "ERROR: Can't detect spanel at $serverip: $res[0] - $res[1]";
        } elseif ($res[2]['spanel_api_version']) { # new

        #print_r($params);
        $res = _spanel_api($serverip, $serverusername, $serverpassword, "account.shared.modify", "set_account_password",
                           array(
                                 'account' => $username,
                                 'password' => $password,
                                 #'note' => "[Account password changed by WHMCS Spanel module on ".date("D M j G:i:s T Y")." for account ID $accountid from IP $_SERVER[REMOTE_ADDR] browser $_SERVER[HTTP_USER_AGENT]]",
                                 )
                           );
        #print_r($res);
        if ($res[0] != 200) {
          $result = _fmt_rinci_err($res);
        } else {
          $result = "success";
        }
	return $result;

        } else {

	$result = "";
	do {
		#print_r($params);
		$res = _spanelold_api0_ip($serverip, $secure, $serverusername, $serverpassword, "change_account_password",
				       array(
					     'username' => $username,
					     'clientpassword' => $password,
					     'reason' => "[Account password changed by WHMCS Spanel module on ".date("D M j G:i:s T Y")." to plan $packageid for account ID $accountid from IP $_SERVER[REMOTE_ADDR] browser $_SERVER[HTTP_USER_AGENT]]",
					     'dont_email_client' => 1,
					     )
				       );
		#print_r($res);
		if (!$res || !$res['status']) {
			$result = "BUG: bug in spanelold.php: invalid return value from spanelold_api0_ip() function: res=$res";
			break;
		}
		elseif ($res['status'] != 200) {
			$result = "ERROR: API function did not return success: status=$res[status], message=$res[message], raw output=$res[raw_output]";
			break;
		}
		else {
			$result = "success";
		}

	} while (0);

	return $result;

        }
}

function spanel_ChangePackage($params) {
	$serverip = $params["serverip"];
	$serverusername = $params["serverusername"];
	$serverpassword = $params["serverpassword"];
	$secure = $params["serversecure"];
	$domain = strtolower($params["domain"]);
	$username = $params["username"];
	$accountid = $params["accountid"];
	$packageid = $params["packageid"];

        $res = detect_spanel($serverip);
        if ($res[0] != 200) {
          $result = "ERROR: Can't detect spanel at $serverip: $res[0] - $res[1]";
        } elseif ($res[2]['spanel_api_version']) { # new

        #print_r($params);
        $res = _spanel_api($serverip, $serverusername, $serverpassword, "account.shared.modify", "set_account_plan",
                           array(
                                 'account' => $username,
                                 'plan' => $params['configoption1'],
                                 #'note' => "[Account plan changed by WHMCS Spanel module on ".date("D M j G:i:s T Y")." to plan $packageid for account ID $accountid from IP $_SERVER[REMOTE_ADDR] browser $_SERVER[HTTP_USER_AGENT]]",
                                 )
                           );
        #print_r($res);
        if ($res[0] != 200) {
          $result = _fmt_rinci_err($res);
        } else {
          $result = "success";
        }
	return $result;

        } else {

	$result = "";
	do {
		#print_r($params);
		$res = _spanelold_api0_ip($serverip, $secure, $serverusername, $serverpassword, "change_account_plan",
				       array(
					     'username' => $username,
					     'plan' => $params['configoption1'],
					     'note' => "[Account plan changed by WHMCS Spanel module on ".date("D M j G:i:s T Y")." to plan $packageid for account ID $accountid from IP $_SERVER[REMOTE_ADDR] browser $_SERVER[HTTP_USER_AGENT]]",
					     'dont_email_client' => 1,
					     )
				       );
		#print_r($res);
		if (!$res || !$res['status']) {
			$result = "BUG: bug in spanelold.php: invalid return value from spanelold_api0_ip() function: res=$res";
			break;
		}
		elseif ($res['status'] != 200) {
			$result = "ERROR: API function did not return success: status=$res[status], message=$res[message], raw output=$res[raw_output]";
			break;
		}
		else {
			$result = "success";
		}

	} while (0);

	return $result;

        }
}

function spanel_LoginLink($params) {
	if ($params["serversecure"]) {
		$http="https";
	} else {
		$http="http";
	}
	echo("<a href=\"".$http."://".$params["serverip"]."/spanel/spanel.cgi\" target=\"_blank\" style=\"color:#cc0000\">login to control panel</a>");
}

function spanel_ClientArea($params) {
  return array(
               'tabOverviewModuleOutputTemplate' => 'templates/loginbuttons.tpl',
               'templateVariables' => array(
                                            ),
               );
}

function spanel_ServiceSingleSignOn($params) {
  global $SPANEL_API_USER;
  global $SPANEL_API_PASSWORD;

  if (!isset($SPANEL_API_USER) || !isset($SPANEL_API_PASSWORD)) {
    return array('success' => false, 'errorMsg' => 'Spanel API user/password not defined');
  }

  $res = detect_spanel($params['serverip']);
  if ($res[0] != 200) {
    return array('success' => false, 'errorMsg' => "Can't detect spanel at IP $params[serverip]: $res[0] - $res[1]");
  }
  if (!$res[2]['is_spanel']) {
    return array('success' => false, 'errorMsg' => "Server at IP $params[serverip] is not Spanel");
  }

  $spanel_admin_user = "";
  if ($_SESSION['adminid']) {
    $mysqlres = mysql_query("SELECT username,notes FROM tbladmins WHERE id=$_SESSION[adminid]") or die("BUG:20181108-1:".mysql_error());
    $row = mysql_fetch_row($mysqlres);
    $admin_username = $row[0];
    if (preg_match('/adminuser=(\w+)/', $row[1], $m)) $spanel_admin_user = $m[1];
  }

  $spanel_url = preg_replace('/^http:/', 'https:', $res[2]['spanel_url']);
  $spanel_url = preg_replace('#/$#', '', $spanel_url);
  if(!$res[2]['spanel_api_version']) { # old
    $res = _spanelold_api0_ip($spanel_ip, 1, $SPANEL_API_USER, $SPANEL_API_PASSWORD,
                              "gen_login_token",
                              array("username"=>$params['username'],
                                    "ip"=>$_SERVER['REMOTE_ADDR'],
                                    "admin_user"=>$spanel_admin_user)
                              );
    if (!$res || $res['status'] != 200) {
      return array('success' => false, 'errorMsg' => "Can't get login token: ".print_r($res, 1));
    }
    $token = $res['output'];
    if ($token < 0) {
      return array('success' => false, 'errorMsg' => "Can't get login token, got $token");
    }
  } else {
    $res = _spanel_api($params['serverip'], $SPANEL_API_USER, $SPANEL_API_PASSWORD,
                       "tmp.wwwutil", "gen_login_token",
                       array("account"=>$params['username'],
                             "ip"=>$_SERVER['REMOTE_ADDR'],
                             "admin_account"=>$spanel_admin_user)
                       );
    if ($res[0] != 200) {
      return array('success' => false, 'errorMsg' => "Can't get login token: ".print_r($res, 1));
    }
    $token = $res[2];
  }
  logActivity("Login to Spanel: client ID=$params[userid], ".($spanel_admin_user ? ", admin_user=$spanel_admin_user":"").", serverip=$params[serverip], username=$params[username]", 0);
  $login_note = "from Masterkey";
  $url = "$spanel_url/login.html?token=$token&login_note=".urlencode($login_note);
  return array(
               'success' => true,
               'redirectTo' => $url,
               );
}

?>
