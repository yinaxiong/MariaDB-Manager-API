<?php

include 'commons.php';
include 'config.php';

class SkyConsoleAPI {
    // Constructor - open connection
    function __construct() {
    	global $DBPath;
		$this->db = new PDO('sqlite:'.$DBPath);
		$this->db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING );  
    }
 
    // Destructor - close connection
    function __destruct() {
    	$this->db = null;
    }
     
    function createUser() {

		if (isset($_GET["system"]) && isset($_GET["name"]) && isset($_GET["password"])) {
			$system = $_GET["system"];
			$username = $_GET["name"];
			$password = $_GET["password"];

			$salt = $this->makeSalt();
			$passwordhash = sha1($salt.$password);
			try {
				$query = $this->db->prepare("INSERT INTO Users (UserName, Password, Salt) VALUES (:username, :password, :salt)");
				$query->execute(array(
					':username' => $username,
					':password' => $passwordhash,
					':salt' => $salt
				));
				sendResponse(200, json_encode(array('id' => $this->db->lastInsertId())));
			}
			catch (PDOException $pe) {
				sendResponse(409, json_encode(array('error' => 'User insertion failed - perhaps username is a duplicate')));
			}
        }
        
    }
        
	protected function makeSalt () {
		return $this->makeRandomString(24);
	}
	
	protected function makeRandomString ($length=8) {
		$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!%,-:;@_{}~";
		for ($i = 0, $makepass = '', $len = strlen($chars); $i < $length; $i++) $makepass .= $chars[mt_rand(0, $len-1)];
		return $makepass;
	}
}

// This is the first thing that gets called when this page is loaded
$api = new SkyConsoleAPI;
$api->createUser();
 
?>