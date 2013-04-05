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

use \PDO;

class Systems extends ImplementAPI {
	protected $nodes_query = null;
	protected $backups_query = null;
	
	protected static $fields = array(
		'startDate' => array('sqlname' => 'InitialStart', 'default' => ''),
		'lastAccess' => array('sqlname' => 'LastAccess', 'default' => ''),
		'state' => array('sqlname' => 'State', 'default' => 0)
	);

	public function __construct ($controller) {
		parent::__construct($controller);
		$this->nodes_query = $this->db->prepare('SELECT NodeID FROM Node WHERE SystemID = :systemid ORDER BY NodeID');
		$this->backups_query = $this->db->prepare('SELECT MAX(Started) FROM Backup WHERE SystemID = :systemid');
	}
	
	public function getAllData () {
		if ('id' == $this->getParam('GET', 'fields')) {
			$systems = $this->db->query('SELECT SystemID AS id FROM System');
			$this->sendResponse(array("system" => $systems->fetchAll(PDO::FETCH_ASSOC)));
		}
		$selects = $this->getSelects(self::$fields, array('SystemID AS system', 'SystemName AS name'));
		$query = $this->db->query("SELECT $selects FROM System");
		$results = array();
		foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $system) {
			$results[] = $this->retrieveOneSystem($system);
		}
        $this->sendResponse(array("system" => $this->filterResults($results)));
	}

	public function getSystemData ($uriparts) {
		$data = $this->readSystemData($uriparts[1]);
		if ($data) $this->sendResponse(array('system' => $this->filterResults(array($this->retrieveOneSystem($data)))));
		else $this->sendErrorResponse('', 404);
	}
	
	public function putSystem ($uriparts) {
		$systemid = $uriparts[1];
		if (!$systemid) $this->sendErrorResponse('Creating a system with ID of zero is not permitted', 400);
		list($insname, $insvalue, $setter, $bind) = $this->settersAndBinds('PUT', self::$fields);
		$bind[':systemid'] = $systemid;
		$name = $this->getParam('PUT', 'name');
		if ($name) {
			$insertname = $name;
			$setter[] = 'SystemName = :name';
			$bind[':name'] = $name;
		}
		else $insertname = 'System '.sprintf('%06d', $systemid);
		if (isset($bind[':startDate'])) $bind[':startDate'] = date('Y-m-d H:i:s', strtotime($bind[':startDate']));
		if (isset($bind[':lastAccess'])) $bind[':lastAccess'] = date('Y-m-d H:i:s', strtotime($bind[':lastAccess']));
		$this->startImmediateTransaction();
		if (!empty($setter)) {
			$update = $this->db->prepare('UPDATE System SET '.implode(', ',$setter).
				' WHERE SystemID = :systemid');
			$update->execute($bind);
			$counter = $update->rowCount();
		}
		else {
			$update = $this->db->prepare('SELECT COUNT(*) FROM System
				WHERE SystemID = :systemid');
			$update->execute($bind);
			$counter = $update->fetch(PDO::FETCH_COLUMN);
		}
		if (0 == $counter) {
			if (empty($bind[':startDate'])) {
				$bind[':startDate'] = date('Y-m-d H:i:s');
				$insname[] = 'InitialStart';
				$insvalue[] = ':startDate';
			}
			if (empty($bind[':lastAccess'])) {
				$bind[':lastAccess'] = date('Y-m-d H:i:s');
				$insname[] = 'LastAccess';
				$insvalue[] = ':lastAccess';
			}
			$insname[] = 'SystemID';
			$insvalue[] = ':systemid';
			$insname[] = 'SystemName';
			$insvalue[] = ':name';
			$bind[':name'] = $insertname;
			$fields = implode(',',$insname);
			$values = implode(',',$insvalue);
			$insert = $this->db->prepare("INSERT INTO System ($fields) VALUES ($values)");
			$insert->execute($bind);
			$this->sendResponse(array('updatecount' => 0,  'insertkey' => $this->db->lastInsertId()));
		}
		$this->sendResponse(array('updatecount' => (empty($setter) ? 0: $counter), 'insertkey' => 0));
	}
	
	public function deleteSystem ($uriparts) {
		$systemid = $uriparts[1];
		$delete = $this->db->prepare('DELETE FROM System WHERE SystemID = :systemid');
		$delete->execute(array(':systemid' => $systemid));
		$counter = $delete->rowCount();
		if ($counter) $this->sendResponse(array('deletecount' => $counter));
		else $this->sendErrorResponse('Delete system did not match any existing system', 404);
	}
	
	public function setSystemProperty ($uriparts) {
		$systemid = (int) $uriparts[1];
		$property = $uriparts[3];
		$value = $this->getParam('PUT', 'value');
		$bind = array(
			':systemid' => $systemid,
			':property' => $property,
			':value' => $value
		);
		$this->startImmediateTransaction();
		$update = $this->db->prepare('UPDATE SystemProperties SET Value = :value WHERE SystemID = :systemid AND Property = :property');
		$update->execute($bind);
		$counter = $update->rowCount();
		if (0 == $counter) {
			$insert = $this->db->prepare('INSERT INTO SystemProperties (SystemID, Property, Value)
				VALUES (:systemid, :property, :value)');
			$insert->execute($bind);
			$this->sendResponse(array('updatecount' => 0,  'insertkey' => $property));
		}
		$this->sendResponse(array('updatecount' => $counter, 'insertkey' => ''));
	}
	
	public function deleteSystemProperty ($uriparts) {
		$systemid = (int) $uriparts[1];
		$property = $uriparts[3];
		$delete = $this->db->prepare('DELETE FROM SystemProperties WHERE SystemID = :systemid AND Property = :property');
		$delete->execute(array(
			':systemid' => $systemid,
			':property' => $property
		));
		$counter = $delete->rowCount();
		if ($counter) $this->sendResponse(array('deletecount' => $counter));
		else $this->sendErrorResponse('Delete node did not match any node', 404);
	}
	
	public function getSystemProperty ($uriparts) {
		$data = $this->readSystemData($uriparts[1]);
		if ($data) {
			$result = $this->retrieveOneSystem($data, true);
			$property = $uriparts[3];
			if (isset($result['properties'][$property])) {
				$this->sendResponse(array('systemproperty' => array($property => $result['properties'][$property])));
			}
		}
		$this->sendErrorResponse('', 404);
	}
	
	protected function readSystemData ($systemID) {
		$id = (int) $systemID;
		$selects = $this->getSelects(self::$fields, array('SystemID AS system', 'SystemName AS name'));
		$statement = $this->db->query("SELECT $selects FROM System WHERE SystemID = $id");
		return $statement->fetch(PDO::FETCH_ASSOC);
	}
	
	protected function retrieveOneSystem ($system) {
		$this->nodes_query->execute(array(':systemid' => (int) $system['system']));
		// Can only be exactly one result for the latest backup
		$this->backups_query->execute(array(':systemid' => $system['system']));
						
		$system['nodes'] = $this->nodes_query->fetchAll(PDO::FETCH_COLUMN);
		$system['lastBackup'] = $this->backups_query->fetchColumn();
		$presults = $this->retrieveProperties($system['system']);
		if (count($presults)) $system['properties'] = $presults;
		return $system;
	}
	
	protected function retrieveProperties ($systemID) {
		$id = (int) $systemID;
		$pstatement = $this->db->query("SELECT Property, Value FROM SystemProperties WHERE SystemID = $id");
		return $pstatement->fetchAll(PDO::FETCH_KEY_PAIR);
	}
}