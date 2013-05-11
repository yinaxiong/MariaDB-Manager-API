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
     
    function userInfo() {
		$query = $this->db->query("SELECT UserID,UserName FROM Users");
		foreach ($query as $row) {
			$id = $row['UserID'];
			$name = $row['UserName'];
			$users[] = array("id"=>$id, "name"=>$name);
		}
			
        $result = array(
            "users" => $users,
        );
        sendResponse(200, json_encode($result));
        return true;
    }
        
}


// This is the first thing that gets called when this page is loaded
$api = new SkyConsoleAPI;
$api->userInfo();
 
?>