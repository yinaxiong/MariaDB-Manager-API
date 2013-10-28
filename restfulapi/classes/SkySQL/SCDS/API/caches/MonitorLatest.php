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
 * Date: October 2013
 * 
 * The MonitorLatest class provides a cache of the most recent monitor observations.
 * 
 */

namespace SkySQL\SCDS\API\caches;

use stdClass;
use SkySQL\COMMON\CACHE\CachedSingleton;
use SkySQL\COMMON\MonitorDatabase;
use SkySQL\SCDS\API\managers\MonitorManager;
use SkySQL\SCDS\API\managers\SystemManager;

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
				'value' => $instance->Value, 'repeats' => $instance->Repeats, 'stamp' => $instance->Stamp
			);
		}
	}
	
	public function getMonitorData ($systemid, $nodeid, $ifmodifiedsince) {
		$system = SystemManager::getInstance()->getByID($systemid);
		$monitors = MonitorManager::getInstance()->getByType(@$system->systemtype);
		$monitorlatest = new stdClass;
		foreach ($monitors as $monitor) {
			$property = $monitor->monitor;
			$monitorlatest->$property = null;
		}
		$lastupdate = 0;
		$modified = false;
		foreach ($this->instances as $monitorid=>$info) if (isset($info[$systemid][$nodeid])) {
			$monitor = MonitorManager::getInstance()->getByMonitorID($monitorid);
			if ($monitor) {
				$property = $monitor->monitor;
				$monitordata = $info[$systemid][$nodeid];
				$monitorlatest->$property = $monitordata['value'] / pow(10,@$monitor->decimals);
				if ($ifmodifiedsince < $monitordata['stamp']) $modified = true;
				$lastupdate = max($lastupdate,$monitordata['stamp']);
			}
		}
		return array($monitorlatest, $lastupdate, $modified);
	}
	
	public function monitorUpdate ($stamp, $systemid, $nodeid, $monitorids, $values) {
		$monitordb = MonitorDatabase::getInstance();
		$monitordb->beginExclusiveTransaction();
		$insertsql = '';
		for ($i = 0; $i < count($monitorids); $i++) if (isset($monitorids[$i]) AND isset($values[$i])) {
			if (isset($this->instances[$monitorids[$i]][$systemid][$nodeid])) {
				if ($this->instances[$monitorids[$i]][$systemid][$nodeid]['value'] === $values[$i]) {
					if (0 == $this->instances[$monitorids[$i]][$systemid][$nodeid]['repeats']) {
						$maininserts[] = array(
							'monitorid' => $monitorids[$i],
							'value' => $values[$i],
							'stamp' => $this->instances[$monitorids[$i]][$systemid][$nodeid]['stamp'],
							'repeats' => 0);
					}
					$updates[] = $monitorids[$i];
					$this->instances[$monitorids[$i]][$systemid][$nodeid]['repeats']++;
					$this->instances[$monitorids[$i]][$systemid][$nodeid]['stamp'] = $stamp;
				}
				else {
					$maininserts[] = array(
						'monitorid' => $monitorids[$i],
						'value' => $this->instances[$monitorids[$i]][$systemid][$nodeid]['value'],
						'stamp' => $this->instances[$monitorids[$i]][$systemid][$nodeid]['stamp'],
						'repeats' => $this->instances[$monitorids[$i]][$systemid][$nodeid]['repeats']
					);
					$deletes[] = $monitorids[$i];
					$this->insertSQL($insertsql, $monitorids[$i], $systemid, $nodeid, $values[$i], $stamp);
					$this->instances[$monitorids[$i]][$systemid][$nodeid]['value'] = $values[$i];
					$this->instances[$monitorids[$i]][$systemid][$nodeid]['repeats'] = 0;
				}
			}
			else {
				$this->instances[$monitorids[$i]][$systemid][$nodeid] = array('value' => $values[$i], 'repeats' => 0, 'stamp' => $stamp);
				$this->insertSQL($insertsql, $monitorids[$i], $systemid, $nodeid, $values[$i], $stamp);
			}
			$this->instances[$monitorids[$i]][$systemid][$nodeid]['stamp'] = $stamp;
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