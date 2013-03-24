<?php

include 'commons.php';
include 'config.php';

class SkyConsoleAPI {
    // Constructor - open connection
    function __construct() {
    	global $DBPath;
		$this->db = new PDO('sqlite:'.$DBPath);
    }
 
    // Destructor - close connection
    function __destruct() {
    	$this->db = null;
    }
     
    function commands() {

		if (isset($_GET["system"]) && isset($_GET["name"]) && isset($_GET["password"])) {
			$system = $_GET["system"];
			$username = $_GET["name"];
			$password = $_GET["password"];

			$saltquery = $this->db->prepare('SELECT Salt FROM Users WHERE UserName = :username');
			$saltquery->execute(array(':username' => $username));
			$salt = $saltquery->fetch(PDO::FETCH_COLUMN);
			$passwordhash = sha1($salt.$password);
			$query = $this->db->prepare('SELECT UserID FROM Users WHERE UserName = :username AND Password = :password');
			$query->execute(array(
				':username' => $username,
				':password' => $passwordhash
			));
			$idset = $query->fetch(PDO::FETCH_COLUMN);
			if (empty($idset)) sendResponse(200, json_encode(array('id' => null)));
			else {
				$result = $this->loginValidUser($idset[0], $system,$username);
				sendResponse(200, json_encode($result));
			}
        }
        
    }
	
	protected function loginValidUser ($id, $system, $username) {
		// update LastAccess timestamp
		$now = new DateTime("now", new DateTimeZone('Europe/Rome'));
		$time = $now->format('Y-m-d H:i:s');
		$cmd = "UPDATE System SET LastAccess='".$time."' WHERE SystemID=".$system;
		$update = $this->db->prepare($cmd);        	
   		$update->execute();

		$query = $this->db->query("SELECT Property,Value FROM UserProperties WHERE UserID='".$id."'");
			
		foreach ($query as $row) {
			$property = $row['Property'];
			$value = $row['Value'];
			$properties[] = array("property" => $property, "value" => $value);
		}
	
       	return array(
        	"id" => $id,
   	    	"properties" => $properties,
   		);
	}
        
}

// This is the first thing that gets called when this page is loaded
$api = new SkyConsoleAPI;
$api->commands();
 
?>