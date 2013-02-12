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
			$name = $_GET["name"];
			$password = $_GET["password"];

			$insert = $this->db->prepare("INSERT INTO Users (UserID, UserName,Password) VALUES(null,'$name','$password')");        	
        	$insert->execute();
        	$rowID = $this->db->lastInsertId();
		
			$update = $this->db->prepare("UPDATE Users SET UserID=".$rowID." WHERE rowid=".$rowID);        	
			$update->execute();	

        	$result = array(
            	"id" => $rowID,
        	);
			
        	sendResponse(200, json_encode($result));
        	return true;
        }
        
    }
        
}

// This is the first thing that gets called when this page is loaded
$api = new SkyConsoleAPI;
$api->createUser();
 
?>