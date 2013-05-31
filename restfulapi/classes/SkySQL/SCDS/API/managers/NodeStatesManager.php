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
 * The NodeStatesManager class caches all Node States and manipulates them
 * 
 */

namespace SkySQL\SCDS\API\managers;

use SkySQL\SCDS\API\models\NodeState;

class NodeStatesManager extends EntityManager {
	protected static $instance = null;
	protected $nodestates = array();
	
	protected function __construct () {
		foreach (NodeState::getAll() as $nodestate) {
			$this->nodestates[$nodestate->state] = $nodestate;
		}
	}
	
	public static function getInstance () {
		return self::$instance instanceof self ? self::$instance : self::$instance = new self();
	}
	
	public function getAll () {
		return array_values($this->nodestates);
	}
	
	public function getByState ($state) {
		return isset($this->nodestates[$state]) ? $this->nodestates[$state] : null;
	}
	
	public function getAllLike ($selector) {
		foreach ($this->nodestates as $nodestate) {
			if (0 == strncasecmp($selector, $nodestate->description, strlen($selector))) $results[] = $nodestate;
		}
		return isset($results) ? $results : array();
	}
	
}