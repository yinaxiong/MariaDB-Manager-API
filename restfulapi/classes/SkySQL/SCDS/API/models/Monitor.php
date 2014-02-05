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

class Monitor extends EntityModel {
	protected static $setkeyvalues = true;
	
	protected static $classname = __CLASS__;
	protected static $managerclass = 'SkySQL\\SCDS\\API\\managers\\MonitorManager';

	protected $ordinaryname = 'monitor';
	protected static $headername = 'Monitor';
	
	protected static $updateSQL = 'UPDATE Monitor SET %s WHERE SystemType = :systemtype AND Monitor = :monitor';
	protected static $countSQL = 'SELECT COUNT(*) FROM Monitor WHERE SystemType = :systemtype AND Monitor = :monitor';
	protected static $countAllSQL = 'SELECT COUNT(*) FROM Monitor';
	protected static $insertSQL = 'INSERT INTO Monitor (%s) VALUES (%s)';
	protected static $deleteSQL = 'DELETE FROM Monitor WHERE SystemType = :systemtype AND Monitor = :monitor';
	protected static $selectSQL = 'SELECT %s FROM Monitor WHERE SystemType = :systemtype AND Monitor = :monitor';
	protected static $selectAllSQL = 'SELECT %s FROM Monitor %s';
	
	protected static $getAllCTO = array('monitor');
	
	protected static $keys = array(
		'systemtype' => array('sqlname' => 'SystemType'),
		'monitor' => array('sqlname' => 'Monitor')
	);

	protected static $fields = array(
		'name' => array('sqlname' => 'Name', 'default' => ''),
		'sql' => array('sqlname' => 'SQL', 'default' => ''),
		'description' => array('sqlname' => 'Description', 'default' => ''),
		'decimals' => array('sqlname' => 'Decimals', 'default' => ''),
		'mapping' => array('sqlname' => 'Mapping', 'default' => ''),
		'charttype' => array('sqlname' => 'ChartType', 'default' => ''),
		'delta' => array('sqlname' => 'delta', 'default' => 0),
		'monitortype' => array('sqlname' => 'MonitorType', 'default' => ''),
		'systemaverage' => array('sqlname' => 'SystemAverage', 'default' => 0),
		'interval' => array('sqlname' => 'Interval', 'default' => 0),
		'unit' => array('sqlname' => 'Unit', 'default' => ''),
		'monitorid' => array('sqlname' => 'MonitorID', 'default' => 0, 'insertonly' => true)
	);
	
	public function __construct ($systemtype='galera', $monitor='') {
		$this->systemtype = $systemtype;
		$this->monitor = $monitor;
	}
	
	protected function validateInsert () {
		$this->setInsertValue('monitorid', null);
	}
	
	protected function insertedKey ($insertid) {
		return $this->monitor;
	}
}
