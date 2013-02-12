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
     
    function settingsValues() {

		if (isset($_GET["property"])) {
			$property = $_GET["property"];
			
			$data = $this->db->query("SELECT Value FROM SettingsValues WHERE Property='".$property."' ORDER BY 'UIOrder'");
			
			foreach ($data as $row) {
				$value = $row['Value'];
				$list[] = array("value" => $value);
			}
			
     	  	$result = array(
        	    	"settingsValues" => $list,
        	);
        	sendResponse(200, json_encode($result));
        	return true;
        }
    }
        
}

// This is the first thing that gets called when this page is loaded
$api = new SkyConsoleAPI;
$api->settingsValues();
 
?>