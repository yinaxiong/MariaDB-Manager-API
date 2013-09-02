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

namespace SkySQL\TESTING;

use SkySQL\SCDS\API\API;
use SkySQL\APIHELP\SkysqlCallAPI;

ini_set('display_errors', 1);
error_reporting(-1);

$noexec = true;

require (dirname(__FILE__).'/api.php');
require (dirname(__FILE__).'/skysqlCallAPI.php');

API::getInstance()->startup(false);

class TestAPI {
	protected $systemid = 0;
	protected $apicall = null;
	
	public function userStatus () {
		if (isset($_POST['testid'])) {
			$method = $_POST['testid'];
			if (method_exists($this, $method)) {
				$this->systemid = @$_POST['systemid'];
				$this->apicall = new SkysqlCallAPI();
				$this->displayResult($this->$method());
				return;
			}
		}
		$this->userChoices();
	}
	
	public function userChoices () {
		echo <<<CHOICES

    <h2>Choose an operation to test</h2>
	<form action="testapi.php" method= "POST">
		<input type="radio" name="testid" value="getAllSystems" />Get all systems<br />
		<input type="radio" name="testid" value="createSystemForm" />Create a system<br />
		<input type="radio" name="testid" value="createNodeForm" />Create a node<br />
		<input type="radio" name="testid" value="getSystemForm" />Get a system<br />
		<input type="radio" name="testid" value="getPropertyForm" />Get a property<br />
		<input type="radio" name="testid" value="setPropertyForm" />Set a property<br />
		<input type="radio" name="testid" value="deletePropertyForm" />Delete a property<br />
		<input type="radio" name="testid" value="getBackupsForm" />Get system backups<br />
		<input type="radio" name="testid" value="getBackupStates" />Get backup states<br />
		<input type="radio" name="testid" value="makeBackupForm" />Make a backup<br />
		<input type="radio" name="testid" value="createUserForm" />Create a user<br />
		<input type="radio" name="testid" value="getAllUsers" />Get all users<br />
		<input type="radio" name="testid" value="deleteUserForm" />Delete a user<br />
		<input type="radio" name="testid" value="loginUserForm" />Login a user<br />
		<input type="radio" name="testid" value="getCommands" />Get all commands<br />
		<input type="radio" name="testid" value="getCommandStates" />Get command states<br />
		<input type="radio" name="testid" value="getCommandSteps" />Get command steps<br />
		<input type="radio" name="testid" value="runCommandForm" />Run command<br />
		<input type="submit" value="Go" />
	</form>

CHOICES;
		
	}
	
	protected function displayResult ($result) {
		if ($result) {
			header('Content-type: application/json');
			echo $result;
		}
	}
	
	public function getSystemForm () {
		echo <<<SYSTEM_FORM
   
    <h2>Get information about a system</h2>
	<form action="testapi.php" method="POST">
		<label for="systemid">System ID</label>
		<input type="text" id="systemid" name="systemid" /><br />
		<input type="hidden" name="testid" value="getSystem" />
		<input type="submit" value="Go" />
	</form>

SYSTEM_FORM;
		
	}
	
	public function getSystem () {
		return $this->apicall->getSystem($this->systemid);
	}
	
	public function createSystemForm () {
		echo <<<SYSTEM_FORM
   
    <h2>Create a system</h2>
	<form action="testapi.php" method="POST">
		<label for="systemname">System Name</label>
		<input type="text" id="systemname" name="systemname" /><br />
		<label for="startdate">Start Date</label>
		<input type="text" id="startdate" name="startdate" /><br />
		<label for="lastaccess">Last Access</label>
		<input type="text" id="lastaccess" name="lastaccess" /><br />
		<label for="status">Status</label>
		<input type="text" id="status" name="status" /><br />
		<input type="hidden" name="testid" value="createSystem" />
		<input type="submit" value="Go" />
	</form>

SYSTEM_FORM;
		
	}
	
	public function createSystem () {
		$name = @$_POST['systemname'];
		$start = @$_POST['initialstart'];
		$access = @$_POST['lastaccess'];
		$state = @$_POST['state'];
		return $this->apicall->createSystem($name, $start, $access,  $state);
	}
	
	public function createNodeForm () {
		echo <<<SYSTEM_FORM
   
    <h2>Create a node</h2>
	<form action="testapi.php" method="POST">
		<label for="systemid">System ID</label>
		<input type="text" id="systemid" name="systemid" /><br />
		<label for="nodename">Node Name</label>
		<input type="text" id="nodename" name="nodename" /><br />
		<label for="status">Status</label>
		<input type="text" id="status" name="status" /><br />
		<input type="hidden" name="testid" value="createNode" />
		<input type="submit" value="Go" />
	</form>

SYSTEM_FORM;
		
	}
	
	public function createNode () {
		$systemid = @$_POST['systemid'];
		$name = @$_POST['systemname'];
		$state = @$_POST['state'];
		return $this->apicall->createNode($systemid, $name, $state);
	}
	
	public function getAllSystems () {
		return $this->apicall->getAllSystems();
	}
	
	public function setPropertyForm () {
		echo <<<PROPERTY_FORM
   
    <h2>Set a value for a property</h2>
	<form action="testapi.php" method="POST">
		<label for="systemid">System ID</label>
		<input type="text" id="systemid" name="systemid" /><br />
		<label for="property">Name of Property</label>
		<input type="text" id="property" name="property" /><br />
		<label for="value">Value for Property</label>
		<input type="text" id="value" name="value" /><br />
		<input type="hidden" name="testid" value="setProperty" />
		<input type="submit" value="Go" />
	</form>

PROPERTY_FORM;
		
	}
	
	public function setProperty () {
		return $this->apicall->setProperty($this->systemid, @$_POST['property'], @$_POST['value']);
	}
	
