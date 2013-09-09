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
use SkySQL\SCDS\API\models\Node;
use SkySQL\SCDS\API\managers\NodeManager;

class Tasks extends ImplementAPI {
	
	public function getMultipleTasks () {
		Task::checkLegal();
		list($total, $tasks) = Task::select($this);
		$this->sendResponse(array('total' => $total, 'tasks' => $this->filterResults($tasks)));
	}
	
	public function getOneTask ($uriparts) {
		Task::checkLegal();
		$task = Task::getByID(array('taskid' => (int) $uriparts[1]));
		$this->sendResponse(array('task' => $this->filterSingleResult($task)));
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
		
		// Remaining code relates to scheduling
		$task->loadData();
		if ($task->atjobnumber) exec ("atrm $task->atjobnumber");
		$task->update(false);
		if ($task->icalentry) {
			$this->setRunAt($task);
			if ($task->isDue()) $this->execute($task);
		}
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
		$task = new Task;
		// insertOnCommand also fixes dates as RFC
		$task->insertOnCommand($command->command);
		//if ($task->icalentry) {
		//	$this->setRunAt($task);
		//	if (!$task->isDue()) $this->sendResponse(array('task' => $task));
		//}
		$this->execute($task);
		$this->sendResponse(array('task' => $task));
	}
	
	// To do with scheduling - may need to be moved
	public function runScheduledCommand ($uriparts) {
		$taskid = (int) $uriparts[1];
		$task = new Task($taskid);
		$task->loadData();
		$task->processCalendarEntry();
		$this->setRunAt($task);
		$this->execute($task);
		exit;
	}
	
	// Internal to scheduling
	protected function setRunAt ($task) {
		$pathtoapi = _API_BASE_FILE;
		$php = @$this->config['shell']['php'];
		if (!is_executable($php)) $this->sendErrorResponse ("Configuration file api.ini says PHP is '$php' but this is not executable", 500);
		$command = sprintf('%s %s \"POST\" \"task/%d\"', $php, $pathtoapi, $task->taskid);
		$atcommand = sprintf('echo "%s" | at -t %s 2>&1', $command, date('YmdHi.s', strtotime($task->nextstart)));
		$lastline = shell_exec($atcommand);
		preg_match('/.*job ([0-9]+) at.*/', @$lastline, $matches);
		if (@$matches[1]) $task->updateJobNumber($matches[1]);
	}
	
	protected function execute ($task) {
		$scriptdir = rtrim(@$this->config['shell']['path'],'/\\');
		$logfile = (isset($this->config['logging']['directory']) AND is_writeable($this->config['logging']['directory'])) ? $this->config['logging']['directory'].'/api.log' : '/dev/null';
		$params = @$task->parameters;
		$hostname = @$this->config['shell']['hostname'];
		$cmd = "$scriptdir/LaunchCommand.sh $scriptdir/RunCommand.sh $task->taskid \"{$task->steps}\" \"$hostname\" \"$params\" \"$task->privateip\" \"$logfile\"";
       	$pid = exec($cmd);
		$this->log("Started command $task->command with task ID $task->taskid on node $task->nodeid with PID $pid\n");
		$task->updatePIDandState($pid);
	}
}