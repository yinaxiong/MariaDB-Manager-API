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
 * The Commands class within the API implements the fetching of commands, command 
 * steps or command states.
 */

namespace SkySQL\Manager\API\controllers;

use SkySQL\Manager\API\API;
use SkySQL\Manager\API\models\Command;
use SkySQL\Manager\API\managers\NodeCommandManager;

class Commands extends ImplementAPI {

	public function __construct ($requestor) {
		parent::__construct($requestor);
		Command::checkLegal();
	}

	public function getCommands ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'nodecommand (only command, state, description, steps fields)', true);
		$results = $this->filterResults(NodeCommandManager::getInstance()->getAll());	// $commands->fetchAll(PDO::FETCH_ASSOC)
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
		foreach (NodeCommandManager::getInstance()->getAll() as $command) {
			$steps = explode(',', $command->steps);
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
