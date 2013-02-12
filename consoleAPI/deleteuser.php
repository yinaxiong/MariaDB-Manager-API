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
     
    function deleteUser() {

		if (isset($_GET["system"]) && isset($_GET["id"])) {
			$system = $_GET["system"];
			$id = $_GET["id"];

			$delete = $this->db->prepare("DELETE FROM 'Users' WHERE UserID=".$id);       	
        	$delete->execute();

        	$result = array(
            	"id" => $id,
        	);
			
        	sendResponse(200, json_encode($result));
        	return true;
        }
        
    }
        
}

// This is the first thing that gets called when this page is loaded
$api = new SkyConsoleAPI;
$api->deleteUser();
 
?>