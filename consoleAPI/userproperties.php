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
     
    function properties() {

		if (isset($_GET["user"])) {
			$userID = $_GET["user"];

			if (is_null($userID) || empty($userID) || ($userID == "null")) {
				$user_condition = "UserID IS NULL";
			} else {
				$user_condition = "UserID='".$userID."'";
			}
								
			if (isset($_GET["property"]) && isset($_GET["value"])) {
				$property = $_GET["property"];
				$value = urldecode($_GET["value"]);
				
				$data = $this->db->query("SELECT Value FROM UserProperties WHERE ".$user_condition." AND Property='".$property."'")->fetch();				
				if (empty($data)) {
					$insert = $this->db->prepare("INSERT INTO UserProperties (UserID, Property, Value) VALUES($userID, '$property', '$value')");        	
        			$insert->execute();
				} else {
					$update = $this->db->prepare("UPDATE UserProperties SET Value='".$value."' WHERE ".$user_condition." AND Property='".$property."'");        	
					$update->execute();	
				}
								
       			$result = array(
            		"result" => "ok",
        		);
			} else {
				
				$select = "SELECT * FROM UserProperties WHERE ".$user_condition;
				$data = $this->db->query($select);
			
				foreach ($data as $row) {
					$property = $row['Property'];
					$value = $row['Value'];
					$properties[] = array("property" => $property, "value" => $value);
				}
			
       			$result = array(
            		"properties" => $properties,
        		);
        	}
        	
        	sendResponse(200, json_encode($result));
        	return true;
        }
    }
        
}

// This is the first thing that gets called when this page is loaded
$api = new SkyConsoleAPI;
$api->properties();
 
?>