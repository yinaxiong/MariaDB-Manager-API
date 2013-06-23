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
     
    function properties() {

		if (isset($_GET["user"])) {
			$username = urldecode($_GET["user"]);
			$bind[':username'] = $username;
			if (isset($_GET["property"]) AND isset($_GET["value"])) {
				$bind['property'] = urldecode($_GET["property"]);
				$bind['value'] = urldecode($_GET["value"]);
				$update = $this->db->prepare('UPDATE UserProperties SET Value = :value WHERE Property = :property AND UserName = :username');
				$update->execute($bind);
				if (0 == $update->rowCount()) {
					$insert = $this->db->prepare('INSERT INTO UserProperties (UserName, Property, Value)
						VALUES (:username, :property, :value)');
					$insert->execute($bind);
				}
       			$result = array(
            		"result" => "ok",
        		);
			} else {
				$select = $this->db->prepare('SELECT Property AS property, Value AS value FROM UserProperties WHERE UserName = :username');
				$select->execute($bind);
				$properties = $select->fetchAll(PDO::FETCH_ASSOC);
       			$result = array(
            		"properties" => $properties,
        		);
        	}
        	sendResponse(200, json_encode($result));
        }
    }
}

// This is the first thing that gets called when this page is loaded
$api = new SkyConsoleAPI;
$api->properties();
