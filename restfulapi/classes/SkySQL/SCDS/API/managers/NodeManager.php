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
 * Date: May 2013
 * 
 * The NodeManager class caches all Nodes and manipulates them
 * 
 */

namespace SkySQL\SCDS\API\managers;

use SkySQL\SCDS\API\Request;
use SkySQL\SCDS\API\models\Node;

class NodeManager extends EntityManager {
	protected static $instance = null;
	protected $nodes = array();
	protected $nodeips = array();
	
	protected function __construct () {
		foreach (Node::getAll(false) as $node) {
			$this->maincache[$node->systemid][$node->nodeid] = $node;
			$this->simplecache[] = $node;
			$this->nodeips[$node->privateip][$node->port ? $node->port : _API_DEFAULT_SQL_PORT][] = $node->nodeid;
		}
		foreach ($this->nodeips as $privateip=>$ipnodes) {
			foreach ($ipnodes as $port=>$nodeids) {
				if (1 < count($nodeids)) {
			if ($privateip) {
				$idlist = implode(',', $nodeids);
						Request::getInstance()->warnings[] = sprintf("Nodes with IDs '%s' have the same private IP address, '%s' and port, '%s'", $idlist, $privateip, $port);
					}
					
				}
			}
		}
	}
	
	public static function getInstance () {
		return self::$instance instanceof self ? self::$instance : self::$instance = parent::getCachedSingleton(__CLASS__);
	}
	
	public function getAll () {
		$merged = array();
		foreach ($this->maincache as $systemnodes) $merged = array_merge($merged, $systemnodes);
		return array_values($merged);
	}
	
	public function getAllForSystem ($system, $state='') {
		if (isset($this->maincache[$system])) {
			if ($state) {
				foreach ($this->maincache[$system] as $node) if ($state == $node->state) $results[] = $node;
				if (isset($results)) return $results;
			}
			else return array_values($this->maincache[$system]);
		}
		return array();
	}
	
	public function getAllIDsForSystem ($system) {
		$nodes = isset($this->maincache[$system]) ? array_values($this->maincache[$system]) : array();
		return array_map(array($this, 'extractID'),$nodes);		
	}
	
	protected function extractID ($node) {
		return $node->nodeid;
	}
	
	public function usedIP ($ip, $port) {
		return (array) @$this->nodeips[$ip][$port];
	}
}