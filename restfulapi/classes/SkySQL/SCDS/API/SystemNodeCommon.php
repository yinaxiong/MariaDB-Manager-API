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

abstract class SystemNodeCommon extends ImplementAPI {
	protected $monitorquery = null;
	protected $systemid = 0;
	
	public function __construct ($controller) {
		parent::__construct($controller);
		$this->monitorquery = $this->db->prepare('SELECT Value, MAX(Stamp) FROM MonitorData 
			WHERE SystemID = :systemid AND MonitorID = :monitorid AND NodeID = :nodeid');
	}

	protected function getConnections ($nodeid) {
		return $this->getMonitorData($nodeid, 1);
	}
	
	protected function getPackets ($nodeid) {
		return $this->getMonitorData($nodeid, 2);
	}
	
	protected function getHealth ($nodeid) {
		return $this->getMonitorData($nodeid, 3);
	}
	
	protected function getMonitorData ($nodeid, $monitorid) {
		$this->monitorquery->execute(
			array(':systemid' => $this->systemid, ':monitorid' => $monitorid, ':nodeid' => $nodeid)
		);
		return $this->monitorquery->fetchAll(PDO::FETCH_COLUMN);
	}
	
	protected function getCommands ($state) {
		$query = $this->db->prepare('SELECT CommandID FROM ValidCommands WHERE State = :state');
		$query->execute(array(':state' => $state));
		return $query->fetchAll(PDO::FETCH_COLUMN);
	}
}