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
     
    function deleteMonitor() {

		if (isset($_GET["id"])) {
			$id = $_GET["id"];

			$delete = $this->db->prepare("DELETE FROM 'Monitors' WHERE MonitorID=".$id);       	
        	$delete->execute();

        	$result = array(
            	"result" => $id,
        	);
			
        	sendResponse(200, json_encode($result));
        	return true;
        }
        
    }
        
}

// This is the first thing that gets called when this page is loaded
$api = new SkyConsoleAPI;
$api->deleteMonitor();
 
?>