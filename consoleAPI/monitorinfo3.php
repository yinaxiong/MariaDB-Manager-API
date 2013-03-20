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

		if (isset($_GET["monitor"]) && isset($_GET["system"]) && isset($_GET["node"])) {
			
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

			if (isset($_GET["count"]) && !empty($_GET["count"]) && ($_GET["count"] != "null"))
				$count = $_GET["count"];
			else
				$count = 15;
			
			$delta = $interval / $count;

			while ($count-- > 0) {
						
				$endTime = date('Y-m-d H:i:s', $unixtime);
				$unixtime -= $delta;
				$startTime = date('Y-m-d H:i:s', $unixtime);
				
				$src = "SELECT MIN(Value),MAX(Value),MIN(Start),MAX(Latest) FROM MonitorData WHERE "
					."MonitorID=".$monitor." AND SystemID=".$system." AND NodeId=".$node
					." AND Start < '".$endTime."' AND Latest >= '".$startTime."'";

				$query = $this->db->query($src);
			
				foreach ($query as $row) {
					$min = $row['MIN(Value)'];
					$max = $row['MAX(Value)'];
					$start = $row['MIN(Start)'];
					$end = $row['MAX(Latest)'];
					$pairs[] = array("min" => $min, "max" => $max, "start" => $start, "end" => $end);
				}

		    } 
			
        	$result = array(
            	"monitor_data" => is_null($pairs) ? null: array_reverse($pairs),
        	);
        	sendResponse(200, json_encode($result));
        	return true;
        }
    }
        
}


// This is the first thing that gets called when this page is loaded
$api = new SkyConsoleAPI;
$api->monitorInfo();
 
?>