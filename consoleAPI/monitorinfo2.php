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
     
    function monitorInfo() {

		if (isset($_GET["monitor"]) && isset($_GET["system"]) && isset($_GET["node"]) && isset($_GET["time"])) {
			
			$monitor = $_GET["monitor"];
			$system = $_GET["system"];
			$node = $_GET["node"];

			// use supplied time or latest time found in DB
			if (isset($_GET["time"]) && !empty($_GET["time"]) && ($_GET["time"] != "null"))
				$time = $_GET["time"];
			else {
				$src = "SELECT MAX(Latest) FROM MonitorData WHERE "
					."MonitorID=".$monitor." AND SystemID=".$system." AND NodeId=".$node;

				$query = $this->db->query($src);

				foreach ($query as $row) {
					$time = $row['MAX(Latest)'];
				}			
			}
			
			$unixtime = strtotime($time);

			if (isset($_GET["interval"]) && !empty($_GET["interval"]) && ($_GET["interval"] != "null"))
				$interval = $_GET["interval"];
			else
				$interval = "1800";  // default 30 minutes

			$endTime = date('Y-m-d H:i:s', $unixtime);
			$unixtime -= $interval;
			$startTime = date('Y-m-d H:i:s', $unixtime);
			
			$src = "SELECT Value,Start,Latest FROM MonitorData WHERE "
					."MonitorID=".$monitor." AND SystemID=".$system." AND NodeId=".$node
					." AND Start < '".$endTime."' AND Latest >= '".$startTime."'";

			$query = $this->db->query($src);
			
			foreach ($query as $row) {
				$value = $row['Value'];
				$start = $row['Start'];
				$latest = $row['Latest'];
				$sets[] = array("value" => $value, "time" => $start);
			}
						
        	$result = array("monitor_data" => is_null($sets) ? null : $sets,);
        	sendResponse(200, json_encode($result));
        	return true;
        }
    }
        
}


// This is the first thing that gets called when this page is loaded
$api = new SkyConsoleAPI;
$api->monitorInfo();
 
?>