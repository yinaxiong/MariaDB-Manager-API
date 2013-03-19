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

		if (isset($_GET["group"])) {
			$group = $_GET["group"];
		
			$commands = $this->db->query("SELECT * FROM Commands WHERE UIOrder IS NOT NULL AND UIGroup='".$group."' ORDER BY UIOrder");
			
			foreach ($commands as $row) {
				$id = $row['CommandID'];
				$name = $row['Name'];
				$description = $row['Description'];
				$icon = $row['Icon'];
				$data[] = array("id" => $id, "name" => $name, "description" => $description, "icon" => $icon);
			}
			
       		$result = array(
            	"commands" => $data,
        	);
        	sendResponse(200, json_encode($result));
        }
    }
        
}

// This is the first thing that gets called when this page is loaded
$api = new SkyConsoleAPI;
$api->commands();
