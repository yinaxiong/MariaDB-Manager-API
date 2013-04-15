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
     
    function monitors() {

		$data = array();
		
		$monitors = $this->db->query("SELECT * FROM Monitors WHERE MonitorType = 'SQL' AND UIOrder IS NOT NULL ORDER BY UIOrder");

		foreach ($monitors as $row) {
			$id = $row['MonitorID'];
			$name = $row['Name'];
			$description = $row['Description'];
			$unit = $row['Unit'];
			$icon = $row['Icon'];
			$type = $row['MonitorType'];
			$delta = $row['delta'] == 1 ? 'true' : 'false';
			$average = $row['SystemAverage'] == 1 ? 'true' : 'false';
			$chartType = $row['ChartType'];
			$interval = $row['Interval'];
			$sql = $row['SQL'];
			$data[] = array("id" => $id, "name" => $name, "description" => $description, "unit" => $unit, "icon" => $icon, "type" => $type, "delta" => $delta, "average" => $average, "chartType" => $chartType, "interval" => $interval, "sql" => $sql);
		}
			
       	$result = array(
            	"monitors" => $data,
        );
        sendResponse(200, json_encode($result));
        return true;
    }
        
}

// This is the first thing that gets called when this page is loaded
$api = new SkyConsoleAPI;
$api->monitors();
 
?>