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
		$query = $this->db->query("SELECT UserID AS id, UserName AS name FROM Users");
        $result = array(
            "users" => $query->fetchAll(PDO::FETCH_ASSOC)
        );
        $this->sendResponse($result);
	}
	
	public function createUser ($uriparts) {
		$username = @urldecode($uriparts[1]);
		if (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {
			$errors[] = "User name must only contain alphameric and underscore, $username submitted";
		}
		$parms = json_decode(file_get_contents("php://input"));
		if (!$parms) $errors[] = 'No valid parameters provided for create user '.$username;
		$name = @$parms->name;
		$password = @$parms->password;
		if (!$password) $errors[] = 'No password provided for create user '.$username;
		if (isset($errors)) {
			$this->sendErrorResponse($errors, 400);
			exit;
		}
		try {
			$query = $this->db->prepare("INSERT INTO Users (UserName, Name, Password) VALUES (:username, :name, :password)");
			$query->execute(array(
				':username' => $username,
				':name' => $name,
				':password' => $password
			));
			$this->sendResponse(array('username' => $username, 'name' => $name));
		}
		catch (PDOException $p) {
			$this->sendErrorResponse('User insertion failed - perhaps username is a duplicate', 409);
		}
	}
	
	public function deleteUser ($uriparts) {
		$username = urldecode($uriparts[1]);
		$query = $this->db->prepare('DELETE FROM Users WHERE UserName = :username');
		$query->execute(array(':username' => $username));
		if ($query->rowCount()) $this->sendResponse('ok');
		else $this->sendErrorResponse('Delete user did not match any user', 404);
	}
	
	public function loginUser ($uriparts) {
		$username = urldecode($uriparts[1]);
		$password = isset($_POST['password']) ? $_POST['password'] : '';
		$query = $this->db->prepare('SELECT COUNT(*) FROM Users WHERE UserName = :username AND Password = :password');
		$query->execute(array(
			':username' => $username,
			':password' => $password
		));
		if ($query->fetch(PDO::FETCH_COLUMN)) $this->sendResponse('ok');
		else $this->sendErrorResponse('Login failed', 409);
	}
}