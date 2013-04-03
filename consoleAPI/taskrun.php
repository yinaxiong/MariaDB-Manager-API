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
     
    function taskRun() {

        if (isset($_GET["command"]) && isset($_GET["system"]) && isset($_GET["node"]) && isset($_GET["user"]) ) {
			$command = $_GET["command"];
			
			if (isset($_GET["params"]) && ($_GET["params"] != "null"))
				$params = $_GET["params"];
			else 
				$params = null;
			
        	$system = $_GET["system"];
        	$node = $_GET["node"];
        	$user = $_GET["user"];
			
			$now = new DateTime("now", new DateTimeZone('Europe/Rome'));
			$time = $now->format('Y-m-d H:i:s');
			
			$insert = $this->db->prepare("INSERT INTO CommandExecution (SystemID,NodeID,CommandID,Params,Start,Completed,StepIndex,State,UserID) VALUES($system,$node,$command,". (is_null($params) ? "NULL" : "'$params'") . ",'$time',NULL,0,0,'$user')");        	
        	$insert->execute();
        	$rowID = $this->db->lastInsertId();
        	global $DBPath, $shellPath;
        	$cmd = $shellPath . 'RunCommand.sh ' . $rowID . ' "' . $DBPath . '" > /dev/null 2>&1 &';
        	$output = exec($cmd);

			$result = array(
           		"task" => $rowID,
        	);
        	sendResponse(200, json_encode($result));
        	return true;        	
        } 
        
    }
	
}


// This is the first thing that gets called when this page is loaded
$api = new SkyConsoleAPI;
$api->taskRun();
 
?>