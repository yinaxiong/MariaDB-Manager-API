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
     
    function monitors() {

		$monitors = $this->db->query("SELECT * FROM Monitors WHERE UIOrder IS NOT NULL ORDER BY UIOrder");
			
		foreach ($monitors as $row) {
			$id = $row['MonitorID'];
			$name = $row['Name'];
			$description = $row['Description'];
			$icon = $row['Icon'];
			$type = $row['ChartType'];
			$data[] = array("id" => $id, "name" => $name, "description" => $description, "icon" => $icon, "type" => $type);
		}
			
       	$result = array(
            	"monitors" => $data,
        );
        sendResponse(200, json_encode($result));
        return true;
    }
        
}

// This is the first thing that gets called when this page is loaded
$api = new SkyConsoleAPI;
$api->monitors();
 
?>