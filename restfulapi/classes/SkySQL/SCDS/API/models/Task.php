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

use SkySQL\SCDS\API\Request;

class Task extends EntityModel {
	protected static $setkeyvalues = false;
	
	protected static $classname = __CLASS__;
	protected $ordinaryname = 'task';
	
	protected static $updateSQL = 'UPDATE CommandExecution SET %s WHERE TaskID = :id';
	protected static $countSQL = 'SELECT COUNT(*) FROM CommandExecution WHERE TaskID = :id';
	protected static $insertSQL = 'INSERT INTO CommandExecution (%s) VALUES (%s)';
	protected static $deleteSQL = 'DELETE FROM CommandExecution WHERE TaskID = :id';
	protected static $selectSQL = 'SELECT %s FROM CommandExecution WHERE TaskID = :id';
	protected static $selectAllSQL = 'SELECT %s FROM CommandExecution %s ORDER BY TaskID';
	
	protected static $getAllCTO = array('id');
	
	protected static $keys = array(
		'id' => 'TaskID'
	);
	
	protected $commandid = 0;
	protected $params = '';

	protected static $fields = array(
		'system' => array('sqlname' => 'SystemID', 'default' => 0, 'insertonly' => true),
		'node' => array('sqlname' => 'NodeID', 'default' => 0, 'insertonly' => true),
		'username' => array('sqlname' => 'UserName', 'default' => '', 'insertonly' => true),
		'command' => array('sqlname' => 'CommandID', 'default' => 0, 'insertonly' => true),
		'params' => array('sqlname' => 'Params', 'default' => '', 'insertonly' => true),
		'start' => array('sqlname' => 'Start', 'default' => '', 'insertonly' => true),
		'completed' => array('sqlname' => 'Completed', 'default' => ''),
		'stepindex' => array('sqlname' => 'StepIndex', 'default' => 0),
		'state' => array('sqlname' => 'State', 'default' => 0)
	);
	
	public function __construct ($taskid=0) {
		$this->id = $taskid;
	}
	
	public function insertOnCommand ($commandid) {
		$this->commandid = $commandid;
		return array(parent::insert(false), $this->params);
	}
	
	protected function validateInsert (&$bind, &$insname, &$insvalue) {
		foreach (array('system','node','username') as $name) {
			if (empty($bind[':'.$name])) $errors = "Value for $name is required to run a command";
		}
		if (isset($errors)) Request::getInstance()->sendErrorResponse($errors, 400);
		if ($this->commandid) {
			$bind[':command'] = $this->commandid;
			$insname[] = self::$fields['command']['sqlname'];
			$insvalue[] = ':command';
		}
		if (isset($bind['params'])) $this->params = $bind['params'];
		if (isset($bind[':completed'])) {
			$unixtime = strtotime($bind[':completed']);
			if (!$unixtime) $bind[':completed'] = date('Y-m-d H:i:s');
			$bind[':stepindex'] = 0;
		}
		if (!isset($bind['start'])) {
			$insname[] = self::$fields['start']['sqlname'];
			$insvalue[] = ':start';
			$bind[':start'] = date('Y-m-d H:i:s');
		}
	}

	protected function validateUpdate (&$bind, &$setters) {
		if (isset($bind[':completed'])) {
			$unixtime = strtotime($bind[':completed']);
			if (!$unixtime) $bind[':completed'] = date('Y-m-d H:i:s');
			if (!isset($bind[':stepindex'])) {
				$sqlname = self::$fields['stepindex']['sqlname'];
				$setters[] = "$sqlname = :stepindex";
			}
			$bind[':stepindex'] = 0;
		}
	}
}
