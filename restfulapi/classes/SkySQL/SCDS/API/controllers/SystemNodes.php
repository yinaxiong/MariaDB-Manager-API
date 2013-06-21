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

use PDO;
use SkySQL\SCDS\API\managers\NodeManager;
use SkySQL\SCDS\API\managers\SystemManager;
use SkySQL\SCDS\API\managers\NodeStateManager;
use SkySQL\SCDS\API\models\Node;

class SystemNodes extends SystemNodeCommon {
	protected $nodeid = 0;
	protected $monitorid = 0;
	
	public function __construct ($controller) {
		parent::__construct($controller);
	}
	
	public function nodeStates ($uriparts) {
		$manager = NodeStateManager::getInstance();
		if (empty($uriparts[1])) {
			$this->sendResponse(array('nodestates' => $this->filterResults($manager->getAll())));
		}
		else {
			$state = urldecode($uriparts[1]);
			$nodestate = $manager->getByState($state);
			if ($nodestate) {
				$nodestate = array_merge(array('state' => $state), $nodestate);
				$this->sendResponse(array('nodestate' => $this->filterSingleResult($nodestate)));
			}
			else $this->sendErrorResponse("No node state $state", 404);
		}
	}
	
	public function getSystemAllNodes ($uriparts) {
		$this->systemid = $uriparts[1];
		if (!$this->validateSystem()) $this->sendErrorResponse("No system with ID $this->systemid", 404);
		$nodes = NodeManager::getInstance()->getAllForSystem($this->systemid, $this->getParam('GET', 'state'));
		foreach ($nodes as $node) $this->extraNodeData($node);
		$this->sendResponse(array('nodes' => $this->filterResults($nodes)));
	}
	
	public function getSystemNode ($uriparts) {
		$this->systemid = $uriparts[1];
		$this->nodeid = $uriparts[3];
		$node = NodeManager::getInstance()->getByID($this->systemid, $this->nodeid);
		if ($node) {
			$this->extraNodeData($node);
			$this->sendResponse(array('node' => $this->filterSingleResult($node)));
		}
		else $this->sendErrorResponse("No matching node for system ID $this->systemid and node ID $this->nodeid", 404);
	}
	
	protected function extraNodeData (&$node) {
		$node->commands = ($this->isFilterWord('commands') AND $node->state) ? $this->getCommands($node->state) : null;
		$node->monitorlatest = $this->getMonitorData($node->nodeid);
		list($node->taskid, $node->command) = $this->getCommand($node->nodeid);
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
		$query = $this->db->prepare('SELECT TaskID, Command FROM Task 
			WHERE SystemID = :systemid AND NodeID = :nodeid AND State = 2');
		$query->execute(array(':systemid' => $this->systemid, ':nodeid' => $nodeid));
		$row = $query->fetch();
		return $row ? array($row->TaskID, $row->Command) : array(null, null);
	}
	
	protected function getCommands ($state) {
		$query = $this->db->prepare('SELECT Command FROM NodeCommands WHERE State = :state');
		$query->execute(array(':state' => $state));
		return $query->fetchAll(PDO::FETCH_COLUMN);
	}
}