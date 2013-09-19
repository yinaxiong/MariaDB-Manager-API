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
 * The System class models a cluster of database servers (nodes).
 * 
 */

namespace SkySQL\SCDS\API\models;

use SkySQL\COMMON\AdminDatabase;

class System extends EntityModel {
	protected static $setkeyvalues = true;
	
	protected static $classname = __CLASS__;
	protected static $managerclass = 'SkySQL\\SCDS\\API\\managers\\SystemManager';

	protected $ordinaryname = 'system';
	protected static $headername = 'System';
	
	protected static $updateSQL = 'UPDATE System SET %s WHERE SystemID = :systemid';
	protected static $countSQL = 'SELECT COUNT(*) FROM System WHERE SystemID = :systemid';
	protected static $countAllSQL = 'SELECT COUNT(*) FROM System';
	protected static $insertSQL = 'INSERT INTO System (%s) VALUES (%s)';
	protected static $deleteSQL = 'DELETE FROM System WHERE SystemID = :systemid';
	protected static $selectSQL = 'SELECT %s FROM System WHERE SystemID = :systemid';
	protected static $selectAllSQL = 'SELECT %s FROM System %s';
	
	protected static $getAllCTO = array('systemid');
	
	protected static $keys = array(
		'systemid' => array('sqlname' => 'SystemID', 'type' => 'int')
	);

	protected static $fields = array(
		'systemtype' => array('sqlname' => 'SystemType', 'desc' => 'Type of the system e.g. aws or galera', 'default' => 'aws', 'validate' => 'systemtype'),
		'name' => array('sqlname' => 'SystemName', 'desc' => 'Name of the system', 'default' => ''),
		'started' => array('sqlname' => 'InitialStart', 'desc' => 'Date the manager system was set up', 'default' => '', 'validate' => 'datetime'),
		'lastaccess' => array('sqlname' => 'LastAccess', 'desc' => 'Last date the manager system was accessed by a user', 'default' => '', 'validate' => 'datetime'),
		'updated' => array('sqlname' => 'Updated', 'desc' => 'Last date the system record was updated', 'forced' => '', 'validate' => 'datetime'),
		'state' => array('sqlname' => 'State', 'desc' => 'Current state of the system', 'default' => 'running', 'validate' => 'systemstate'),
		'dbusername' => array('sqlname' => 'DBUserName', 'desc' => 'System default for database user name', 'default' => ''),
		'dbpassword' => array('sqlname' => 'DBPassword', 'desc' => 'System default for database password', 'default' => ''),
		'repusername' => array('sqlname' => 'RepUserName', 'desc' => 'System default for replication user name', 'default' => ''),
		'reppassword' => array('sqlname' => 'RepPassword', 'desc' => 'System default for replication user name', 'default' => '')
	);
	
	protected static $derived = array(
		'nodes' => array('type' => 'array', 'desc' => 'ID numbers of nodes belonging to this system'),
		'lastbackup' => array('type' => 'datetime', 'desc' => 'Date and time of last backup'),
		'properties' => array('type' => 'object', 'desc' => 'System properties'),
		'monitorlatest' => array('type' => 'object', 'desc' => 'Latest value for system for each monitor')
	);
	
	public function __construct ($systemid) {
		$this->systemid = $systemid;
	}
	
	protected function setDefaults () {
		if (empty($this->bind[':name'])) {
			$this->setInsertValue('name', 'System '.sprintf('%06d', $this->systemid));
		}
	}

	protected function validateInsert () {
		$this->setCorrectFormatDateWithDefault('started');
		$this->setCorrectFormatDateWithDefault('lastaccess');
	}
	
	protected function validateUpdate () {
		$this->setCorrectFormatDate('started');
		$this->setCorrectFormatDate('lastaccess');
	}

	public function markUpdated ($stamp=0) {
		if (0 == $stamp) $stamp = time();
		$query = AdminDatabase::getInstance()->prepare('UPDATE System SET Updated = :updated 
			WHERE SystemID = :systemid');
		$query->execute(array(
			':updated' => date('Y-m-d H:i:s', $stamp),
			':systemid' => $this->systemid
		));
	}

	protected function insertedKey ($insertid) {
		return $this->systemid;
	}
}
