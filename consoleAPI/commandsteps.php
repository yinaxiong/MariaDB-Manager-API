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

		$set = $this->db->query("SELECT Commands.CommandID, StepID  FROM Commands, CommandStep WHERE Commands.CommandID=CommandStep.CommandID ORDER BY 'StepOrder'");
			
		$lastCommand = null;
		$steps = null;
		foreach ($set as $row) {
			$command = $row['CommandID'];
			$step = $row['StepID'];
			if (strcasecmp($command,$lastCommand) != 0) {
				if (!is_null($lastCommand)) {
					$data[] = array("command" => $lastCommand, "steps" => $steps);
					$steps = null;
					$steps[] = $step;
				} else {
					$steps[] = $step;
				}
				$lastCommand = $command;
			} else {
				$steps[] = $step;
			}	
		}
		if (!is_null($lastCommand))
			$data[] = array("command" => $lastCommand, "steps" => $steps);
			
       	$result = array(
            	"command_steps" => $data,
        );
        sendResponse(200, json_encode($result));
        return true;
    }
        
}

// This is the first thing that gets called when this page is loaded
$api = new SkyConsoleAPI;
$api->commandSteps();
 
?>