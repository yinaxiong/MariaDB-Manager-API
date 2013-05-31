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
 * The Application PropertyManager class looks after cached application properties
 * 
 */

namespace SkySQL\SCDS\API\managers;

use SkySQL\COMMON\AdminDatabase;

class ApplicationPropertyManager extends PropertyManager {
	protected $name = 'application';
	protected $updateSQL = 'UPDATE ApplicationProperties SET Value = :value WHERE ApplicationID = :id AND Property = :property';
	protected $insertSQL = 'INSERT INTO ApplicationProperties (ApplicationID, Property, Value) VALUES (:id, :property, :value)';
	protected $deleteSQL = 'DELETE FROM ApplicationProperties WHERE ApplicationID = :id AND Property = :property';
	protected $selectSQL = 'SELECT Value FROM ApplicationProperties WHERE ApplicationID = :id AND Property = :property';
	protected $selectAllSQL = 'SELECT Property, Value FROM ApplicationProperties WHERE ApplicationID = $this->id';
	
	protected static $instance = null;
	protected $properties = array();
	
	protected function __construct () {
		$selectall = AdminDatabase::getInstance()->prepare('SELECT ApplicationID AS id, Property AS property, Value AS value FROM ApplicationProperties');
		$selectall->execute();
		foreach ($selectall->fetchALL() as $property) {
			$this->properties[$property->id][$property->property] = $property->value;
		}
	}
	
	public static function getInstance () {
		return self::$instance instanceof self ? self::$instance : self::$instance = new self();
	}
}
