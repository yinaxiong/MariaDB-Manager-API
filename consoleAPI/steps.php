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
     
    function steps() {

		$data = $this->db->query("SELECT * FROM Step");
			
		foreach ($data as $row) {
			$id = $row['StepID'];
			$script = $row['Script'];
			$icon = $row['Icon'];
			$description = $row['Description'];
			$steps[] = array("id" => $id, "script" => $script, "icon" => $icon, "description" => $description);
		}
			
       	$result = array(
            	"steps" => $steps,
        );
        sendResponse(200, json_encode($result));
        return true;
    }
        
}

// This is the first thing that gets called when this page is loaded
$api = new SkyConsoleAPI;
$api->steps();
 
?>