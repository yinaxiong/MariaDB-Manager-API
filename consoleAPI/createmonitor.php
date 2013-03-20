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
     
    function createMonitor() {

		if (isset($_GET["name"]) && isset($_GET["description"]) && isset($_GET["sql"]) && isset($_GET["delta"])  && isset($_GET["average"]) && isset($_GET["interval"])) {
			$name = $_GET["name"];
			$description = $_GET["description"];
			$sql = $_GET["sql"];
			$delta = $_GET["delta"] == 'true' ? 1 : 0;
			$average = $_GET["average"] == 'true' ? 1 : 0;
			$interval = $_GET["interval"];

			$insert = $this->db->prepare("INSERT INTO Monitors (MonitorID, Name, Description, SQL, delta, MonitorType, SystemAverage, ChartType, Interval) 
														VALUES (null,'$name','$description','$sql','$delta','SQL','$average','LineChart','$interval')");        	
        	$insert->execute();
        	$rowID = $this->db->lastInsertId();
			$update = $this->db->prepare("UPDATE Monitors SET MonitorID=".$rowID." WHERE rowid=".$rowID);        	
			$update->execute();	

        	$result = array(
            	"id" => $rowID,
        	);
			
        	sendResponse(200, json_encode($result));
        	return true;
        }
        
    }
        
}

// This is the first thing that gets called when this page is loaded
$api = new SkyConsoleAPI;
$api->createMonitor();
 
?>