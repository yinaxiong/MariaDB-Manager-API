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

		if (isset($_GET["system"])) {
			$systemID = $_GET["system"];
			
			if (isset($_GET["property"]) && isset($_GET["value"])) {
				$property = $_GET["property"];
				$value = $_GET["value"];

				$update = $this->db->prepare("UPDATE SystemProperties SET Value=".$value." WHERE SystemID=".$systemID." AND Property='".$property."'");        	
				$update->execute();	

       			$result = array(
            		"result" => "ok",
        		);
			} else {
				
				$data = $this->db->query("SELECT * FROM SystemProperties WHERE SystemID=".$systemID);
			
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