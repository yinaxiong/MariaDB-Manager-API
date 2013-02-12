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
			$name = $_GET["name"];
			$password = $_GET["password"];

			$query = $this->db->query("SELECT UserID FROM Users WHERE UserName='".$name."' AND Password='".$password."'");
			
			foreach ($query as $row) {
				$id = $row['UserID'];
			}

			if (!is_null($id)) {

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
	
	        	$result = array(
    	        	"id" => $id,
        	    	"properties" => $properties,
        		);


			} else {
			
        		$result = array(
            		"id" => $id,
        		);
			
			}
			
        	sendResponse(200, json_encode($result));
        	return true;
        }
        
    }
        
}

// This is the first thing that gets called when this page is loaded
$api = new SkyConsoleAPI;
$api->commands();
 
?>