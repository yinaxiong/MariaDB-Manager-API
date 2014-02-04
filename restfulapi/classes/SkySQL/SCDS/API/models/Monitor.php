<?php

/*
 ** Part of the SkySQL Manager API.
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
 * Copyright 2013 (c) SkySQL Ab
 * 
 * Author: Martin Brampton
 * Date: May 2013
 * 
 * The Monitor class models a type of monitor which observes system/node behaviour.
 * 
 */

namespace SkySQL\SCDS\API\models;

use SkySQL\SCDS\API\managers\MonitorManager;

class Monitor extends EntityModel {
	protected static $setkeyvalues = true;
	
	protected static $managerclass = 'SkySQL\\SCDS\\API\\managers\\MonitorManager';

	protected static $updateSQL = 'UPDATE Monitor SET %s WHERE SystemType = :systemtype AND Monitor = :monitor';
	protected static $countSQL = 'SELECT COUNT(*) FROM Monitor WHERE SystemType = :systemtype AND Monitor = :monitor';
	protected static $countAllSQL = 'SELECT COUNT(*) FROM Monitor';
	protected static $insertSQL = 'INSERT INTO Monitor (%s) VALUES (%s)';
	protected static $deleteSQL = 'DELETE FROM Monitor WHERE SystemType = :systemtype AND Monitor = :monitor';
	protected static $selectSQL = 'SELECT %s FROM Monitor WHERE SystemType = :systemtype AND Monitor = :monitor';
	protected static $selectAllSQL = 'SELECT %s FROM Monitor %s';
	
	protected static $getAllCTO = array('monitor');
	
	protected static $keys = array(
		'systemtype' => array('sqlname' => 'SystemType', 'desc' => 'Code for System Type'),
		'monitor' => array('sqlname' => 'Monitor', 'desc' => 'Identifying name of the monitor')
	);

	protected static $fields = array(
		'name' => array('sqlname' => 'Name', 'desc' => 'Informal name of the monitor', 'default' => ''),
		'sql' => array('sqlname' => 'SQL', 'desc' => 'SQL or other instruction used to implement monitor', 'default' => ''),
		'description' => array('sqlname' => 'Description', 'desc' => 'Description of the monitor', 'default' => ''),
		'decimals' => array('sqlname' => 'Decimals', 'desc' => 'Number of implied decimal places in observations', 'default' => ''),
		'mapping' => array('sqlname' => 'Mapping', 'desc' => 'Mapping of non-numeric observations to integers', 'default' => ''),
		'charttype' => array('sqlname' => 'ChartType', 'desc' => 'The type of chart appropriate for display of monitor data', 'default' => ''),
		'delta' => array('sqlname' => 'delta', 'desc' => 'Whether the measurements are cumulative', 'default' => 0),
		'monitortype' => array('sqlname' => 'MonitorType', 'desc' => 'Indication of which monitor mechanism is to be used', 'default' => ''),
		'systemaverage' => array('sqlname' => 'SystemAverage', 'desc' => 'Whether the observations can be averaged for the whole system', 'default' => 0),
		'interval' => array('sqlname' => 'Interval', 'desc' => 'The frequency of observations, in seconds', 'default' => 0),
		'unit' => array('sqlname' => 'Unit', 'desc' => 'The units of measurement for the monitor', 'default' => ''),
		'monitorid' => array('sqlname' => 'MonitorID', 'desc' => 'The unique ID for the monitor, used internally for efficiency', 'default' => 0, 'insertonly' => true)
	);
	
	public function __construct ($systemtype='galera', $monitor='') {
		$this->systemtype = $systemtype;
		$this->monitor = $monitor;
	}
	
	protected function requestURI () {
		return "monitorclass/$this->systemtype/key/$this->monitor";
	}
	
	protected function validateInsert () {
		$this->setInsertValue('monitorid', null);
	}
	
	protected function insertedKey ($insertid) {
		return $this->monitor;
	}
	
	public static function getByType ($systemtype) {
		return MonitorManager::getInstance()->getByType($systemtype);
	}
	
	public static function getByMonitorID ($monitorid) {
		return MonitorManager::getInstance()->getByMonitorID($monitorid);
	}
	
	public static function lastTimeChanged () {
		return MonitorManager::getInstance()->timeStamp();
	}
}
