<?php

/*
 ** Part of the SkySQL Manager API.
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

namespace SkySQL\SCDS\API\controllers;

use SkySQL\SCDS\API\caches\MonitorLatest;
use SkySQL\SCDS\API\managers\MonitorManager;
use SkySQL\SCDS\API\managers\SystemManager;
use SkySQL\SCDS\API\managers\NodeManager;
use SkySQL\COMMON\MonitorDatabase;
use \PDO;

final class Monitors extends ImplementAPI {
	protected $systemid = 0;
	protected $nodeid = 0;
	protected $monitor = null;
	protected $monitorid = 0;
	protected $scale = 1;
	protected $rawtimes = array();
	protected $rawvalues = array();
	protected $rawrepeats = array();
	protected $monitordb = null;
	
	public function getMonitorClasses ($uriparts) {
		$manager = MonitorManager::getInstance();
		if ($this->ifmodifiedsince AND $this->ifmodifiedsince > $manager->timeStamp()) {
			header (HTTP_PROTOCOL.' 304 Not Modified');
			exit;
		}
		if (empty($uriparts[1])) {
			$monitors = $manager->getAll();
			foreach ($monitors as $type=>$results) $monitors[$type] = $this->filterResults($results);
			$this->sendResponse(array('monitorclasses' => $monitors));
		}
		elseif (empty($uriparts[3])) {
			$systemtype = $uriparts[1];
			$monitors = $manager->getByType($systemtype);
			$this->sendResponse(array('monitorclasses' => $this->filterResults($monitors)));
		}
		else {
			$systemtype = $uriparts[1];
			$monitorkey = $uriparts[3];
			$monitor = 	$manager->getByID($systemtype, $monitorkey);
			$this->sendResponse(array('monitorclass' => $this->filterSingleResult($monitor)));
		}
    }
	
	public function putMonitorClass ($uriparts) {
		$manager = MonitorManager::getInstance();
		$oldmonitor = $manager->getByID($uriparts[1], $uriparts[3]);
		if ($oldmonitor AND !$this->paramEmpty($this->requestmethod, 'decimals')) {
			$newdecimals = (int) $this->getParam($this->requestmethod, 'decimals', 0) - (int) @$oldmonitor->decimals;
			if ($newdecimals) {
				$multiplier = pow(10, (int) $newdecimals);
				$update = MonitorDatabase::getInstance()->prepare("UPDATE MonitorData SET Value = Value * :multiplier WHERE MonitorID = :monitorid");
				$update->execute(array(
					':multiplier' => $multiplier,
					':monitorid' => $oldmonitor->monitorid
				));
			}
		}
		$manager->putMonitor($uriparts[1], $uriparts[3]);
	}
	
	public function deleteMonitorClass ($uriparts) {
		MonitorManager::getInstance()->deleteMonitor($uriparts[1], $uriparts[3]);
	}
	
	public function storeBulkMonitorData () {
		$monitors = $this->getParam('POST', 'm', array());
		$systems = $this->getParam('POST', 's', array());
		$nodes = $this->getParam('POST', 'n', array());
		$values = $this->getParam('POST', 'v', array());
		if (1 < count(array_unique(array(count($monitors), count($systems), count($nodes), count($values))))) {
			$errors[] = 'Bulk data must provide arrays of equal size';
		}
		else if (0 == count($monitors)) $errors[] = 'No bulk data provided';
		$limit = count($monitors);
		for ($i = 0; $i < $limit; $i++) {
			if (!$monitors[$i] AND !$systems[$i] AND !$nodes[$i] AND !$values[$i]) {
				unset($monitors[$i],$systems[$i],$nodes[$i],$values[$i]);
				continue;
			}
			$monitor = MonitorManager::getInstance()->getByMonitorID($monitors[$i]);
			if (!$monitor) $errors[] = "No such monitor ID {$monitors[$i]}";
			if (!SystemManager::getInstance()->getByID($systems[$i])) $errors[] = "No such system ID: {$systems[$i]}";
			if (0 != $nodes[$i] AND !NodeManager::getInstance()->getByID($systems[$i], $nodes[$i])) $errors[] = "No such node as system ID {$systems[$i]}, node ID {$nodes[$i]}";
			if (!empty($monitor->decimals)) $values[$i] = (int) round($values[$i] * pow(10,$monitor->decimals));
		}
		if (isset($errors)) $this->sendErrorResponse($errors, 400);
		
		$this->monitordb = MonitorDatabase::getInstance();
		$this->monitordb->beginExclusiveTransaction();
		//$latest = $this->monitordb->query('SELECT Value, Repeats, MonitorID, SystemID, NodeID, rowid, MAX(Stamp) FROM MonitorData GROUP BY SystemID, MonitorID, NodeID ');
		//foreach ($latest->fetchAll() as $instance) {
		//	$instances[$instance->MonitorID][$instance->SystemID][$instance->NodeID] = array(
		//		'value' => $instance->Value, 'repeats' => $instance->Repeats, 'rowid' => $instance->rowid
		//	);
		//}
		//$instances = MonitorLatest::getInstance()->getForUpdate();
		
		$stamp = $this->getParam('POST', 'timestamp', time());
		$inserts = MonitorLatest::getInstance()->monitorUpdate($stamp, $systems[0], $nodes[0], $monitors, $values);
		foreach ($inserts as $insert) {
			if (isset($insertrows)) {
				$insertrows .= sprintf("UNION SELECT %d, %d, %d, %d, %d, %d\n", (int) $insert['monitorid'], (int) $systems[0], (int) $nodes[0], ('null' == $insert['value'] ? 'NULL' : (int) $insert['value']), $insert['timestamp'], $insert['repeats']);
			}
			else {
				$insertrows = "INSERT INTO MonitorData SELECT";
				$insertrows .= sprintf(" %d AS MonitorID, %d AS SystemID, %d AS NodeID, %d AS Value, %d AS Stamp, %d AS Repeats\n", (int) $insert['monitorid'], (int) $systems[0], (int) $nodes[0], ('null' == $insert['value'] ? 'NULL' : (int) $insert['value']), $insert['timestamp'], $insert['repeats']);
			}
		}
		
		/*
		for ($i = 0; $i < count($monitors); $i++) {
			$previous = isset($instances[$monitors[$i]][$systems[$i]][$nodes[$i]]);
			if ($previous) $value = $instances[$monitors[$i]][$systems[$i]][$nodes[$i]]['value'];
			if ($previous AND $value == $values[$i] AND $instances[$monitors[$i]][$systems[$i]][$nodes[$i]]['repeats']) {
				//$updaterows[] = $instances[$monitors[$i]][$systems[$i]][$nodes[$i]]['rowid'];
				$updaterows[] = $monitors[$i];
			}
			else {
				if (isset($insertrows)) {
					$insertrows .= sprintf("UNION SELECT %d, %d, %d, %d, %d, %d\n", (int) $monitors[$i], (int) $systems[$i], (int) $nodes[$i], ('null' == $values[$i] ? 'NULL' : (int) $values[$i]), (int) $stamp, (($previous AND $value == $values[$i]) ? 1 : 0));
				}
				else {
					$insertrows = "INSERT INTO MonitorData SELECT";
					$insertrows .= sprintf(" %d AS MonitorID, %d AS SystemID, %d AS NodeID, %d AS Value, %d AS Stamp, %d AS Repeats\n", (int) $monitors[$i], (int) $systems[$i], (int) $nodes[$i], ('null' == $values[$i] ? 'NULL' : (int) $values[$i]), (int) $stamp, (($previous AND $value == $values[$i]) ? 1 : 0));
				}
				$bind[":monitorid$i"] = (int) $monitors[$i];
				$bind[":systemid$i"] = (int) $systems[$i];
				$bind[":nodeid$i"] = (int) $nodes[$i];
				$bind[":value$i"] = 'null' == $values[$i] ? null : (int) $values[$i];
				$bind[":stamp$i"] = $stamp;
				$bind[":repeats$i"] = ($previous AND $value == $values[$i]) ? 1 : 0;
			}
		}		
		if (isset($updaterows)) {
			$rowlist = implode(',',$updaterows);
			$update = $this->monitordb->prepare("UPDATE MonitorData SET Repeats = Repeats + 1, Stamp = :stamp 
				WHERE SystemID = :systemid AND NodeID = :nodeid AND MonitorID IN (:rowlist) AND Repeats > 0");
			$update->execute(array(
				':stamp' => $stamp,
				':systemid' => $systems[0],
				':nodeid' => $nodes[0],
				':rowlist' => $rowlist
			));
		}
		 * 
		 */
		if (isset($insertrows)) {
			$this->monitordb->query($insertrows);
			// $insert->execute($bind);
		}
		
		$this->monitordb->commitTransaction();
		$this->sendResponse('Data accepted');
	}
	
	public function monitorLatest ($uriparts) {
		$this->analyseMonitorURI($uriparts, 'monitorData');
		$latest = MonitorLatest::getInstance()->getLatestValue($this->monitorid, $this->systemid, $this->nodeid);
		if (false === $latest) $this->sendErrorResponse('No data matches the request', 404);
		else $this->sendResponse(array('latest' => $latest));
	}
	
	public function monitorData ($uriparts) {
		$this->analyseMonitorURI($uriparts, 'monitorData');
		$this->getSpanParameters();
		$average = ('avg' == $this->getParam('GET', 'method', 'avg'));
		if ($this->start) {
			$this->count = min($this->count, (int) floor(($this->finish - $this->start) / $this->interval));
			$this->finish = $this->start + ($this->count * $this->interval);
		}
		else {
			$this->start = $this->finish - ($this->interval * $this->count);
		}
		$results = array('start' => $this->start, 'finish' => $this->finish, 'count' => $this->count, 'interval' => $this->interval);
		$this->monitordb = MonitorDatabase::getInstance();
		$data = $this->getRawData($this->start, $this->finish);
		$preceding = $this->getPreceding($this->start);
		if (empty($preceding)) {
			if (empty($data)) $this->sendNullData($average);
			array_unshift($data, array('timestamp' => $this->start, 'value' => $data[0]['value']));
		}
		else array_unshift($data, $preceding);
		if ($average) {
			$aresults = $this->getAveraged($data, $results);
			$mmresults = $this->getMinMax($data, $results);
			foreach (array_keys($aresults['value']) as $sub) {
				if (null === $mmresults['min'][$sub] AND null === $mmresults['max'][$sub]) $aresults['value'][$sub] = null;
			}
			$this->sendResponse(array('monitor_data' => $aresults));
		}
		else $this->sendResponse(array('monitor_data' => $this->getMinMax($data, $results)));
	}
	
	protected function sendNullData ($average) {
		if ($average) $data = array('timestamp' => array(), 'value' => array());
		else $data = array('timestamp' => array(), 'min' => array(), 'max' => array());
		$this->sendResponse(array('monitor_data' => $data));
	}
	
	protected function getAveraged ($data, $results) {
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
	
	protected function getMinMax ($data, $results) {
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
			$thisvalue = (float) $data[$i]['value'];
			while ($k < $this->count AND $thistime > $top) {
				if (!isset($results['min'][$k])) {
					$results['min'][$k] = $results['max'][$k] = (float) $data[$i-1]['value'];
				}
				$k++;
				if ($k >= $this->count) break 2;
				$results['timestamp'][$k] = $results['timestamp'][$k-1] + $this->interval;
				$base = $top;
				$top = $base + $this->interval;
			}
			if ($thistime < $top) {
				$results['min'][$k] = isset($results['min'][$k]) ? min ($results['min'][$k],$thisvalue) : $thisvalue;
				$results['max'][$k] = isset($results['max'][$k]) ? max ($results['max'][$k],$thisvalue) : $thisvalue;
			}
		}
		return $results;
	}
	
	public function getRawMonitorData ($uriparts) {
		$this->analyseMonitorURI($uriparts, 'monitorData');
		$this->getSpanParameters();
		$start = $this->start ? $this->start : $this->finish - ($this->interval * $this->count);
		$this->monitordb = MonitorDatabase::getInstance();
		$data = $this->getRawData($start, $this->finish);
		array_walk($data, array($this,'transformRawData'));
		$this->sendResponse(array('monitor_rawdata' => array('timestamp' => $this->rawtimes, 'value' => $this->rawvalues, 'repeats' => $this->rawrepeats)));
	}
	
	protected function getPreceding ($start) {
		$preceding = $this->monitordb->prepare('SELECT 1.0 * Value/:scale AS value, Stamp AS timestamp, Repeats AS repeats
			FROM MonitorData WHERE SystemID = :systemid AND NodeID = :nodeid AND MonitorID = :monitorid
			AND Stamp < :start ORDER BY Stamp DESC');
		$preceding->execute(array(
			':scale' => $this->scale,
			':monitorid' => $this->monitorid,
			':systemid' => $this->systemid,
			':nodeid' => $this->nodeid,
			':start' => $start
		));
		return $preceding->fetch(PDO::FETCH_ASSOC);
	}
	
	protected function getRawData ($from, $to) {
		$select = $this->monitordb->prepare('SELECT 1.0 * Value/:scale AS value, 
			Stamp AS timestamp, Repeats AS repeats FROM MonitorData
			WHERE SystemID = :systemid AND NodeID = :nodeid AND MonitorID = :monitorid
			AND Stamp BETWEEN :from AND :to ORDER BY Stamp');
		$select->execute(array(
			':scale' => $this->scale,
			':monitorid' => $this->monitorid,
			':systemid' => $this->systemid,
			':nodeid' => $this->nodeid,
			':from' => $from,
			':to' => $to
		));
		$rawdata = $select->fetchALL(PDO::FETCH_ASSOC);
		$latestdata = MonitorLatest::getInstance()->getOneMonitorData($this->monitorid, $this->systemid, $this->nodeid);
		if ($latestdata AND $latestdata['timestamp'] <= $to) array_push($rawdata, $latestdata);
		return $rawdata;
	}
	
	protected function transformRawData ($rawdata) {
		$this->rawtimes[] = $rawdata['timestamp'];
		$this->rawvalues[] = $rawdata['value'];
		$this->rawrepeats[] = $rawdata['repeats'];
	}
	
	protected function analyseMonitorURI ($uriparts) {
		$this->systemid = (int) $uriparts[1];
		if ('node' == $uriparts[2]) {
			$this->nodeid = (int) $uriparts[3];
			$this->monitorid = $this->getMonitorIDFromName($this->systemid, $uriparts[5]);
		}
		elseif ('monitor' == $uriparts[2]) {
			$this->nodeid = 0;
			$this->monitorid = $this->getMonitorIDFromName($this->systemid, $uriparts[3]);
		}
		else $this->sendErrorResponse("Internal contradiction in Monitors->storeMonitorData", 500);
		$this->scale = isset($this->monitor->decimals) ? pow(10,$this->monitor->decimals) : 1;
	}
	
	protected function getMonitorIDFromName ($systemid, $monitorkey) {
		$system = SystemManager::getInstance()->getByID($systemid);
		if (empty($system)) $this->sendErrorResponse("System $systemid does not exist", 400);
		$this->monitor = MonitorManager::getInstance()->getByID($system->systemtype, $monitorkey);
		if (empty($this->monitor)) $this->sendErrorResponse("Monitor $monitorkey for system ID $systemid not available", 400);
		return $this->monitor->monitorid;
	}
	
	protected function getSpanParameters () {
		$this->start = (int) $this->getParam('GET', 'start', 0);
		$this->finish = (int) $this->getParam('GET', 'finish', time());
		$this->interval = (int) $this->getParam('GET', 'interval', (int) $this->config['monitor-defaults']['interval']);
		$this->count = (int) $this->getParam('GET', 'count', (int) $this->config['monitor-defaults']['count']);
	}
}

