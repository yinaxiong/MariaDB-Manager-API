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
 * Date: June 2013
 * 
 * The NodeStateManager class caches all Node States and manipulates them
 * 
 */

namespace SkySQL\SCDS\API\managers;

use SkySQL\SCDS\API\API;
use SkySQL\SCDS\API\models\NodeState;
use stdClass;

class NodeStateManager {
	protected static $instance = null;
	
	public static function getInstance () {
		return self::$instance instanceof self ? self::$instance : self::$instance = new self();
	}
	
	public function getAll () {
		$allstates = new stdClass();
		foreach (array_keys(API::$systemtypes) as $type) $allstates->$type = $this->getAllForType($type);
		return $allstates;
	}
	
	public function getAllForType ($type) {
		return isset(API::$systemtypes[$type]) ? API::mergeStates(API::$systemtypes[$type]['nodestates']) : array();
	}
	
	public function getByState ($systemtype, $state) {
		return isset(API::$systemtypes[$systemtype]['nodestates'][$state]) ? API::$systemtypes[$systemtype]['nodestates'][$state] : null;
	}
	
	public function getByStateID ($systemtype, $stateid) {
		$nodestates = @API::$systemtypes[$systemtype]['nodestates'];
		if (is_array($nodestates)) foreach ($nodestates as $state=>$properties) {
			if ($stateid == $properties['stateid']) return $state;
		}
	}
	
	public function getAllLike ($selector) {
		foreach (API::$nodestates as $state=>$nodestate) {
			if (0 == strncasecmp($selector, $nodestate['description'], strlen($selector))) $results[$state] = $nodestate;
		}
		return API::mergeStates((array) @$results);
	}
}