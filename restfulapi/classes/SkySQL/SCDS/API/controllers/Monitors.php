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

use SkySQL\SCDS\API\managers\MonitorManager;
use SkySQL\SCDS\API\models\Monitor;
use \PDO;

final class Monitors extends ImplementAPI {
	protected $systemid = 0;
	protected $nodeid = 0;
	protected $monitorid = 0;
	protected $rawtimes = array();
	protected $rawvalues = array();
	protected $rawrepeats = array();
	
	public function getMonitorClasses ($uriparts) {
		$manager = MonitorManager::getInstance();
		if (empty($uriparts[1])) {
			$monitors = $manager->getAll();
			$this->sendResponse(array('monitorclasses' => $this->filterResults($monitors)));
		}
		else {
			$monitorkey = urldecode($uriparts[1]);
			$monitor = 	$manager->getByID($monitorkey);
			$this->sendResponse(array('monitorclass' => $this->filterSingleResult($monitor)));
		}
    }
	
	public function putMonitorClass ($uriparts) {
		MonitorManager::getInstance()->putMonitor(urldecode($uriparts[1]));
	}
	
	public function deleteMonitorClass ($uriparts) {
		MonitorManager::getInstance()->deleteMonitor(urldecode($uriparts[1]));
	}
	
	public function storeMonitorData ($uriparts) {
		$this->analyseMonitorURI($uriparts);
		$monitor = MonitorManager::getInstance()->getByID($this->monitorid);
		if ($this->paramEmpty('POST', 'value')) $this->sendErrorResponse('Updating monitor data but no value supplied', 400);
		$value = $this->getParam('POST', 'value');
		$stamp = $this->getParam('POST', 'timestamp', time());
		if ('null' == $value) $value = null;
		else {
			$scale = isset($monitor->scale) ? $monitor->scale : 0;
			$value =  $scale ? (int) round($value * pow(10,$scale)) : (int) $value;
		}
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
		$this->db->commitTransaction();
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
		$average = ('avg' == $this->getParam('GET', 'method', 'avg'));
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
			if (empty($data)) $this->sendNullData($average);
			array_unshift($data, array('timestamp' => $this->start, 'value' => $data[0]['value']));
		}
		else array_unshift($data, $preceding);
		$this->sendResponse(array('monitor_data' => ($average ? $this->getAveraged($data) : $this->getMinMax($data))));
	}
	
	protected function sendNullData ($average) {
		if ($average) $data = array('timestamp' => array(), 'value' => array());
		else $data = array('timestamp' => array(), 'min' => array(), 'max' => array());
		$this->sendResponse(array('monitor_data' => $data));
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
