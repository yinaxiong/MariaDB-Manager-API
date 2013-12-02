<?php

/*
 ** Part of the SkySQL Manager API.
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
 * Copyright 2013 (c) SkySQL Ab
 * 
 * Author: Martin Brampton
 * Date: February 2013
 * 
 * The Commands class within the API implements the fetching of commands, command 
 * steps or command states.
 */

namespace SkySQL\SCDS\API\controllers;

use PDO;
use SkySQL\SCDS\API\API;
use SkySQL\SCDS\API\models\Command;

class Commands extends ImplementAPI {

	public function __construct ($requestor) {
		parent::__construct($requestor);
		Command::checkLegal();
	}

	public function getCommands ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'command (only command, state, description, steps fields)', true);
		$commands = $this->db->query("SELECT SystemType AS systemtype, Command AS command, State AS state, Description AS description, Steps AS steps FROM NodeCommands 
			WHERE UIOrder IS NOT NULL AND NOT (SystemType = 'galera' AND State = 'provisioned' AND Command = 'restore') ORDER BY UIOrder");
		$results = $this->filterResults($commands->fetchAll(PDO::FETCH_ASSOC));
		foreach ($results as &$command) $command['steps'] = API::trimCommaSeparatedList($command['steps']);
		if (count($results)) $this->sendResponse(array('node_commands' => $results));
		else $this->sendErrorResponse('', 404);
	}
	
	public function getStates ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'command states (state, description, finished fields)', true);
        $this->sendResponse(array("commandStates" => $this->filterResults(API::mergeStates(API::$commandstates))));
	}
	
	public function getSteps ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'command steps (step, description fields)', true);
		$knownsteps = array_keys(API::$commandsteps);
		$select = $this->db->query("SELECT Command AS command, Steps AS steps FROM NodeCommands");
		$commands = $select->fetchAll();
		foreach ($commands as $command) {
			$steps = array_map('trim', explode(',', $command->steps));
			foreach (array_diff($steps, $knownsteps) as $unknown) {
				$errorsteps[$unknown][] = $command->command;
			}
		}
		foreach ((array) @$errorsteps as $step=>$commands) {
			$this->requestor->warnings[] = sprintf("Step '%s' is invalid; used by command(s): ", $step).implode(',',$commands);
		}
		$this->sendResponse(array('command_steps' => $this->filterResults(API::mergeStates(API::$commandsteps, 'step'))));
	}
}
