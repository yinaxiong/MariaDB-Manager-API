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

class SystemNodes extends ImplementAPI {
	protected $systemid = 0;
	protected $nodeid = 0;
	protected $monitorid = 0;
	protected $monitorquery = null;
	
	public function __construct ($controller) {
		parent::__construct($controller);
		$this->monitorquery = $this->db->prepare('SELECT Value, MAX(Latest) FROM MonitorData 
			WHERE SystemID = :systemid AND MonitorID = :monitorid AND NodeID = :nodeid');
	}
	
	public function nodeStates ($uriparts) {
		if (!empty($uriparts[1])) {
			if (preg_match('/[0-9]+/', $uriparts[1])) {
				$condition = ' WHERE State = :state';
				$bind = array(':state' => $uriparts[1]);
			}
			else {
				$condition = ' WHERE Description LIKE :description';
				$bind = array(':description' => $uriparts[1].'%');
			}
		}
		else {
			$condition = '';
			$bind = array();
		}
		$query = $this->db->prepare("SELECT State AS state, Description AS description, Icon AS icon
			FROM NodeStates".$condition);
		$query->execute($bind);
		$states = $query->fetchAll(PDO::FETCH_ASSOC);
		if (isset($_GET['show'])) $states = $this->filterResults($states, $_GET['show']);
        $this->sendResponse(array('nodestates' => $states));
	}
	
	public function getSystemAllNodes ($uriparts) {
		$this->systemid = $uriparts[1];
		$statement = $this->db->prepare('SELECT Node.NodeID AS id, NodeName AS name, State AS status,
			Hostname AS hostname, PublicIP AS publicip, PrivateIP AS privateip, 
			InstanceID AS instanceid, Username AS username, passwd
			FROM Node LEFT JOIN NodeData ON Node.NodeID = NodeData.NodeID WHERE Node.SystemID = :systemID');
		$statement->execute(array(':systemID' => $this->systemid));
		$nodes = $statement->fetchAll(PDO::FETCH_ASSOC);
		if (isset($_GET['show'])) $nodes = $this->filterResults($nodes, $_GET['show']);
		$this->sendResponse(array('nodes' => $nodes));
	}
	
	public function getSystemNode ($uriparts) {
		$this->systemid = $uriparts[1];
		$this->nodeid = $uriparts[3];
		$statement = $this->db->prepare('SELECT NodeName AS name, State AS status, 
			PrivateIP AS privateIP, PublicIP AS publicIP, InstanceID AS instanceID FROM Node 
			LEFT JOIN NodeData ON Node.NodeID = NodeData.NodeID AND Node.SystemID = NodeData.SystemID
			WHERE Node.SystemID = :systemID AND Node.NodeID = :nodeID');
		$statement->execute(array(':systemID' => $this->systemid, ':nodeID' => $this->nodeid));
		$node = $statement->fetch(PDO::FETCH_ASSOC);
		if ($node) {
			$node['commands'] = is_null($node['status']) ? null : $this->getCommands($node['status']);
			$node['connections'] = $this->getConnections();
			$node['packets'] = $this->getPackets();
			$node['health'] = $this->getHealth();
			list($node['task'], $node['command']) = $this->getCommand();
			if (isset($_GET['show'])) {
				$nodes = $this->filterResults(array($node), $_GET['show']);
				$node = $nodes[0];
			}
			$this->sendResponse(array('node' => $node));
		}
		else $this->sendErrorResponse('', 404);
	}
	
	public function createSystemNode ($uriparts) {
		$this->systemid = $uriparts[1];
		if (!$this->validateSystem()) $this->sendErrorResponse('Create node gave non-existent system ID '.$this->systemid, 400);
		$parms = json_decode(file_get_contents("php://input"), true);
		$name = $parms['name'];
		$insert = $this->db->prepare('INSERT INTO Node (SystemID, NodeName, State) VALUES (:systemid, :name, :state)');
		$insert->execute(array(
			':systemid' => $this->systemid,
			':name' => $name,
			':state' => (int) @$parms['state']
		));
		$nodeid = $this->db->lastInsertId();
		if (!$name) {
			$name = 'Node '.sprintf('%06d', $nodeid);
			$update = $this->db->prepare('UPDATE Node SET NodeName = :nodename WHERE NodeID = :nodeid');
			$update->execute(array(
				':nodename' => $name,
				':nodeid' => $nodeid
			));
		}
		$this->sendResponse(array('node' => array(
			'node' => $nodeid,
			'system' => $this->systemid,
			'name' => $name,
			'status' => (int) $parms['state']
		)));
	}
	
	protected function validateSystem () {
		$query = $this->db->prepare('SELECT COUNT(*) FROM System WHERE SystemID = :systemid');
		$query->execute(array(':systemid' => $this->systemid));
		return $query->fetch(PDO::FETCH_COLUMN) ? true : false;
	}
	
	protected function getCommands ($status) {
		$query = $this->db->prepare('SELECT CommandID FROM ValidCommands WHERE State = :state');
		$query->execute(array(':state' => $status));
		return $query->fetchAll(PDO::FETCH_COLUMN);
	}
	
	protected function getConnections () {
		return $this->getMonitorData(1);
	}
	
	protected function getPackets () {
		return $this->getMonitorData(2);
	}
	
	protected function getHealth () {
		return $this->getMonitorData(3);
	}
	
	protected function getMonitorData ($monitorid) {
		$this->monitorquery->execute(
			array(':systemid' => $this->systemid, ':monitorid' => $monitorid, ':nodeid' => $this->nodeid)
		);
		return $this->monitorquery->fetchAll(PDO::FETCH_COLUMN);
	}
	
	protected function getCommand () {
		$query = $this->db->prepare('SELECT rowid, CommandID FROM CommandExecution 
			WHERE SystemID = :systemid AND NodeID = :nodeid');
		$query->execute(array(':systemid' => $this->systemid, ':nodeid' => $this->nodeid));
		$row = $query->fetch();
		return $row ? array($row->rowid, $row->CommandID) : array(null, null);
	}
}