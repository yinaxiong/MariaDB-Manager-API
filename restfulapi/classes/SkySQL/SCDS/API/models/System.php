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
 * The System class models a cluster of database servers (nodes).
 * 
 */

namespace SkySQL\SCDS\API\models;

use SkySQL\COMMON\AdminDatabase;
use SkySQL\SCDS\API\API;
use SkySQL\SCDS\API\Request;
use SkySQL\SCDS\API\models\System;
use SkySQL\SCDS\API\managers\SystemPropertyManager;

class System extends EntityModel {
	protected static $setkeyvalues = false;
	
	protected static $managerclass = 'SkySQL\\SCDS\\API\\managers\\SystemManager';

	protected static $updateSQL = 'UPDATE System SET %s WHERE SystemID = :systemid';
	protected static $countSQL = 'SELECT COUNT(*) FROM System WHERE SystemID = :systemid';
	protected static $countAllSQL = 'SELECT COUNT(*) FROM System';
	protected static $insertSQL = 'INSERT INTO System (%s) VALUES (%s)';
	protected static $deleteSQL = 'DELETE FROM System WHERE SystemID = :systemid';
	protected static $selectSQL = 'SELECT %s FROM System WHERE SystemID = :systemid';
	protected static $selectAllSQL = 'SELECT %s FROM System %s';
	
	protected static $getAllCTO = array('systemid');
	
	protected static $keys = array(
		'systemid' => array('sqlname' => 'SystemID', 'desc' => 'ID for the System', 'type' => 'int')
	);

	protected static $fields = array(
		'systemtype' => array('sqlname' => 'SystemType', 'desc' => 'Type of the system e.g. aws or galera', 'default' => 'aws', 'validate' => 'systemtype'),
		'name' => array('sqlname' => 'SystemName', 'desc' => 'Name of the system', 'default' => ''),
		'started' => array('sqlname' => 'InitialStart', 'desc' => 'Date the manager system was set up', 'default' => '', 'validate' => 'datetime'),
		'lastaccess' => array('sqlname' => 'LastAccess', 'desc' => 'Last date the manager system was accessed by a user', 'default' => '', 'validate' => 'datetime'),
		'updated' => array('sqlname' => 'Updated', 'desc' => 'Last date the system record was updated', 'forced' => 'datetime'),
		'state' => array('sqlname' => 'State', 'desc' => 'Current state of the system', 'default' => 'created', 'validate' => 'systemstate'),
		'dbusername' => array('sqlname' => 'DBUserName', 'desc' => 'System default for database user name', 'default' => ''),
		'dbpassword' => array('sqlname' => 'DBPassword', 'desc' => 'System default for database password', 'default' => '', 'mask' => _MOS_NOTRIM),
		'repusername' => array('sqlname' => 'RepUserName', 'desc' => 'System default for replication user name', 'default' => ''),
		'reppassword' => array('sqlname' => 'RepPassword', 'desc' => 'System default for replication user name', 'default' => '', 'mask' => _MOS_NOTRIM)
	);
	
	protected static $derived = array(
		'nodes' => array('type' => 'array', 'desc' => 'ID numbers of nodes belonging to this system'),
		'lastbackup' => array('type' => 'datetime', 'desc' => 'Date and time of last backup'),
		'properties' => array('type' => 'object', 'desc' => 'System properties'),
		'monitorlatest' => array('type' => 'object', 'desc' => 'Latest value for system for each monitor'),
		'lastmonitored' => array('type' => 'datetime', 'desc' => 'Date-time a monitor observation was last received')
	);
	
	public function __construct ($systemid=0) {
		$this->systemid = $systemid;
	}
	
	protected function requestURI () {
		return "system/$this->systemid";
	}
	
	protected function validateInsert () {
		AdminDatabase::getInstance()->beginImmediateTransaction();
		$this->setCorrectFormatDateWithDefault('started');
		$this->setCorrectFormatDateWithDefault('lastaccess');
		$this->checkCredentials();
	}

