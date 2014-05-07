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
 * Date: May 2013
 * 
 * The Node class models a node in a System, which is a cluster of database servers.
 * 
 */

namespace SkySQL\Manager\API\models;

use stdClass;
use SkySQL\COMMON\AdminDatabase;
use SkySQL\Manager\API\API;
use SkySQL\Manager\API\Request;
use SkySQL\Manager\API\managers\NodeManager;
use SkySQL\Manager\API\managers\NodeStateManager;
use SkySQL\Manager\API\managers\ComponentPropertyManager;
use SkySQL\Manager\API\models\System;
use SkySQL\Manager\API\models\NodeCommand;

class Node extends EntityModel {
	protected static $setkeyvalues = true;
	
	protected static $managerclass = 'SkySQL\\Manager\\API\\managers\\NodeManager';
	
	protected static $updateSQL = 'UPDATE Node SET %s WHERE SystemID = :systemid AND NodeID = :nodeid';
	protected static $countSQL = 'SELECT COUNT(*) FROM Node WHERE SystemID = :systemid AND NodeID = :nodeid';
	protected static $countAllSQL = 'SELECT COUNT(*) FROM Node';
	protected static $insertSQL = 'INSERT INTO Node (%s) VALUES (%s)';
	protected static $deleteSQL = 'DELETE FROM Node WHERE SystemID = :systemid AND NodeID = :nodeid';
	protected static $selectSQL = 'SELECT %s FROM Node WHERE SystemID = :systemid AND NodeID = :nodeid';
	protected static $selectAllSQL = 'SELECT %s FROM Node %s ORDER BY SystemID, NodeID';
	
	protected static $getAllCTO = array('systemid', 'nodeid');
	
	protected static $keys = array(
		'systemid' => array('sqlname' => 'SystemID', 'desc' => 'ID for the System containing the Node', 'desc' => 'ID for the System'),
		'nodeid' => array('sqlname' => 'NodeID', 'desc' => 'ID for the Node')
	);

