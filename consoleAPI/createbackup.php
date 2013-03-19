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
     
    function createbackup() {
		if (isset($_GET["system"]) && isset($_GET["node"]) && isset($_GET["level"])) {
			$system = $_GET["system"];
			$node = $_GET["node"];
			$level = $_GET["level"];

			$now = new DateTime("now", new DateTimeZone('Europe/Rome'));
			$time = $now->format('Y-m-d H:i:s');
			
			$insert = $this->db->prepare("INSERT INTO Backup (SystemID,NodeID,BackupLevel,Started) VALUES($system,$node,$level,'$time')");        	
        	$insert->execute();
        	$BackupID = $this->db->lastInsertId();

			// 1 = full, 2 = incremental
			if ($level==2) {
				$query = $this->db->query("SELECT MAX(Started),BinLog FROM Backup WHERE SystemID=".$system." AND NodeID=".$node." AND BackupLevel=1");
				foreach ($query as $row) {
					$binlog = $row['BinLog'];
				}
				$result = array(
            		"id" => $BackupID,
            		"binlog" => $binlog,
        		);

			} else {
       			$result = array(
            		"id" => $BackupID,
        	);
			
			}
			
         	sendResponse(200, json_encode($result));
        	return true;
        }
    }
        
}


// This is the first thing that gets called when this page is loaded
$api = new SkyConsoleAPI;
$api->createbackup();
 
?>