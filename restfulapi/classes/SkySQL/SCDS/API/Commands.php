<?php

/*
 * Part of the SCDS API.
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
 * The Commands class within the API implements the fetching of commands, command 
 * steps or command states.
 */

namespace SkySQL\SCDS\API;

use SkySQL\COMMON\AdminDatabase;
use \PDO as PDO;

class Commands extends ImplementAPI {

	public function getCommands () {
		if (isset($_GET['group'])) {
			$statement = $this->db->prepare("SELECT CommandID AS id, Name AS name, Description AS description, Icon AS icon FROM Commands WHERE UIOrder IS NOT NULL AND UIGroup = :group ORDER BY UIOrder");
			$commands = $statement->execute(array(':group' => $_GET['group']));
		}
		else $commands = $this->db->query("SELECT CommandID AS id, Name AS name, Description AS description, Icon AS icon FROM Commands WHERE UIOrder IS NOT NULL ORDER BY UIOrder");
		$results = $commands->fetchAll(PDO::FETCH_ASSOC);
		if (count($results)) $this->sendResponse(array('commands' => $results));
		else $this->sendErrorResponse('', 404);
	}
	
	public function getStates () {
		$states = $this->db->query("SELECT State AS id, Description AS description, Icon AS icon FROM CommandStates");
        $this->sendResponse(array("commandStates" => $states->fetchAll(PDO::FETCH_ASSOC)));
	}
	
	public function getSteps () {
		$data = array();
		$commandquery = $this->db->query('SELECT CommandID FROM Commands');
		$stepstatement = $this->db->prepare('SELECT StepID FROM CommandStep WHERE CommandID = :commandid ORDER BY StepOrder');
		foreach ($commandquery->fetchAll(PDO::FETCH_COLUMN) as $commandid) {
			$stepstatement->execute(array(':commandid' => (int) $commandid));
			$steps = $stepstatement->fetchAll(PDO::FETCH_COLUMN);
			$data[] = array("command" => $commandid, "steps" => $steps);
		}
		$this->sendResponse(array("command_steps" => $data));
	}
}