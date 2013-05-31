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
 * Obtain node information with GET request to /system/{id}/node/{id}
 * Obtain a monitor with GET request to /system/{id}/node/{id}/monitor/{id}
 */

namespace SkySQL\SCDS\API\controllers;

use SkySQL\SCDS\API\managers\NodeManager;
use SkySQL\SCDS\API\managers\SystemManager;
use SkySQL\SCDS\API\managers\NodeStatesManager;
use SkySQL\SCDS\API\models\Node;

class SystemNodes extends SystemNodeCommon {
	protected $nodeid = 0;
	protected $monitorid = 0;
	
	public function __construct ($controller) {
		parent::__construct($controller);
	}
	
	public function nodeStates ($uriparts) {
		$manager = NodeStatesManager::getInstance();
		if (empty($uriparts[1])) $nodestates = $manager->getAll();
		else {
			if (preg_match('/[0-9]+/', $uriparts[1])) {
				$nodestate = $manager->getByState((int) $uriparts[1]);
				$nodestates = $nodestate ? array($nodestate) : array();
			}
			else $nodestates = $manager->getAllLike(urldecode($uriparts[1]));
		}
		$this->sendResponse(array('nodestates' => $this->filterResults($nodestates)));
	}
	
	public function getSystemAllNodes ($uriparts) {
		$this->systemid = $uriparts[1];
		$nodes = NodeManager::getInstance()->getAllForSystem($this->systemid);
		$this->extraNodeData($nodes);
		$this->sendResponse(array('node' => $this->filterResults($nodes)));
	}
	
	public function getSystemNode ($uriparts) {
		$this->systemid = $uriparts[1];
		$this->nodeid = $uriparts[3];
		$node = NodeManager::getInstance()->getByID($this->systemid, $this->nodeid);
		if ($node) {
			$nodes = array($node);
			$this->extraNodeData($nodes);
			$this->sendResponse(array('node' => $this->filterResults($nodes)));
		}
		else $this->sendErrorResponse('No matching nodes', 404);
	}
	
	protected function extraNodeData (&$nodes) {
		foreach ($nodes as &$node) {
			$node->commands = ($this->isFilterWord('commands') AND $node->state) ? $this->getCommands($node->state) : null;
			$node->connections = $this->isFilterWord('connections') ? $this->getConnections($node->id) : null;
			$node->packets = $this->isFilterWord('packets') ? $this->getPackets($node->id) : null;
			$node->health = $this->isFilterWord('health') ? $this->getHealth($node->id) : null;
			list($node->task, $node->command) = $this->getCommand($node->id);
		}
	}
	
	public function putSystemNode ($uriparts) {
		$this->systemid = (int) $uriparts[1];
		$this->nodeid = (int) @$uriparts[3];
		if ($this->validateSystem()) NodeManager::getInstance()->saveNode($this->systemid, $this->nodeid);
		else $this->sendErrorResponse('Create node request gave non-existent system ID '.$this->systemid, 400);
	}
	
	public function deleteSystemNode ($uriparts) {
		$this->systemid = (int) $uriparts[1];
		$this->nodeid = (int) $uriparts[3];
		if ($this->validateSystem()) NodeManager::getInstance()->deleteNode($this->systemid, $this->nodeid);
		else $this->sendErrorResponse('Delete node request gave non-existent system ID '.$this->systemid, 400);
	}
	
	protected function validateSystem () {
		return SystemManager::getInstance()->getByID($this->systemid) ? true : false;
	}
	
	protected function getCommand ($nodeid) {
		$query = $this->db->prepare('SELECT TaskID, CommandID FROM CommandExecution 
			WHERE SystemID = :systemid AND NodeID = :nodeid AND State = 2');
		$query->execute(array(':systemid' => $this->systemid, ':nodeid' => $nodeid));
		$row = $query->fetch();
		return $row ? array($row->TaskID, $row->CommandID) : array(null, null);
	}
}