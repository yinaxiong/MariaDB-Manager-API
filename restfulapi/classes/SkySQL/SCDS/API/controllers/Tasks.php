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
use SkySQL\SCDS\API\models\Schedule;
use SkySQL\SCDS\API\models\Command;
use SkySQL\SCDS\API\models\Node;
use SkySQL\SCDS\API\managers\NodeManager;

class Tasks extends TaskScheduleCommon {
	
	public function getMultipleTasks () {
		Task::checkLegal();
		list($total, $tasks) = Task::select($this);
		$this->sendResponse(array('total' => $total, 'tasks' => $this->filterResults($tasks)));
	}
	
	public function getOneTask ($uriparts) {
		Task::checkLegal();
		$task = Task::getByID(array('taskid' => (int) $uriparts[1]));
		if ($task) {
			if ($this->ifmodifiedsince < strtotime($task->updated)) $this->modified = true;
			if ($this->ifmodifiedsince AND !$this->modified) {
				header (HTTP_PROTOCOL.' 304 Not Modified');
				exit;
			}
		}
		$this->sendResponse(array('task' => $this->filterSingleResult($task)));
	}
	
	public function cancelOneTask ($uriparts) {
		Task::checkLegal();
		$task = Task::getByID(array('taskid' => (int) $uriparts[1]));
		if ($task) {
			$counter = $task->updateState('cancelled');
			$this->sendResponse(array('deletecount' => $counter));
		}
		$this->sendErrorResponse(sprintf("Attempt to cancel task ID '%d' but ID does not match any task", (int) $uriparts[1]), 404);
	}
	
	public function getSelectedTasks ($uriparts) {
		Task::checkLegal();
		list($total, $tasks) = Task::select($this, trim(urldecode($uriparts[1])));
		$this->sendResponse(array('total' => $total, 'tasks' => $this->filterResults($tasks)));
	}
	
	public function updateTask ($uriparts) {
		Task::checkLegal();
		$task = new Task((int) $uriparts[1]);
		$task->update();
	}
	
	public function runCommand ($uriparts) {
		Command::checkLegal('icalentry');
		$command = new Command(urldecode($uriparts[1]));
		$command->setPropertiesFromParams();
		$state = $this->getParam('POST', 'state');
		$node = NodeManager::getInstance()->getByID($command->systemid, $command->nodeid);
		if ($state AND ($node instanceof Node) AND $state != $node->state) {
			$this->sendErrorResponse(sprintf('Command required node (%d, %d) to be in state %s but it is in state %s', $node->systemid, $node->nodeid, $state, $node->state), 409);
		}
		$scriptdir = rtrim(@$this->config['shell']['path'],'/\\');
		foreach (array('LaunchCommand','RunCommand') as $script) {
			if (!is_executable($scriptdir."/$script.sh")) {
				$errors[] = "Script $scriptdir/$script.sh does not exist or is not executable";
			}
		}
		if (isset($errors)) $this->sendErrorResponse($errors,500);
		if (empty($command->icalentry)) {
			if (Task::tasksNotFinished($node)) {
				$this->sendErrorResponse(sprintf('Command on node (%d, %d) but at least one command is already running there', $node->systemid, $node->nodeid), 409);
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
		if ($schedule->isDue()) $this->runScheduledCommand($schedule);
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