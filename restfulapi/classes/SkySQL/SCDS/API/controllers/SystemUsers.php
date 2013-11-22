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
 * Date: February 2013
 * 
 */

namespace SkySQL\SCDS\API\controllers;

use SkySQL\SCDS\API\managers\UserManager;
use SkySQL\SCDS\API\managers\UserPropertyManager;
use SkySQL\SCDS\API\models\User;

class SystemUsers extends ImplementAPI {
	
	public function __construct ($controller) {
		parent::__construct($controller);
		User::checkLegal();
	}

	public function getUsers () {
		$users = UserManager::getInstance()->getAllPublic();
		foreach ($users as &$user) $user->properties = UserPropertyManager::getInstance()->getAllProperties($user->username);
        $this->sendResponse(array('users' => $this->filterResults((array) $users)));
	}
	
	public function getUserInfo ($uriparts) {
		$username = @$uriparts[1];
		$user = UserManager::getInstance()->getByName($username);
		if ($user) {
			$user = $user->publicCopy();
			$user->properties = UserPropertyManager::getInstance()->getAllProperties($username);
			$this->sendResponse(array ('user' => $this->filterSingleResult($user)));
		}
		else $this->sendErrorResponse('No user found with username '.$username, 404);
	}
	
	public function putUser ($uriparts) {
		$username = @$uriparts[1];
		//$username = $uriparts[1];
		if (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {
			$this->sendErrorResponse("User name must only contain alphameric and underscore, '$username' submitted", 400);
		}
		$user = new User($username);
		$user->save();
	}
	
	public function deleteUser ($uriparts) {
		$username = $uriparts[1];
		UserManager::getInstance()->deleteUser($username);
	}

	public function loginUser ($uriparts) {
		$username = $uriparts[1];
		$password = $this->getParam('POST', 'password', '', _MOS_NOTRIM);
		$manager = UserManager::getInstance();
		if ($manager->authenticate($username,$password)) {
			$this->getUserInfo ($uriparts);
		}
		$this->sendErrorResponse('Login failed', 409);
	}
}