<?php

/*
 * Part of the SCDS API.
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

namespace SkySQL\SCDS\API;

use \PDO;
use \PDOException;

class SystemUsers extends ImplementAPI {
	
	public function getUsers () {
		$query = $this->db->query("SELECT UserID AS id, UserName AS username, Name AS name FROM Users");
        $this->sendResponse(array('users' => $query->fetchAll(PDO::FETCH_ASSOC)));
	}
	
	public function getUserInfo ($uriparts) {
		$username = @urldecode($uriparts[1]);
		$getuser = $this->db->prepare('SELECT COUNT(*) AS number, Name FROM Users WHERE UserName = :username');
		$getuser->execute(array(':username' => $username));
		$user = $getuser->fetch();
		if ($user->number) {
			$properties = $this->db->prepare('SELECT up.Property AS property, up.Value AS value
				FROM UserProperties AS up INNER JOIN Users As u ON up.UserID = u.UserID WHERE u.UserName = :username');
			$properties->execute(array(':username' => $username));
			$this->sendResponse(array ('username' => $username, 'name' => $user->Name, 'properties' => $properties->fetchAll(PDO::FETCH_ASSOC)));
		}
		else $this->sendErrorResponse('No user found with username '.$username, 404);
	}
	
	public function putUser ($uriparts) {
		$username = @urldecode($uriparts[1]);
		if (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {
			$this->sendErrorResponse("User name must only contain alphameric and underscore, $username submitted", 400);
		}
		$this->startImmediateTransaction();
		$name = $this->getParam('PUT', 'name');
		$password = $this->getParam('PUT', 'password');
		$salt = $this->getSalt($username);
		if (empty($salt)) {
			if (!$password) $this->sendErrorResponse('No password provided for create user '.$username, 400);
			$salt = $this->makeSalt();
			$passwordhash = sha1($salt.$password);
			try {
				$query = $this->db->prepare("INSERT INTO Users (UserName, Name, Password, Salt) VALUES (:username, :name, :password, :salt)");
				$query->execute(array(
					':username' => $username,
					':name' => $name,
					':password' => $passwordhash,
					':salt' => $salt
				));
				$this->sendResponse(array('username' => $username, 'name' => $name));
			}
			catch (PDOException $pe) {
				$this->sendErrorResponse('User insertion failed unexpectedly', 500, $pe);
			}
		}
		else {
			$result['username'] = $username;
			if ($name) {
				$sets[] = 'Name = :name';
				$bind[':name'] = $name;
				$result['name'] = $name;
				
			}
			if ($password) {
				$sets[] = 'Password = :password';
				$bind[':password'] = sha1($salt.$password);
			}
			if (isset($sets)) {
				$bind[':username'] = $username;
				$query = $this->db->prepare('UPDATE Users SET '.implode(', ', $sets).' WHERE UserName = :username');
				$query->execute($bind);
			}
			$this->sendResponse(array('result' => $result));
		}
	}
	
	public function putUserProperty ($uriparts) {
		$username = $uriparts[1];
		$this->startImmediateTransaction();
		$userid = $this->getUserID($username);
		if (!$userid) $this->sendErrorResponse('Attempt to add or update a property for a user who does not exist', 404);
		$propertyname = $uriparts[3];
		$bind[':value'] = $this->getParam('PUT', 'value');
		$bind[':userid'] = $userid;
		$bind[':propertyname'] = $propertyname;
		$update = $this->db->prepare('UPDATE UserProperties SET Value = :value WHERE UserID = :userid AND Property = :propertyname');
		$update->execute($bind);
		if (0 == $update->rowCount()) {
			$insert = $this->db->prepare('INSERT INTO UserProperties (UserID, Property, Value) VALUES (:userid, :propertyname, :value)');
			$insert->execute($bind);
		}
		$this->sendResponse();
	}
	
	public function deleteUserProperty ($uriparts) {
		$username = $uriparts[1];
		$this->startImmediateTransaction();
		$userid = $this->getUserID($username);
		if (!$userid) $this->sendErrorResponse('Attempt to add or update a property for a user who does not exist', 404);
		$propertyname = $uriparts[3];
		$delete = $this->db->prepare('DELETE FROM UserProperties WHERE UserID = :userid AND Property = :propertyname');
		$delete->execute(array(
			':userid' => $userid,
			':propertyname' => $propertyname
		));
		$this->sendResponse();
	}
		
	protected function makeSalt () {
		return $this->makeRandomString(24);
	}
	
	protected function makeRandomString ($length=8) {
		$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!%,-:;@_{}~";
		for ($i = 0, $makepass = '', $len = strlen($chars); $i < $length; $i++) $makepass .= $chars[mt_rand(0, $len-1)];
		return $makepass;
	}

	public function deleteUser ($uriparts) {
		$username = urldecode($uriparts[1]);
		$query = $this->db->prepare('DELETE FROM Users WHERE UserName = :username');
		$query->execute(array(':username' => $username));
		if ($query->rowCount()) $this->sendResponse();
		else $this->sendErrorResponse('Delete user did not match any user', 404);
	}
	
	public function loginUser ($uriparts) {
		$username = urldecode($uriparts[1]);
		$password = $this->getParam('POST', 'password');
		$salt = $this->getSalt($username);
		if ($salt) {
			$passwordhash = sha1($salt.$password);
			$query = $this->db->prepare('SELECT Name FROM Users WHERE UserName = :username AND Password = :password');
			$query->execute(array(
				':username' => $username,
				':password' => $passwordhash
			));
			$name = $query->fetch(PDO::FETCH_COLUMN);
			if ($name) {
				$this->loginValidUser($username);
				$this->sendResponse(array('username' => $username, 'name' => $name));
			}
		}
		$this->sendErrorResponse('Login failed', 409);
	}

	protected function loginValidUser () {
		$systemid = $this->getParam('POST', 'systemid');
		if ($systemid) {
			$uplast = $this->db->prepare("UPDATE System SET LastAccess = datetime('now') WHERE SystemID = :systemid");
			$uplast->execute(array(':systemid' => $systemid));
		}
	}

	protected function getSalt ($username) {
		$saltquery = $this->db->prepare('SELECT Salt FROM Users WHERE UserName = :username');
		$saltquery->execute(array(':username' => $username));
		return $saltquery->fetch(PDO::FETCH_COLUMN);
	}
	
	protected function getUserID ($username) {
		$idquery = $this->db->prepare('SELECT UserID FROM Users WHERE UserName = :username');
		$idquery->execute(array(':username' => $username));
		return $idquery->fetch(PDO::FETCH_COLUMN);
	}
}