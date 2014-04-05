<?php

/*
 ** Part of the MariaDB Manager API.
 * 
 * This file is distributed as part of MariaDB Enterprise.  It is free
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
 * Copyright 2013 (c) SkySQL Corporation Ab
 * 
 * Author: Martin Brampton
 * Date: February 2013
 * 
 * The Monitors class deals with requests to do with monitors
 * 
 */

namespace SkySQL\SCDS\API\controllers;

use SkySQL\SCDS\API\caches\MonitorLatest;
use SkySQL\SCDS\API\caches\MonitorQueries;
use SkySQL\SCDS\API\models\Monitor;
use SkySQL\SCDS\API\models\System;
use SkySQL\SCDS\API\models\Node;
use SkySQL\COMMON\MonitorDatabase;
use \PDO;

final class Monitors extends ImplementAPI {
	protected $defaultResponse = 'monitor';
	protected $systemid = 0;
	protected $nodeid = 0;
	protected $monitor = null;
	protected $monitorid = 0;
	protected $scale = 1;
	protected $rawtimes = array();
	protected $rawvalues = array();
	protected $rawrepeats = array();
	protected $monitordb = null;
	protected $start = 0;
	protected $finish = 0;
	protected $interval = 0;
	protected $count = 0;
	protected $average = true;
	
