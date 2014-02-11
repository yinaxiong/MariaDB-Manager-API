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
 * The Task class models a job in a System, which is the running of a command.
 * 
 */

namespace SkySQL\SCDS\API\models;

use PDO;
use stdClass;
use SkySQL\SCDS\API\API;
use SkySQL\SCDS\API\Request;
use SkySQL\COMMON\AdminDatabase;
use SkySQL\SCDS\API\models\System;
use SkySQL\SCDS\API\models\Node;
use SkySQL\SCDS\API\models\User;

class Task extends TaskScheduleCommon {
	protected static $setkeyvalues = false;
	
	protected static $updateSQL = 'UPDATE Task SET %s WHERE TaskID = :taskid';
	protected static $countSQL = 'SELECT COUNT(*) FROM Task WHERE TaskID = :taskid';
	protected static $countAllSQL = 'SELECT COUNT(*) FROM Task';
	protected static $insertSQL = 'INSERT INTO Task (%s) VALUES (%s)';
	protected static $deleteSQL = 'DELETE FROM Task WHERE TaskID = :taskid';
	protected static $selectSQL = 'SELECT %s FROM Task WHERE TaskID = :taskid';
	protected static $selectAllSQL = 'SELECT %s FROM Task %s ORDER BY Updated DESC';
	
	protected static $getAllCTO = array('taskid');
	
	protected static $keys = array(
		'taskid' => array('sqlname' => 'TaskID', 'desc' => 'ID of the task')
	);
	public $taskid = 0;
	
	protected $node = null;
	protected $xparameters = array();

	protected static $fields = array(
		'systemid' => array('sqlname' => 'SystemID', 'desc' => 'ID of the System on which task was run', 'default' => 0, 'insertonly' => true),
		'nodeid' => array('sqlname' => 'NodeID', 'desc' => 'ID of the Node on which task was run', 'default' => 0, 'insertonly' => true),
		'privateip' => array('sqlname' => 'PrivateIP', 'desc' => 'IP Address of the node on which the task was run', 'default' => '', 'insertonly' => true),
		'scheduleid' => array('sqlname' => 'ScheduleID', 'desc' => 'If the task was scheduled, the ID number of the schedule', 'default' => '', 'insertonly' => true),
		'username' => array('sqlname' => 'UserName', 'desc' => 'Username of the user who requested the task', 'default' => '', 'insertonly' => true),
		'command' => array('sqlname' => 'Command', 'desc' => 'The command that was run', 'default' => '', 'insertonly' => true),
		'parameters' => array('sqlname' => 'Params', 'desc' => 'The parameters supplied for the command steps', 'default' => '', 'insertonly' => true),
		'steps' => array('sqlname' => 'Steps', 'desc' => 'The steps that were run', 'default' => ''),
		'started' => array('sqlname' => 'Started', 'desc' => 'The date and time at which the task started', 'default' => '', 'validate' => 'datetime', 'insertonly' => true),
		'pid' => array('sqlname' => 'PID', 'desc' => 'The PID (process ID) for the running command', 'default' => 0),
		'updated' => array('sqlname' => 'Updated', 'desc' => 'Last date and time the task record was updated', 'forced' => 'datetime'),
		'completed' => array('sqlname' => 'Completed', 'desc' => 'The date and time the task completed', 'default' => '', 'validate' => 'datetime'),
		'stepindex' => array('sqlname' => 'StepIndex', 'desc' => 'If the task is running, the current step number', 'default' => 0),
		'state' => array('sqlname' => 'State', 'desc' => 'The current state of the task', 'default' => 'running'),
		'errormessage' => array('sqlname' => 'ErrorMessage', 'desc' => 'If the task failed, an error message', 'default' => '')
	);

	protected static $derived = array(
		'finished' => array('type' => 'boolean', 'desc' => 'Whether the task is finished')
	);

	public function __construct ($taskid=0) {
		$this->taskid = $taskid;
	}
	
	protected function requestURI () {
		return "task/$this->taskid";
	}
	
	public function insertOnCommand ($command) {
		$this->command = $command;
		parent::insert(false);
		$this->derivedFields();
	}

	protected function insertedKey ($insertid) {
		$this->taskid = $insertid;
		return $insertid;
	}

	public function updatePIDandState ($pid) {
		$database = AdminDatabase::getInstance();
		$update = $database->prepare("UPDATE Task SET PID = :pid, State = 'running' WHERE TaskID = :taskid");
		$update->execute(array(':pid' => $pid, ':taskid' => $this->taskid));
		$this->pid = $pid;
		$this->state = 'running';
	}
	
	public function updateState ($state) {
		$database = AdminDatabase::getInstance();
		$update = $database->prepare("UPDATE Task SET State = :state WHERE TaskID = :taskid");
		$update->execute(array(':state' => $state, ':taskid' => $this->taskid));
		$this->state = $state;
		return $update->rowCount();
	}
	
	public function markErrorCompletion () {
		$database = AdminDatabase::getInstance();
		$this->completed = date('Y-m-d H:i:s');
		$this->state = 'error';
		$update = $database->prepare("UPDATE Task SET State = 'error', Completed = :now WHERE TaskID = :taskid");
		$update->execute(array(':now' => $this->completed, ':taskid' => $this->taskid));
	}
	
	public function setNodeData ($systemid, $nodeid) {
		$this->node = Node::getByID($systemid, $nodeid);
		if ($this->node) {
			$this->steps = $this->node->getSteps($this->command);
			$this->privateip = $this->node->privateip;
		}
	}
	
