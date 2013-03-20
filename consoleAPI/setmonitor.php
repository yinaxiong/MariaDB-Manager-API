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
     
    function setMonitor() {

		if (isset($_GET["id"]) && isset($_GET["name"]) && isset($_GET["description"]) && isset($_GET["unit"]) && isset($_GET["sql"]) && isset($_GET["delta"])  && isset($_GET["average"]) && isset($_GET["interval"]) && isset($_GET["chartType"])) {
			$id = $_GET["id"];
			$name = $_GET["name"];
			$description = $_GET["description"];
			$unit = $_GET["unit"];
			$sql = $_GET["sql"];
			$delta = $_GET["delta"] == 'true' ? 1 : 0;
			$average = $_GET["average"] == 'true' ? 1 : 0;
			$interval = $_GET["interval"];
			$chartType = $_GET["chartType"];

			if (empty($id) || ($id == "null")) {
				$insert = $this->db->prepare("INSERT INTO Monitors (MonitorID, Name, Description, Unit, SQL, delta, MonitorType, SystemAverage, ChartType, Interval) 
														VALUES (null,'$name','$description','$unit','$sql','$delta','SQL','$average','$chartType','$interval')");        	
        		$insert->execute();
        		$rowID = $this->db->lastInsertId();
				$update = $this->db->prepare("UPDATE Monitors SET MonitorID=".$rowID." WHERE rowid=".$rowID);        	
				$update->execute();
	        	$result = array(
    	        	"result" => $rowID,
        		);
			} else {
				$update = $this->db->prepare("UPDATE Monitors SET Name='".$name."',Description='".$description."',Unit='".$unit."',SQL='".$sql."',delta=".$delta.",SystemAverage=".$average.",ChartType='".$chartType."',Interval=".$interval." WHERE MonitorID=".$id);        	
				$update->execute();
	        	$result = array(
    	        	"result" => $id,
        		);
			
			}
			
        	sendResponse(200, json_encode($result));
        	return true;
        }
        
    }
        
}

// This is the first thing that gets called when this page is loaded
$api = new SkyConsoleAPI;
$api->setMonitor();
 
?>