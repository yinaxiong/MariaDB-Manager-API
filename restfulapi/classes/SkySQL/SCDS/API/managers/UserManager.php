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
 * The UserManager class caches all Users and manipulates them
 * 
 */

namespace SkySQL\SCDS\API\managers;

use SkySQL\SCDS\API\models\User;

class UserManager extends EntityManager {
	protected static $instance = null;
	protected $users = array();
	
	protected function __construct () {
		foreach (User::getAll() as $user) {
			//$this->users[$user->id] = $user;
			$this->users[$user->username] = $user;
		}
	}
	
	public static function getInstance () {
		return self::$instance instanceof self ? self::$instance : self::$instance = new self();
	}
	
	public function getByName ($name) {
		return isset($this->users[$name]) ? $this->users[$name] : null;
	}
	
	public function authenticate ($username, $password) {
		$user = $this->getByName($username);
		return $user ? $user->authenticate($password) : false;
	}
	
	public function getAllPublic () {
		$users = array_values($this->users);
		foreach ($users as $user) $results[] = $user->publicCopy();
		return (array) @$results;
	}
	
	public function createUser ($username) {
		$this->clearCache();
		$user = new User($username);
		$user->insert();
	}
	
	public function updateUser ($username) {
		$this->clearCache();
		$user = new User($username);
		$user->update();
	}
	
	public function saveUser ($username) {
		$this->clearCache();
		$user = new User($username);
		$user->save();
	}
	
	public function deleteUser ($username) {
		$user = new User($username);
		if (isset($this->users[$username])) unset($this->users[$username]);
		$this->clearCache();
		UserPropertyManager::getInstance()->deleteAllProperties($username);
		$user->delete();
	}
}