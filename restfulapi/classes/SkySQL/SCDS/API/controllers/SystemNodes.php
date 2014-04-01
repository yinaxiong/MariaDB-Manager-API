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
 * Implements:
 * Obtain node information with GET request to /system/{id}/node/{id}
 * Obtain a monitor with GET request to /system/{id}/node/{id}/monitor/{id}
 */

namespace SkySQL\SCDS\API\controllers;

use SkySQL\SCDS\API\API;
use SkySQL\SCDS\API\models\Node;
use SkySQL\SCDS\API\models\Task;
use SkySQL\SCDS\API\models\System;
use SkySQL\SCDS\API\managers\ComponentPropertyManager;
use SkySQL\SCDS\API\caches\CachedProvisionedNodes;

class SystemNodes extends SystemNodeCommon {
	protected $defaultResponse = 'node';
	protected $nodeid = 0;
	protected $monitorid = 0;
	protected $systemtype = '';
	
	public function __construct ($controller) {
		parent::__construct($controller);
	}
	
	public function nodeStates ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'state', true, 'fields');
		if (empty($uriparts[1])) {
			$this->sendResponse(array('nodestates' => $this->filterResults(Node::getAllStates())));
		}
		else {
			$this->sendResponse(array('nodestates' => $this->filterResults(Node::getAllStatesForType($uriparts[1]))));
		}
	}
	
	public function getSystemAllNodes ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, '', true, 'state, fields');
		$this->systemid = $uriparts[1];
		$poe = $this->requestor->getHeader('Poe');
		// Method sendPOE does not return
		if ($poe) Node::sendPOE($this->systemid);
		if (!$this->validateSystem()) $this->sendErrorResponse("No system with ID $this->systemid", 404);
		$nodes = Node::getAllForSystem($this->systemid, $this->getParam('GET', 'state'));
		foreach ($nodes as $node) $this->extraNodeData($node);
		$this->sendResponse(array('nodes' => $this->filterResults($nodes)));
	}
	
	public function getProvisionedNodes ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'provisionednodes', true);
		$pnodescache = CachedProvisionedNodes::getInstance();
		$nodes = $pnodescache->getIfChangedSince($this->ifmodifiedsince);
		if (count($nodes) OR !$this->ifmodifiedsince) $this->sendResponse(array('provisionednodes' => $nodes));
		header (HTTP_PROTOCOL.' 304 Not Modified');
		exit;
	}
	
	public function getSystemNode ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, '', false, 'fields');
		$this->systemid = (int) $uriparts[1];
		$this->nodeid = (int) $uriparts[3];
		$node = Node::getByID($this->systemid, $this->nodeid);
		if ($node) {
			if ($this->ifmodifiedsince < strtotime($node->updated)) $this->modified = true;
			$this->extraNodeData($node);
			if ($this->ifmodifiedsince AND !$this->modified) {
				header (HTTP_PROTOCOL.' 304 Not Modified');
				exit;
			}
			$this->sendResponse(array('node' => $this->filterSingleResult($node)));
		}
		else $this->sendErrorResponse("No matching node for system ID $this->systemid and node ID $this->nodeid", 404);
	}
	
	public function getNodeField ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, '', false, 'specified field');
		$this->systemid = (int) $uriparts[1];
		$this->nodeid = (int) $uriparts[3];
		$name = $uriparts[5];
		$node = Node::getByID($this->systemid, $this->nodeid);
		if ($node AND isset($node->$name)) $this->sendResponse($node->$name);
		$this->sendErrorResponse(sprintf("No field '%s' found for %s", $name, Node::getDescription($this->systemid, $this->nodeid)), 404);
	}
	
	public function getSystemNodeProcesses ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'process', true, 'fields');
		$this->systemid = (int) $uriparts[1];
		$this->nodeid = (int) $uriparts[3];
		$this->sendResponse(array('process' => $this->filterResults($this->getNodeProcesses($this->nodeid))));
	}
	
	public function getProcessPlan ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'processplan', true, 'fields');
		$this->systemid = (int) $uriparts[1];
		$this->nodeid = (int) $uriparts[3];
		$processid = (int) $uriparts[5];
		if ($processid) $plans = $this->targetDatabaseQuery("SHOW EXPLAIN FOR $processid", $this->nodeid);
		if (empty($plans)) $this->sendErrorResponse(sprintf("No process plan found for ID '%d'",$processid), 404);
		$this->sendResponse(array('processplan' => $plans[0]));
		exit;
	}
	
	public function killSystemNodeProcess ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'none');
		$this->systemid = (int) $uriparts[1];
		$this->nodeid = (int) $uriparts[3];
		$processid = (int) $uriparts[5];
		if ($processid) $this->targetDatabaseQuery("KILL QUERY $processid", $this->nodeid);
		exit;
	}
	
	protected function extraNodeData (&$node) {
		$node->commands = ($this->isFilterWord('commands') AND $node->state) ? $node->getCommands() : null;
		if ($this->isFilterWord('monitorlatest') OR $this->isFilterWord('lastmonitored')) {
			list ($monitorlatest, $lastmonitored) = $this->getMonitorData($node->nodeid);
			if ($this->isFilterWord('monitorlatest')) $node->monitorlatest = $monitorlatest;
			if ($this->isFilterWord('lastmonitored')) $node->lastmonitored = $lastmonitored;
		}
		$node->task = Task::latestForNode($node);
	}

	public function createSystemNode ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'Insert-Update', false, 'Fields for node resource');
		$this->createSystemNodeCommon($uriparts, $metadata);
		$this->createNode($this->systemid);
	}
	
	protected function createNode ($systemid) {
		$node = new Node($systemid);
		$node->insert();
	}
	
	public function createSystemNodeOnceOnly ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'Insert-Update', false, 'Fields for node resource');
		$this->createSystemNodeCommon($uriparts, $metadata);
		$deluniqid = $this->db->prepare("DELETE FROM POE WHERE uniqid = '/system/' || :systemid || '/node/' || :partthree");
		$deluniqid->execute(array(':partthree' => $uriparts[3]));
		if ($deluniqid->rowCount()) $this->createNode($this->systemid);
		$this->db->rollbackTransaction();
		header(HTTP_PROTOCOL.' 405 Operation Not Supported');
		exit;
	}
	
	protected function createSystemNodeCommon ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'Insert-Update', false, 'Fields for node resource');
		Node::checkLegal();
		$this->db->beginImmediateTransaction();
		$this->systemid = (int) $uriparts[1];
		if (!$this->validateSystem()) $this->sendErrorResponse('Create node request gave non-existent system ID '.$this->systemid, 400);
	}
	
	public function updateSystemNode ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'Insert-Update', false, 'Fields for node resource');
		$this->db->beginImmediateTransaction();
		Node::checkLegal('stateid');
		$this->systemid = (int) $uriparts[1];
		$this->nodeid = (int) @$uriparts[3];
		$node = new Node ($this->systemid, $this->nodeid);
		$node->update();
	}
	
	public function deleteSystemNode ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'Delete-Count');
		$this->systemid = (int) $uriparts[1];
		$this->nodeid = (int) $uriparts[3];
		if ($this->validateSystem()) {
			$node = Node::getByID($this->systemid, $this->nodeid);
			if (!$node) $this->sendErrorResponse(sprintf("Delete node, no node with System ID '%s', Node ID '%s'",$this->systemid, $this->nodeid), 400);
			if (!empty(API::$systemtypes[$this->systemtype]['nodestates'][$node->state]['protected'])) {
				$this->sendErrorResponse(sprintf("Delete node System ID '%s', Node ID '%s' request, but cannot delete node in state '%s'",$this->systemid, $this->nodeid, $node->state), 400);
			}
			ComponentPropertyManager::getInstance()->deleteAllComponents($this->systemid, $this->nodeid);
			// Call a delete script to unset known host
			// The following will not return
			$node->delete();
		}
		else $this->sendErrorResponse('Delete node request gave non-existent system ID '.$this->systemid, 400);
	}
	
	protected function validateSystem () {
		$system = System::getByID($this->systemid);
		if (@$system->systemtype) $this->systemtype = $system->systemtype;
		return $system ? true : false;
	}
	
	protected function validateNode () {
		return Node::getByID($this->systemid, $this->nodeid) ? true : false;
	}
}
