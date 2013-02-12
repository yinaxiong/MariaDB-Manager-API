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
     
    function systemInfo() {

		$system_query = $this->db->query('SELECT * FROM System');	
		
		foreach ($system_query as $system_row) {
			$systemID = $system_row['SystemID'];
			$status = $system_row['State'];
			
			$nodes_query = $this->db->query('SELECT NodeID FROM Node WHERE SystemID = '.$systemID.' ORDER BY NodeID');
			foreach ($nodes_query as $row) {
				$nodes[] = $row['NodeID'];
			}

			$backup_query = $this->db->query('SELECT MAX(Started) FROM Backup WHERE SystemID = '.$systemID);
			foreach ($backup_query as $row) {
				$lastBackup = $row['MAX(Started)'];
			}
						
        	$system = array(
            	"id" => $systemID,
            	"name" => $system_row['SystemName'],
            	"startDate" => $system_row['InitialStart'],
            	"lastAccess" => $system_row['LastAccess'],
            	"status" => $system_row['State'],
            	"nodes" => $nodes,
            	"lastBackup" => $lastBackup,
        	);
        	$systems[] = $system;
        }
        
        sendResponse(200, json_encode(array("systems" => $systems)));
        return true;
    }
        
}


// This is the first thing that gets called when this page is loaded
$api = new SkyConsoleAPI;
$api->systemInfo();
 
?>