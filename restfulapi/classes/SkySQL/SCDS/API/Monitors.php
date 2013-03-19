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
 * The Monitors class deals with requests to do with monitors
 * 
 */

namespace SkySQL\SCDS\API;

use \PDO;

final class Monitors extends ImplementAPI {
	protected $systemid = 0;
	protected $nodeid = 0;
	protected $monitorid = 0;
	
	public function getClasses ($uriparts) {
		$where[] = 'UIOrder IS NOT NULL';
		if (!empty($uriparts[1])) {
			if (preg_match('/[0-9]+/', $uriparts[1])) {
				$where[] = 'MonitorID = :monitorid';
				$bind = array(':monitorid' => $uriparts[1]);
			}
			else {
				$where[] = 'Name LIKE :name';
				$bind = array(':name' => $uriparts[1].'%');
			}
		}
		else $bind = array();
		$query = $this->db->prepare("SELECT MonitorID AS id, Name AS name, SQL AS sql,
			Description AS description, Icon AS icon, ChartType AS type, UIOrder AS uiorder,
			delta, MonitorType AS monitortype, SystemAverage AS systemaverage FROM Monitors
			WHERE ".implode(' AND ', $where)." ORDER BY UIOrder");
		$query->execute($bind);
		$types = $query->fetchAll(PDO::FETCH_ASSOC);
		if (isset($_GET['show'])) $types = $this->filterResults($types, $_GET['show']);
        $this->sendResponse(array('monitorclasses' => $types));
	}
	
	public function storeSameMonitorData ($uriparts) {
		$this->analyseMonitorURI($uriparts, 'storeSameMonitorData');
		if (!isset($_POST['value'])) $this->returnErrorResponse('Updating monitor data but no value supplied', 400);
		$store = $this->db->prepare("UPDATE MonitorData SET Latest = datetime('now') WHERE
			SystemID = :systemid AND MonitorID = :monitorid AND NodeID = :nodeid AND Value = :value
			AND Start = (SELECT MAX(Start) FROM MonitorData WHERE
			SystemID = :systemid AND MonitorID = :monitorid AND NodeID = :nodeid AND Value = :value)");
		$store->execute(array(
				':monitorid' => $this->monitorid,
				':systemid' => $this->systemid,
				':nodeid' => $this->nodeid,
				':value' => $_POST['value']
		));
	}
	
	public function storeNewMonitorData ($uriparts) {
		$this->analyseMonitorURI($uriparts, 'storeNewMonitorData');
		$params = json_decode(file_get_contents("php://input"), true);
		if (!isset($params['value'])) $this->returnErrorResponse('Inserting monitor data but no value supplied', 400);
		$store = $this->db->prepare("INSERT INTO MonitorData (SystemID, NodeID, MonitorID, Value, Start, Latest)
			VALUES (:systemid, :nodeid, :monitorid, :value, datetime('now'), datetime('now')");
		$store->execute(array(
				':monitorid' => $this->monitorid,
				':systemid' => $this->systemid,
				':nodeid' => $this->nodeid,
				':value' => $params['value']
		));
	}
	
	public function monitorData ($uriparts) {
		$this->analyseMonitorURI($uriparts, 'monitorData');
		$unixtime = strtotime(empty($_GET['time']) ? $this->getLatestTime() : $_GET['time']);
		$interval = empty($_GET['interval']) ? 0 : (int) $_GET['interval'];
		if (!$interval) $interval = 30;
		$count = empty($_GET['count']) ? 0 : (int) $_GET['count'];
		if (!$count) $count = 15;
		$monitorinfo = $this->db->prepare('SELECT Value AS value, Start AS start, Latest AS latest
			FROM MonitorData WHERE MonitorID = :monitorid AND SystemID = :systemid AND NodeID = :nodeid
			AND Start < :time AND Latest >= :time');
		$pairs = array();
		while ($count-- > 0) {
			$time = date('Y-m-d H:i:s', $unixtime);
			$unixtime -= $interval;
			$monitorinfo->execute(array(
				':monitorid' => $this->monitorid,
				':systemid' => $this->systemid,
				':nodeid' => $this->nodeid,
				':time' => $time
			));
			$pairs[] = $monitorinfo->fetchAll(PDO::FETCH_ASSOC);
			$this->sendResponse(array('monitor_data' => array_reverse($pairs)));
		}
		
	}
	
	protected function analyseMonitorURI ($uriparts, $method) {
		$this->systemid = $uriparts[1];
		if ('node' == $uriparts[2]) {
			$this->nodeid = $uriparts[3];
			$this->monitorid = $uriparts[5];
		}
		elseif ('monitor' == $uriparts[2]) {
			$this->nodeid = 0;
			$this->monitorid = $uriparts[3];
		}
		else $this->sendErrorResponse("Internal contradiction in Monitors->$method", 500);
	}
	
	protected function getLatestTime () {
		$latest = $this->db->prepare('SELECT MAX(Latest) FROM MonitorData WHERE
			MonitorID = :monitorid, SystemID = :systemid, NodeID = :nodeid');
		$latest->execute(array(
			':monitorid' => $this->monitorid,
			':systemid' => $this->systemid,
			':nodeid' => $this->nodeid
		));
		$date = $latest->fetch(PDO::FETCH_COLUMN);
		if ($date) return $date;
		$this->sendResponse(array('monitor_data' => array()));
	}
}
