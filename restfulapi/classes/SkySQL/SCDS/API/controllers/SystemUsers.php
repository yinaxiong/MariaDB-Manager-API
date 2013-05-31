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
 * Date: February 2013
 * 
 */

namespace SkySQL\SCDS\API\controllers;

use SkySQL\SCDS\API\managers\UserManager;
use SkySQL\SCDS\API\managers\UserPropertyManager;
use SkySQL\SCDS\API\models\User;

class SystemUsers extends ImplementAPI {
	
	public function getUsers () {
		$users = UserManager::getInstance()->getAllPublic();
		foreach ($users as &$user) $user->properties = UserPropertyManager::getInstance()->getAllProperties($user->username);
        $this->sendResponse(array('users' => $this->filterResults((array) $users)));
	}
	
	public function getUserInfo ($uriparts) {
		$username = @urldecode($uriparts[1]);
		$user = UserManager::getInstance()->getByName($username);
		if ($user) $this->sendResponse(array ('username' => $username, 'name' => $user->name, 
			'properties' => UserPropertyManager::getInstance()->getAllProperties($username)));
		else $this->sendErrorResponse('No user found with username '.$username, 404);
	}
	
	public function putUser ($uriparts) {
		$username = @urldecode($uriparts[1]);
		if (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {
			$this->sendErrorResponse("User name must only contain alphameric and underscore, $username submitted", 400);
		}
		$user = new User($username);
		$user->save();
	}
	
	public function deleteUser ($uriparts) {
		$username = urldecode($uriparts[1]);
		UserManager::getInstance()->deleteUser($username);
	}
	
	public function putUserProperty ($uriparts) {
		$username = urldecode($uriparts[1]);
		$property = urldecode($uriparts[3]);
		$value = $this->getParam('PUT', 'value');
		UserPropertyManager::getInstance()->setProperty($username, $property, $value);
	}
	
	public function deleteUserProperty ($uriparts) {
		$username = urldecode($uriparts[1]);
		$property = urldecode($uriparts[3]);
		UserPropertyManager::getInstance()->deleteProperty($username, $property);
	}
	
	public function loginUser ($uriparts) {
		$username = urldecode($uriparts[1]);
		$password = $this->getParam('POST', 'password');
		$manager = UserManager::getInstance();
		if ($manager->authenticate($username,$password)) {
			$user = $manager->getByName($username);
			$this->sendResponse(array('username' => $user->username, 'name' => $user->name));
		}
		$this->sendErrorResponse('Login failed', 409);
	}
}