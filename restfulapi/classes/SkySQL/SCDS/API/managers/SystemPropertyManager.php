<?php

/*
 ** Part of the SkySQL Manager API.
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
 * Copyright 2013 (c) SkySQL Ab
 * 
 * Author: Martin Brampton
 * Date: May 2013
 * 
 * The System PropertyManager class looks after cached system properties
 * 
 */

namespace SkySQL\SCDS\API\managers;

class SystemPropertyManager extends PropertyManager {
	
	protected $name = 'system';
	
	protected $updateSQL = "UPDATE SystemProperties SET Value = :value, Updated = datetime('now') WHERE SystemID = :key AND Property = :property";
	protected $insertSQL = "INSERT INTO SystemProperties (SystemID, Property, Updated, Value) VALUES (:key, :property, datetime('now'), :value)";
	protected $deleteSQL = 'DELETE FROM SystemProperties WHERE SystemID = :key AND Property = :property';
	protected $deleteAllSQL = 'DELETE FROM SystemProperties WHERE SystemID = :key';
	protected $selectSQL = 'SELECT Value FROM SystemProperties WHERE SystemID = :key AND Property = :property';
	protected $selectAllSQL = 'SELECT SystemID AS key, Property AS property, Updated AS updated, Value AS value FROM SystemProperties';
	
	protected static $instance = null;
	
	public static function getInstance () {
		return self::$instance instanceof self ? self::$instance : self::$instance = parent::getCachedSingleton(__CLASS__);
	}
	
	protected function wasModified($key) {
		SystemManager::getInstance()->markUpdated($key);
	}
}
