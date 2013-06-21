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
 * The Task class models a job in a System, which is the running of a command.
 * 
 */

namespace SkySQL\SCDS\API\models;

use PDO;
use SkySQL\SCDS\API\Request;
use SkySQL\COMMON\AdminDatabase;
use SkySQL\SCDS\API\managers\NodeManager;

class Task extends EntityModel {
	protected static $setkeyvalues = false;
	
	protected static $classname = __CLASS__;

	protected $ordinaryname = 'task';
	protected static $headername = 'Task';
	
	protected static $updateSQL = 'UPDATE Task SET %s WHERE TaskID = :taskid';
	protected static $countSQL = 'SELECT COUNT(*) FROM Task WHERE TaskID = :taskid';
	protected static $insertSQL = 'INSERT INTO Task (%s) VALUES (%s)';
	protected static $deleteSQL = 'DELETE FROM Task WHERE TaskID = :taskid';
	protected static $selectSQL = 'SELECT %s FROM Task WHERE TaskID = :taskid';
	protected static $selectAllSQL = 'SELECT %s FROM Task %s ORDER BY TaskID';
	
	protected static $getAllCTO = array('taskid');
	
	protected static $keys = array(
		'taskid' => array('sqlname' => 'TaskID', 'type' => 'int')
	);
	
	protected $command = '';
	protected $myparams = '';
	protected $node = null;
	protected $steps = '';

	protected static $fields = array(
		'systemid' => array('sqlname' => 'SystemID', 'default' => 0, 'insertonly' => true),
		'nodeid' => array('sqlname' => 'NodeID', 'default' => 0, 'insertonly' => true),
		'privateip' => array('sqlname' => 'PrivateIP', 'default' => '', 'insertonly' => true),
		'username' => array('sqlname' => 'UserName', 'default' => '', 'insertonly' => true),
		'command' => array('sqlname' => 'Command', 'default' => '', 'insertonly' => true),
		'params' => array('sqlname' => 'Params', 'default' => '', 'insertonly' => true),
		'started' => array('sqlname' => 'Started', 'default' => '', 'insertonly' => true),
		'pid' => array('sqlname' => 'PID', 'default' => 0),
		'completed' => array('sqlname' => 'Completed', 'default' => '', 'validate' => 'datetime'),
		'stepindex' => array('sqlname' => 'StepIndex', 'default' => 0),
		'state' => array('sqlname' => 'State', 'default' => 'running')
	);
	
	public function __construct ($taskid=0) {
		$this->taskid = $taskid;
	}
	
	public function insertOnCommand ($command) {
		$this->command = $command;
		return array(parent::insert(false), $this->myparams, $this->node, $this->steps);
	}
	
	protected function validateInsert () {
		$request = Request::getInstance();
		foreach (array('systemid','nodeid','username') as $name) {
			if (empty($this->bind[':'.$name])) $errors[] = "Value for $name is required to run a command";
		}
		if (isset($errors)) $request->sendErrorResponse($errors, 400);
		$this->node = NodeManager::getInstance()->getByID($this->bind[':systemid'], $this->bind[':nodeid']);
		$this->privateip = $this->node->privateip;
		if (!$this->node) $request->sendErrorResponse("No node with system ID {$this->bind[':systemid']} and node ID {$this->bind[':nodeid']}", 400);
		$getcmd = AdminDatabase::getInstance()->prepare('SELECT Steps FROM NodeCommands WHERE Command = :command AND State = :state');
		$getcmd->execute(array(':command' => $this->command, ':state' => $this->node->state));
		$this->steps = $getcmd->fetchColumn();
		if (!$this->steps) $request->sendErrorResponse("Command $this->command is not valid for specified node in its current state", 400);
		foreach (array('command','privateip') as $name) {
			$this->setInsertValue($name, $this->$name);
		}
		$this->setInsertValue('state', 'running');
		$this->myparams = isset($this->bind[':params']) ? $this->bind[':params'] : '';
		$this->setCorrectFormatDate('completed');
		$this->setCorrectFormatDateWithDefault('started');
	}
	
	protected function validateUpdate () {
		if (isset($this->bind[':completed'])) {
			$unixtime = strtotime($this->bind[':completed']);
			if (!$unixtime) $this->bind[':completed'] = date('Y-m-d H:i:s');
			if (!isset($this->bind[':stepindex'])) {
				$sqlname = self::$fields['stepindex']['sqlname'];
				$this->setters[] = "$sqlname = :stepindex";
			}
			$this->bind[':stepindex'] = 0;
		}
	}
}
