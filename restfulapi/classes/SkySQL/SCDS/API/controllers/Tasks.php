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
 * Date: February 2013
 * 
 * The Tasks class handles task related requests.
 * 
 */

namespace SkySQL\SCDS\API\controllers;

use SkySQL\SCDS\API\models\Task;
use SkySQL\SCDS\API\models\Command;

class Tasks extends ImplementAPI {
	
	public function getMultipleTasks () {
		Task::checkLegal();
		$tasks = Task::select();
		$this->sendResponse(array('tasks' => $this->filterResults($tasks)));
	}
	
	public function getOneTask ($uriparts) {
		Task::checkLegal();
		$task = Task::getByID(array('taskid' => (int) $uriparts[1]));
		$this->sendResponse(array('task' => $this->filterSingleResult($task)));
	}
	
	public function updateTask ($uriparts) {
		Task::checkLegal();
		$task = new Task((int) $uriparts[1]);
		$task->update();
	}
	
	public function runCommand ($uriparts) {
		Command::checkLegal();
		$command = urldecode($uriparts[1]);
		$task = new Task;
		list($TaskID, $params, $node, $steps) = $task->insertOnCommand($command);
		$runfile = rtrim($this->config['shell']['path'],'/\\').'/RunCommand.sh';
		if (!file_exists($runfile)) $this->sendErrorResponse("Script for run command $runfile does not exist", 500);
		if (!is_executable($runfile)) $this->sendErrorResponse("Script for run command $runfile exists but is not executable", 500);
		$cmd = "$runfile $TaskID \"$steps\" \"{$this->config['shell']['hostname']}\" \"$params\" \"$node->privateip\" > /dev/null 2>&1 &";
       	exec($cmd);
		$this->sendResponse(array('task' => Task::getByID(array('taskid' => $TaskID))));
	}
}