	public function getPropertyForm () {
		echo <<<PROPERTY_FORM
   
    <h2>Get the value of a property</h2>
	<form action="testapi.php" method="POST">
		<label for="systemid">System ID</label>
		<input type="text" id="systemid" name="systemid" /><br />
		<label for="property">Name of Property</label>
		<input type="text" id="property" name="property" /><br />
		<input type="hidden" name="testid" value="getProperty" />
		<input type="submit" value="Go" />
	</form>

PROPERTY_FORM;
		
	}
	
	public function getProperty () {
		return $this->apicall->getProperty($this->systemid, @$_POST['property']);
	}
	
	public function deletePropertyForm () {
		echo <<<PROPERTY_FORM
   
    <h2>Delete a property</h2>
	<form action="testapi.php" method="POST">
		<label for="systemid">System ID</label>
		<input type="text" id="systemid" name="systemid" /><br />
		<label for="property">Name of Property</label>
		<input type="text" id="property" name="property" />
		<input type="hidden" name="testid" value="deleteProperty" />
		<input type="submit" value="Go" />
	</form>

PROPERTY_FORM;
		
	}
	
	public function deleteProperty () {
		return $this->apicall->deleteProperty($this->systemid, @$_POST['property']);
	}
	
	public function getBackupsForm () {
		echo <<<BACKUP_FORM
   
    <h2>Get system backups</h2>
	<form action="testapi.php" method="POST">
		<label for="systemid">System ID</label>
		<input type="text" id="systemid" name="systemid" /><br />
		<input type="hidden" name="testid" value="getBackups" />
		<input type="submit" value="Go" />
	</form>

BACKUP_FORM;
		
	}
	
	public function getBackups () {
		return $this->apicall->getSystemBackups($this->systemid);
	}
	
	public function getBackupStates () {
		return $this->apicall->getBackupStates();
	}
	
	public function makeBackupForm () {
		echo <<<BACKUP_FORM
   
    <h2>Make a system backup</h2>
	<form action="testapi.php" method="POST">
		<label for="systemid">System ID</label>
		<input type="text" id="systemid" name="systemid" /><br />
		<label for="nodeid">Node ID</label>
		<input type="text" id="nodeid" name="nodeid" /><br />
		<label for="level">Backup Level</label>
		<input type="text" id="level" name="level" /><br />
		<input type="hidden" name="testid" value="makeBackup" />
		<input type="submit" value="Go" />
	</form>

BACKUP_FORM;
		
	}
	
	public function makeBackup () {
		return $this->apicall->makeBackup($this->systemid, @$_POST['nodeid'], @$_POST['level']);
	}

	public function getAllUsers () {
		return $this->apicall->getSystemUsers();
	}
	
	public function createUserForm () {
		echo <<<USER_FORM
   
    <h2>Create a user</h2>
	<form action="testapi.php" method="POST">
		<label for="realname">Real Name of User</label>
		<input type="text" id="realname" name="realname" /><br />
		<label for="username">Username for login</label>
		<input type="text" id="username" name="username" /><br />
		<label for="password">User Password</label>
		<input type="text" id="password" name="password" /><br />
		<input type="hidden" name="testid" value="createUser" />
		<input type="submit" value="Go" />
	</form>

USER_FORM;
		
	}
	
	public function createUser () {
		return $this->apicall->createUser(@$_POST['username'], @$_POST['realname'], @$_POST['password']);
	}
	
	public function deleteUserForm () {
		echo <<<DELETE_FORM
   
    <h2>Delete a user</h2>
	<form action="testapi.php" method="POST">
		<label for="username">Username for deletion</label>
		<input type="text" id="username" name="username" /><br />
		<input type="hidden" name="testid" value="deleteUser" />
		<input type="submit" value="Go" />
	</form>

DELETE_FORM;
		
	}
	
	public function deleteUser () {
		return $this->apicall->deleteUser(@$_POST['username']);
	}
	
	public function loginUserForm () {
		echo <<<LOGIN_FORM
   
    <h2>Login a user</h2>
	<form action="testapi.php" method="POST">
		<label for="username">Username for login</label>
		<input type="text" id="username" name="username" /><br />
		<label for="password">User Password</label>
		<input type="text" id="password" name="password" /><br />
		<input type="hidden" name="testid" value="loginUser" />
		<input type="submit" value="Go" />
	</form>

LOGIN_FORM;
		
	}

	public function loginUser () {
		return $this->apicall->loginUser(@$_POST['username'], @$_POST['password']);
	}
	
	public function getCommands () {
		return $this->apicall->getCommands();
	}
	
	public function getCommandStates () {
		return $this->apicall->getCommandStates();
	}
	
	public function getCommandSteps () {
		return $this->apicall->getCommandSteps();
	}
	
	public function runCommandForm () {
		echo <<<COMMAND_FORM
   
    <h2>Run a command</h2>
	<form action="testapi.php" method="POST">
		<label for="command">Command to run</label>
		<input type="text" id="command" name="command" /><br />
		<label for="systemid">System ID</label>
		<input type="text" id="systemid" name="systemid" /><br />
		<label for="nodeid">Node ID</label>
		<input type="text" id="nodeid" name="nodeid" /><br />
		<label for="username">Username for login</label>
		<input type="text" id="username" name="username" /><br />
		<input type="hidden" name="testid" value="runCommand" />
		<input type="submit" value="Go" />
	</form>

COMMAND_FORM;
		
	}
	
	public function runCommand () {
		return $this->apicall->runCommand(@$_POST['command'], @$_POST['systemid'], @$_POST['nodeid'], @$_POST['username']);
	}
}

$tester = new TestAPI();
$tester->userStatus();