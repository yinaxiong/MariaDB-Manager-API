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

use SkySQL\COMMON\AdminDatabase;
use \PDO as PDO;

class SystemNodes extends ImplementAPI {
	protected $systemid = 0;
	protected $nodeid = 0;
	protected $monitorid = 0;
	protected $monitorquery = null;
	
	public function __construct () {
		parent::__construct();
		$this->monitorquery = $this->db->prepare('SELECT Value, MAX(Latest) FROM MonitorData 
			WHERE SystemID = :systemid AND MonitorID = :monitorid AND NodeID = :nodeid');
	}
	
	public function getSystemNodes ($uriparts) {
		$this->systemid = $uriparts[1];
		$this->nodeid = $uriparts[3];
		$statement = $this->db->prepare('SELECT NodeName AS name, State AS status, 
			PrivateIP AS privateIP, PublicIP AS publicIP, InstanceID AS instanceID FROM Node 
			INNER JOIN NodeData ON Node.NodeID = NodeData.NodeID AND Node.SystemID = NodeData.SystemID
			WHERE Node.SystemID = :systemID AND Node.NodeID = :nodeID');
		$statement->execute(array(':systemID' => $this->systemid, ':nodeID' => $this->nodeid));
		$node = $statement->fetch();
		if ($node) {
			$node['commands'] = is_null($node['status']) ? null : $this->getCommands($node['status']);
			$node['connections'] = $this->getConnections();
			$node['packets'] = $this->getPackets();
			$node['health'] = $this->getHealth();
			list($node['task'], $node['command']) = $this->getCommand();
			$this->sendResponse(array('node' => $node));
		}
		else $this->sendErrorResponse('', 404);
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
			WHERE SystemID = :systemid AND MonitorID = 3 AND NodeID = :nodeid');
		$query->execute(array(':systemid' => $this->systemid, ':nodeid' => $this->nodeid));
		$row = $query->fetch();
		return array($row->rowid, $row->CommandID);
	}
	
	public function getSystemMonitors ($uriparts) {
		$this->systemid = $uriparts[1];
		$this->nodeid = $uriparts[3];
		$this->monitorid = $uriparts[5];
		
	}
}