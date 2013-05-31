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
 * The UserPropertyManager class looks after cached user properties
 * 
 */

namespace SkySQL\SCDS\API\managers;

use SkySQL\COMMON\AdminDatabase;

class UserPropertyManager extends PropertyManager {
	protected $name = 'user';
	protected $updateSQL = 'UPDATE UserProperties SET Value = :value WHERE UserName = :id AND Property = :property';
	protected $insertSQL = 'INSERT INTO UserProperties (UserName, Property, Value) VALUES (:id, :property, :value)';
	protected $deleteSQL = 'DELETE FROM UserProperties WHERE UserName = :id AND Property = :property';
	protected $deleteAllSQL = 'DELETE FROM UserProperties WHERE UserName = :id';
	protected $selectSQL = 'SELECT Value FROM UserProperties WHERE UserName = :id AND Property = :property';
	protected $selectAllSQL = 'SELECT Property, Value FROM UserProperties WHERE UserName = $this->id';
	
	protected static $instance = null;
	protected $properties = array();
	
	protected function __construct () {
		$selectall = AdminDatabase::getInstance()->prepare('SELECT UserName AS id, Property AS property, Value AS value FROM UserProperties');
		$selectall->execute();
		foreach ($selectall->fetchALL() as $property) {
			$this->properties[$property->id][$property->property] = $property->value;
		}
	}
	
	public static function getInstance () {
		return self::$instance instanceof self ? self::$instance : self::$instance = new self();
	}
}
