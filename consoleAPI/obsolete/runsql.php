<?php

ini_set('display_errors',1);
error_reporting(-1);

include 'commons.php';
include 'config.php';

class SkyConsoleAPI {
	protected $db = null;
	protected $subjectdb = null;
	
    // Constructor - open connection
    public function __construct() {
    	global $DBPath, $DBType;
		$this->db = new PDO($DBType.$DBPath, 'skytest', 'skytest');
		$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }
 
    // Destructor - close connection
    public function __destruct() {
    	$this->db = null;
    }
     
    public function runsql() {
		try {
			if (isset($_GET["sql"])) $query = urldecode($_GET["sql"]);
			else throw new PDOException('No query provided');
			if (strcasecmp('SELECT ', substr($query,0,7))) throw new PDOException('Query is not a SELECT statement');
			$hostdata = $this->getHostData();
			$this->subjectdb = new PDO("mysql:host=$hostdata->Hostname;dbname=information_schema", $hostdata->Username, $hostdata->passwd);
			$this->subjectdb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$this->subjectdb->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
			$statement = $this->subjectdb->prepare($query);
			$statement->execute();
			$statement->setFetchMode(PDO::FETCH_ASSOC);
			$rows = $statement->fetchAll();
		}
		catch (PDOException $pe) {
			$result = array(
				"error" => $pe->getMessage()
			);
			sendResponse(200, json_encode($result), 'application/json');
			exit;
		}
		
   		$result = array(
           	"results" => $rows,
       	);
       	sendResponse(200, json_encode($result), 'application/json');
       	return true;
    }

	protected function getHostData () {
		if (!empty($_GET['node'])) {
			$statement = $this->db->prepare("SELECT Hostname, Username, passwd FROM NodeData WHERE NodeID = :node");
			$statement->execute(array(':node' => (int) $_GET['node']));
			$noderecord = $statement->fetch(PDO::FETCH_OBJ);
			if ($noderecord) return $noderecord;
		}
		throw new PDOException ('No valid node provided');
	}
}

// This is the first thing that gets called when this page is loaded
$api = new SkyConsoleAPI;
$api->runsql();