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
     
    function setnodedata() {

		if (isset($_GET["node"]) && isset($_GET["public"])) {
			$systemID = $_GET["system"];
			$nodeID = $_GET["node"];
			$publicIP = $_GET["public"];
			
			$cmd="UPDATE NodeData SET PublicIP=".$publicIP." WHERE NodeID=".$nodeID." AND SystemId=".$systemID;
			$update = $this->db->prepare($cmd);
        	$update->execute();
			
       		$result = "ok";
        	sendResponse(200, json_encode($result));
        	return true;
        }
    }
        
}

// This is the first thing that gets called when this page is loaded
$api = new SkyConsoleAPI;
$api->setnodedata();
 
?>