	protected function insertedKey ($insertid) {
		$this->systemid = $insertid;
		if (empty($this->name)) {
			$this->name = 'System '.sprintf('%06d', $insertid);
			$update = AdminDatabase::getInstance()->prepare(sprintf(self::$updateSQL, 'SystemName = :name'));
			$update->execute(array(
				':systemid' => $this->systemid,
				':name' => $this->name
			));
		}
		return $insertid;
	}

	protected function validateUpdate () {
		$this->setCorrectFormatDate('started');
		$this->setCorrectFormatDate('lastaccess');
		$oldsystem = System::getByID($this->systemid);
		if (empty($this->systemtype)) $this->systemtype = $oldsystem->systemtype;
		if (empty($this->dbusername)) $this->dbusername = $oldsystem->dbusername;
		if (empty($this->dbpassword)) $this->dbpassword = $oldsystem->dbpassword;
		if (empty($this->repusername)) $this->repusername = $oldsystem->repusername;
		if (empty($this->reppassword)) $this->reppassword = $oldsystem->reppassword;
		$this->checkCredentials();
	}
	
	protected function checkCredentials () {
		if ('system' == @API::$systemtypes[$this->systemtype]['wheretofinddb']) {
			if (empty($this->dbusername)) $errors[] = sprintf("A system of type '%s' must have database user set", $this->systemtype);
			elseif ('root' == $this->dbusername) $errors[] = "A system cannot have a database user of 'root'";
			if (empty($this->dbpassword)) $errors[] = sprintf("A system of type '%s' must have database password set", $this->systemtype);
		}
		elseif ('node'== @API::$systemtypes[$this->systemtype]['wheretofinddb']) {
			if (!empty($this->dbusername)) $errors[] = sprintf("A system of type '%s' must not have database user set", $this->systemtype);
			if (!empty($this->dbpassword)) $errors[] = sprintf("A system of type '%s' must not have database password set", $this->systemtype);
		}
		if ('system' == @API::$systemtypes[$this->systemtype]['wheretofindrep']) {
			if (empty($this->repusername)) $errors[] = sprintf("A system of type '%s' must have replication user set", $this->systemtype);
			elseif ('root' == $this->repusername) $errors[] = "A system cannot have a replication user of 'root'";
			if (empty($this->reppassword)) $errors[] = sprintf("A system of type '%s' must have replication password set", $this->systemtype);
		}
		elseif ('node'== @API::$systemtypes[$this->systemtype]['wheretofindrep']) {
			if (!empty($this->repusername)) $errors[] = sprintf("A system of type '%s' must not have replication user set", $this->systemtype);
			if (!empty($this->reppassword)) $errors[] = sprintf("A system of type '%s' must not have replication password set", $this->systemtype);
		}
		if (isset($errors)) Request::getInstance()->sendErrorResponse($errors, 400);
	}

	public function markUpdated ($stamp=0) {
		if (0 == $stamp) $stamp = time();
		$query = AdminDatabase::getInstance()->prepare('UPDATE System SET Updated = :updated 
			WHERE SystemID = :systemid');
		$query->execute(array(
			':updated' => date('Y-m-d H:i:s', $stamp),
			':systemid' => $this->systemid
		));
		$this->clearCache(true);
	}

	public static function sendPOE () {
		$id = uniqid('/system/factory', true);
		$new = AdminDatabase::getInstance()->prepare("INSERT INTO POE (uniqid, stamp) VALUES (:uniqid, datetime('now'))");
		$new->execute(array(':uniqid' => $id));
		header('POE-Links: '.$id);
		exit;
	}
	
	public function delete ($alwaysrespond=true) {
		SystemPropertyManager::getInstance()->deleteAllProperties($this->systemid);
		$node = new Node($this->systemid);
		// Will delete all nodes for system and return
		$node->delete();
		parent::delete($alwaysrespond);
	}
}
