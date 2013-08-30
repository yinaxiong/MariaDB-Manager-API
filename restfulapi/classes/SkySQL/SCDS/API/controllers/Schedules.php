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
 * The Schedules class handles schedule related requests.
 * 
 */

namespace SkySQL\SCDS\API\controllers;

use SkySQL\SCDS\API\models\Schedule;

class Schedules extends ImplementAPI {
	
	public function __construct ($controller) {
		parent::__construct($controller);
		Schedule::checkLegal();
	}

	public function getMultipleSchedules () {
		list($total, $schedules) = Schedule::select($this);
		$this->sendResponse(array('total' => $total, 'schedules' => $this->filterResults($schedules)));
	}
	
	public function getOneSchedule ($uriparts) {
		$schedule = Schedule::getByID(array('scheduleid' => (int) $uriparts[1]));
		$this->sendResponse(array('schedule' => $this->filterSingleResult($schedule)));
	}
	
	public function getSelectedSchedules ($uriparts) {
		list($total, $schedules) = Schedule::select($this, trim(urldecode($uriparts[1])));
		$this->sendResponse(array('total' => $total, 'schedules' => $this->filterResults($schedules)));
	}
	
	public function updateSchedule ($uriparts) {
		$schedule = new Schedule((int) $uriparts[1]);
		$schedule->loadData();
		if ($schedule->atjobnumber) exec ("atrm $schedule->atjobnumber");
		$schedule->update(false);
		if ($schedule->icalentry) {
			$this->setRunAt($schedule);
			if ($schedule->isDue()) $this->execute($schedule);
		}
	}
	
	public function runCommand ($uriparts) {
		Command::checkLegal('icalentry');
		$command = urldecode($uriparts[1]);
		$scriptdir = rtrim(@$this->config['shell']['path'],'/\\');
		foreach (array('LaunchCommand','RunCommand') as $script) {
			if (!is_executable($scriptdir."/$script.sh")) {
				$errors[] = "Script $scriptdir/$script.sh does not exist or is not executable";
			}
		}
		if (isset($errors)) $this->sendErrorResponse($errors,500);
		$task = new Task;
		$task->insertOnCommand($command);
		if ($task->icalentry) {
			$this->setRunAt($task);
			if (!$task->isDue()) $this->sendResponse(array('task' => $task));
		}
		$this->execute($task);
		$this->sendResponse(array('task' => $task));
	}
	
	public function runScheduledCommand ($uriparts) {
		$taskid = (int) $uriparts[1];
		$task = new Task($taskid);
		$task->loadData();
		$task->processCalendarEntry();
		$this->setRunAt($task);
		$this->execute($task);
		exit;
	}
	
	protected function setRunAt ($task) {
		$pathtoapi = _API_BASE_FILE;
		$php = $this->config['shell']['php'];
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
		$cmd = "$scriptdir/LaunchCommand.sh $scriptdir/RunCommand.sh $task->taskid \"{$task->steps}\" \"{$this->config['shell']['hostname']}\" \"$params\" \"$task->privateip\" \"$logfile\"";
       	$pid = exec($cmd);
		$this->log("Started command $task->command with task ID $task->taskid on node $task->nodeid with PID $pid\n");
		$task->updatePIDandState($pid);
	}
}