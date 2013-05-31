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
 * The Systems class within the API implements calls to get system data or
 * system properties.
 * 
 */

namespace SkySQL\SCDS\API\controllers;

use SkySQL\SCDS\API\managers\SystemManager;
use SkySQL\SCDS\API\managers\NodeManager;
use SkySQL\SCDS\API\managers\SystemPropertyManager;
use SkySQL\SCDS\API\models\System;

class Systems extends SystemNodeCommon {
	protected $backups_query = null;
	
	protected static $fields = array(
		'name' => array('sqlname' => 'SystemName', 'default' => ''),
		'startDate' => array('sqlname' => 'InitialStart', 'default' => ''),
		'lastAccess' => array('sqlname' => 'LastAccess', 'default' => ''),
		'state' => array('sqlname' => 'State', 'default' => 0)
	);

	public function __construct ($controller) {
		parent::__construct($controller);
		$this->backups_query = $this->db->prepare('SELECT MAX(Started) FROM Backup WHERE SystemID = :systemid');
	}
	
	public function getAllData () {
		foreach (SystemManager::getInstance()->getAll() as $system) {
			$results[] = $this->retrieveOneSystem($system);
		}
        $this->sendResponse(array("system" => $this->filterResults((array) @$results)));
	}

	public function getSystemData ($uriparts) {
		$this->systemid = (int) $uriparts[1];
		$data = SystemManager::getInstance()->getByID($this->systemid);
		if ($data) $this->sendResponse(array('system' => $this->filterResults(array($this->retrieveOneSystem($data)))));
		else $this->sendErrorResponse("No system with ID of $this->systemid was found", 404);
	}
	
	public function putSystem ($uriparts) {
		$this->systemid = (int) $uriparts[1];
		if (!$this->systemid) $this->sendErrorResponse('Creating a system with ID of zero is not permitted', 400);
		$system = new System($this->systemid);
		$system->save();
	}
	
	public function deleteSystem ($uriparts) {
		$this->systemid = (int) $uriparts[1];
		SystemManager::getInstance()->deleteSystem($this->systemid);
	}
	
	public function setSystemProperty ($uriparts) {
		$this->systemid = (int) $uriparts[1];
		$property = $uriparts[3];
		$value = $this->getParam('PUT', 'value');
		SystemPropertyManager::getInstance()->setProperty($this->systemid, $property, $value);
	}
	
	public function deleteSystemProperty ($uriparts) {
		$this->systemid = (int) $uriparts[1];
		$property = urldecode($uriparts[3]);
		SystemPropertyManager::getInstance()->deleteProperty($this->systemid, $property);
	}
	
	public function getSystemProperty ($uriparts) {
		$this->systemid = (int) $uriparts[1];
		$property = urldecode($uriparts[3]);
		return SystemPropertyManager::getInstance()->getProperty($this->systemid, $property);
	}
	
	protected function retrieveOneSystem ($system) {
		$this->systemid = (int) $system->system;
		$system->nodes = NodeManager::getInstance()->getAllIDsForSystem($this->systemid);
		$system->lastBackup = $this->isFilterWord('lastBackup') ? $this->retrieveLastBackup() : null;
		$system->properties = $this->isFilterWord('properties') ? $this->retrieveProperties($this->systemid) : null;
		$system->commands = ($this->isFilterWord('commands') AND $system->state) ? $this->getCommands($system->state) : null;
		$system->connections = $this->isFilterWord('connections') ? $this->getConnections(0) : null;
		$system->packets = $this->isFilterWord('packets') ? $this->getPackets(0) : null;
		$system->health = $this->isFilterWord('health') ? $this->getHealth(0) : null;
		return $system;
	}
	
	protected function retrieveLastBackup () {
		// Can only be exactly one result for the latest backup
		$this->backups_query->execute(array(':systemid' => $this->systemid));
		return $this->backups_query->fetchColumn();
	}
	
	protected function retrieveProperties () {
		return SystemPropertyManager::getInstance()->getAllProperties($this->systemid);
	}
}