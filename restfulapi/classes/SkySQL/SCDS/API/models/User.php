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
 * The User class models a user of the system.
 * 
 */

namespace SkySQL\SCDS\API\models;

use SkySQL\SCDS\API\Request;

class User extends EntityModel {
	protected static $setkeyvalues = true;
	
	protected static $classname = __CLASS__;
	protected static $managerclass = 'SkySQL\\SCDS\\API\\managers\\UserManager';

	protected $ordinaryname = 'user';
	protected static $headername = 'User';
	
	protected static $updateSQL = 'UPDATE User SET %s WHERE UserName = :username';
	protected static $countSQL = 'SELECT COUNT(*) FROM User WHERE UserName = :username';
	protected static $countAllSQL = 'SELECT COUNT(*) FROM User';
	protected static $insertSQL = 'INSERT INTO User (%s) VALUES (%s)';
	protected static $deleteSQL = 'DELETE FROM User WHERE UserName = :username';
	protected static $selectSQL = 'SELECT %s FROM User WHERE UserName = :username';
	protected static $selectAllSQL = 'SELECT %s FROM User %s';
	
	protected static $getAllCTO = array('id');
	
	protected static $keys = array(
		'username' => array('sqlname' => 'UserName')
	);

	protected static $fields = array(
		'name' => array('sqlname' => 'Name', 'default' => ''),
		'password' => array('sqlname' => 'Password', 'default' => '', 'mask' => _MOS_NOTRIM, 'secret' => true)
	);

	protected static $derived = array(
		'properties' => array('type' => 'object', 'desc' => 'An object providing properties and values for the user')
	);

	protected static $savedpass = '';
	protected static $encrypted = '';
	
	public function __construct ($username) {
		$this->username = $username;
	}
	
	public function authenticate ($password) {
		return $this->password === crypt($password, $this->password);
	}
	
	public function publicCopy () {
		$copy = new self($this->username);
		foreach (self::$fields as $name=>$field) {
			if (empty($field['secret'])) $copy->$name = $this->$name;
		}
		return $copy;
	}

	function blowfishSalt ($cost = 13) {
		if (!is_numeric($cost) OR $cost < 4 OR $cost > 31) $cost = 13;
		for ($i = 0; $i < 8; $i += 1) $randpart[] = pack('S', mt_rand(0, 0xffff));
		$randpart[] = substr(microtime(), 2, 6);
		$rand = sha1(implode('', $randpart), true);
		return '$2a$' . sprintf('%02d', $cost) . '$' . strtr(substr(base64_encode($rand), 0, 22), array('+' => '.'));
	}

	protected function validateInsert () {
		$this->fixPasswordAndSalt();
		if (empty($this->bind[':password'])) Request::getInstance()->sendErrorResponse('No password provided for create user',400);
	}
	
	protected function validateUpdate () {
		$this->fixPasswordAndSalt();
		if (!empty($this->bind[':password'])) $this->setter[] = 'Password = :password';
	}
	
	protected function fixPasswordAndSalt () {
		if (isset($this->bind[':password'])) {
			if (self::$savedpass == $this->bind[':password']) $this->bind[':password'] = self::$encrypted;
			else {
				self::$savedpass = $this->bind[':password'];
				self::$encrypted = $this->bind[':password'] = crypt($this->bind[':password'], $this->blowfishSalt());
			}
		}
	}

	protected function insertedKey ($insertid) {
		return $this->username;
	}
}
