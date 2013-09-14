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
 * The Component PropertyManager class looks after cached component properties,
 * where a component is something that is installed on a node.
 * 
 */

namespace SkySQL\SCDS\API\managers;

class ComponentPropertyManager extends PropertyManager {
	
	protected $name = 'component';
	
	protected $updateSQL = 'UPDATE ComponentProperties SET Value = :value WHERE ComponentID = :key AND Property = :property';
	protected $insertSQL = 'INSERT INTO ComponentProperties (ComponentID, Property, Value) VALUES (:key, :property, :value)';
	protected $deleteSQL = 'DELETE FROM ComponentProperties WHERE ComponentID = :key AND Property = :property';
	protected $deleteAllSQL = 'DELETE FROM ComponentProperties WHERE ComponentID = :key';
	protected $selectSQL = 'SELECT Value FROM ComponentProperties WHERE ComponentID = :key AND Property = :property';
	protected $selectAllSQL = 'SELECT ComponentID AS key, Property AS property, Value AS value FROM ComponentProperties';
	
	protected static $instance = null;
	
	public static function getInstance () {
		return self::$instance instanceof self ? self::$instance : self::$instance = parent::getCachedSingleton(__CLASS__);
	}
	
	private function makeKey ($systemid, $nodeid, $name) {
		return "$systemid|$nodeid|$name";
	}
	
	public function setComponentProperty ($systemid, $nodeid, $name, $property, $value) {
		$key = $this->makeKey($systemid, $nodeid, $name);
		parent::setProperty($key, $property, $value);
	}
	
	public function deleteComponentProperty ($systemid, $nodeid, $name, $property) {
		$key = $this->makeKey($systemid, $nodeid, $name);
		parent::deleteProperty($key, $property);
	}
	
	public function getComponentProperty ($systemid, $nodeid, $name, $property) {
		$key = $this->makeKey($systemid, $nodeid, $name);
		return parent::getProperty($key, $property);
	}
	
	public function getAllComponentProperties ($systemid, $nodeid, $name) {
		$key = $this->makeKey($systemid, $nodeid, $name);
		return parent::getAllProperties($key);
	}
	
	public function deleteAllComponentProperties ($systemid, $nodeid, $name) {
		$key = $this->makeKey($systemid, $nodeid, $name);
		parent::deleteAllProperties($key);
	}
}
