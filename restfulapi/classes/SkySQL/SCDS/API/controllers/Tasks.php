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
 * Date: February 2013
 * 
 * The Tasks class handles task related requests.
 * 
 */

namespace SkySQL\SCDS\API\controllers;

use SkySQL\SCDS\API\Request;
use SkySQL\SCDS\API\models\Task;
use SkySQL\SCDS\API\models\Schedule;
use SkySQL\SCDS\API\models\Command;
use SkySQL\SCDS\API\models\Node;

class Tasks extends TaskScheduleCommon {
	protected $defaultResponse = 'task';
	
	public function getMultipleTasks ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, '', true, 'fields');
		Task::checkLegal();
		list($total, $tasks) = Task::select($this);
		$this->sendResponse(array('total' => $total, 'tasks' => $this->filterResults($tasks)));
	}
	
	public function getOneTask ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, '', false, 'fields');
		Task::checkLegal();
		$task = Task::getByID((int) $uriparts[1]);
		if ($task) {
			if ($this->ifmodifiedsince < strtotime($task->updated)) $this->modified = true;
			if ($this->ifmodifiedsince AND !$this->modified) {
				header (HTTP_PROTOCOL.' 304 Not Modified');
				exit;
			}
		}
		else $this->sendErrorResponse(sprintf("No task with taskid '%d'", (int) $uriparts[1]), 404);
		$this->sendResponse(array('task' => $this->filterSingleResult($task)));
	}
	
	public function cancelOneTask ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'Delete-Count');
		Task::checkLegal();
		$task = Task::getByID((int) $uriparts[1]);
		if ($task AND $task->pid) {
			$this->cancel($task);
			$counter = $task->updateState('cancelled');
			$this->sendResponse(array('deletecount' => $counter));
		}
		if ($task) $this->sendErrorResponse(sprintf("Attempt to cancel task ID '%d' but ID does not match any task", (int) $uriparts[1]), 404);
		else $this->sendErrorResponse(sprintf("Attempt to cancel task ID '%d' but task record has no PID", (int) $uriparts[1]), 409);
	}
	
	public function getSelectedTasks ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, '', true, 'fields');
		Task::checkLegal();
		list($total, $tasks) = Task::select($this, trim($uriparts[1]));
		$this->sendResponse(array('total' => $total, 'tasks' => $this->filterResults($tasks)));
	}
	
	public function updateTask ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'Insert-Update', false, 'Fields for task resource');
		Task::checkLegal();
		$task = new Task((int) $uriparts[1]);
		$task->update();
	}
	
	public function runCommand ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'task or schedule', false, 'systemid, nodeid, username, parameters, state, steps, icalentry', 'systemid, nodeid, username');
		Command::checkLegal('icalentry');
		$command = new Command($uriparts[1]);
		if ($this->paramEmpty($this->requestmethod,'systemid')) $errors[] = sprintf("Command '%s' requested, but required systemid not provided", $command->command);
		if ($this->paramEmpty($this->requestmethod,'nodeid')) $errors[] = sprintf("Command '%s' requested, but required nodeid not provided", $command->command);
		if ($this->paramEmpty($this->requestmethod,'username')) $errors[] = sprintf("Command '%s' requested, but required username not provided", $command->command);
		if (Request::getInstance()->compareVersion('1.0', 'gt') AND !$this->paramEmpty($this->requestmethod, 'parameters')) {
			$errors[] = sprintf("Command '%s' requested, but obsolete parameter 'parameters' submitted", $command->command);
		}
		if (isset($errors)) $this->sendErrorResponse($errors, 400);
		$command->setPropertiesFromParams();
		$state = $this->getParam('POST', 'state');
		$node = Node::getByID($command->systemid, $command->nodeid);
		if (!($node instanceof Node)) $this->sendErrorResponse(sprintf("Command '%s' requested on node (S%d, N%d) but there is no such node", $command->command, $command->systemid, $command->nodeid), 409);
		if ($state AND $state != $node->state) {
			$this->sendErrorResponse(sprintf("Command '%s' required %s to be in state '%s' but it is in state '%s'", $command->command, Node::getDescription($node->systemid, $node->nodeid), $state, $node->state), 409);
		}
		$scriptdir = rtrim(@$this->config['shell']['path'],'/\\');
		foreach (array('LaunchCommand','RunCommand') as $script) {
			if (!is_executable($scriptdir."/$script.sh")) {
				$errors[] = "Script $scriptdir/$script.sh does not exist or is not executable";
			}
		}
		if (isset($errors)) $this->sendErrorResponse($errors,500);
		if (empty($command->icalentry)) {
			if (Task::tasksNotFinished($command->command, $node)) {
				$this->sendErrorResponse(sprintf("Command '%s' on %s but another command is still running on the node, or a critical command is running on the system", $command->command, Node::getDescription($node->systemid, $node->nodeid)), 409);
			}
			$this->immediateCommand ($command);
		}
		else $this->scheduledCommand($command);
	}
	
	protected function scheduledCommand ($command) {
		$schedule = new Schedule;
		// insertOnCommand also fixes dates as RFC
		$schedule->insertOnCommand($command->command);
		$this->setRunAt($schedule);
		//if ($schedule->isDue()) $this->runScheduleNow($schedule);
		$this->sendResponse(array('schedule' => $schedule->withDateFix()));
	}
	
	protected function immediateCommand ($command) {
		$task = new Task;
		// insertOnCommand also fixes dates as RFC
		$task->insertOnCommand($command->command);
		$this->execute($task);
		$this->sendResponse(array('task' => $task->withDateFix()));
	}
}