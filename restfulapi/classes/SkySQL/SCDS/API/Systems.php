<?php

/*
 * Part of the SCDS API.
 * 
 * This file is distributed as part of the SkySQL Cloud Data Suite.  It is free
 * software: you can redistribute it and/or modify it under the terms of the
 * GNU General Public License as published by the Free Software Foundation,
 * version 2.
 * 
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
 * details.
 * 
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 * 
 * Copyright 2013 (c) SkySQL Ab
 * 
 * Author: Martin Brampton
 * Date: February 2013
 * 
 * The Systems class within the API implements calls to get system data or
 * system properties.
 * 
 */

namespace SkySQL\SCDS\API;

use SkySQL\COMMON\AdminDatabase;
use \PDO;

class Systems extends ImplementAPI {
	protected $nodes_query = null;
	protected $backups_query = null;
	
	public function __construct () {
		parent::__construct();
		$this->nodes_query = $this->db->prepare('SELECT NodeID FROM Node WHERE SystemID = :systemid ORDER BY NodeID');
		$this->backups_query = $this->db->prepare('SELECT MAX(Started) FROM Backup WHERE SystemID = :systemid');
	}
	
	public function getAllData () {
		$systems = $this->db->query('SELECT * FROM System');
		$results = array();
		foreach ($systems as $system) $results[] = $this->retrieveOneSystem($system);
        $this->sendResponse(array("systems" => $results));
        return true;
	}

	public function getSystemData ($uriparts) {
		$data = $this->readSystemData($uriparts[1]);
		if ($data) $this->sendResponse(array('system' => $this->retrieveOneSystem($data, true)));
		else $this->sendErrorResponse('', 404);
	}
	
	public function setSystemProperty ($uriparts) {
		$systemid = (int) $uriparts[1];
		$property = $uriparts[3];
		$value = file_get_contents("php://input");
		$pstatement = $this->db->prepare('REPLACE INTO SystemProperties
			(SystemID, Property, Value) VALUES(:systemid, :property, :value)');
		$pstatement->execute(array(
			':systemid' => $systemid,
			':property' => $property,
			':value' => $value
		));
		//parse_str(file_get_contents("php://input"),$post_vars);
		$this->sendResponse(array(
			'id' => $systemid,
			'property' => $property,
			'value' => $value
		));
	}
	
	public function deleteSystemProperty ($uriparts) {
		$systemid = (int) $uriparts[1];
		$property = $uriparts[3];
		$pstatement = $this->db->prepare('DELETE FROM SystemProperties WHERE SystemID = :systemid AND Property = :property');
		$pstatement->execute(array(
			':systemid' => $systemid,
			':property' => $property
		));
		$rowcount = $pstatement->rowCount();
		$this->sendResponse(array(
			'id' => $systemid,
			'property' => $property,
			'rowcount' => $rowcount
		), ($rowcount ? 200 : 404));
	}
	
	public function getSystemProperty ($uriparts) {
		$data = $this->readSystemData($uriparts[1]);
		if ($data) {
			$result = $this->retrieveOneSystem($data, true);
			$property = $uriparts[3];
			if (isset($result['properties'][$property])) {
				$this->sendResponse(array($property => $result['properties'][$property]));
				exit;
			}
		}
		$this->sendErrorResponse('', 404);
	}
	
	protected function readSystemData ($systemID) {
		$id = (int) $systemID;
		$statement = $this->db->query("SELECT * FROM System WHERE SystemID = $id");
		return $statement->fetch();
	}
	
	protected function retrieveOneSystem ($system, $withProperties=false) {
		$this->nodes_query->execute(array(':systemid' => $system->SystemID));
		// Can only be exactly one result for the latest backup
		$this->backups_query->execute(array(':systemid' => $system->SystemID));
						
        $result = array(
           	"id" => $system->SystemID,
           	"name" => $system->SystemName,
           	"startDate" => $system->InitialStart,
           	"lastAccess" => $system->LastAccess,
           	"status" => $system->State,
           	"nodes" => $nodeids = $this->nodes_query->fetchAll(PDO::FETCH_COLUMN),
           	"lastBackup" => $this->backups_query->fetchColumn()
       	);
		
		if ($withProperties) {
			$presults = $this->retrieveProperties($system->SystemID);
			if (count($presults)) $result['properties'] = $presults;
		}
		
		return $result;
	}
	
	protected function retrieveProperties ($systemID) {
		$id = (int) $systemID;
		$pstatement = $this->db->query("SELECT Property, Value FROM SystemProperties WHERE SystemID = $id");
		return $pstatement->fetchAll(PDO::FETCH_KEY_PAIR);
	}
}