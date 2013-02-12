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
     
    function nodeStates() {

		$nodestates_query = $this->db->query('SELECT State, Description, Icon FROM NodeStates');
			
		foreach ($nodestates_query as $row) {
			$state = $row['State'];
			$description = $row['Description'];
			$icon = $row['Icon'];
			$data[] = array("state" => $state, "description" => $description, "icon" => $icon);
		}
			
       	$result = array(
            	"nodeStates" => $data,
        );
        sendResponse(200, json_encode($result));
        return true;
    }
        
}

// This is the first thing that gets called when this page is loaded
$api = new SkyConsoleAPI;
$api->nodeStates();
 
?>