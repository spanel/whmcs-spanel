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

require_once "spanel_api_client.inc.php";

function spanelhs_ConfigOptions() {
	$configarray = array(
	 "Package Name" => array( "Type" => "text", "Size" => "25", ),
	 "Web Space Quota" => array( "Type" => "text", "Size" => "5", "Description" => "MB" ),
	 "Shell Access" => array( "Type" => "yesno", "Description" => "Tick to grant access" ),
	);
	return $configarray;
}

function spanelhs_CreateAccount($params) {
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

        #print_r($params);
        $res = spanel_call_api_http("Account::XenVPS::modify", "create_account",
                                    array(
                                          'email'      => $clientsdetails['email'],
                                          #'domain'     => $domain,
                                          'plan'       => $params['configoption1'],
                                          'account'    => $username,
                                          'password'   => $password,
                                          'note'       => "[Account created by WHMCS SpanelHS module on ".date("D M j G:i:s T Y")." for client $clientsdetails[firstname] $clientsdetails[lastname] <$clientsdetails[email]> (ID $clientsdetails[userid]) from IP $_SERVER[REMOTE_ADDR] browser $_SERVER[HTTP_USER_AGENT]]",
                                          'extra_info' => yaml_emit($params),
                                          ),
                                    array(
                                          'host'       => $serverip,
                                          'port'       => 443,
                                          'user'       => $serverusername,
                                          'password'   => $serverpassword,
                                          )
                                    );
	return $res[0] == 200 ? "success" : "ERROR $res[0]: $res[1]";
}

function spanelhs_TerminateAccount($params) {
	$serverip = $params["serverip"];
	$serverusername = $params["serverusername"];
	$serverpassword = $params["serverpassword"];
	$secure = $params["serversecure"];
	$domain = strtolower($params["domain"]);
	$packagetype = $params["type"]; # hostingaccount or reselleraccount
	$username = $params["username"];
	$accountid = $params["accountid"];
	$packageid = $params["packageid"];

        $res = spanel_call_api_http("Account::XenVPS::modify", "delete_account",
                                    array(
                                          'account'    => $username,
                                          'note'       => "[Account deleted by WHMCS SpanelHS module on ".date("D M j G:i:s T Y")." for account ID $accountid from IP $_SERVER[REMOTE_ADDR] browser $_SERVER[HTTP_USER_AGENT]]",
                                          ),
                                    array(
                                          'host'       => $serverip,
                                          'port'       => 443,
                                          'user'       => $serverusername,
                                          'password'   => $serverpassword,
                                          )
                                    );
        return $res[0] == 200 ? "success" : "ERROR $res[0]: $res[1]";
}

function spanelhs_SuspendAccount($params) {
	$serverip = $params["serverip"];
	$serverusername = $params["serverusername"];
	$serverpassword = $params["serverpassword"];
	$secure = $params["serversecure"];
	$domain = strtolower($params["domain"]);
	$packagetype = $params["type"]; # hostingaccount or reselleraccount
	$username = $params["username"];
	$accountid = $params["accountid"];
	$packageid = $params["packageid"];

        $res = spanel_call_api_http("Account::XenVPS::modify", "suspend_account",
                                    array(
                                          'account'    => $username,
                                          'note'       => "[Account suspended by WHMCS SpanelHS module on ".date("D M j G:i:s T Y")." for account ID $accountid from IP $_SERVER[REMOTE_ADDR] browser $_SERVER[HTTP_USER_AGENT]]",
                                          ),
                                    array(
                                          'host'       => $serverip,
                                          'port'       => 443,
                                          'user'       => $serverusername,
                                          'password'   => $serverpassword,
                                          )
                                    );
        return $res[0] == 200 ? "success" : "ERROR $res[0]: $res[1]";
	$result = "";
}

function spanelhs_UnsuspendAccount($params) {
	$serverip = $params["serverip"];
	$serverusername = $params["serverusername"];
	$serverpassword = $params["serverpassword"];
	$secure = $params["serversecure"];
	$domain = strtolower($params["domain"]);
	$packagetype = $params["type"]; # hostingaccount or reselleraccount
	$username = $params["username"];
	$accountid = $params["accountid"];
	$packageid = $params["packageid"];

        $res = spanel_call_api_http("Account::XenVPS::modify", "unsuspend_account",
                                    array(
                                          'account'    => $username,
                                          #'note'       => "[Account unsuspended by WHMCS SpanelHS module on ".date("D M j G:i:s T Y")." for account ID $accountid from IP $_SERVER[REMOTE_ADDR] browser $_SERVER[HTTP_USER_AGENT]]",
                                          ),
                                    array(
                                          'host'       => $serverip,
                                          'port'       => 443,
                                          'user'       => $serverusername,
                                          'password'   => $serverpassword,
                                          )
                                    );
        return $res[0] == 200 ? "success" : "ERROR $res[0]: $res[1]";
}

function spanelhs_ChangePassword($params) {
	$serverip = $params["serverip"];
	$serverusername = $params["serverusername"];
	$serverpassword = $params["serverpassword"];
	$secure = $params["serversecure"];
	$domain = strtolower($params["domain"]);
	$username = $params["username"];
	$password = $params["password"];
	$accountid = $params["accountid"];
	$packageid = $params["packageid"];

        $res = spanel_call_api_http("Account::XenVPS::modify", "set_account_password",
                                    array(
                                          'account'    => $username,
                                          'password'   => $password,
                                          #'note'       => "[Account password changed by WHMCS SpanelHS module on ".date("D M j G:i:s T Y")." for account ID $accountid from IP $_SERVER[REMOTE_ADDR] browser $_SERVER[HTTP_USER_AGENT]]",
                                          ),
                                    array(
                                          'host'       => $serverip,
                                          'port'       => 443,
                                          'user'       => $serverusername,
                                          'password'   => $serverpassword,
                                          )
                                    );
        return $res[0] == 200 ? "success" : "ERROR $res[0]: $res[1]";
}

function spanelhs_ChangePackage($params) {
	$serverip = $params["serverip"];
	$serverusername = $params["serverusername"];
	$serverpassword = $params["serverpassword"];
	$secure = $params["serversecure"];
	$domain = strtolower($params["domain"]);
	$username = $params["username"];
	$accountid = $params["accountid"];
	$packageid = $params["packageid"];

        $res = spanel_call_api_http("Account::XenVPS::modify", "set_account_plan",
                                    array(
                                          'account'    => $username,
                                          'plan'       => $params['configoption1'],
                                          'force'      => 1,
                                          #'note'       => "[Account plan changed to $params[configoption1] by WHMCS SpanelHS module on ".date("D M j G:i:s T Y")." for account ID $accountid from IP $_SERVER[REMOTE_ADDR] browser $_SERVER[HTTP_USER_AGENT]]",
                                          ),
                                    array(
                                          'host'       => $serverip,
                                          'port'       => 443,
                                          'user'       => $serverusername,
                                          'password'   => $serverpassword,
                                          )
                                    );
	return $result;
}

function spanelhs_LoginLink($params) {
	if ($params["serversecure"]) {
		$http="https";
	} else {
		$http="http";
	}
	echo("<a href=\"".$http."://".$params["serverip"]."/spanel/spanel.cgi\" target=\"_blank\" style=\"color:#cc0000\">login to control panel</a>");
}

?>
