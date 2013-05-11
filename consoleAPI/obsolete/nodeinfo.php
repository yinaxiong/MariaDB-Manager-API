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
     
    function nodeInfo() {
		if (isset($_GET["system"]) && isset($_GET["node"])) {
			$systemID = $_GET["system"];
			$nodeID = $_GET["node"];

			if (strcasecmp($nodeID, "0") == 0) {
				$system_query = $this->db->query('SELECT SystemName,State FROM System');	
				foreach ($system_query as $row) {
					$name = $row['SystemName'];
					$status = $row['State'];
					$privateIP = null;
					$publicIP = null;
				}
			} else {
				$node_query = $this->db->query('SELECT * FROM Node, NodeData WHERE Node.SystemID='.$systemID.' AND Node.NodeID='.$nodeID.' AND Node.NodeID=NodeData.NodeID AND Node.SystemID=NodeData.SystemID');
				foreach ($node_query as $row) {
					$name = $row['NodeName'];
					$status = $row['State'];
					$privateIP = $row['PrivateIP'];
					$publicIP = $row['PublicIP'];
					$instanceID = $row['InstanceID'];
				}
			}
			
			if (is_null($status)) {
				$commands = null;
			} else {
				$cmd_query = $this->db->query('SELECT CommandID FROM ValidCommands WHERE State=' . $status);
				foreach ($cmd_query as $row) {
					$commands[] = $row['CommandID'];
				}
			}
			
			$connections_query = $this->db->query('SELECT Value,MAX(Latest) from MonitorData WHERE SystemID='.$systemID.' AND MonitorID=1 AND NodeID='.$nodeID);
			foreach ($connections_query as $row) {
				$connections = $row['Value'];
			}

			$packets_query = $this->db->query('SELECT Value,MAX(Latest) from MonitorData WHERE SystemID='.$systemID.' AND MonitorID=2 AND NodeID='.$nodeID);
			foreach ($packets_query as $row) {
				$packets = $row['Value'];
			}

			$health_query = $this->db->query('SELECT Value,MAX(Latest) from MonitorData WHERE SystemID='.$systemID.' AND MonitorID=3 AND NodeID='.$nodeID);
			foreach ($health_query as $row) {
				$health = $row['Value'];
			}

			$task_query = $this->db->query('SELECT rowid,CommandID FROM CommandExecution WHERE SystemID='.$systemID.' AND NodeID='.$nodeID.' AND State=2');
			foreach ($task_query as $row) {
				$task = $row['rowid'];
				$command = $row['CommandID'];
			}
						        	
        	$result = array(
            	"name" => $name,
            	"status" => $status,
            	"privateIP" => $privateIP,
            	"publicIP" => $publicIP,
            	"instanceID" => $instanceID,       	
            	"health" => $health,
            	"connections" => $connections,
            	"packets" => $packets,
            	"commands" => $commands,
            	"task" => $task,
            	"command" => $command,
        	);
        	sendResponse(200, json_encode($result));
        	return true;
        }
    }
        
}


// This is the first thing that gets called when this page is loaded
$api = new SkyConsoleAPI;
$api->nodeInfo();
 
?>