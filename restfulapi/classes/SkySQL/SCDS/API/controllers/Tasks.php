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

class Tasks extends ImplementAPI {
	public function getMultipleTasks () {
		$tasks = Task::select();
		$this->sendResponse(array('task' => $this->filterResults($tasks)));
	}
	
	public function getOneTask ($uriparts) {
		$tasks = Task::getByID(array('id' => (int) $uriparts[1]));
		$this->sendResponse(array('task' => $this->filterResults($tasks)));
	}
	
	public function updateTask ($uriparts) {
		$task = new Task((int) $uriparts[1]);
		$task->update();
	}
	
	public function runCommand ($uriparts) {
		$command = urldecode($uriparts[1]);
		list($commandid,$steps) = $this->getCommand($command);
		$task = new Task;
		list($TaskID, $params) = $task->insertOnCommand($commandid);
		$runfile = rtrim($this->config['shell']['path'],'/\\').'/RunCommand.sh';
		if (!file_exists($runfile)) $this->sendErrorResponse("Script for run command $runfile does not exist", 500);
		if (!is_executable($runfile)) $this->sendErrorResponse("Script for run command $runfile exists but is not executable", 500);
		$cmd = "$runfile $TaskID \"$steps\" \"{$this->config['shell']['hostname']}\" \"$params\" > /dev/null 2>&1 &";
       	exec($cmd);
       	$this->getOneTask(array(1 => $TaskID));
	}
	
	protected function getCommand ($command) {
		$getter = $this->db->prepare('SELECT CommandID, Steps FROM Commands WHERE Name LIKE :command');
		$getter->execute(array(':command' => $command));
		$comdata = $getter->fetch();
		if (!$comdata->CommandID) $this->sendErrorResponse("Apparently valid command $command not found in Commands table", 500);
		return array($comdata->CommandID,$comdata->Steps);
	}
}