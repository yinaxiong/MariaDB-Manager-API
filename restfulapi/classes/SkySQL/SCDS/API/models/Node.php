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
 * The Node class models a node in a System, which is a cluster of database servers.
 * 
 */

namespace SkySQL\SCDS\API\models;

use PDO;
use SkySQL\COMMON\AdminDatabase;
use SkySQL\SCDS\API\API;
use SkySQL\SCDS\API\Request;
use SkySQL\SCDS\API\managers\NodeStateManager;
use SkySQL\SCDS\API\managers\SystemManager;

class Node extends EntityModel {
	protected static $setkeyvalues = true;
	
	protected static $classname = __CLASS__;
	protected static $managerclass = 'SkySQL\\SCDS\\API\\managers\\NodeManager';

	protected $ordinaryname = 'node';
	protected static $headername = 'Node';
	
	protected static $updateSQL = 'UPDATE Node SET %s WHERE SystemID = :systemid AND NodeID = :nodeid';
	protected static $countSQL = 'SELECT COUNT(*) FROM Node WHERE SystemID = :systemid AND NodeID = :nodeid';
	protected static $countAllSQL = 'SELECT COUNT(*) FROM Node';
	protected static $insertSQL = 'INSERT INTO Node (%s) VALUES (%s)';
	protected static $deleteSQL = 'DELETE FROM Node WHERE SystemID = :systemid AND NodeID = :nodeid';
	protected static $selectSQL = 'SELECT %s FROM Node WHERE SystemID = :systemid AND NodeID = :nodeid';
	protected static $selectAllSQL = 'SELECT %s FROM Node %s ORDER BY SystemID, NodeID';
	
	protected static $getAllCTO = array('systemid', 'nodeid');
	
	protected static $keys = array(
		'systemid' => array('sqlname' => 'SystemID', 'type' => 'int'),
		'nodeid' => array('sqlname' => 'NodeID', 'type' => 'int')
	);

	protected static $fields = array(
		'name' => array('sqlname' => 'NodeName', 'default' => ''),
		'state' => array('sqlname' => 'State', 'default' => 'offline'),
		'hostname' => array('sqlname' => 'Hostname', 'default' => ''),
		'publicip' => array('sqlname' => 'PublicIP', 'default' => '', 'validate' => 'ipaddress'),
		'privateip' => array('sqlname' => 'PrivateIP', 'default' => '', 'validate' => 'ipaddress'),
		'port' => array('sqlname' => 'Port', 'default' => 0),
		'instanceid' => array('sqlname' => 'InstanceID', 'default' => ''),
		'dbusername' => array('sqlname' => 'DBUserName', 'default' => ''),
		'dbpassword' => array('sqlname' => 'DBPassword', 'default' => '')
	);
	
	protected static $derived = array(
		'commands' => array('type' => 'array', 'desc' => 'Names of commands that could be run in the present node state'),
		'monitorlatest' => array('type' => 'object', 'desc' => 'Latest value for node for each monitor'),
		'command' => array('type' => 'varchar', 'desc' => 'Name of the command currently running on the node'),
		'taskid' => array('type' => 'int', 'desc' => 'ID number of the task running on the node')
	);
	
	public function __construct ($systemid, $nodeid=0) {
		$this->systemid = $systemid;
		$this->nodeid = $nodeid;
	}

	public function getSystemType () {
		$system = SystemManager::getInstance()->getByID($this->systemid);
		return @$system->systemtype;
	}

	public function getCommands () {
		$query = AdminDatabase::getInstance()->prepare('SELECT Command AS command, Description AS description, Icon AS icon, Steps AS steps 
			FROM NodeCommands WHERE SystemType = :systemtype AND State = :state  ORDER BY UIOrder');
		$query->execute(array(
			':systemtype' => $this->getSystemType(),
			':state' => $this->state
		));
		return $query->fetchAll();
	}
	
	protected function keyComplete () {
		return $this->nodeid ? true : false;
	}
	
	protected function makeNewKey () {
		$highest = AdminDatabase::getInstance()->prepare('SELECT MAX(NodeID) FROM Node WHERE SystemID = :systemid');
		$highest->execute(array(':systemid' => $this->systemid));
		$this->nodeid = 1 + (int) $highest->fetch(PDO::FETCH_COLUMN);
		$this->bind[':nodeid'] = $this->nodeid;
	}
	
	protected function setDefaults () {
		if (empty($this->bind[':name'])) {
			$this->setInsertValue('name', 'Node '.sprintf('%06d', $this->nodeid));
		}
	}

	protected function insertedKey ($insertid) {
		return $this->nodeid;
	}
	
	protected function validateState () {
		return NodeStateManager::getInstance()->getByState($this->getSystemType(), $this->state) ? true : false;
	}
	
	protected function validateInsert () {
		if (empty($this->privateip)) Request::getInstance()->sendErrorResponse('Private IP must be provided to create a node', 400);
		if (!$this->validateState()) Request::getInstance()->sendErrorResponse(sprintf("Node State of '%s' not valid in System Type '%s'", $this->state, $this->getSystemType()), 400);
	}
	
	protected function validateUpdate () {
		if (!$this->validateState()) Request::getInstance()->sendErrorResponse(sprintf("Node State of '%s' not valid in System Type '%s'", $this->state, $this->getSystemType()), 400);
	}
	
	public static function deleteAllForSystem ($systemid) {
		$delete = AdminDatabase::getInstance()->prepare('DELETE FROM Node WHERE SystemID = :systemid');
		$delete->execute(array(':systemid' => $systemid));
	}
}
