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
 * The User class models a user of the system.
 * 
 */

namespace SkySQL\SCDS\API\models;

use SkySQL\SCDS\API\Request;

class User extends EntityModel {
	protected static $setkeyvalues = true;
	
	protected static $classname = __CLASS__;

	protected $ordinaryname = 'user';
	
	protected static $updateSQL = 'UPDATE Users SET %s WHERE UserName = :username';
	protected static $countSQL = 'SELECT COUNT(*) FROM Users WHERE UserName = :username';
	protected static $insertSQL = 'INSERT INTO Users (%s) VALUES (%s)';
	protected static $deleteSQL = 'DELETE FROM Users WHERE UserName = :username';
	protected static $selectSQL = 'SELECT %s FROM Users WHERE UserName = :username';
	protected static $selectAllSQL = 'SELECT %s FROM Users %s';
	
	protected static $getAllCTO = array('id');
	
	protected static $keys = array(
		'username' => 'UserName'
	);

	protected static $fields = array(
		'name' => array('sqlname' => 'Name', 'default' => ''),
		'password' => array('sqlname' => 'Password', 'default' => '', 'secret' => true),
		'salt' => array('sqlname' => 'Salt', 'default' => '', 'internal' => true)
	);
	
	public function __construct ($username) {
		$this->username = $username;
	}
	
	public function authenticate ($password) {
		return $this->password == sha1($this->salt.$password);
	}
	
	public function publicCopy () {
		$copy = new self($this->username);
		foreach (self::$fields as $name=>$field) {
			if (empty($field['secret']) AND empty($field['internal'])) $copy->$name = $this->$name;
		}
		return $copy;
	}

	protected function makeSalt () {
		return $this->makeRandomString(24);
	}

	protected function validateInsert (&$bind, &$insname, &$insvalue) {
		$this->fixPasswordAndSalt($bind);
		if (empty($bind[':password'])) Request::getInstance()->sendErrorResponse('No password provided for create user',400);
	}
	
	protected function validateUpdate (&$bind, &$setters) {
		$this->fixPasswordAndSalt($bind);
		if (!empty($bind[':password'])) {
			$setters[] = 'Password = :password';
			$setters[] = 'Salt = :salt';
		}
	}
	
	protected function fixPasswordAndSalt (&$bind) {
		if (isset($bind[':password'])) {
			$bind[':salt'] = $this->makeSalt();
			$bind[':password'] = sha1($bind[':salt'].$bind[':password']);
		}
		else unset($bind[':salt']);
	}
}