	protected function setSteps () {
		$request = Request::getInstance();
		$this->steps = $this->node->getSteps($this->command);
		if (!$this->steps) $request->sendErrorResponse(sprintf("Command '%s' is not valid for %s in its current state of '%s'", $this->command, Node::getDescription($this->node->systemid, $this->node->nodeid), $this->node->state), 409);
		$permitted = $request->getParam('POST', 'steps');
		if ($permitted) {
			$permits = array_map('trim', explode(',', $permitted));
			$steparray = explode(',', $this->steps);
			if ($permits != $steparray) $request->sendErrorResponse(sprintf("Command '%s' required %s to run steps '%s' but it would currently run steps '%s'", $this->command, Node::getDescription($this->systemid, $this->nodeid), $permitted, $this->steps), 409);
		}
	}
	
	protected function derivedFields () {
		$this->finished = (isset(API::$commandstates[$this->state]) AND API::$commandstates[$this->state]['finished']);
	}
	
	protected function validateInsert () {
		$request = Request::getInstance();
		foreach (array('systemid','nodeid','username') as $name) {
			if (empty($this->bind[':'.$name])) $errors[] = "Value for $name is required to run a command";
		}
		if (isset($errors)) $request->sendErrorResponse($errors, 400);
		$this->node = Node::getByID($this->bind[':systemid'], $this->bind[':nodeid']);
		if (!$this->node) $request->sendErrorResponse("No node with system ID {$this->bind[':systemid']} and node ID {$this->bind[':nodeid']}", 400);
		if (!User::getByID($this->bind[':username'])) {
			$request->sendErrorResponse(sprintf("User name '%s' for command not a valid user", $this->bind[':username']), 400);
		}
		$this->privateip = $this->node->privateip;
		$this->setSteps();
		foreach (array('command','privateip', 'steps') as $name) {
			$this->setInsertValue($name, $this->$name);
		}
		$this->setCorrectFormatDate('completed');
		$this->setCorrectFormatDateWithDefault('started');
		if ($request->compareVersion('1.0', 'gt')) $this->processParameters();
		else $this->removeSensitiveParameters();
	}
	
	protected function validateUpdate () {
		if (isset($this->bind[':completed']) AND 'done' == @$this->bind[':state']) {
			$unixtime = strtotime($this->bind[':completed']);
			if (!$unixtime) $this->bind[':completed'] = date('Y-m-d H:i:s');
			if (!isset($this->bind[':stepindex'])) {
				$sqlname = self::$fields['stepindex']['sqlname'];
				$this->setter[] = "$sqlname = :stepindex";
			}
			$this->bind[':stepindex'] = 0;
		}
		$oldtask = Task::getByID($this->taskid);
		if ('running' != $oldtask->state AND ('cancelled' == $this->state OR 'missing' == $this->state)) {
			$this->state = $oldtask->state;
			$this->bind[':state'] = $oldtask->state;
		}
		$this->removeSensitiveParameters();
	}
	
	public static function latestForNode ($node) {
		$latest = AdminDatabase::getInstance()->prepare("SELECT TaskID FROM Task WHERE SystemID = :systemid AND NodeID = :nodeid ORDER BY Updated DESC");
		$latest->execute(array(
			':systemid' => $node->systemid,
			':nodeid' => $node->nodeid
		));
		$taskid = $latest->fetch(PDO::FETCH_COLUMN);
		if ($taskid) {
			$task = self::getByID($taskid);
			$task->derivedFields();
			return $task;
		}
		else return new stdClass();
	}
	
	// Checks across the whole system for unfinished "risky" running tasks
	public static function tasksNotFinished ($commandname, $node) {
		$system = System::getByID($node->systemid);
		if (!isset(API::$systemtypes[$system->systemtype])) Request::getInstance()->sendErrorResponse(sprintf("System with ID '%s' does not have valid system type", $node->systemid), 500);
		$database = AdminDatabase::getInstance();
		$unfinished = API::unfinishedCommandStates();
		$where[] = "State IN ($unfinished)";
		$where[] = "NodeID = :nodeid";
		$bind[':nodeid'] = $node->nodeid;
		$nconditions = implode(' AND ', $where);
		$nodecommands = $database->prepare("SELECT COUNT(*) FROM Task WHERE $nconditions");
		$nodecommands->execute($bind);
		if ($nodecommands->fetch(PDO::FETCH_COLUMN)) return true;
		$onepersystem = API::$systemtypes[$system->systemtype]['onecommandpersystem'];
		if (!$onepersystem) return false;
		unset($where, $bind);
		$persystems = array_map('trim', explode(',', $onepersystem));
		if (!in_array($commandname, $persystems)) return false;
		$systemcondition = "'".implode("','", $persystems)."'";
		$where[] = "State IN ($unfinished)";
		$where[] = "SystemID = :systemid";
		$where[] = "Command IN ($systemcondition)";
		$bind[':systemid'] = $node->systemid;
		$sconditions = implode(' AND ', $where);
		$count = AdminDatabase::getInstance()->prepare("SELECT COUNT(*) FROM Task WHERE $sconditions");
		$count->execute($bind);
		return $count->fetch(PDO::FETCH_COLUMN) ? true : false;
	}

	// Optional parameters are fromdate and todate, comma separated, in $args[0]
	protected static function specialSelected ($args) {
		return parent::dateRange(@$args[0], 'Started', 'tasks');
	}
}
