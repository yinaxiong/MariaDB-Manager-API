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
 * The UserPropertyManager class looks after cached user properties
 * 
 */

namespace SkySQL\Manager\API\managers;

class UserPropertyManager extends PropertyManager {
	
	protected $name = 'user';
	
	protected $updateSQL = "UPDATE UserProperties SET Value = :value, Updated = datetime('now') WHERE UserName = :key AND Property = :property";
	protected $insertSQL = "INSERT INTO UserProperties (UserName, Property, Value, Updated) VALUES (:key, :property, :value, datetime('now'))";
	protected $deleteSQL = 'DELETE FROM UserProperties WHERE UserName = :key AND Property = :property';
	protected $deleteAllSQL = 'DELETE FROM UserProperties WHERE UserName = :key';
	protected $selectSQL = 'SELECT Value FROM UserProperties WHERE UserName = :key AND Property = :property';
	protected $selectAllSQL = 'SELECT UserName AS key, Property AS property, Value AS value, Updated AS updated FROM UserProperties';
	
	protected static $instance = null;
	
	public static function getInstance () {
		return self::$instance instanceof self ? self::$instance : self::$instance = parent::getCachedSingleton(__CLASS__);
	}
}
