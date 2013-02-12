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
     
    function commandstates() {

		$query = $this->db->query("SELECT * FROM CommandStates");
			
		foreach ($query as $row) {
			$id = $row['State'];
			$description = $row['Description'];
			$icon = $row['Icon'];
			$data[] = array("id" => $id, "description" => $description, "icon" => $icon);
		}
			
       	$result = array(
            "commandStates" => $data,
        );
        sendResponse(200, json_encode($result));
        return true;
    }
        
}

// This is the first thing that gets called when this page is loaded
$api = new SkyConsoleAPI;
$api->commandstates();
 
?>