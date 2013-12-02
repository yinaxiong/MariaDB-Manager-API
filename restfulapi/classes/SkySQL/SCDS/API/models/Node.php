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
 * The Node class models a node in a System, which is a cluster of database servers.
 * 
 */

namespace SkySQL\SCDS\API\models;

use SkySQL\COMMON\AdminDatabase;
use SkySQL\SCDS\API\API;
use SkySQL\SCDS\API\Request;
use SkySQL\SCDS\API\managers\NodeManager;
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
		'state' => array('sqlname' => 'State', 'default' => 'created'),
		'updated' => array('sqlname' => 'Updated', 'desc' => 'Last date the system record was updated', 'forced' => 'datetime'),
		'hostname' => array('sqlname' => 'Hostname', 'default' => ''),
		'publicip' => array('sqlname' => 'PublicIP', 'default' => '', 'validate' => 'ipaddress'),
		'privateip' => array('sqlname' => 'PrivateIP', 'default' => '', 'validate' => 'ipaddress'),
		'port' => array('sqlname' => 'Port', 'default' => 0),
		'instanceid' => array('sqlname' => 'InstanceID', 'default' => ''),
		'dbusername' => array('sqlname' => 'DBUserName', 'desc' => 'Node system override for database user name', 'default' => ''),
		'dbpassword' => array('sqlname' => 'DBPassword', 'desc' => 'Node system override for database password', 'default' => '', 'mask' => _MOS_NOTRIM),
		'repusername' => array('sqlname' => 'RepUserName', 'desc' => 'Node system override for replication user name', 'default' => ''),
		'reppassword' => array('sqlname' => 'RepPassword', 'desc' => 'Node system override for replication user name', 'default' => '', 'mask' => _MOS_NOTRIM)
	);
	
	protected static $derived = array(
		'commands' => array('type' => 'array', 'desc' => 'Command objects representing commands that could be run in the present node state'),
		'monitorlatest' => array('type' => 'object', 'desc' => 'Latest value for node for each monitor'),
		'lastmonitored' => array('type' => 'datetime', 'desc' => 'Date-time a monitor observation was last received'),
		'task' => array('type' => 'object', 'desc' => 'The task currently running on the node')
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
		$query = AdminDatabase::getInstance()->prepare("SELECT Command AS command, Description AS description, Steps AS steps 
			FROM NodeCommands WHERE (SystemType = :systemtype OR SystemType = 'provision') AND State = :state 
			AND NOT (SystemType = 'galera' AND State = 'provisioned' AND Command = 'restore') ORDER BY UIOrder");
		$query->execute(array(
			':systemtype' => $this->getSystemType(),
			':state' => $this->state
		));
		$commands = $query->fetchAll();
		foreach ($commands as &$command) $command->steps = API::trimCommaSeparatedList($command->steps);
		return $commands;
	}
	
	protected function insertedKey ($insertid) {
		$this->nodeid = $insertid;
		if (empty($this->name)) {
			$this->name = 'Node '.sprintf('%06d', $insertid);
			$update = AdminDatabase::getInstance()->prepare(sprintf(self::$updateSQL, 'NodeName = :name'));
			$update->execute(array(
				':systemid' => $this->systemid,
				':nodeid' => $this->nodeid,
				':name' => $this->name
			));
		}
		return $insertid;
	}
	
	protected function validateState () {
		$nsm = NodeStateManager::getInstance();
		if ($nsm->isProvisioningState(@$this->state)) return true;
		return $nsm->getByState($this->getSystemType(), @$this->state) ? true : false;
	}
	
	protected function validateInsert () {
		if (empty($this->privateip)) Request::getInstance()->sendErrorResponse('Private IP must be provided to create a node', 400);
		if (NodeManager::getInstance()->usedIP($this->privateip)) Request::getInstance()->sendErrorResponse(sprintf("Node Private IP of '%s' duplicates an existing IP", $this->privateip), 409);
		if (!empty($this->state) AND 'created' != $this->state) Request::getInstance()->sendErrorResponse(sprintf("Node State of '%s' not permitted for new node", @$this->state), 400);
		$this->checkCredentials();
	}
	
	protected function validateUpdate () {
		$manager = NodeManager::getInstance();
		if (! empty($this->private)) {
			if ($manager->usedIP($this->privateip)) Request::getInstance()->sendErrorResponse(sprintf("Node Private IP of '%s' duplicates an existing IP", $this->privateip), 409);
		}
		if (@$this->state AND !$this->validateState()) Request::getInstance()->sendErrorResponse(sprintf("Node State of '%s' not valid in System Type '%s'", @$this->state, $this->getSystemType()), 400);
		$oldnode = $manager->getByID($this->systemid, $this->nodeid);
		if (empty($this->dbusername)) $this->dbusername = $oldnode->dbusername;
		if (empty($this->dbpassword)) $this->dbpassword = $oldnode->dbpassword;
		if (empty($this->repusername)) $this->repusername = $oldnode->repusername;
		if (empty($this->reppassword)) $this->reppassword = $oldnode->reppassword;
		$this->checkCredentials();
	}

	protected function checkCredentials () {
		$systemtype = $this->getSystemType();
		if ('node' == @API::$systemtypes[$systemtype]['wheretofinddb']) {
			if (empty($this->dbusername)) $errors[] = sprintf("A node in a system of type '%s' must have database user set", $systemtype);
			elseif ('root' == $this->dbusername) $errors[] = "A node cannot have a database user of 'root'";
			if (empty($this->dbpassword)) $errors[] = sprintf("A node in a system of type '%s' must have database password set", $systemtype);
		}
		elseif ('system'== @API::$systemtypes[$systemtype]['wheretofinddb']) {
			if (!empty($this->dbusername)) $errors[] = sprintf("A node in a system of type '%s' must not have database user set", $systemtype);
			if (!empty($this->dbpassword)) $errors[] = sprintf("A node in a system of type '%s' must not have database password set", $systemtype);
		}
		if ('node' == @API::$systemtypes[$systemtype]['wheretofindrep']) {
			if (empty($this->repusername)) $errors[] = sprintf("A node in a system of type '%s' must have replication user set", $systemtype);
			elseif ('root' == $this->repusername) $errors[] = "A node cannot have a replication user of 'root'";
			if (empty($this->reppassword)) $errors[] = sprintf("A node in a system of type '%s' must have replication password set", $systemtype);
		}
		elseif ('system'== @API::$systemtypes[$systemtype]['wheretofindrep']) {
			if (!empty($this->repusername)) $errors[] = sprintf("A node in a system of type '%s' must not have replication user set", $systemtype);
			if (!empty($this->reppassword)) $errors[] = sprintf("A node in a system of type '%s' must not have replication password set", $systemtype);
		}
		if (isset($errors)) Request::getInstance()->sendErrorResponse($errors, 400);
	}

	public function markUpdated ($stamp=0) {
		if (0 == $stamp) $stamp = time();
		$query = AdminDatabase::getInstance()->prepare('UPDATE Node SET Updated = :updated 
			WHERE SystemID = :systemid AND NodeID = :nodeid');
		$query->execute(array(
			':updated' => date('Y-m-d H:i:s', $stamp),
			':systemid' => $this->systemid,
			':nodeid' => $this->nodeid
		));
	}
	
	// Only used when system is being deleted, so no need to mark system updated
	public static function deleteAllForSystem ($systemid) {
		$delete = AdminDatabase::getInstance()->prepare('DELETE FROM Node WHERE SystemID = :systemid');
		$delete->execute(array(':systemid' => $systemid));
	}
}
