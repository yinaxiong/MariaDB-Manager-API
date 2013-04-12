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
     
    function taskInfo() {
		
		if (isset($_GET["task"]) && !empty($_GET["task"]) && ($_GET["task"] != "null")) {
			$task = $_GET["task"];
			$query = $this->db->query("SELECT rowid, * FROM CommandExecution WHERE rowid = " . $task);
		} else if (isset($_GET["status"]) && !empty($_GET["status"]) && ($_GET["status"] != "null")) {
			$status = $_GET["status"];
			$query = $this->db->query("SELECT rowid, * FROM CommandExecution WHERE State = " . $status);
		} else if (isset($_GET["node"]) && ($_GET["node"] != "null")) {
			$node = $_GET["node"];
			$query = $this->db->query("SELECT rowid, * FROM CommandExecution WHERE NodeID='".$node."' ORDER BY Start DESC");		
		} else {
			$query = $this->db->query("SELECT rowid, * FROM CommandExecution ORDER BY Start DESC");
		}
		
		foreach ($query as $row) {
			$id = $row['rowid'];
			$node = $row['NodeID'];
			$command = $row['CommandID'];
			$params = $row['Params'];
			$index = $row['StepIndex'];
			$status = $row['State'];
			$user = $row['UserID'];
			$start = $row['Start'];
			$end = $row['Completed'];
       		
       		$data[] = array(
        	"id" => $id,
        	"node" => $node,
           	"command" => $command,
           	"params" => $params,
           	"index" => $index,
           	"status" => $status,
           	"user" => $user,
           	"start" => $start,
           	"end" => $end,
        	);
		}

        $result = array(
            "tasks" => $data,
        );
        sendResponse(200, json_encode($result));
        return true;

    }
        
}


// This is the first thing that gets called when this page is loaded
$api = new SkyConsoleAPI;
$api->taskInfo();
 
?>