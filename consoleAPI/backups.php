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
     
    function backups() {
		if (isset($_GET["system"])) {
			$system = $_GET["system"];
			
			if (isset($_GET["date"]) && !empty($_GET["date"]) && ($_GET["date"] != "null"))
				$date = $_GET["date"];
			else
				$date = null;

			$query = $this->db->query("SELECT rowid, * FROM Backup WHERE SystemID=".$system.(is_null($date) ? "" : " AND Started >= '".$date."'")." ORDER BY Started DESC" );
			foreach ($query as $row) {
				$id = $row['rowid'];
				$node = $row['NodeID'];
				$level = $row['BackupLevel'];
				$status = $row['State'];
				$size = $row['Size'];
				$started = $row['Started'];
				$updated = $row['Updated'];
				$restored = $row['Restored'];
				$storage = $row['Storage'];
				$log = $row['Log'];
				
				$list[] = array("id" => $id,
							"node" => $node,
							"level" => $level,
							"status" => $status, 
							"size" => $size,
							"started" => $started,
							"updated" => $updated,
							"restored" => $restored,
							"storage" => $storage,
							"log" => $log,
							);
			}
			
        	$result = array(
            	"backups" => $list,
        	);
        	sendResponse(200, json_encode($result));
        	return true;
        }
    }
        
}


// This is the first thing that gets called when this page is loaded
$api = new SkyConsoleAPI;
$api->backups();
 
?>