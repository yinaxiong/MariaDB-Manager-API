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
	
	public function getMonitorClasses ($uriparts) {
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
			delta, MonitorType AS monitortype, SystemAverage AS systemaverage,
			Interval AS interval, Unit AS unit FROM Monitors
			WHERE ".implode(' AND ', $where)." ORDER BY UIOrder");
		$query->execute($bind);
		$types = $this->filterResults($query->fetchAll(PDO::FETCH_ASSOC));
        $this->sendResponse(array('monitorclasses' => $types));
	}
	
	public function createMonitorClass () {
		$query = $this->db->prepare("INSERT INTO Monitors (Name, SQL, Description,
			Icon, ChartType, UIOrder, delta, MonitorType, SystemAverage, Interval, Unit)
			VALUES (:name, :sql, :description, :icon, :type, :uiorder,
			:delta, :monitortype, :systemaverage, :interval, :unit)");
		$query->execute($this->monitorBind());
		$this->sendResponse();
	}
	
	public function updateMonitorClass ($uriparts) {
		$this->monitorid = $uriparts[0];
		$query = $this->db->prepare("UPDATE Monitors SET Name = :name, SQL = :sql,
			Description = :description, Icon = :icon, ChartType = :type, UIOrder = :uiorder,
			delta = :delta, MonitorType = :monitortype, SystemAverage = :systemaverage,
			Interval = :interval, Unit = :unit FROM Monitors
			WHERE MonitorID = :monitorid");
		$query->execute($this->monitorBind($this->monitorid));
		$this->sendResponse();
	}
	
	public function deleteMonitorClass ($uriparts) {
		$this->monitorid = $uriparts[0];
		$query = $this->db->prepare('DELETE FROM Monitors WHERE MonitorID = :monitorid');
		$query->execute(array(':monitorid' => $this->monitorid));
	}
	
	protected function monitorBind ($monitorid=0) {
		$bind = array(
			':name' => $this->getParam('PUT','name'), 
			':sql' => $this->getParam('PUT','sql'), 
			':description' => $this->getParam('PUT','description'), 
			':icon' => $this->getParam('PUT','icon'), 
			':type' => $this->getParam('PUT','type'), 
			':uiorder' => $this->getParam('PUT','uiorder'),
			':delta' => $this->getParam('PUT','delta'), 
			':monitortype' => $this->getParam('PUT','monitortype'), 
			':systemaverage' => $this->getParam('PUT','systemaverage'), 
			':interval' => $this->getParam('PUT','interval'), 
			':unit' => $this->getParam('PUT','unit')
		);
		if ($monitorid) $bind[':systemid'] = $monitorid;
		return $bind;
	}
	
	public function storeMonitorData ($uriparts) {
		$this->analyseMonitorURI($uriparts);
		$value = $this->getParam('PUT', 'value');
		if (!$value) $this->returnErrorResponse('Updating monitor data but no value supplied', 400);
		
		$this->db->query('BEGIN EXCLUSIVE TRANSACTION');
		$update = $this->db->prepare('SELECT Value, rowid FROM MonitorData WHERE
			SystemID = :systemid AND MonitorID = :monitorid AND NodeID = :nodeid
			ORDER BY Latest DESC');
		$update->execute(array(
			':monitorid' => $this->monitorid,
			':systemid' => $this->systemid,
			':nodeid' => $this->nodeid
		));
		$lastrecord = $update->fetch();
		if ($lastrecord->Value == $value) {
			$store = $this->db->prepare("UPDATE MonitorData SET Latest = datetime('now') WHERE rowid = :rowid");
			$store->execute(array(':rowid', $lastrecord->rowid));
		}
		else {
			$store = $this->db->prepare("INSERT INTO MonitorData (SystemID, NodeID, MonitorID, Value, Start, Latest)
				VALUES (:systemid, :nodeid, :monitorid, :value, datetime('now'), datetime('now')");
			$store->execute(array(
				':monitorid' => $this->monitorid,
				':systemid' => $this->systemid,
				':nodeid' => $this->nodeid,
				':value' => $value
			));
		}
		$this->db->query('COMMIT TRANSACTION');
	}
	
	public function monitorData ($uriparts) {
		$this->analyseMonitorURI($uriparts, 'monitorData');
		$timeparm = $this->getParam('GET', 'time');
		$unixtime = strtotime(empty($timeparm) ? $this->getLatestTime() : $timeparm);
		$results['endtime'] = $unixtime;
		$results['interval'] = $this->getParam('GET', 'interval', (int) $this->config['monitor-defaults']['interval']);
		$count = $this->getParam('GET', 'count', (int) $this->config['monitor-defaults']['count']);
		$results['count'] = $count;
		$results['datamethod'] = $this->getParam('GET', 'datamethod', 'mean');
		$monitorinfo = $this->db->prepare("SELECT $selector FROM MonitorData
			WHERE MonitorID = :monitorid AND SystemID = :systemid AND NodeID = :nodeid
			AND Start < :time AND Latest >= :time");
		$pairs = array();
		while ($count-- > 0) {
			$time = date('Y-m-d H:i:s', $unixtime);
			$unixtime -= $results['interval'];
			$monitorinfo->execute(array(
				':monitorid' => $this->monitorid,
				':systemid' => $this->systemid,
				':nodeid' => $this->nodeid,
				':time' => $time
			));
			$pairs[] = $monitorinfo->fetchAll(PDO::FETCH_ASSOC);
		}
		$this->sendResponse(array('monitor_data' => array_reverse($pairs)));
	}
	
	protected function analyseMonitorURI ($uriparts) {
		$this->systemid = $uriparts[1];
		if ('node' == $uriparts[2]) {
			$this->nodeid = $uriparts[3];
			$this->monitorid = $uriparts[5];
		}
		elseif ('monitor' == $uriparts[2]) {
			$this->nodeid = 0;
			$this->monitorid = $uriparts[3];
		}
		else $this->sendErrorResponse("Internal contradiction in Monitors->storeMonitorData", 500);
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
