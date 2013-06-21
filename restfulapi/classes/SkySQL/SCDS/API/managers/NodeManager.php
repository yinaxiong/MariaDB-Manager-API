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
 * Date: May 2013
 * 
 * The NodeManager class caches all Nodes and manipulates them
 * 
 */

namespace SkySQL\SCDS\API\managers;

use SkySQL\SCDS\API\models\Node;

class NodeManager extends EntityManager {
	protected static $instance = null;
	protected $nodes = array();
	
	protected function __construct () {
		foreach (Node::getAll() as $node) {
			$this->nodes[$node->systemid][$node->nodeid] = $node;
		}
	}
	
	public static function getInstance () {
		return self::$instance instanceof self ? self::$instance : self::$instance = parent::getCachedSingleton(__CLASS__);
	}
	
	public function getByID ($system, $id) {
		return isset($this->nodes[$system][$id]) ? $this->nodes[$system][$id] : null;
	}
	
	public function getAll () {
		return array_values($this->nodes);
	}
	
	public function getAllForSystem ($system, $state=0) {
		if (isset($this->nodes[$system])) {
			if ($state) {
				foreach ($this->nodes[$system] as $node) if ($state == $node->state) $results[] = $node;
				if (isset($results)) return $results;
			}
			else return array_values($this->nodes[$system]);
		}
		return array();
	}
	
	public function getAllIDsForSystem ($system) {
		$nodes = isset($this->nodes[$system]) ? array_values($this->nodes[$system]) : array();
		return array_map(array($this, 'extractID'),$nodes);		
	}
	
	protected function extractID ($node) {
		return $node->nodeid;
	}
	
	public function createNode () {
		$node = new Node(null);
		$node->insert();
	}
	
	public function updateNode ($system, $id) {
		$node = new Node($system,$id);
		$node->update();
	}
	
	public function saveNode ($system, $id) {
		$node = new Node($system,$id);
		$node->save();
	}
	
	public function deleteNode ($system, $id) {
		$node = new Node($system,$id);
		if (isset($this->nodes[$system][$id])) unset($this->nodes[$system][$id]);
		$node->delete();
	}
}