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
 * Implements:
 * Determine next time a scheduled task should run
 * 
 */

namespace SkySQL\SCDS\API\controllers;

use SkySQL\SCDS\API\models\Schedule;

abstract class TaskScheduleCommon extends ImplementAPI {
	
	public function __construct ($controller) {
		parent::__construct($controller);
	}

	protected function setRunAt ($schedule) {
		$pathtoapi = _API_BASE_FILE;
		$php = $this->config['shell']['php'];
		if (!is_executable($php)) $this->sendErrorResponse ("Configuration file api.ini says PHP is '$php' but this is not executable", 500);
		$command = sprintf('%s %s \"POST\" \"schedule/%d\"', $php, $pathtoapi, $schedule->scheduleid);
		$atcommand = sprintf('echo "%s" | at -t %s 2>&1', $command, date('YmdHi.s', strtotime($schedule->nextstart)));
		$lastline = shell_exec($atcommand);
		preg_match('/.*job ([0-9]+) at.*/', @$lastline, $matches);
		if (@$matches[1]) $schedule->updateJobNumber($matches[1]);
	}
	
	public function runScheduledCommand ($parm) {
		if ($parm instanceof Schedule) $schedule = $parm;
		else {
			$schedule = new Schedule((int) $parm[1]);
			$schedule->loadData();
		}
		exec ("atrm $schedule->atjobnumber");
		$schedule->processCalendarEntry();
		$this->setRunAt($schedule);
		$task = $schedule->makeTask();
		$task->insert(false);
		$this->execute($task);
		exit;
	}
	
	protected function execute ($task) {
		$scriptdir = rtrim(@$this->config['shell']['path'],'/\\');
		$logfile = (isset($this->config['logging']['directory']) AND is_writeable($this->config['logging']['directory'])) ? $this->config['logging']['directory'].'/api.log' : '/dev/null';
		$params = @$task->parameters;
		$hostname = @$this->config['shell']['hostname'];
		$cmd = "$scriptdir/LaunchCommand.sh $scriptdir/RunCommand.sh $task->taskid \"{$task->steps}\" \"$hostname\" \"$params\" \"$task->privateip\" \"$logfile\"";
       	$pid = exec($cmd);
		$this->log(LOG_INFO, "Started command $task->command with task ID $task->taskid on node $task->nodeid with PID $pid");
		$task->updatePIDandState($pid);
	}
}