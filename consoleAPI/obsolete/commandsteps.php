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
     
    function commandSteps() {

		$set = $this->db->query("SELECT CommandID, Steps  FROM Commands");
		foreach ($set as $row) {
			$stepnums = array_map('intval', explode(',', $row['Steps']));
			foreach ($stepnums as $num) $steps[] = (string) $num;
			$data[] = array("command" => $row['CommandID'], "steps" => $steps);
			unset($steps);
		}
			
       	$result = array(
            	"command_steps" => (isset($data) ? $data : array()),
        );
        sendResponse(200, json_encode($result));
    }
        
}

// This is the first thing that gets called when this page is loaded
$api = new SkyConsoleAPI;
$api->commandSteps();
