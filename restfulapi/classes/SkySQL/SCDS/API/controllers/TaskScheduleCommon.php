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
 * Implements:
 * Determine next time a scheduled task should run
 * 
 */

namespace SkySQL\SCDS\API\controllers;

use SkySQL\SCDS\API\API;
use SkySQL\SCDS\API\models\Schedule;
use SkySQL\SCDS\API\models\Task;
use SkySQL\SCDS\API\models\Node;

abstract class TaskScheduleCommon extends ImplementAPI {
	
	public function __construct ($controller) {
		parent::__construct($controller);
	}

	protected function setRunAt ($schedule) {
		$pathtoapi = _API_BASE_FILE;
		if (empty($this->config['shell']['php']) OR !is_executable($this->config['shell']['php'])) $this->sendErrorResponse (sprintf("Configuration file api.ini says PHP is '%s' but this is not executable", $this->config['shell']['php']), 500);
		$command = sprintf('%s %s \"POST\" \"schedule/%d\"', $this->config['shell']['php'], $pathtoapi, $schedule->scheduleid);
		$elapsed = (int) round((30 + strtotime($schedule->nextstart) - time())/60);
		$atcommand = sprintf('echo "%s" | at %s 2>&1', $command, "now +$elapsed minute");
		$lastline = shell_exec("export SHELL=/bin/sh; " . $atcommand);
		preg_match('/.*job ([0-9]+) at.*/', @$lastline, $matches);
		if (@$matches[1]) $schedule->updateJobNumber($matches[1]);
	}
	
	public function runScheduledCommand ($uriparts) {
		$schedule = Schedule::getByID((int) $uriparts[1]);
		if (!($schedule instanceof Schedule)) {
			$this->log(LOG_CRIT, sprintf("Call to runScheduleCommand gave scheduleid as '%d' but no such schedule", (int) $uriparts[1]));
		}
		else {
			exec ("atrm $schedule->atjobnumber");
			$schedule->processCalendarEntry();
			$this->setRunAt($schedule);
			$this->runScheduleNow($schedule);
		}
		exit;
	}
	
	protected function runScheduleNow ($schedule) {
		$task = $schedule->makeTask();
		$node = Node::getByID($task->systemid, $task->nodeid);
		if (!$node) {
			$task->state = 'error';
			$task->errormessage = 'Unable to run schedule command because the relevant node does not exist';
			$task->insert(false);
		}
		elseif (Task::tasksNotFinished($task->command, $node)) {
			$task->state = 'error';
			$task->errormessage = 'Scheduled command could not run because other commands are running';
			$task->insert(false);
		}
		else {
			$task->insert(false);
			$this->execute($task);
		}
	}
	
	protected function execute ($task) {
		$scriptdir = rtrim(@$this->config['shell']['path'],'/\\');
		$shellparams[] = "$scriptdir/LaunchCommand.sh";
		$shellparams[] = "$scriptdir/RunCommand.sh";
		$shellparams[] = $task->taskid;
		$shellparams[] = $task->steps;
		$shellparams[] = $task->privateip;
		$parmobject = $task->getParameterObject();
		foreach ($parmobject as $name=>$value) $shellparams[] = "$name=$value";
		foreach ($task->getEncryptedParameters() as $name=>$value) $shellparams[] = "$name=$value";
		$cmd = call_user_func_array(array($this, 'makeShellCall'), $shellparams);
		$pid = exec($cmd);
		$this->log(LOG_INFO, "Started command $task->command with task ID $task->taskid on node $task->nodeid with PID $pid");
		$task->updatePIDandState($pid);
	}
	
	protected function cancel ($task) {
		$scriptdir = rtrim(@$this->config['shell']['path'],'/\\');
		$cmd = $this->makeShellCall("$scriptdir/CancelCommand.sh", $task->pid);
		exec($cmd);
		$this->log(LOG_INFO, "Cancelled command $task->command with task ID $task->taskid on node $task->nodeid with PID $task->pid");
	}
	
	protected function makeShellCall () {
		return implode(' ', array_map('escapeshellarg', func_get_args()));
	}
}
