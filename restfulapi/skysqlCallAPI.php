<?php

/*
 * An interface to the SkySQL Cloud Data Suite API (SCDS).
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
 * The skysqlCallAPI class is provided to simplify the calling of the RESTful API
 * provided as part of SCDS.  It is used by creating an api object:
 * 
 *		$apicaller = new skysqlCallAPI();
 * 
 * and then calling methods on $apicaller.
 * 
 */

namespace SkySQL\APIHELP;

use SkySQL\COMMON\EAC_HTTP\HttpRequest;

define ('LOCATION_OF_SKYSQL_API', 'http://api.skysql.black-sheep-research.com');
define ('AUTHORIZATION_ID_SKYSQL_API', '1');
define ('AUTHORIZATION_CODE_SKYSQL_API', '1f8d9e040e65d7b105538b1ed0231770');
define ('LOG_FILE_SKYSQL_API', '/usr/local/skysql/log/api.log');

require (dirname(__FILE__).'/classes/SkySQL/COMMON/EAC_HTTP/eac_httprequest.auth.php');
require (dirname(__FILE__).'/classes/SkySQL/COMMON/EAC_HTTP/eac_httprequest.cache.php');
require (dirname(__FILE__).'/classes/SkySQL/COMMON/EAC_HTTP/eac_httprequest.class.php');
require (dirname(__FILE__).'/classes/SkySQL/COMMON/EAC_HTTP/eac_httprequest.curl.php');
require (dirname(__FILE__).'/classes/SkySQL/COMMON/EAC_HTTP/eac_httprequest.socket.php');
require (dirname(__FILE__).'/classes/SkySQL/COMMON/EAC_HTTP/eac_httprequest.stream.php');

class SkysqlCallException extends \Exception {}

class SkysqlCallAPI {
	protected $requestor = null;
	protected $httpresult = 200;
	
	public function __construct () {
		$this->requestor = new HttpRequest();
	}
	
	public function getHttpStatus () {
		return $this->httpresult;
	}
	
	public function getAllSystems () {
		return $this->get(LOCATION_OF_SKYSQL_API.'/system');
	}
	
	public function getSystem ($systemid) {
		$systemid = (int) $systemid;
		return $this->get(LOCATION_OF_SKYSQL_API."/system/$systemid");
	}
	
	public function createSystem ($name='', $startdate='', $lastaccess='', $state='') {
		$parms = json_encode(array(
			'name' => $name,
			'startDate' => $startdate,
			'lastAccess' => $lastaccess,
			'state' => $state
		));
		return $this->put(LOCATION_OF_SKYSQL_API."/system", $parms);
	}
	
	public function createNode ($systemid, $name='', $state='') {
		$parms = json_encode(array(
			'name' => $name,
			'state' => $state
		));
		return $this->put(LOCATION_OF_SKYSQL_API."/system/$systemid/node", $parms);
	}
	
	public function getProperty ($systemid, $property) {
		$this->checkProperty($systemid, $property);
		return $this->get(LOCATION_OF_SKYSQL_API."/system/$systemid/property/".$property);
	}
	
	public function setProperty ($systemid, $property, $value) {
		$this->checkProperty($systemid, $property);
		return $this->put(LOCATION_OF_SKYSQL_API."/system/$systemid/property/".$property, print_r($value,true));
	}
	
	public function deleteProperty ($systemid, $property) {
		$this->checkProperty($systemid, $property);
		return $this->delete(LOCATION_OF_SKYSQL_API."/system/$systemid/property/".$property);
	}
	
	public function getSystemBackups ($systemid) {
		$systemid = (int) $systemid;
		return $this->get(LOCATION_OF_SKYSQL_API."/system/$systemid/backup");
	}
	
	public function getBackupStates () {
		return $this->get(LOCATION_OF_SKYSQL_API."/backupstate");
	}
	
	public function makeBackup ($systemid, $nodeid, $level) {
		$backup = json_encode(array('nodeid' => (int) $nodeid, 'level' => (int) $level));
		$systemid = (int) $systemid;
		return $this->put(LOCATION_OF_SKYSQL_API."/system/$systemid/backup/", $backup);
	}
	
	public function getSystemUsers () {
		return $this->get(LOCATION_OF_SKYSQL_API."/user");
	}
	
	public function createUser ($username, $realname, $password) {
		$this->checkUser($username);
		$user = json_encode(array('name' => print_r($realname,true), 'password' => print_r($password,true)));
		return $this->put(LOCATION_OF_SKYSQL_API."/user/".$username, $user);
	}
	
	public function deleteUser ($username) {
		$this->checkUser($username);
		return $this->delete(LOCATION_OF_SKYSQL_API."/user/".$username);
	}
	
	public function loginUser ($username, $password) {
		$this->checkUser($username);
		return $this->post(LOCATION_OF_SKYSQL_API."/user/".$username, array('password' => print_r($password,true)));
	}
	
	public function getCommands () {
		return $this->get(LOCATION_OF_SKYSQL_API."/command");
	}
	
	public function getCommandStates () {
		return $this->get(LOCATION_OF_SKYSQL_API."/command/state");
	}
	
	public function getCommandSteps () {
		return $this->get(LOCATION_OF_SKYSQL_API."/command/step");
	}
	
	protected function checkProperty (&$systemid, $property) {
		$systemid = (int) $systemid;
		if (!preg_match('/[a-zA-Z0-9]/', $property)) throw new SkysqlCallException('SkySQL API: System property not alphanumeric');
	}
	
	protected function checkUser ($username) {
		if (!preg_match('/[a-zA-Z0-9]/', $username)) throw new SkysqlCallException('SkySQL API: Username not alphanumeric');
	}
	
	protected function get() {
		$args = func_get_args();
		return $this->httpCall('get', $args);
	}
	
	protected function put() {
		$args = func_get_args();
		return $this->httpCall('put', $args);
	}
	
	protected function post() {
		$args = func_get_args();
		return $this->httpCall('post', $args);
	}
	
	protected function delete() {
		$args = func_get_args();
		return $this->httpCall('delete', $args);
	}
	
	protected function head() {
		$args = func_get_args();
		return $this->httpCall('head', $args);
	}
	
	protected function options() {
		$args = func_get_args();
		return $this->httpCall('options', $args);
	}
	
	protected function httpCall ($method, $args) {
		$date = date('r');
		$matches = array();
		preg_match('#^https?://[a-z0-9-]+(\.[a-z0-9-]+)*([/?](.+))?#i', @$args[0], $matches);
		$uri = @$matches[3];
		$authstring = md5($uri.AUTHORIZATION_CODE_SKYSQL_API.$date);
		$this->requestor->header('Authorization: api-auth-'.AUTHORIZATION_ID_SKYSQL_API.'-'.$authstring, true);
		$this->requestor->header('Date: '.$date, true);
		$this->requestor->header('Accept: application/json', true);
		$this->requestor->header('X-skysql-apiversion: 1');
		$result = call_user_func_array(array($this->requestor,$method), $args);
		$this->httpresult = $this->requestor->getHttpStatus();
		return $result;
	}
}