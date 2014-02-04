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
 * The Systems class within the API implements calls to handle system data
 * 
 */

namespace SkySQL\SCDS\API\controllers;

use stdClass;
use SkySQL\SCDS\API\API;
use SkySQL\SCDS\API\managers\SystemPropertyManager;
use SkySQL\SCDS\API\models\System;
use SkySQL\SCDS\API\models\Node;

class Systems extends SystemNodeCommon {
	protected $defaultResponse = 'system';
	protected $backups_query = null;
	
	public function __construct ($controller) {
		parent::__construct($controller);
		$this->backups_query = $this->db->prepare("SELECT strftime('%s',MAX(Started)) FROM Backup WHERE SystemID = :systemid");
		System::checkLegal();
	}
	
	public function getAllData ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, '', true, 'fields');
		$poe = $this->requestor->getHeader('Poe');
		// Method sendPOE does not return
		if ($poe) System::sendPOE();
		foreach (System::getAll() as $system) {
			$results[] = $this->retrieveOneSystem($system);
		}
        $this->sendResponse(array("systems" => $this->filterResults((array) @$results)));
	}
	
	public function getSystemTypes ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'systemtype', true);
		$result = new stdClass();
		foreach (API::$systemtypes as $type=>$about) {
			$result->$type = $about['description'];
		}
		$this->sendResponse(array('systemtypes' => $result));
	}

	public function getSystemData ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, '', false, 'fields');
		$this->systemid = (int) $uriparts[1];
		$data = System::getByID($this->systemid);
		if ($data) {
			if ($this->ifmodifiedsince < strtotime($data->updated)) $this->modified = true;
			$system = $this->retrieveOneSystem($data);
			if ($this->ifmodifiedsince AND !$this->modified) {
				header (HTTP_PROTOCOL.' 304 Not Modified');
				exit;
			}
			$this->sendResponse(array('system' => $this->filterSingleResult($system)));
		}
		else $this->sendErrorResponse("No system with ID of $this->systemid was found", 404);
	}
	
	public function createSystem ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'Insert-Update', false, 'Fields for system resource');
		$this->db->beginImmediateTransaction();
		$system = new System();
		$system->insert();
	}
	
	public function createSystemOnceOnly ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'Insert-Update', false, 'Fields for system resource');
		$this->db->beginImmediateTransaction();
		$deluniqid = $this->db->prepare("DELETE FROM POE WHERE uniqid = '/system/' || :partone");
		$deluniqid->execute(array(':partone' => $uriparts[1]));
		if ($deluniqid->rowCount()) {
			$system = new System();
			$system->insert();
		}
		$this->db->rollbackTransaction();
		header(HTTP_PROTOCOL.' 405 Operation Not Supported');
		exit;
	}
	
	public function updateSystem ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'Insert-Update', false, 'Fields for system resource');
		$this->systemid = (int) $uriparts[1];
		$this->db->beginImmediateTransaction();
		if (!System::getByID($this->systemid)) {
			$this->sendErrorResponse(sprintf("Cannot update system with ID '%s' - does not exist", $this->systemid), 400);
		}
		$system = new System($this->systemid);
		$system->update();
	}
	
	public function deleteSystem ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata);
		$this->systemid = (int) $uriparts[1];
		$system = new System($this->systemid);
		$system->delete();
	}
	
	public function getSystemProcesses ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'process', true);
		$this->systemid = (int) $uriparts[1];
		$nodes = Node::getAllIDsForSystem($this->systemid);
		$processes = array();
		foreach ($nodes as $nodeid) $processes = array_merge($processes, $this->getNodeProcesses ($nodeid));
		$this->sendResponse(array('process' => $this->filterResults($processes)));
	}
	
	protected function retrieveOneSystem ($system) {
		$this->systemid = (int) $system->systemid;
		$system->nodes = Node::getAllIDsForSystem($this->systemid);
		$system->lastbackup = $this->isFilterWord('lastbackup') ? $this->retrieveLastBackup() : null;
		$system->properties = $this->isFilterWord('properties') ? SystemPropertyManager::getInstance()->getAllProperties($this->systemid) : null;
		// Not sure if there are any system commands
		// $system->commands = ($this->isFilterWord('commands') AND $system->state) ? $this->getCommands($system->state) : null;
		if ($this->isFilterWord('monitorlatest') OR $this->isFilterWord('lastmonitored')) {
			list ($monitorlatest, $lastmonitored) = $this->getMonitorData();
			if ($this->isFilterWord('monitorlatest')) $system->monitorlatest = $monitorlatest;
			if ($this->isFilterWord('lastmonitored')) $system->lastmonitored = $lastmonitored;
		}
		return $system;
	}
	
	protected function retrieveLastBackup () {
		// Can only be exactly one result for the latest backup
		$this->backups_query->execute(array(':systemid' => $this->systemid));
		$unixtime = $this->backups_query->fetchColumn();
		if ($this->ifmodifiedsince < $unixtime) $this->modified = true;
		return $unixtime ? date('r', $unixtime) : null;
	}
}