	protected static $fields = array(
		'name' => array('sqlname' => 'NodeName', 'desc' => 'Name of the Node', 'default' => ''),
		'state' => array('sqlname' => 'State', 'desc' => 'Current state of the node', 'default' => 'created'),
		'updated' => array('sqlname' => 'Updated', 'desc' => 'Last date the node record was updated', 'forced' => 'datetime'),
		'hostname' => array('sqlname' => 'Hostname', 'desc' => 'In some systems, a hostname that identifies the node', 'default' => ''),
		'publicip' => array('sqlname' => 'PublicIP', 'desc' => 'In some systems, the public IP address of the node', 'default' => '', 'validate' => 'ipaddress'),
		'privateip' => array('sqlname' => 'PrivateIP', 'desc' => 'The IP address that accesses the node internally to the manager', 'default' => '', 'validate' => 'ipaddress'),
		'port' => array('sqlname' => 'Port', 'desc' => 'The port number used to access the database on the node', 'default' => 0),
		'instanceid' => array('sqlname' => 'InstanceID', 'desc' => 'The instance ID field is for information only and is not used within the Manager', 'default' => ''),
		'dbusername' => array('sqlname' => 'DBUserName', 'desc' => 'Node system override for database user name', 'default' => ''),
		'dbpassword' => array('sqlname' => 'DBPassword', 'desc' => 'Node system override for database password', 'default' => '', 'mask' => _MOS_NOTRIM),
		'repusername' => array('sqlname' => 'RepUserName', 'desc' => 'Node system override for replication user name', 'default' => ''),
		'reppassword' => array('sqlname' => 'RepPassword', 'desc' => 'Node system override for replication user name', 'default' => '', 'mask' => _MOS_NOTRIM),
		'scriptrelease' => array('sqlname' => 'ScriptRelease', 'desc' => 'Release number for scripts installed on node', 'default' => '1.0'),
		'dbtype' => array('sqlname' => 'DBType', 'desc' => 'Database server product installed', 'default' => 'MariaDB'),
		'dbversion' => array('sqlname' => 'DBVersion', 'desc' => 'Database server version installed', 'default' => '5.5.35'),
		'linuxname' => array('sqlname' => 'LinuxName', 'desc' => 'Linux Distribution installed', 'default' => 'CentOS'),
		'linuxversion' => array('sqlname' => 'LinuxVersion', 'desc' => 'Linux Distribution version installed', 'default' => '6.5'),
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

	protected function requestURI () {
		return "system/$this->systemid/node/$this->nodeid";
	}
	
	public function getSystemType () {
		if (self::isProvisioningState($this->state)) return 'provision';
		else return $this->getNaturalSystemType ();
	}

	public function getNaturalSystemType () {
		$system = System::getByID($this->systemid);
		return @$system->systemtype;
	}

	public function getCommands () {
		$commands = NodeCommand::getRunnable($this->getSystemType(), $this->state);
		foreach ($commands as $sub=>&$command) {
			if (Task::tasksNotFinished($command->command, $this)) unset($commands[$sub]);
			else $doablecommands[] = $command->command;
		}
		foreach ($commands as $sub=>&$command) {
			$this->checkForUpgrade($command, (array) @$doablecommands);
		}
		return array_values($commands);
	}
	
	public function getSteps ($commandname) {
		$commandobject = NodeCommand::getByID($commandname, $this->getSystemType(), $this->state);
		$this->checkForUpgrade($commandobject);
		return $commandobject ? $commandobject->steps : '';
	}
	
	protected function checkForUpgrade ($commandobject, $doablecommands) {
		if ($commandobject instanceof NodeCommand AND 'connect' != $commandobject->command AND $commandobject->steps AND version_compare($this->scriptrelease, _API_RELEASE_NUMBER, 'lt')) {
			if (in_array('stop', $doablecommands)) $commandobject->steps = 'stop,upgrade';
			else $commandobject->steps = 'upgrade';
		} 
	}
	
	public function insert ($alwaysrespond = true) {
		$system = new System($this->systemid);
		$system->markUpdated();
		parent::insert($alwaysrespond);
	}

	public function update ($alwaysrespond = true) {
		$request = Request::getInstance();
		$old = self::getByID($this->systemid, $this->nodeid);
		if (!$old) $request->sendErrorResponse(sprintf("Update node, no node with system ID '%s' and node ID '%s'", $this->systemid, $this->nodeid), 400);
		$stateid = $request->getParam($request->getMethod(), 'stateid', 0);
		if ($stateid) {
			$newstate = self::getStateByID($this->getNaturalSystemType(), $stateid);
			$request->putParam($request->getMethod(), 'state', $newstate);
		}
		else $newstate = $request->getParam($request->getMethod(), 'state');
		if ($newstate AND $newstate != $old->state AND self::isProvisioningState($old->state)) {
			// Force loading of the Node State set of classes
			class_exists ('SkySQL\\Manager\\API\\models\\NodeProvisioningStates');
			try {
				$stateobj = NodeNullState::create($old->state);
				$stateobj->make($newstate);
			}
			catch (LogicException $l) {
				$request->sendErrorResponse($l->getMessage(), 500);
			}
			catch (DomainException $d) {
				$request->sendErrorResponse($d->getMessage(), 409);
			}
		}
		parent::update($alwaysrespond);
	}
	
	public function delete ($alwaysrespond=true) {
		if ($this->nodeid) {
			if (isset($this->maincache[$this->systemid][$this->nodeid])) unset($this->maincache[$this->systemid][$this->nodeid]);
			$system = new System($this->systemid);
			$system->markUpdated();
			parent::delete($alwaysrespond);
		}
		else {
			// Must delete components before altering data about nodes
			ComponentPropertyManager::getInstance()->deleteAllComponentsForSystem($this->systemid);
			self::deleteAllForSystem($this->systemid);
			$this->clearCache();
		}
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
		if (self::isProvisioningState(@$this->state)) return true;
		return self::getStateByName($this->getSystemType(), @$this->state) ? true : false;
	}
	
	protected function validateInsert () {
		if (empty($this->privateip)) Request::getInstance()->sendErrorResponse('Private IP must be provided to create a node', 400);
		$this->checkIPPort();
		if (!empty($this->state) AND 'created' != $this->state) Request::getInstance()->sendErrorResponse(sprintf("Node State of '%s' not permitted for new node", @$this->state), 400);
		$this->checkCredentials();
	}
	
	protected function checkIPPort () {
		$already = NodeManager::getInstance()->usedIP($this->privateip, $this->port);
		if (!empty($already)) {
			Request::getInstance()->sendErrorResponse(sprintf("Node Private IP of '%s' and Port '%s' duplicates an existing IP/port found in node ID(s) '%s'", $this->privateip, $this->port, implode(',', $already)), 409);
		}
	}
	
	protected function validateUpdate () {
		$manager = NodeManager::getInstance();
		if (! empty($this->private)) {
			$this->checkIPPort();
		}
		if (@$this->state AND !$this->validateState()) Request::getInstance()->sendErrorResponse(sprintf("Node State of '%s' not valid in System Type '%s'", @$this->state, $this->getSystemType()), 400);
		$oldnode = $manager->getByID($this->systemid, $this->nodeid);
		if (!$oldnode) Request::getInstance()->sendErrorResponse(sprintf("Node with systemid '%s' and nodeid '%s' does not exist", $this->systemid, $this->nodeid), 404);
		if (empty($this->dbusername)) $this->dbusername = $oldnode->dbusername;
		if (empty($this->dbpassword)) $this->dbpassword = $oldnode->dbpassword;
		if (empty($this->repusername)) $this->repusername = $oldnode->repusername;
		if (empty($this->reppassword)) $this->reppassword = $oldnode->reppassword;
		$this->checkCredentials();
	}

	protected function checkCredentials () {
		$systemtype = $this->getNaturalSystemType();
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

	// Appears to never be used - why?
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
	
	public static function getDescription ($systemid, $nodeid) {
		$node = self::getByID($systemid, $nodeid);
		if ($node) {
			$system = System::getByID($systemid);
			if ($system) return sprintf("node called '%s' in system called '%s' (S%d, N%d)", $node->name, $system->name, $systemid, $nodeid);
			else {
				$systemname = 'unknown';
				return sprintf("node called '%s' in system called '%s' (S%d, N%d)", $node->name, $systemname, $systemid, $nodeid);
			}
		}
		else return "unknown node";
	}

	public static function getAllForSystem ($system, $state='') {
		return NodeManager::getInstance()->getAllForSystem($system, $state);
	}
	
	public static function getAllIDsForSystem ($system) {
		return NodeManager::getInstance()->getAllIDsForSystem($system);
	}
	// Only used when system is being deleted, so no need to mark system updated
	public static function deleteAllForSystem ($systemid) {
		$delete = AdminDatabase::getInstance()->prepare('DELETE FROM Node WHERE SystemID = :systemid');
		$delete->execute(array(':systemid' => $systemid));
	}

	public static function sendPOE ($systemid) {
		$id = uniqid("/system/$systemid/node/factory");
		$new = AdminDatabase::getInstance()->prepare("INSERT INTO POE (link, stamp) VALUES (:link, datetime('now')");
		$new->execute(array(':link' => $id));
		header('POE-Links: '.$id);
		exit;
	}
	
	public static function getAllStates () {
		$allstates = new stdClass();
		foreach (array_keys(API::$systemtypes) as $type) $allstates->$type = self::getAllStatesForType($type);
		return $allstates;
	}
	
	public static function getAllStatesForType ($type) {
		$pstates = API::mergeStates(API::$provisionstates);
		$nstates = isset(API::$systemtypes[$type]) ? API::mergeStates(API::$systemtypes[$type]['nodestates']) : array();
		return array_merge($pstates, $nstates);
	}
	
	public static function getStateByName ($systemtype, $state) {
		return isset(API::$systemtypes[$systemtype]['nodestates'][$state]) ? API::$systemtypes[$systemtype]['nodestates'][$state] : null;
	}
	
	public static function getStateByID ($systemtype, $stateid) {
		$nodestates = @API::$systemtypes[$systemtype]['nodestates'];
		if (is_array($nodestates)) foreach ($nodestates as $state=>$properties) {
			if ($stateid == $properties['stateid']) return $state;
		}
	}
	
	public static function isProvisioningState ($state) {
		return isset(API::$provisionstates[$state]);
	}
}
