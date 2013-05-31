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
 * Date: May 2013
 * 
 * The Monitor class models a type of monitor which observes system/node behaviour.
 * 
 */

namespace SkySQL\SCDS\API\models;

use \PDO as PDO;

class Monitor extends EntityModel {
	protected static $setkeyvalues = false;
	
	protected static $classname = __CLASS__;

	protected $ordinaryname = 'monitor';
	
	protected static $updateSQL = 'UPDATE Monitors SET %s WHERE MonitorID = :id';
	protected static $countSQL = 'SELECT COUNT(*) FROM Monitors WHERE MonitorID = :id';
	protected static $insertSQL = 'INSERT INTO Monitors (%s) VALUES (%s)';
	protected static $deleteSQL = 'DELETE FROM Monitors WHERE MonitorID = :id';
	protected static $selectSQL = 'SELECT %s FROM Monitors WHERE MonitorID = :id';
	protected static $selectAllSQL = 'SELECT %s FROM Monitors %s';
	
	protected static $getAllCTO = array('id');
	
	protected static $keys = array(
		'id' => 'MonitorID'
	);

	protected static $fields = array(
		'name' => array('sqlname' => 'Name', 'default' => ''),
		'sql' => array('sqlname' => 'SQL', 'default' => ''),
		'description' => array('sqlname' => 'Description', 'default' => ''),
		'type' => array('sqlname' => 'ChartType', 'default' => ''),
		'delta' => array('sqlname' => 'delta', 'default' => 0),
		'monitortype' => array('sqlname' => 'MonitorType', 'default' => ''),
		'systemaverage' => array('sqlname' => 'SystemAverage', 'default' => ''),
		'interval' => array('sqlname' => 'Interval', 'default' => ''),
		'unit' => array('sqlname' => 'Unit', 'default' => '')		
	);
	
	public function __construct ($monitorid=0) {
		$this->id = $monitorid;
	}
	
	protected function validateInsert (&$bind, &$insname, &$insvalue) {
		
	}
}
