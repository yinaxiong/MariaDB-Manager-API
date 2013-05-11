<?php

include 'commons.php';
include 'config.php';

class SkyConsoleAPI {
	function S3retrieve() {

		$bucket = $_GET['bucket'];
		$object = $_GET['object'];
		$cmd = '/usr/local/skysql/skysql_aws/S3Control.sh retrieve ';
		$cmd .= $bucket . ' ' . $object;
		$file = popen($cmd, 'r');
		$lines = array();

		while (!feof($file)) {
			$line = fgets($file);
			echo $line;
			echo "</br>";
		}
		pclose($file);
	
	}
        
}


// This is the first thing that gets called when this page is loaded
$api = new SkyConsoleAPI;
$api->S3retrieve();
 
?>
