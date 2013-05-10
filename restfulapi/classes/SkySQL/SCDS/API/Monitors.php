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
	protected $rawtimes = array();
	protected $rawvalues = array();
	protected $rawrepeats = array();
	
	protected static $fields = array(
		'description' => array('sqlname' => 'Description', 'default' => ''),
		'type' => array('sqlname' => 'ChartType', 'default' => ''),
		'delta' => array('sqlname' => 'delta', 'default' => 0),
		'monitortype' => array('sqlname' => 'MonitorType', 'default' => ''),
		'systemaverage' => array('sqlname' => 'SystemAverage', 'default' => ''),
		'interval' => array('sqlname' => 'Interval', 'default' => ''),
		'unit' => array('sqlname' => 'Unit', 'default' => '')		
	);
	
	public function getMonitorClasses ($uriparts) {
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
		$sql = "SELECT MonitorID AS id, Name AS name, SQL AS sql,
			Description AS description, ChartType AS type, 
			delta, MonitorType AS monitortype, SystemAverage AS systemaverage,
			Interval AS interval, Unit AS unit FROM Monitors";
		if (!empty($where)) $sql .= ' WHERE '.implode(' AND ', $where);
		$query = $this->db->prepare($sql);
		$query->execute($bind);
		$types = $this->filterResults($query->fetchAll(PDO::FETCH_ASSOC));
        $this->sendResponse(array('monitorclasses' => $types));
	}
	
	public function createMonitorClass () {
		//$this->log(print_r($_POST,true))."\n";
		$query = $this->db->prepare("INSERT INTO Monitors (Name, SQL, Description,
			ChartType, delta, MonitorType, SystemAverage, Interval, Unit)
			VALUES (:name, :sql, :description, :type,
			:delta, :monitortype, :systemaverage, :interval, :unit)");
		$query->execute($this->monitorBind());
		$this->sendResponse(array('updatecount' => 0, 'insertkey' => $this->db->lastInsertId()));
	}
	
	public function updateMonitorClass ($uriparts) {
		$this->monitorid = $uriparts[1];
		list($insname, $insvalue, $setter, $bind) = $this->settersAndBinds('PUT', self::$fields);
		$bind[':monitorid'] = $this->monitorid;
		if (!empty($setter)) {
			$update = $this->db->prepare('UPDATE Monitors SET '.implode(', ',$setter).
				' WHERE MonitorID = :monitorid');
			$update->execute($bind);
			$counter = $update->fetch(PDO::FETCH_COLUMN);
		}
		else $counter = 0;
		$this->sendResponse(array('updatecount' => $counter, 'insertkey' => 0));
	}
	
	public function deleteMonitorClass ($uriparts) {
		$this->monitorid = $uriparts[1];
		$delete = $this->db->prepare('DELETE FROM Monitors WHERE MonitorID = :monitorid');
		$delete->execute(array(':monitorid' => $this->monitorid));
		$counter = $delete->rowCount();
		if ($counter) $this->sendResponse(array('deletecount' => $counter));
		else $this->sendErrorResponse('Delete monitor class did not match any monitor class', 404);
	}
	
	protected function monitorBind ($monitorid=0) {
		$bind = array(
			':name' => $this->getParam($this->requestmethod,'name'), 
			':sql' => $this->getParam($this->requestmethod,'sql'), 
			':description' => $this->getParam($this->requestmethod,'description'), 
			':type' => $this->getParam($this->requestmethod,'type'), 
			':delta' => $this->getParam($this->requestmethod,'delta'), 
			':monitortype' => $this->getParam($this->requestmethod,'monitortype'), 
			':systemaverage' => $this->getParam($this->requestmethod,'systemaverage'), 
			':interval' => $this->getParam($this->requestmethod,'interval'), 
			':unit' => $this->getParam($this->requestmethod,'unit')
		);
		if ($monitorid) $bind[':monitorid'] = $monitorid;
		return $bind;
	}
	
	public function storeMonitorData ($uriparts) {
		$this->analyseMonitorURI($uriparts);
		if ($this->paramEmpty('POST', 'value')) $this->sendErrorResponse('Updating monitor data but no value supplied', 400);
		$value = $this->getParam('POST', 'value');
		$stamp = $this->getParam('POST', 'timestamp', time());
		if ('null' == $value) $value = null;
		
		$this->db->query('BEGIN EXCLUSIVE TRANSACTION');
		$check = $this->db->prepare('SELECT Value, Repeats, rowid FROM MonitorData WHERE
			SystemID = :systemid AND MonitorID = :monitorid AND NodeID = :nodeid
			ORDER BY Stamp DESC');
		$check->execute(array(
			':monitorid' => $this->monitorid,
			':systemid' => $this->systemid,
			':nodeid' => $this->nodeid
		));
		$lastrecord = $check->fetch();
		if (is_object($lastrecord) AND $lastrecord->Value == $value AND $lastrecord->Repeats) {
			$store = $this->db->prepare("UPDATE MonitorData SET Stamp = :stamp, Repeats = Repeats + 1 WHERE rowid = :rowid");
			$store->execute(array(
				':stamp' => $stamp,
				':rowid' => $lastrecord->rowid
			));
		}
		else {
			$store = $this->db->prepare("INSERT INTO MonitorData (SystemID, NodeID, MonitorID, Value, Stamp, Repeats)
				VALUES (:systemid, :nodeid, :monitorid, :value, :stamp, :repeats)");
			$store->execute(array(
				':monitorid' => $this->monitorid,
				':systemid' => $this->systemid,
				':nodeid' => $this->nodeid,
				':value' => $value,
				':stamp' => $stamp,
				':repeats' => ((is_object($lastrecord) AND $lastrecord->Value == $value) ? 1 : 0)
			));
		}
		$this->db->query('COMMIT TRANSACTION');
		$this->sendResponse();
	}
	
	public function monitorLatest ($uriparts) {
		$this->analyseMonitorURI($uriparts, 'monitorData');
		$select = $this->db->prepare('SELECT Value FROM MonitorData
			WHERE SystemID = :systemid AND NodeID = :nodeid AND MonitorID = :monitorid
			ORDER BY Stamp DESC');
		$select->execute(array(
			':monitorid' => $this->monitorid,
			':systemid' => $this->systemid,
			':nodeid' => $this->nodeid
		));
		$latest = $select->fetch(PDO::FETCH_COLUMN);
		if (false === $latest) $this->sendErrorResponse('No data matches the request', 404);
		else $this->sendResponse(array('latest' => $latest));
	}
	
	public function monitorData ($uriparts) {
		$this->analyseMonitorURI($uriparts, 'monitorData');
		$this->getSpanParameters();
		if ($this->start) {
			$this->count = min($this->count, (int) floor(($this->finish - $this->start) / $this->interval));
			$this->finish = $this->start + ($this->count * $this->interval);
		}
		else {
			$this->start = $this->finish - ($this->interval * $this->count);
		}
		$data = $this->getRawData($this->start, $this->finish);
		$preceding = $this->getPreceding($this->start);
		if (empty($preceding)) {
			if (empty($data)) $this->sendResponse(array('monitor_data' => array()));
			array_unshift($data, array('timestamp' => $this->start, 'value' => $data[0]['value']));
		}
		else array_unshift($data, $preceding);
		$this->sendResponse(array('monitor_data' => ('avg' == $this->getParam('GET', 'method', 'avg') ? $this->getAveraged($data) : $this->getMinMax($data))));
	}
	
	protected function getAveraged($data) {
		$results['timestamp'][0] = $this->start + (int) ($this->interval/2);
		$results['value'][0] = $k = 0;
		$base = $this->start;
		$top = $base + $this->interval;
		$n = count($data);
		//$data[$n+1]['timestamp'] = 
		$data[$n]['timestamp'] = $this->finish+10;
		//$data[$n+1]['value'] = 
		$data[$n]['value'] = $data[$n-1]['value'];
		//$n++;
		for ($i=0; $i < $n; $i++) {
			$nexttime = $data[$i+1]['timestamp'];
			$results['value'][$k] += $data[$i]['value'] * min($this->interval,(min($top,$nexttime) - max($base,$data[$i]['timestamp'])));
			while ($k < $this->count AND $nexttime > $top) {
				$results['value'][$k] = $results['value'][$k] / $this->interval;
				$k++;
				if ($k >= $this->count) break 2;
				$results['timestamp'][$k] = $results['timestamp'][$k-1] + $this->interval;
				$base = $top;
				$top = $base + $this->interval;
				$results['value'][$k] = $data[$i]['value'] * (min($top,$data[$i+1]['timestamp']) - $base);
			}
		}
		return $results;
	}
	
	protected function getMinMax ($data) {
		$results['timestamp'][0] = $this->start + (int) $this->interval/2;
		$k = 0;
		$base = $this->start;
		$top = $base + $this->interval;
		$n = count($data);
		$data[$n+1]['timestamp'] = $data[$n]['timestamp'] = $this->finish+10;
		$data[$n+1]['value'] = $data[$n]['value'] = $data[$n-1]['value'];
		$n++;
		for ($i=0; $i < $n; $i++) {
			$thistime = $data[$i]['timestamp'];
			$thisvalue = $data[$i]['value'];
			if ($thistime < $top) {
				$results['min'][$k] = isset($results['min'][$k]) ? min ($results['min'][$k],$thisvalue) : $thisvalue;
				$results['max'][$k] = isset($results['max'][$k]) ? max ($results['max'][$k],$thisvalue) : $thisvalue;
			}
			while ($k < $this->count AND $thistime > $top) {
				if (!isset($results['min'][$k])) {
					$results['min'][$k] = $results['max'][$k] = $data[$i-1]['value'];
				}
				$k++;
				if ($k >= $this->count) break 2;
				$results['timestamp'][$k] = $results['timestamp'][$k-1] + $this->interval;
				$base = $top;
				$top = $base + $this->interval;
			}
		}
		return $results;
	}
	
	public function getRawMonitorData ($uriparts) {
		$this->analyseMonitorURI($uriparts, 'monitorData');
		$this->getSpanParameters();
		$start = $this->start ? $this->start : $this->finish - ($this->interval * $this->count);
		$data = $this->getRawData($start, $this->finish);
		array_walk($data, array($this,'transformRawData'));
		$this->sendResponse(array('monitor_rawdata' => array('timestamp' => $this->rawtimes, 'value' => $this->rawvalues, 'repeats' => $this->rawrepeats)));
	}
	
	protected function getPreceding ($start) {
		$preceding = $this->db->prepare('SELECT Value AS value, Stamp AS timestamp, Repeats AS repeats
			FROM MonitorData WHERE SystemID = :systemid AND NodeID = :nodeid AND MonitorID = :monitorid
			AND Stamp < :start ORDER BY Stamp DESC');
		$preceding->execute(array(
			':monitorid' => $this->monitorid,
			':systemid' => $this->systemid,
			':nodeid' => $this->nodeid,
			':start' => $start
		));
		return $preceding->fetch(PDO::FETCH_ASSOC);
	}
	
	protected function getRawData ($from, $to) {
		$select = $this->db->prepare('SELECT Value AS value, 
			Stamp AS timestamp, Repeats AS repeats FROM MonitorData
			WHERE SystemID = :systemid AND NodeID = :nodeid AND MonitorID = :monitorid
			AND Stamp BETWEEN :from AND :to ORDER BY Stamp');
		$select->execute(array(
			':monitorid' => $this->monitorid,
			':systemid' => $this->systemid,
			':nodeid' => $this->nodeid,
			':from' => $from,
			':to' => $to
		));
		return $select->fetchALL(PDO::FETCH_ASSOC);
	}
	
	protected function transformRawData ($rawdata) {
		$this->rawtimes[] = $rawdata['timestamp'];
		$this->rawvalues[] = $rawdata['value'];
		$this->rawrepeats[] = $rawdata['repeats'];
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
	
	protected function getSpanParameters () {
		$this->start = (int) $this->getParam('GET', 'start', 0);
		$this->finish = (int) $this->getParam('GET', 'finish', time());
		$this->interval = (int) $this->getParam('GET', 'interval', (int) $this->config['monitor-defaults']['interval']);
		$this->count = (int) $this->getParam('GET', 'count', (int) $this->config['monitor-defaults']['count']);
	}
}
