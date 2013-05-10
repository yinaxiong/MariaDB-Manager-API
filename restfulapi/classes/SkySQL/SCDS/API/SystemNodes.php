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
 * Implements:
 * Obtain node information with GET request to /system/{id}/node/{id}
 * Obtain a monitor with GET request to /system/{id}/node/{id}/monitor/{id}
 */

namespace SkySQL\SCDS\API;

use \PDO as PDO;

class SystemNodes extends SystemNodeCommon {
	protected $nodeid = 0;
	protected $monitorid = 0;
	
	protected static $fields = array(
		'state' => array('sqlname' => 'State', 'default' => 0),
		'hostname' => array('sqlname' => 'Hostname', 'default' => ''),
		'publicIP' => array('sqlname' => 'PublicIP', 'default' => ''),
		'privateIP' => array('sqlname' => 'PrivateIP', 'default' => ''),
		'instanceID' => array('sqlname' => 'InstanceID', 'default' => ''),
		'username' => array('sqlname' => 'Username', 'default' => ''),
		'passwd' => array('sqlname' => 'passwd', 'default' => '')
	);
	
	public function __construct ($controller) {
		parent::__construct($controller);
	}
	
	public function nodeStates ($uriparts) {
		if (!empty($uriparts[1])) {
			$state = urldecode($uriparts[1]);
			if (preg_match('/[0-9]+/', $state)) {
				$condition = ' WHERE State = :state';
				$bind = array(':state' => $state);
			}
			else {
				$condition = ' WHERE Description LIKE :description';
				$bind = array(':description' => $state.'%');
			}
		}
		else {
			$condition = '';
			$bind = array();
		}
		$query = $this->db->prepare("SELECT State AS state, Description AS description, Icon AS icon
			FROM NodeStates".$condition);
		$query->execute($bind);
		$states = $this->filterResults($query->fetchAll(PDO::FETCH_ASSOC));
        $this->sendResponse(array('nodestates' => $states));
	}
	
	public function getSystemAllNodes ($uriparts) {
		$this->systemid = $uriparts[1];
		$selects = $this->getSelects(self::$fields, array('SystemID AS system', 'NodeID AS id', 'NodeName AS name'));
		$statement = $this->db->prepare("SELECT $selects FROM Node WHERE Node.SystemID = :systemID");
		$statement->execute(array(':systemID' => $this->systemid));
		$this->returnNodes($statement);
	}
	
	public function getSystemNode ($uriparts) {
		$this->systemid = $uriparts[1];
		$this->nodeid = $uriparts[3];
		$selects = $this->getSelects(self::$fields, array('SystemID AS system', 'NodeID AS id', 'NodeName AS name'));
		$statement = $this->db->prepare("SELECT $selects FROM Node 
			WHERE Node.SystemID = :systemID AND Node.NodeID = :nodeID");
		$statement->execute(array(':systemID' => $this->systemid, ':nodeID' => $this->nodeid));
		$this->returnNodes($statement);
	}
	
	protected function returnNodes ($statement) {
		$nodes = $statement->fetchAll(PDO::FETCH_ASSOC);
		if ($nodes) {
			$this->extraNodeData($nodes);
			$this->sendResponse(array('node' => $this->filterResults($nodes)));
		}
		else $this->sendErrorResponse('', 404);
	}
	
	protected function extraNodeData (&$nodes) {
		foreach ($nodes as &$node) {
			$node['commands'] = ($this->isFilterWord('commands') AND $node['state']) ? $this->getCommands($node['state']) : null;
			$node['connections'] = $this->isFilterWord('connections') ? $this->getConnections($node['id']) : null;
			$node['packets'] = $this->isFilterWord('packets') ? $this->getPackets($node['id']) : null;
			$node['health'] = $this->isFilterWord('health') ? $this->getHealth($node['id']) : null;
			list($node['task'], $node['command']) = $this->getCommand($node['id']);
		}
	}
	
	public function putSystemNode ($uriparts) {
		$this->systemid = $uriparts[1];
		if (!$this->validateSystem()) $this->sendErrorResponse('Create node gave non-existent system ID '.$this->systemid, 400);
		$this->nodeid = $uriparts[3];
		if (!$this->nodeid) $this->sendErrorResponse('Cannot create or update node with ID of zero', 400);
		list($insname, $insvalue, $setter, $bind) = $this->settersAndBinds('PUT', self::$fields);
		$bind[':systemid'] = $this->systemid;
		$bind[':nodeid'] = $this->nodeid;
		$this->startImmediateTransaction();
		if (!empty($setter)) {
			$update = $this->db->prepare('UPDATE Node SET '.implode(', ',$setter).
				' WHERE SystemID = :systemid AND NodeID = :nodeid');
			$update->execute($bind);
			$counter = $update->rowCount();
		}
		else {
			$update = $this->db->prepare('SELECT COUNT(*) FROM Node
				WHERE SystemID = :systemid AND NodeID = :nodeid');
			$update->execute($bind);
			$counter = $update->fetch(PDO::FETCH_COLUMN);
		}
		if (0 == $counter) {
			$insname[] = 'SystemID';
			$insvalue[] = ':systemid';
			$insname[] = 'NodeID';
			$insvalue[] = ':nodeid';
			if (empty($bind[':name'])) {
				$bind[':name'] = 'Node '.sprintf('%06d', $this->nodeid);
				$insname[] = 'NodeName';
				$insvalue[] = ':name';
			}
			$fields = implode(',',$insname);
			$values = implode(',',$insvalue);
			$insert = $this->db->prepare("INSERT INTO Node ($fields) VALUES ($values)");
			$insert->execute($bind);
			$this->sendResponse(array('updatecount' => 0,  'insertkey' => $this->db->lastInsertId()));
		}
		$this->sendResponse(array('updatecount' => (empty($setter) ? 0: $counter), 'insertkey' => 0));
	}
	
	public function deleteSystemNode ($uriparts) {
		$this->systemid = $uriparts[1];
		if (!$this->validateSystem()) $this->sendErrorResponse('Create node gave non-existent system ID '.$this->systemid, 400);
		$this->nodeid = $uriparts[3];
		$delete = $this->db->prepare('DELETE FROM Node WHERE SystemID = :systemid AND NodeID = :nodeid');
		$delete->execute(array(
			':systemid' => $this->systemid,
			':nodeid' => $this->nodeid
			));
		$counter = $delete->rowCount();
		if ($counter) $this->sendResponse(array('deletecount' => $counter));
		else $this->sendErrorResponse('Delete node did not match any node', 404);
	}
	
	protected function validateSystem () {
		$query = $this->db->prepare('SELECT COUNT(*) FROM System WHERE SystemID = :systemid');
		$query->execute(array(':systemid' => $this->systemid));
		return $query->fetch(PDO::FETCH_COLUMN) ? true : false;
	}
	
	protected function getCommand ($nodeid) {
		$query = $this->db->prepare('SELECT TaskID, CommandID FROM CommandExecution 
			WHERE SystemID = :systemid AND NodeID = :nodeid AND State = 2');
		$query->execute(array(':systemid' => $this->systemid, ':nodeid' => $nodeid));
		$row = $query->fetch();
		return $row ? array($row->TaskID, $row->CommandID) : array(null, null);
	}
}