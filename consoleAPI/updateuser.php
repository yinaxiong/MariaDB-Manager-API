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
     
    function updateUser() {

		if (isset($_GET["system"]) && isset($_GET["id"])) {
			$system = $_GET["system"];
			$id = $_GET["id"];
			
			if (isset($_GET["name"]))
				$name = $_GET["name"];
			else
				$name = null;

			if (isset($_GET["password"]))
				$password = $_GET["password"];
			else
				$password = null;

			if (!is_null($name) && !is_null($password))
				$cmd = "UPDATE Users SET UserName='".$name."', Password='".$password."' WHERE UserID=".$id;
			else if (!is_null($name))
				$cmd = "UPDATE Users SET UserName='".$name."' WHERE UserID=".$id;
			else if (!is_null($password))
				$cmd = "UPDATE Users SET Password='".$password."' WHERE UserID=".$id;

			$update = $this->db->prepare($cmd);        	
        	$update->execute();

        	$result = array(
            	"id" => $cmd,
        	);
			
        	sendResponse(200, json_encode($result));
        	return true;
        }
        
    }
        
}

// This is the first thing that gets called when this page is loaded
$api = new SkyConsoleAPI;
$api->updateUser();