	public function getMonitorClasses ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, '', false, 'fields');
		$this->monitorClassesModifiedSince();
		$monitors = Monitor::getAll();
		foreach ($monitors as $type=>$results) $monitors[$type] = $this->filterResults($results);
		$this->sendResponse(array('monitorclasses' => $monitors));
	}

	public function getMonitorClassesByType ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, '', false, 'fields');
		$this->monitorClassesModifiedSince();
		$monitors = Monitor::getByType($uriparts[1]);
			$this->sendResponse(array('monitorclasses' => $this->filterResults($monitors)));
	}
	
	protected function monitorClassesModifiedSince () {
		if ($this->ifmodifiedsince AND $this->ifmodifiedsince > Monitor::lastTimeChanged()) {
			header (HTTP_PROTOCOL.' 304 Not Modified');
			exit;
		}
    }
	
	public function getOneMonitorClass ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, '', false, 'fields');
		$monitor = 	Monitor::getByID($uriparts[1], $uriparts[3]);
		if (!$monitor) $this->sendErrorResponse (sprintf("Monitor class for system type '%s' and key '%s' not found", $uriparts[1], $uriparts[3]), 404);
		$this->sendResponse(array('monitorclass' => $this->filterSingleResult($monitor)));
	}
	
	public function putMonitorClass ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'Insert-Update', false, 'Fields belonging to the monitor resource');
		$oldmonitor = Monitor::getByID($uriparts[1], $uriparts[3]);
		if ($oldmonitor AND !$this->paramEmpty($this->requestmethod, 'decimals')) {
			$newdecimals = (int) $this->getParam($this->requestmethod, 'decimals', 0) - (int) @$oldmonitor->decimals;
			if ($newdecimals) {
				$multiplier = pow(10, (int) $newdecimals);
				$db = MonitorDatabase::getInstance();
				$db->splitMonitorData();
				$db->rescaleMonitorData($oldmonitor->monitorid, $multiplier);
			}
		}
		$monitor = new Monitor($uriparts[1], $uriparts[3]);
		$this->requestor->unsetParam($this->requestmethod, 'monitorid');
		$monitor->save();
	}
	
	public function deleteMonitorClass ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'Delete-Count');
		$monitor = new Monitor($uriparts[1], $uriparts[3]);
		$monitor->delete();
	}
	
	public function storeBulkMonitorData ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'Text: Data Accepted', false, 'timestamp; array of m, s, n, v');
		$monitors = $this->getParam('POST', 'm', array());
		$values = $this->getParam('POST', 'v', array());
		$systemid = $this->getParam('POST', 'systemid', 0);
		$nodeid = $this->getParam('POST', 'nodeid', 0);
		if (count($monitors) != count($values)) {
			$errors[] = 'Bulk data must provide arrays of equal size';
		}
		$limit = count($monitors);
		for ($i = 0; $i < $limit; $i++) {
			if (!$monitors[$i] AND !$values[$i]) {
				unset($monitors[$i], $values[$i]);
				continue;
			}
			$monitor = Monitor::getByMonitorID($monitors[$i]);
			if (!$monitor) $errors[] = "No such monitor ID '{$monitors[$i]}'";
			$monitordata[$monitors[$i]] = (int) empty($monitor->decimals) ? round($values[$i]) : round($values[$i] * pow(10,$monitor->decimals));
		}
		if (empty($monitordata)) $errors[] = 'No bulk data provided';
		if (!System::getByID($systemid)) $errors[] = "No such system ID: '{$systemid}'";
		if (0 != $nodeid AND !Node::getByID($systemid, $nodeid)) $errors[] = "No such node as system ID '{$systemid}', node ID '{$nodeid}'";
		if (isset($errors)) $this->sendErrorResponse($errors, 400);
		
		$this->monitordb = MonitorDatabase::getInstance();
		$this->monitordb->splitMonitorData();
		$tablename = $this->monitordb->createMonitoringTable($systemid, $nodeid);
		$this->monitordb->beginExclusiveTransaction();
		
		$stamp = $this->getParam('POST', 'timestamp', time());
		$inserts = MonitorLatest::getInstance()->monitorUpdate($stamp, $systemid, $nodeid, $monitordata);
		foreach ($inserts as $insert) {
			if (isset($insertrows)) {
				$insertrows .= sprintf("UNION SELECT %d, %d, %d, %d\n", (int) $insert['monitorid'], ('null' == $insert['value'] ? 'NULL' : (int) $insert['value']), $insert['timestamp'], $insert['repeats']);
			}
			else {
				$insertrows = "INSERT INTO $tablename SELECT";
				$insertrows .= sprintf(" %d AS MonitorID, %d AS Value, %d AS Stamp, %d AS Repeats\n", (int) $insert['monitorid'], ('null' == $insert['value'] ? 'NULL' : (int) $insert['value']), $insert['timestamp'], $insert['repeats']);
			}
		}
		
		if (isset($insertrows)) {
			$this->monitordb->query($insertrows);
			// $insert->execute($bind);
		}
		
		$this->monitordb->commitTransaction();
		MonitorQueries::getInstance()->newData(array_keys($monitordata), $systemid, $nodeid, $stamp);
		$this->sendResponse('Data accepted');
	}
	
	public function monitorLatest ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'A single value', false, 'The latest monitor observation');
		$this->analyseMonitorURI($uriparts, 'monitorData');
		$latest = MonitorLatest::getInstance()->getLatestValue($this->monitorid, $this->systemid, $this->nodeid);
		if (false === $latest) $this->sendErrorResponse('No data matches the request', 404);
		else $this->sendResponse(array('latest' => $latest));
	}
	
	public function monitorData ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'Arrays of data - see notes', false, 'method (avg or maxmin), start, finish, interval, count');
		$this->analyseMonitorURI($uriparts, 'monitorData');
		$this->getSpanParameters();
		if ($this->start) {
			$this->count = min($this->count, (int) floor(($this->finish - $this->start) / $this->interval));
			$this->finish = $this->start + ($this->count * $this->interval);
		}
		else {
			$this->start = $this->finish - ($this->interval * $this->count);
		}
		if ($this->ifmodifiedsince AND MonitorQueries::getInstance()->hasBeenDone($this->monitorid, $this->systemid, $this->nodeid, $this->finish, $this->count, $this->interval, $this->average)) {
			header (HTTP_PROTOCOL.' 304 Not Modified');
			exit;
		}
		$results = array('start' => $this->start, 'finish' => $this->finish, 'count' => $this->count, 'interval' => $this->interval);
		$this->monitordb = MonitorDatabase::getInstance();
		$data = $this->getRawData($this->start, $this->finish);
		if (empty($data) OR $data[0]['timestamp'] > $this->start) {
			$preceding = $this->getPreceding($this->start);
			if (empty($preceding)) {
				if (empty($data)) $this->sendNullData($this->average);
				array_unshift($data, array('timestamp' => $this->start, 'value' => $data[0]['value']));
			}
			else array_unshift($data, $preceding);
		}
		MonitorQueries::getInstance()->newQuery($this->monitorid, $this->systemid, $this->nodeid, $this->finish, $this->count, $this->interval, $this->average);
		if ($this->average) {
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
	
	public function getRawMonitorData ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'Arrays of data - see notes', false, 'start, finish, interval, count');
		$this->analyseMonitorURI($uriparts, 'monitorData');
		$this->getSpanParameters();
		$start = $this->start ? $this->start : $this->finish - ($this->interval * $this->count);
		$this->monitordb = MonitorDatabase::getInstance();
		$data = $this->getRawData($start, $this->finish);
		array_walk($data, array($this,'transformRawData'));
		$this->sendResponse(array('monitor_rawdata' => array('timestamp' => $this->rawtimes, 'value' => $this->rawvalues, 'repeats' => $this->rawrepeats)));
	}
	
	protected function getPreceding ($start) {
		if ($this->monitordb->existsMonitoringTable ($this->systemid, $this->nodeid)) {
			$tablename = $this->monitordb->makeTableName($this->systemid, $this->nodeid);
			$preceding = $this->monitordb->prepare("SELECT 1.0 * Value/:scale AS value, Stamp AS timestamp, Repeats AS repeats
				FROM $tablename WHERE MonitorID = :monitorid AND Stamp < :start ORDER BY Stamp DESC");
			$preceding->execute(array(
				':scale' => $this->scale,
				':monitorid' => $this->monitorid,
				':start' => $start
			));
			return $preceding->fetch(PDO::FETCH_ASSOC);
		}
		else return null;
	}
	
	protected function getRawData ($from, $to) {
		if ($this->monitordb->existsMonitoringTable ($this->systemid, $this->nodeid)) {
			$tablename = $this->monitordb->makeTableName($this->systemid, $this->nodeid);
			$select = $this->monitordb->prepare("SELECT 1.0 * Value/:scale AS value, 
				Stamp AS timestamp, Repeats AS repeats FROM $tablename
				WHERE MonitorID = :monitorid AND Stamp BETWEEN :from AND :to ORDER BY Stamp");
		$select->execute(array(
			':scale' => $this->scale,
			':monitorid' => $this->monitorid,
			':from' => $from,
			':to' => $to
		));
		$rawdata = $select->fetchALL(PDO::FETCH_ASSOC);
		$latestdata = MonitorLatest::getInstance()->getOneMonitorData($this->monitorid, $this->systemid, $this->nodeid);
		if ($latestdata AND $latestdata['timestamp'] <= $to) array_push($rawdata, $latestdata);
		return $rawdata;
	}
		else return array();
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
		$system = System::getByID($systemid);
		if (empty($system)) $this->sendErrorResponse("System $systemid does not exist", 400);
		$this->monitor = Monitor::getByID($system->systemtype, $monitorkey);
		if (empty($this->monitor)) $this->sendErrorResponse("Monitor $monitorkey for system ID $systemid not available", 400);
		return $this->monitor->monitorid;
	}
	
	protected function getSpanParameters () {
		$this->start = (int) $this->getParam('GET', 'start', 0);
		$this->interval = (int) $this->getParam('GET', 'interval', (int) $this->config['monitor-defaults']['interval']);
		$this->finish = (int) $this->getParam('GET', 'finish', $this->roundedTime($this->interval));
		$this->count = (int) $this->getParam('GET', 'count', (int) $this->config['monitor-defaults']['count']);
		if ('minmax' == $this->getParam('GET', 'method', 'avg')) $this->average = false;
	}
	
	protected function roundedTime ($interval) {
		$time = time();
		return $time - ($time % $interval);
	}
}
