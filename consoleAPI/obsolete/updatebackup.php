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
     
    function updatebackup() {
		if (isset($_GET["id"]) && isset($_GET["state"])) {
			$id = $_GET["id"];
			$state = $_GET["state"];

			if (isset($_GET["size"]) && !empty($_GET["size"]) && ($_GET["size"] != "null"))
				$size = $_GET["size"];
			else 
				$size = null;

			if (isset($_GET["storage"]) && !empty($_GET["storage"]) && ($_GET["storage"] != "null"))
				$storage = $_GET["storage"];
			else 
				$storage = null;

			if (isset($_GET["binlog"]) && !empty($_GET["binlog"]) && ($_GET["binlog"] != "null"))
				$binlog = $_GET["binlog"];
			else 
				$binlog = null;

			if (isset($_GET["log"]) && !empty($_GET["log"]) && ($_GET["log"] != "null"))
				$log = $_GET["log"];
			else 
				$log = null;

			$now = new DateTime("now", new DateTimeZone('Europe/Rome'));
			$time = $now->format('Y-m-d H:i:s');
			
			$cmd = "UPDATE Backup SET State=".$state.
			(is_null($size) ? "" : ",Size=".$size).
			",Updated='".$time."'".
			(is_null($storage) ? "" : ",Storage='".$storage."'").
			(is_null($binlog) ? "" : ",BinLog='".$binlog."'").
			(is_null($log) ? "" : ",Log='".$log."'").
			" WHERE rowid=".$id;
			$update = $this->db->prepare($cmd);        	
        	$update->execute();

        	$result = array(
            	"result" => "ok",
        	);
			
         	sendResponse(200, json_encode($result));
        	return true;
        }
    }
        
}


// This is the first thing that gets called when this page is loaded
$api = new SkyConsoleAPI;
$api->updatebackup();
 
?>