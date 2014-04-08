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
 * Date: September 2013
 * 
 * The CachedSystems class provides a cached version of the response to a 
 * ../system request
 * 
 */

namespace SkySQL\Manager\API\caches;

use SkySQL\COMMON\CACHE\CachedSingleton;
use SkySQL\Manager\API\models\System;
use SkySQL\Manager\API\models\Node;
use SkySQL\Manager\API\API;
use stdClass;

class CachedProvisionedNodes extends CachedSingleton {
	protected static $instance = null;
	
	protected static $systemfields = array(
		'dbusername',
		'dbpassword',
		'repusername',
		'reppassword'
	);
	
	protected static $nodefields = array(
		'systemid',
		'nodeid',
		'name',
		'hostname',
		'privateip',
		'dbusername',
		'dbpassword',
		'repusername',
		'reppassword'
	);
	
	protected $systems = array();
	protected $nodes = array();
	protected $types = array();
	
	public static function getInstance () {
		return self::$instance instanceof self ? self::$instance : self::$instance = parent::getCachedSingleton(__CLASS__);
	}
	
	protected function __construct () {
		// Systems call must be first
		$this->systems = $this->getRelevantSystemData();
		$this->nodes = $this->getProvisionedNodes();
	}
	
	public function getIfChangedSince ($unixtime) {
		$writecache = false;
		// Systems call must be first
		$psystems = $this->getRelevantSystemData();
		if ($psystems != $this->systems) {
			$this->systems = $psystems;
			$writecache = true;
		}
		$pnodes = $this->getProvisionedNodes();
		if ($pnodes != $this->nodes) {
			$this->nodes = $pnodes;
			$writecache = true;
		}
		if ($writecache) $this->cacheNow();
		return $this->timeStamp() < $unixtime ? array() : $this->nodes;
	}
	
	protected function getProvisionedNodes () {
		$nodes = Node::getAll();
		if ($nodes) foreach ($nodes as $node) {
			if (isset(API::$provisionstates[$node->state])) continue;
			$pnode = new stdClass();
			$system = $this->systems[$node->systemid];
			foreach (self::$nodefields as $field) $pnode->$field = empty($node->$field) ? @$system->$field : $node->$field;
			$pnode->systemtype = @$this->types[$node->systemid];
			$pnodes[] = $pnode;
		}
		return (array) @$pnodes;
	}
	
	protected function getRelevantSystemData () {
		foreach (System::getAll() as $system) {
			$this->types[$system->systemid] = $system->systemtype;
			$psystem = new stdClass();
			foreach (self::$systemfields as $field) $psystem->$field = @$system->$field;
			$psystems[$system->systemid] = $psystem;
		}
		return (array) @$psystems;
	}
}
