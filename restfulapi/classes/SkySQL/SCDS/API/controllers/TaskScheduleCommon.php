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

use SkySQL\SCDS\API\Request;
use SkySQL\SCDS\API\models\Schedule;
use SkySQL\SCDS\API\models\Task;
use SkySQL\SCDS\API\managers\NodeManager;

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
		$node = NodeManager::getInstance()->getByID($task->systemid, $task->nodeid);
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
		$logfile = (isset($this->config['logging']['directory']) AND is_writeable($this->config['logging']['directory'])) ? $this->config['logging']['directory'].'/api.log' : '/dev/null';
		$params = $this->decryptParameters(@$task->parameters);
		$hostname = @$this->config['shell']['hostname'];
		$cmd = $this->makeShellCall("$scriptdir/LaunchCommand.sh", "$scriptdir/RunCommand.sh", $task->taskid, $task->steps, $hostname, $params, $task->privateip, $logfile);
		$pid = exec($cmd);
		$this->log(LOG_INFO, "Started command $task->command with task ID $task->taskid on node $task->nodeid with PID $pid");
		$task->updatePIDandState($pid);
	}
	
	protected function makeShellCall () {
		return implode(' ', array_map('escapeshellarg', func_get_args()));
	}
	
	protected function decryptParameters ($parameters) {
		if ($parameters) {
			Request::getInstance()->parse_str($parameters, $parray);
			if (count($parray)) {
				foreach (array('rootpassword', 'sshkey') as $field) if (isset($parray[$field])) {
					$parray[$field] = $this->decryptOneField($parray[$field]);
				}
				foreach ($parray as $field=>$value) $newparray[] = "$field=$value";
				return implode('&', $newparray);
			}
			else return $parameters;
		}
		else return '';
	}
	
	protected function decryptOneField ($string) {
	    $key = pack('H*', Request::getInstance()->getAPIKey());
    
	    $ciphertext_dec = base64_decode($string);
    
	    # retrieves the IV, iv_size should be created using mcrypt_get_iv_size()
	    $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
	    $iv_dec = substr($ciphertext_dec, 0, $iv_size);
    
	    # retrieves the cipher text (everything except the $iv_size in the front)
	    $ciphertext = substr($ciphertext_dec, $iv_size);

	    # may remove 00h valued characters from end of plain text
	    return mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $ciphertext, MCRYPT_MODE_CBC, $iv_dec);
	}
}
