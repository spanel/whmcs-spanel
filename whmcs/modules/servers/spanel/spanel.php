<?php

/*
************************************************************************************
*************************** WHMCS Server Module Template ***************************
************************************************************************************
You will need to rename this file to be the name of the module you are creating.
You should then replace all occurrences of servertemplate with the new filename.
************************************************************************************
************************************************************************************
*/

require_once "HTTP/Client.php";

# given a server IP, returns the spanel hostname for it. or, returns a negative
# number on error

function _spanel_gethostname($serverip) {
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

function _spanel_api0_hostname($hostname, $secure, $user, $pass, $func, $args=array()) {
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
	$yaml_result['_spanel_hostname'] = $hostname;
        return $yaml_result;
}

# invokes API function on an spanel panel. returns API response structure. see
# doc/api0.pod for API response examples.

function _spanel_api0_ip($ip, $secure, $user, $pass, $func, $args=array()) {
	if (!$ip) return array('status'=>400, 'message'=>"No IP supplied");
	$hostname = _spanel_gethostname($ip);
	if ($hostname < 0) return array('status'=>-$hostname, 'message'=>"Cannot find Spanel hostname, please check whether https://$ip/ or https://$ip/spanel/spanel.cgi is an Spanel login page and accessible");
	#print($hostname);
	return _spanel_api0_hostname($hostname, $secure, $user, $pass, $func, $args);
}

###

function spanel_ConfigOptions() {
	$configarray = array(
	 "Package Name" => array( "Type" => "text", "Size" => "25", ),
	 "Web Space Quota" => array( "Type" => "text", "Size" => "5", "Description" => "MB" ),
	 "Shell Access" => array( "Type" => "yesno", "Description" => "Tick to grant access" ),
	);
	return $configarray;
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

	$result = "";
	do {
		#print_r($params);
		$res = _spanel_api0_ip($serverip, $secure, $serverusername, $serverpassword, "create_account",
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
			$result = "BUG: bug in spanel.php: invalid return value from spanel_api0_ip() function: res=$res";
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

	$result = "";
	do {
		#print_r($params);
		$res = _spanel_api0_ip($serverip, $secure, $serverusername, $serverpassword, "delete_account",
				       array(
					     'username' => $username,
					     'reason' => "[Account deleted by WHMCS Spanel module on ".date("D M j G:i:s T Y")." for account ID $accountid from IP $_SERVER[REMOTE_ADDR] browser $_SERVER[HTTP_USER_AGENT]]",
					     'dont_email_client' => 1,
					     )
				       );
		#print_r($res);
		if (!$res || !$res['status']) {
			$result = "BUG: bug in spanel.php: invalid return value from spanel_api0_ip() function: res=$res";
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

	$result = "";
	do {
		#print_r($params);
		$res = _spanel_api0_ip($serverip, $secure, $serverusername, $serverpassword, "suspend_account",
				       array(
					     'username' => $username,
					     'reason' => "[Account suspended by WHMCS Spanel module on ".date("D M j G:i:s T Y")." for account ID $accountid from IP $_SERVER[REMOTE_ADDR] browser $_SERVER[HTTP_USER_AGENT]]",
					     'dont_email_client' => 1,
					     )
				       );
		#print_r($res);
		if (!$res || !$res['status']) {
			$result = "BUG: bug in spanel.php: invalid return value from spanel_api0_ip() function: res=$res";
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

	$result = "";
	do {
		#print_r($params);
		$res = _spanel_api0_ip($serverip, $secure, $serverusername, $serverpassword, "unsuspend_account",
				       array(
					     'username' => $username,
					     'reason' => "[Account unsuspended by WHMCS Spanel module on ".date("D M j G:i:s T Y")." for account ID $accountid from IP $_SERVER[REMOTE_ADDR] browser $_SERVER[HTTP_USER_AGENT]]",
					     'dont_email_client' => 1,
					     )
				       );
		#print_r($res);
		if (!$res || !$res['status']) {
			$result = "BUG: bug in spanel.php: invalid return value from spanel_api0_ip() function: res=$res";
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

	$result = "";
	do {
		#print_r($params);
		$res = _spanel_api0_ip($serverip, $secure, $serverusername, $serverpassword, "change_account_password",
				       array(
					     'username' => $username,
					     'clientpassword' => $password,
					     'reason' => "[Account password changed by WHMCS Spanel module on ".date("D M j G:i:s T Y")." to plan $packageid for account ID $accountid from IP $_SERVER[REMOTE_ADDR] browser $_SERVER[HTTP_USER_AGENT]]",
					     'dont_email_client' => 1,
					     )
				       );
		#print_r($res);
		if (!$res || !$res['status']) {
			$result = "BUG: bug in spanel.php: invalid return value from spanel_api0_ip() function: res=$res";
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

function spanel_ChangePackage($params) {
	$serverip = $params["serverip"];
	$serverusername = $params["serverusername"];
	$serverpassword = $params["serverpassword"];
	$secure = $params["serversecure"];
	$domain = strtolower($params["domain"]);
	$username = $params["username"];
	$accountid = $params["accountid"];
	$packageid = $params["packageid"];

	$result = "";
	do {
		#print_r($params);
		$res = _spanel_api0_ip($serverip, $secure, $serverusername, $serverpassword, "change_account_plan",
				       array(
					     'username' => $username,
					     'plan' => $params['configoption1'],
					     'note' => "[Account plan changed by WHMCS Spanel module on ".date("D M j G:i:s T Y")." to plan $packageid for account ID $accountid from IP $_SERVER[REMOTE_ADDR] browser $_SERVER[HTTP_USER_AGENT]]",
					     'dont_email_client' => 1,
					     )
				       );
		#print_r($res);
		if (!$res || !$res['status']) {
			$result = "BUG: bug in spanel.php: invalid return value from spanel_api0_ip() function: res=$res";
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

function spanel_LoginLink($params) {
	if ($params["serversecure"]) {
		$http="https";
	} else {
		$http="http";
	}
	echo("<a href=\"".$http."://".$params["serverip"]."/spanel/spanel.cgi\" target=\"_blank\" style=\"color:#cc0000\">login to control panel</a>");
}

?>
