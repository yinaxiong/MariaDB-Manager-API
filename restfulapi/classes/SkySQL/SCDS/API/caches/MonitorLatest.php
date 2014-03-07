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
 * Date: October 2013
 * 
 * The MonitorLatest class provides a cache of the most recent monitor observations.
 * 
 */

namespace SkySQL\SCDS\API\caches;

use stdClass;
use SkySQL\COMMON\CACHE\CachedSingleton;
use SkySQL\COMMON\MonitorDatabase;
use SkySQL\SCDS\API\models\Monitor;
use SkySQL\SCDS\API\models\System;

class MonitorLatest extends CachedSingleton {
	protected static $instance = null;
	
	protected $instances = array();

	public static function getInstance () {
		return self::$instance instanceof self ? self::$instance : self::$instance = parent::getCachedSingleton(__CLASS__);
	}
	
	protected function __construct () {
		$monitordb = MonitorDatabase::getInstance();
		$monitordb->beginExclusiveTransaction();
		$latest = $monitordb->query('SELECT Value, Repeats, MonitorID, SystemID, NodeID, Stamp FROM LatestMonitorData');
		foreach ($latest->fetchAll() as $instance) {
			$this->instances[$instance->MonitorID][$instance->SystemID][$instance->NodeID] = array(
				'value' => $instance->Value, 'repeats' => $instance->Repeats, 'timestamp' => $instance->Stamp
			);
		}
	}
	
	public function getLatestValue ($monitorid, $systemid, $nodeid) {
		if (isset($this->instances[$monitorid][$systemid][$nodeid])) {
			$monitor = Monitor::getByMonitorID($monitorid);
			$value = $this->instances[$monitorid][$systemid][$nodeid]['value'];
			return $value ? $value / pow(10,@$monitor->decimals) : $value;
		}
		else return false;
	}
	
	public function getLatestStamp ($monitorid, $systemid, $nodeid) {
		return isset($this->instances[$monitorid][$systemid][$nodeid]) ? $this->instances[$monitorid][$systemid][$nodeid]['timestamp'] : false;
	}
	
	public function getOneMonitorData ($monitorid, $systemid, $nodeid) {
		if (isset($this->instances[$monitorid][$systemid][$nodeid])) {
			$data = $this->instances[$monitorid][$systemid][$nodeid];
			$data['value'] = $this->getLatestValue($monitorid, $systemid, $nodeid);
			return $data;
		}
		return false;
	}
	
	public function getMonitorData ($systemid, $nodeid, $ifmodifiedsince) {
		$system = System::getByID($systemid);
		$monitors = Monitor::getByType(@$system->systemtype);
		$monitorlatest = new stdClass;
		foreach ($monitors as $monitor) {
			$property = $monitor->monitor;
			$monitorlatest->$property = null;
		}
		$lastupdate = 0;
		$modified = false;
		foreach ($this->instances as $monitorid=>$info) if (isset($info[$systemid][$nodeid])) {
			$monitor = Monitor::getByMonitorID($monitorid);
			if ($monitor) {
				$property = $monitor->monitor;
				$monitordata = $info[$systemid][$nodeid];
				$monitorlatest->$property = $monitordata['value'] ? $monitordata['value'] / pow(10,@$monitor->decimals) : $monitordata['value'];
				if ($ifmodifiedsince < $monitordata['timestamp']) $modified = true;
				$lastupdate = max($lastupdate, (int) $monitordata['timestamp']);
			}
		}
		return array($monitorlatest, $lastupdate, $modified);
	}
	
	public function monitorUpdate ($stamp, $systemid, $nodeid, $monitordata) {
		$monitordb = MonitorDatabase::getInstance();
		$monitordb->beginExclusiveTransaction();
		$insertsql = '';
		foreach ($monitordata as $monitorid=>$value) {
			if (isset($this->instances[$monitorid][$systemid][$nodeid])) {
				if ($this->instances[$monitorid][$systemid][$nodeid]['value'] == $value) {
					if (0 == $this->instances[$monitorid][$systemid][$nodeid]['repeats']) {
						$maininserts[] = array(
							'monitorid' => $monitorid,
							'value' => $value,
							'timestamp' => $this->instances[$monitorid][$systemid][$nodeid]['timestamp'],
							'repeats' => 0);
					}
					$updates[] = $monitorid;
					$this->instances[$monitorid][$systemid][$nodeid]['repeats']++;
					$this->instances[$monitorid][$systemid][$nodeid]['timestamp'] = $stamp;
				}
				else {
					$maininserts[] = array(
						'monitorid' => $monitorid,
						'value' => $this->instances[$monitorid][$systemid][$nodeid]['value'],
						'timestamp' => $this->instances[$monitorid][$systemid][$nodeid]['timestamp'],
						'repeats' => $this->instances[$monitorid][$systemid][$nodeid]['repeats']
					);
					$deletes[] = $monitorid;
					$this->insertSQL($insertsql, $monitorid, $systemid, $nodeid, $value, $stamp);
					$this->instances[$monitorid][$systemid][$nodeid]['value'] = $value;
					$this->instances[$monitorid][$systemid][$nodeid]['repeats'] = 0;
				}
			}
			else {
				$this->instances[$monitorid][$systemid][$nodeid] = array('value' => $value, 'repeats' => 0, 'timestamp' => $stamp);
				$this->insertSQL($insertsql, $monitorid, $systemid, $nodeid, $value, $stamp);
			}
			$this->instances[$monitorid][$systemid][$nodeid]['timestamp'] = $stamp;
		}
		if (!empty($updates)) {
			$uplist = implode(',', $updates);
			$update = $monitordb->prepare("UPDATE LatestMonitorData SET Repeats = Repeats + 1, Stamp = :stamp
				WHERE SystemID = :systemid AND NodeID = :nodeid AND MonitorID IN ($uplist)");
			$update->execute(array(
				':stamp' => $stamp,
				':systemid' => $systemid,
				':nodeid' => $nodeid
			));
		}
		if (!empty($deletes)) {
			$delist = implode(',', $deletes);
			$delete = $monitordb->prepare("DELETE FROM LatestMonitorData
				WHERE SystemID = :systemid AND NodeID = :nodeid AND MonitorID IN ($delist)");
			$delete->execute(array(
				':systemid' => $systemid,
				':nodeid' => $nodeid
			));
		}
		if ($insertsql) $monitordb->query($insertsql);
		$this->clearCache();
		return (array) @$maininserts;
	}
	
	protected function insertSQL (&$insertrows, $monitorid, $systemid, $nodeid, $value, $stamp) {
		if (empty($insertrows)) {
			$insertrows = "INSERT INTO LatestMonitorData SELECT";
			$insertrows .= sprintf(" %d AS MonitorID, %d AS SystemID, %d AS NodeID, %d AS Value, %d AS Stamp, %d AS Repeats\n",
				(int) $monitorid, (int) $systemid, (int) $nodeid, ('null' == $value ? 'NULL' : (int) $value), (int) $stamp, 0);
		}
		else {
			$insertrows .= sprintf("UNION SELECT %d, %d, %d, %d, %d, %d\n",
				(int) $monitorid, (int) $systemid, (int) $nodeid, ('null' == $value ? 'NULL' : (int) $value), (int) $stamp, 0);
		}
	}
}