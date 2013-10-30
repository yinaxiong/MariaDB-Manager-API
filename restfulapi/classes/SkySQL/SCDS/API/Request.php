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
 * Date: June 2013
 * 
 * The Request class is the main controller for the API.  It is an abstract
 * class, and has one final subclass for each method of calling the API.
 * 
 * The $uriTable array defines the API RESTful interface, and links calls to
 * classes and methods.  The Request class puts this into effect.
 * 
 * The constructor splits up the model URIs into their constituent parts.
 * 
 * The doControl method first fetches the request URI and extracts the relevant
 * part, then explodes it into parts.  Then the request URI is compared with 
 * the model URIs to find what is to be done (or an error return).
 * 
 * The sendResponse method is provided for the benefit of the classes that 
 * implement the API.
 * 
 */

namespace SkySQL\SCDS\API;

use \PDOException;
use SkySQL\COMMON\ErrorRecorder;
use SkySQL\COMMON\Diagnostics;
use SkySQL\SCDS\API\controllers\Metadata;

if (basename(@$_SERVER['REQUEST_URI']) == basename(__FILE__)) die ('This software is for use within a larger system');

class aliroProfiler {
    private $start=0;
    private $prefix='';
	private $microsec = 0;

    function __construct ( $prefix='' ) {
	    $this->reset();
        $this->prefix = $prefix;
    }

	public function reset () {
	    list($usec, $sec) = explode(" ", microtime());
        $this->start = (float)$usec + (float)$sec;
		$this->microsec = (int) ($usec * 1000000);
	}
	
	public function getMicroSeconds () {
		return $this->microsec;
	}

    public function mark( $label ) {
        return sprintf ( "$this->prefix %.3f $label", microtime(true) - $this->start );
    }

    public function getElapsed () {
    	return microtime(true) - $this->start;
    }
}

abstract class Request {
	private static $instance = null;
	
	public $warnings = array();
	
	// Longer URI patterns must precede similar shorter ones for correct functioning
	protected static $uriTable = array(
		array('class' => 'Applications', 'method' => 'getApplicationProperty', 'uri' => 'application/[0-9]+/property/[A-Za-z0-9_]+', 'http' => 'GET'),
		array('class' => 'Applications', 'method' => 'setApplicationProperty', 'uri' => 'application/[0-9]+/property/[A-Za-z0-9_]+', 'http' => 'PUT'),
		array('class' => 'Applications', 'method' => 'deleteApplicationProperty', 'uri' => 'application/[0-9]+/property/[A-Za-z0-9_]+', 'http' => 'DELETE'),
		array('class' => 'SystemProperties', 'method' => 'getSystemProperty', 'uri' => 'system/[0-9]+/property/[A-Za-z0-9_]+', 'http' => 'GET'),
		array('class' => 'SystemProperties', 'method' => 'setSystemProperty', 'uri' => 'system/[0-9]+/property/[A-Za-z0-9_]+', 'http' => 'PUT'),
		array('class' => 'SystemProperties', 'method' => 'deleteSystemProperty', 'uri' => 'system/[0-9]+/property/[A-Za-z0-9_]+', 'http' => 'DELETE'),
		array('class' => 'SystemBackups', 'method' => 'updateSystemBackup', 'uri' => 'system/[0-9]+/backup/[0-9]+', 'http' => 'PUT'),
		array('class' => 'SystemBackups', 'method' => 'getOneBackup', 'uri' => 'system/[0-9]+/backup/[0-9]+', 'http' => 'GET'),
		array('class' => 'SystemBackups', 'method' => 'getSystemBackups', 'uri' => 'system/[0-9]+/backup', 'http' => 'GET'),
		array('class' => 'SystemBackups', 'method' => 'makeSystemBackup', 'uri' => 'system/[0-9]+/backup', 'http' => 'POST'),
		array('class' => 'SystemBackups', 'method' => 'getBackupStates', 'uri' => 'backupstate', 'http' => 'GET'),
		array('class' => 'Monitors', 'method' => 'monitorData', 'uri' => 'system/[0-9]+/node/[0-9]+/monitor/.+/data', 'http' => 'GET'),
		array('class' => 'Monitors', 'method' => 'monitorLatest', 'uri' => 'system/[0-9]+/node/[0-9]+/monitor/.+/latest', 'http' => 'GET'),
		array('class' => 'Monitors', 'method' => 'monitorData', 'uri' => 'system/[0-9]+/monitor/.+/data', 'http' => 'GET'),
		array('class' => 'Monitors', 'method' => 'monitorLatest', 'uri' => 'system/[0-9]+/monitor/.+/latest', 'http' => 'GET'),
		array('class' => 'Monitors', 'method' => 'getRawMonitorData', 'uri' => 'system/[0-9]+/node/[0-9]+/monitor/.+/rawdata', 'http' => 'GET'),
		array('class' => 'Monitors', 'method' => 'getRawMonitorData', 'uri' => 'system/[0-9]+/monitor/.+/rawdata', 'http' => 'GET'),
		array('class' => 'Monitors', 'method' => 'storeBulkMonitorData', 'uri' => 'monitordata', 'http' => 'POST'),
		array('class' => 'ComponentProperties', 'method' => 'getComponentPropertyUpdated', 'uri' => 'system/[0-9]+/node/[0-9]+/component/[A-Za-z0-9_:\-]+/property/[A-Za-z0-9_]+/updated', 'http' => 'GET'),
		array('class' => 'ComponentProperties', 'method' => 'getComponentProperty', 'uri' => 'system/[0-9]+/node/[0-9]+/component/[A-Za-z0-9_:\-]+/property/[A-Za-z0-9_]+', 'http' => 'GET'),
		array('class' => 'ComponentProperties', 'method' => 'setComponentProperty', 'uri' => 'system/[0-9]+/node/[0-9]+/component/[A-Za-z0-9_:\-]+/property/[A-Za-z0-9_]+', 'http' => 'PUT'),
		array('class' => 'ComponentProperties', 'method' => 'deleteComponentProperty', 'uri' => 'system/[0-9]+/node/[0-9]+/component/[A-Za-z0-9_:\-]+/property/[A-Za-z0-9_]+', 'http' => 'DELETE'),
		array('class' => 'ComponentProperties', 'method' => 'getComponentProperties', 'uri' => 'system/[0-9]+/node/[0-9]+/component/[A-Za-z0-9_:\-]+', 'http' => 'GET'),
		array('class' => 'ComponentProperties', 'method' => 'deleteComponentProperties', 'uri' => 'system/[0-9]+/node/[0-9]+/component/[A-Za-z0-9_:\-]+/', 'http' => 'DELETE'),
		array('class' => 'ComponentProperties', 'method' => 'getComponents', 'uri' => 'system/[0-9]+/node/[0-9]+/component', 'http' => 'GET'),
		array('class' => 'ComponentProperties', 'method' => 'deleteComponents', 'uri' => 'system/[0-9]+/node/[0-9]+/component', 'http' => 'DELETE'),
		array('class' => 'SystemNodes', 'method' => 'getProcessPlan', 'uri' => 'system/[0-9]+/node/[0-9]+/process/[0-9]+', 'http' => 'GET'),
		array('class' => 'SystemNodes', 'method' => 'killSystemNodeProcess', 'uri' => 'system/[0-9]+/node/[0-9]+/process/[0-9]+', 'http' => 'DELETE'),
		array('class' => 'SystemNodes', 'method' => 'getSystemNodeProcesses', 'uri' => 'system/[0-9]+/node/[0-9]+/process', 'http' => 'GET'),
		array('class' => 'SystemNodes', 'method' => 'getSystemNode', 'uri' => 'system/[0-9]+/node/[0-9]+', 'http' => 'GET'),
		array('class' => 'SystemNodes', 'method' => 'deleteSystemNode', 'uri' => 'system/[0-9]+/node/[0-9]+', 'http' => 'DELETE'),
		array('class' => 'SystemNodes', 'method' => 'getSystemAllNodes', 'uri' => 'system/[0-9]+/node', 'http' => 'GET'),
		array('class' => 'SystemNodes', 'method' => 'updateSystemNode', 'uri' => 'system/[0-9]+/node/[0-9]+', 'http' => 'PUT'),
		array('class' => 'SystemNodes', 'method' => 'createSystemNode', 'uri' => 'system/[0-9]+/node', 'http' => 'POST'),
		array('class' => 'SystemNodes', 'method' => 'nodeStates', 'uri' => 'nodestate/.+', 'http' => 'GET'),
		array('class' => 'SystemNodes', 'method' => 'nodeStates', 'uri' => 'nodestate', 'http' => 'GET'),
		array('class' => 'SystemNodes', 'method' => 'getProvisionedNodes', 'uri' => 'provisionednode', 'http' => 'GET'),
		array('class' => 'UserTags', 'method' => 'getUserTags', 'uri' => 'user/.+/.+tag/.+', 'http' => 'GET'),
		array('class' => 'UserTags', 'method' => 'getAllUserTags', 'uri' => 'user/.+/.+tag', 'http' => 'GET'),
		array('class' => 'UserTags', 'method' => 'addUserTags', 'uri' => 'user/.+/.+tag/.+', 'http' => 'POST'),
		array('class' => 'UserTags', 'method' => 'deleteUserTags', 'uri' => 'user/.+/.+tag/.+/.+', 'http' => 'DELETE'),
		array('class' => 'UserTags', 'method' => 'deleteUserTags', 'uri' => 'user/.+/.+tag/.+', 'http' => 'DELETE'),
		array('class' => 'UserTags', 'method' => 'deleteUserTags', 'uri' => 'user/.+/.+tag', 'http' => 'DELETE'),
		array('class' => 'UserProperties', 'method' => 'getUserProperty', 'uri' => 'user/.*/property/.*', 'http' => 'GET'),
		array('class' => 'UserProperties', 'method' => 'putUserProperty', 'uri' => 'user/.*/property/.*', 'http' => 'PUT'),
		array('class' => 'UserProperties', 'method' => 'deleteUserProperty', 'uri' => 'user/.*/property/.*', 'http' => 'DELETE'),
		array('class' => 'SystemUsers', 'method' => 'getUserInfo', 'uri' => 'user/.*', 'http' => 'GET'),
		array('class' => 'SystemUsers', 'method' => 'putUser', 'uri' => 'user/.*', 'http' => 'PUT'),
		array('class' => 'SystemUsers', 'method' => 'deleteUser', 'uri' => 'user/.*', 'http' => 'DELETE'),
		array('class' => 'SystemUsers', 'method' => 'loginUser', 'uri' => 'user/.*', 'http' => 'POST'),
		array('class' => 'SystemUsers', 'method' => 'getUsers', 'uri' => 'user', 'http' => 'GET'),
		array('class' => 'Systems', 'method' => 'getSystemProcesses', 'uri' => 'system/[0-9]+/process', 'http' => 'GET'),
		array('class' => 'Systems', 'method' => 'getSystemData', 'uri' => 'system/[0-9]+', 'http' => 'GET'),
		array('class' => 'Systems', 'method' => 'updateSystem', 'uri' => 'system/[0-9]+', 'http' => 'PUT'),
		array('class' => 'Systems', 'method' => 'createSystem', 'uri' => 'system', 'http' => 'POST'),
		array('class' => 'Systems', 'method' => 'deleteSystem', 'uri' => 'system/[0-9]+', 'http' => 'DELETE'),
		array('class' => 'Systems', 'method' => 'getAllData', 'uri' => 'system', 'http' => 'GET'),
		array('class' => 'Systems', 'method' => 'getSystemTypes', 'uri' => 'systemtype', 'http' => 'GET'),
		array('class' => 'Buckets', 'method' => 'getData', 'uri' => 'bucket', 'http' => 'GET'),
		array('class' => 'Commands', 'method' => 'getStates', 'uri' => 'command/state', 'http' => 'GET'),
		array('class' => 'Commands', 'method' => 'getSteps', 'uri' => 'command/step', 'http' => 'GET'),
		array('class' => 'Commands', 'method' => 'getCommands', 'uri' => 'command', 'http' => 'GET'),
		array('class' => 'Tasks', 'method' => 'runCommand', 'uri' => 'command/.+', 'http' => 'POST'),
		array('class' => 'Schedules', 'method' => 'runScheduledCommand', 'uri' => 'schedule/[0-9]+', 'http' => 'POST'),
		array('class' => 'Schedules', 'method' => 'getOneSchedule', 'uri' => 'schedule/[0-9]+', 'http' => 'GET'),
		array('class' => 'Schedules', 'method' => 'deleteOneSchedule', 'uri' => 'schedule/[0-9]+', 'http' => 'DELETE'),
		array('class' => 'Schedules', 'method' => 'getSelectedSchedules', 'uri' => 'schedule/.+', 'http' => 'GET'),
		array('class' => 'Schedules', 'method' => 'getSelectedSchedules', 'uri' => 'schedule', 'http' => 'GET'),
		array('class' => 'Schedules', 'method' => 'updateSchedule', 'uri' => 'schedule/[0-9]+', 'http' => 'PUT'),
		array('class' => 'Schedules', 'method' => 'getMultipleSchedules', 'uri' => 'schedule', 'http' => 'GET'),
		array('class' => 'Tasks', 'method' => 'getOneTask', 'uri' => 'task/[0-9]+', 'http' => 'GET'),
		array('class' => 'Tasks', 'method' => 'cancelOneTask', 'uri' => 'task/[0-9]+', 'http' => 'DELETE'),
		array('class' => 'Tasks', 'method' => 'getSelectedTasks', 'uri' => 'task/.+', 'http' => 'GET'),
		array('class' => 'Tasks', 'method' => 'updateTask', 'uri' => 'task/[0-9]+', 'http' => 'PUT'),
		array('class' => 'Tasks', 'method' => 'getMultipleTasks', 'uri' => 'task', 'http' => 'GET'),
		array('class' => 'RunSQL', 'method' => 'runQuery', 'uri' => 'runsql', 'http' => 'GET'),
		array('class' => 'Monitors', 'method' => 'getMonitorClasses', 'uri' => 'monitorclass/.+/key/.+', 'http' => 'GET'),
		array('class' => 'Monitors', 'method' => 'getMonitorClasses', 'uri' => 'monitorclass/.+/key', 'http' => 'GET'),
		array('class' => 'Monitors', 'method' => 'getMonitorClasses', 'uri' => 'monitorclass//key', 'http' => 'GET'),
		array('class' => 'Monitors', 'method' => 'getMonitorClasses', 'uri' => 'monitorclass/.+', 'http' => 'GET'),
		array('class' => 'Monitors', 'method' => 'getMonitorClasses', 'uri' => 'monitorclass', 'http' => 'GET'),
		array('class' => 'Monitors', 'method' => 'putMonitorClass', 'uri' => 'monitorclass/.+/key/.+', 'http' => 'PUT'),
		array('class' => 'Monitors', 'method' => 'deleteMonitorClass', 'uri' => 'monitorclass/.+/key/.+', 'http' => 'DELETE'),
		array('class' => 'UserData', 'method' => 'getBackupLog', 'uri' => 'userdata/(log|binlog)', 'http' => 'GET'),
		array('class' => 'Request', 'method' => 'listAPI', 'uri' => 'metadata/apilist', 'http' => 'GET'),
		array('class' => 'Request', 'method' => 'APIDate', 'uri' => 'apidate', 'http' => 'GET'),
		array('class' => 'Metadata', 'method' => 'getEntity', 'uri' => 'metadata/entity/[A-Za-z]+', 'http' => 'GET'),
		array('class' => 'Metadata', 'method' => 'getEntities', 'uri' => 'metadata/entities', 'http' => 'GET'),
		array('class' => 'Metadata', 'method' => 'metadataSummary', 'uri' => 'metadata', 'http' => 'GET'),
	);
	
	protected static $uriTablePrepared = false;
	protected static $uriregex = '([0-9a-zA-Z_\-\.\~\*\(\)]*)';
	protected static $suffixes = array('html', 'json');
	
	protected static $codes = array(
        100 => 'Continue',
        101 => 'Switching Protocols',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => '(Unused)',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported'
	);

	protected $timer = null;
	protected $micromarker = 0;
	protected $config = array();
	protected $uri = '';
	protected $apibase = array();
	protected $headers = array();
	protected $requestmethod = '';
	protected $requestviapost = false;
	protected $putdata = '';
	protected $accept = '';
	protected $suffix = '';
	protected $suppress = false;
	protected $clientip = '';
	protected $apikey = '';
	
	protected function __construct() {
		$this->timer = new aliroProfiler();
		$this->micromarker = $this->timer->getMicroSeconds();
		$this->clientip = API::getIP();
		if (!self::$uriTablePrepared) {
			foreach (self::$uriTable as &$uridata) {
				$parts = explode('/', trim($uridata['uri'],'/'));
				foreach ($parts as $part) $uridata['uriparts'][] = str_replace('.*', self::$uriregex, $part);
				$this->apibase[$parts[0]] = 1;
			}
			$this->apibase = array_keys($this->apibase);
			self::$uriTablePrepared = true;
		}
        $this->config = $this->readAndCheckConfig();
		define ('_SKYSQL_API_CACHE_DIRECTORY', rtrim(@$this->config['cache']['directory'],'/').'/');
		define ('_SKYSQL_API_OBJECT_CACHE_TIME_LIMIT', $this->config['cache']['timelimit']);
		define ('_SKYSQL_API_OBJECT_CACHE_SIZE_LIMIT', $this->config['cache']['sizelimit']);
		$this->getHeaders();
		$this->uri = $this->getURI();
		$this->getSuffix();
		$this->handleAccept();
		if ('true' == $this->getParam($this->requestmethod, 'suppress_response_codes')) $this->suppress = true;
		$this->getQueryString();
	}

	public function parse_str ($string, &$array) {
		$array = array();
		$parts = explode('&', $string);
		foreach ($parts as $part) {
			$assigns = explode('=', $part, 2);
			if (2 == count($assigns)) $array[$assigns[0]] = $assigns[1];
		}
	}

	protected function readAndCheckConfig () {
		if (!is_readable(_API_INI_FILE_LOCATION)) {
			$this->fatalError(sprintf('No readable API configuration file at %s', _API_INI_FILE_LOCATION));
		}
        $config = parse_ini_file(_API_INI_FILE_LOCATION, true);
		if (!$config) $this->fatalError(sprintf('Could not parse configuration file at %s', _API_INI_FILE_LOCATION));
		if (empty($config['apikeys'])) $this->fatalError(sprintf('Configuration at %s does not specify any API Keys', _API_INI_FILE_LOCATION));
		if (empty($config['cache']['directory'])) $this->warnings[] = sprintf('Configuration at %s does not specify a caching directory', _API_INI_FILE_LOCATION);
		elseif (!is_writeable($config['cache']['directory'])) $this->warnings[] = sprintf('Caching directory %s is not writeable, cannot write cache, please check existence, permissions, SELinux',$config['cache']['directory']);
		if (empty($config['shell']['path'])) $this->warnings[] = sprintf('Configuration at %s does not specify a path for scripts used to run commands', _API_INI_FILE_LOCATION);
		if (empty($config['shell']['php'])) $this->warnings[] = sprintf('Configuration at %s does not specify a path to the PHP executable needed for scheduling', _API_INI_FILE_LOCATION);
		if (empty($config['shell']['hostname'])) $this->warnings[] = sprintf('Configuration at %s does not specify the hostname for scripts to call back to the API', _API_INI_FILE_LOCATION);
		if (empty($config['database']['pdoconnect'])) $this->warnings[] = sprintf('Configuration at %s does not specify PDO connect string for Admin DB', _API_INI_FILE_LOCATION);
		if (empty($config['database']['monconnect'])) $this->warnings[] = sprintf('Configuration at %s does not specify PDO connect string for Monitor DB', _API_INI_FILE_LOCATION);
		if (empty($config['cache']['timelimit'])) $config['cache']['timelimit'] = _SKYSQL_API_OBJECT_CACHE_TIME_DEFAULT;
		if (empty($config['cache']['sizelimit'])) $config['cache']['sizelimit'] = _SKYSQL_API_OBJECT_CACHE_SIZE_DEFAULT;
		if (empty($config['monitor-defaults']['interval'])) $config['monitor-defaults']['interval'] = _SKYSQL_API_MONITOR_INTERVAL_DEFAULT;
		if (empty($config['monitor-defaults']['count'])) $config['monitor-defaults']['count'] = _SKYSQL_API_MONITOR_COUNT_DEFAULT;
		
		return $config;
	}
	
	protected function getQueryString () {
		$querystring = $this->getParam($this->requestmethod, 'querystring');
		if ($querystring) {
			$data = &$this->getArrayFromName($this->requestmethod);
			$this->parse_str($querystring, $newdata);
			foreach ($newdata as $name=>$value) $data[$name] = $value;
		}
	}
	
	public function getHeader ($name) {
		return isset($this->headers[$name]) ? $this->headers[$name] : null;
	}
	
	protected function fatalError ($error, $status=500) {
		error_log($error);
		$this->log(LOG_CRIT, $error);
		$this->sendHeaders($status);
		echo $status.' '.(isset(self::$codes[$status]) ? self::$codes[$status] : '').' - '.$error;
		exit;
	}
	
	protected function getURI () {
		$sepquery = explode('?', @$_SERVER['REQUEST_URI']);
		$sepindex = explode('index.php', $sepquery[0]);
		$afterindex = trim(end($sepindex), '/');
		$sepapi = explode('/', $afterindex);
		while (count($sepapi) AND !in_array($sepapi[0],$this->apibase)) array_shift($sepapi);
		return count($sepapi) ? implode('/',$sepapi) : $afterindex;
	}
	
	protected function getSuffix() {
		foreach (self::$suffixes as $suffix) {
			$slen = strlen($suffix) + 1;
			if (substr($this->uri,-$slen) == '.'.$suffix) {
				$this->uri = substr($this->uri,0,-$slen);
				$this->suffix = $suffix;
				break;
			}
		}
	}
	
	protected function handleAccept () {
		if ('json' == $this->suffix) $this->accept = 'application/json';
		else {
			$accepts = $this->getParam($this->requestmethod, '_accept', @$_SERVER['HTTP_ACCEPT']);
			$this->accept = stripos($accepts, 'application/json') !== false ? 'application/json' : 'text/html';
		}
	}
	
	public static function getInstance () {
		return self::$instance instanceof self ? self::$instance : self::$instance = self::makeAppropriateInstance();
	}
	
	private static function makeAppropriateInstance() {
		global $argv;
		if (empty($argv)) {
			$class = ('POST' == @$_SERVER['REQUEST_METHOD'] AND isset($_POST['_method'])) ? 'TunnelRequest' : 'StandardRequest';
		}
		else $class = 'CommandRequest';
		return call_user_func(array(__NAMESPACE__.'\\'.$class,'getInstance'));
	}
	
	public function getMethod () {
		return $this->requestmethod;
	}
	
	public function getAccept () {
		return $this->accept;
	}

	public function doControl () {
		$this->log(LOG_INFO, "$this->requestmethod /$this->uri".($this->suffix ? '.'.$this->suffix : ''));
		if ('yes' == @$this->config['logging']['verbose']) {
			if (count($_POST)) $this->log(LOG_DEBUG, print_r($_POST,true));
			if (count($_GET)) $this->log(LOG_DEBUG, print_r($_GET,true));
			if (!empty($this->putdata)) $this->log(LOG_DEBUG, print_r($this->putdata,true));
		}
		$uriparts = array_map('urldecode', explode('/', $this->uri));
		$link = $this->getLinkByURI($uriparts);
		if ($link) {
			try {
				if ('metadata' != $uriparts[0] AND 'userdata' != $uriparts[0] AND 'apidate' != $uriparts[0]) $this->checkSecurity();
				if ('Request' == $link['class']) $object = $this;
				else {
					$class = __NAMESPACE__.'\\controllers\\'.$link['class'];
					if (!class_exists($class)) {
						$this->sendErrorResponse("Request $this->uri no such class as $class", 500);
					}
					$factory = $link['class'].'Factory';
					if (method_exists($class, $factory)) $object = call_user_func (array($class, $factory), $uriparts, $this);
					else $object = new $class($this);
				}
				$method = $link['method'];
				if (!method_exists($object, $method)) {
					$this->sendErrorResponse("Request $this->uri no such method as $method in class $class", 500);
				}
				$object->$method($uriparts);
				$this->sendErrorResponse("Selected method $method of class $class returned to controller", 500);
			}
			catch (PDOException $pe) {
				$this->sendErrorResponse('Unexpected database error: '.$pe->getMessage(), 500, $pe);
			}
		}
		else $this->sendErrorResponse ("Request $this->uri with HTTP request $this->requestmethod does not match the API", 404);
	}
	
	protected function listAPI () {
		$controller = new Metadata($this);
		$controller->listAPI(self::$uriTable);
	}
	
	protected function APIDate () {
		$this->sendResponse(array('apidate' => _API_CODE_ISSUE_DATE));
	}
	
	protected function checkSecurity () {
		$headertime = isset($this->headers['Date']) ? strtotime($this->headers['Date']) : 0;
		if ($headertime > time()+300 OR $headertime < time()-900) {
			$this->log(LOG_ERR, 'Auth error - Header time: '.($headertime ? $headertime : '*zero*').' actual time: '.time());
			$this->sendErrorResponse('Date header out of range '.(empty($this->headers['Date']) ? '*empty*' : $this->headers['Date']).', current '.date('r'), 401);
		}
		$matches = array();
		if (preg_match('/api\-auth\-([0-9]+)\-([0-9a-z]{32,32})/', @$this->headers['Authorization'], $matches)) {
			if (isset($matches[1]) AND isset($matches[2])) {
				if (isset($this->config['apikeys'][$matches[1]])) {
					$this->apikey = $this->config['apikeys'][$matches[1]];
					$checkstring = \md5($this->uri.$this->apikey.$this->headers['Date']);
					if ($matches[2] == $checkstring) return;
				}
			}
		}
		$this->log(LOG_ERR, 'Auth error - Header authorization: '.@$this->headers['Authorization'].' calculated auth: '.@$checkstring.' Based on URI: '.$this->uri.' key: '.@$this->config['apikeys'][@$matches[1]].' Date: '.$this->headers['Date']);
		$this->sendErrorResponse('Invalid Authorization header', 401);
	}
	
	public function getAPIKey () {
		return $this->apikey;
	}
	
	protected function getLinkByURI ($uriparts) {
		$partcount = count($uriparts);
		foreach (self::$uriTable as $link) {
			if ($this->requestmethod != $link['http']) continue;
			if (count($link['uriparts']) != $partcount) continue;
			$matched = true;
			foreach ($link['uriparts'] as $i=>$part) {
				if (!($part == $uriparts[$i]) AND !$this->uriMatch($part, $uriparts[$i])) {
					$matched = false;
					break;
				}
			}
			if ($matched) return $link;
		}
		return false;
	}

	protected function uriMatch ($pattern, $actual) {
		return @preg_match("/^$pattern$/", $actual);
	}
        
	public function getConfig () {
		return $this->config;
	}
	
	public function getAllParamNames ($arrname) {
		$arr = &$this->getArrayFromName($arrname);
		return array_diff(array_keys($arr),
			array('fields','limit','offset','suppress_response_codes','querystring','_method', '_accept', 'uri1', 'uri2', 'uri3', 'uri4'));
	}

	public function getParam ($arrname, $name, $def=null, $mask=0) {
		$arr = &$this->getArrayFromName($arrname);
		/*
		if (!is_array($arr)) return $def;
		if (strlen($this->requestmethod > 4 AND 'POST' == substr($this->requestmethod,0,4))) {
			if ($arrname == substr($this->requestmethod,4)) $arr =& $_POST;
			else return $def;
		}
		 * 
		 */
		
	    if (isset($arr[$name])) {
	        if (is_array($arr[$name])) foreach (array_keys($arr[$name]) as $key) {
	        	$result[$key] = $this->getParam ($arr[$name], $key, $def, $mask);
	        }
	        else {
	            $result = $arr[$name];
	            if (!($mask&_MOS_NOTRIM)) $result = trim($result);
	            if (!is_numeric($result)) {
	            	if (get_magic_quotes_gpc() AND !($mask & _MOS_NOSTRIP)) $result = stripslashes($result);
	                if (!($mask&_MOS_ALLOWRAW) AND is_numeric($def)) $result = $def;
	            }
	        }
	    }
	    return isset($result) ? $result : $def;
	}
	
	public function putParam ($arrname, $name, $value) {
		$arr = &$this->getArrayFromName($arrname);
		if (is_array($arr)) $arr[$name] = $value;
	}
	
	public function paramEmpty ($arrname, $name) {
		$arr = &$this->getArrayFromName($arrname);
		return (is_array($arr)) ? !isset($arr[$name]) : true;
	}
	
	protected function &getArrayFromName ($arrname) {
		if (is_array($arrname)) $arr =& $arrname;
		elseif ($this->requestviapost) $arr =& $_POST;
		elseif ('GET' == $arrname) $arr =& $_GET;
		elseif ('POST' == $arrname) $arr =& $_POST;
		elseif ('PUT' == $arrname OR 'DELETE' == $arrname) $arr =& $this->putdata;
		if (is_array(@$arr)) {
			if (strlen($this->requestmethod > 4 AND 'POST' == substr($this->requestmethod,0,4))) {
				if ($arrname == substr($this->requestmethod,4)) $arr =& $_POST;
			}
		}
		else $arr = array();
		return $arr;
	}

	// Sends response to API request - data will be JSON encoded if content type is JSON
	public function sendResponse ($body='', $status=200) {
		if (!is_array($body)) $body = array('result' => $body);
		if ($this->suppress OR 'yes' == @$this->config['debug']['showhttpcode']) $body['httpcode'] = $status;
		//if ('yes' == @$this->config['logging']['verbose']) $this->log(LOG_INFO, print_r($body, true));
		if (count((array) $this->warnings)) {
			$body['warnings'] = (array) $this->warnings;
			foreach ((array) $this->warnings as $warning) $this->log(LOG_WARNING, $warning);
		}
		$this->sendHeaders($status);
		if ('yes' == @$this->config['debug']['reflectheaders']) $body['requestheaders'] = $this->headers;
		$output = json_encode($body);
		echo 'application/json' == $this->accept ? $output : $this->prettyPage(nl2br(str_replace("\t", "&nbsp;&nbsp;&nbsp;&nbsp;", $this->prettyPrint($output))));
		exit;
	}
	
	public function sendErrorResponse ($errors, $status, $exception=null) {
		foreach ((array) $errors as $error) $this->log(LOG_ERR, $error);
		$this->sendHeaders($status);
		$body['errors'] = (array) $errors;
		if (count($this->warnings)) {
			$body['warnings'] = (array) $this->warnings;
			foreach ((array) $this->warnings as $warning) $this->log(LOG_WARNING, $warning);
		}
		if ('yes' == @$this->config['debug']['reflectheaders']) $body['requestheaders'] = $this->headers;
		if ($this->suppress OR 'yes' == @$this->config['debug']['showhttpcode'] OR 'text/html' == $this->accept) {
			$body['httpcode'] = 'text/html' == $this->accept ? $status.' '.@self::$codes[$status] : $status;
		}
		$recorder = ErrorRecorder::getInstance();
		$recorder->recordError('Sent error response: '.$status, md5(Diagnostics::trace()), implode("\r\n", (array) $errors), $exception);
		$output = json_encode($body);
		echo 'application/json' == $this->accept ? $output : $this->prettyPage(nl2br(str_replace("\t", "&nbsp;&nbsp;&nbsp;&nbsp;", $this->prettyPrint($output))));
		exit;
	}
	
	protected function prettyPage ($body) {
		return <<<PRETTY_PAGE
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html
   PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
   "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<title>Results | MariaDB Enterprise API</title>
</head>
<body>
$body
</body>
</html>		
		
PRETTY_PAGE;
		
	}
	
	// Function prettyPrint is by Kendall Hopkins (http://stackoverflow.com/users/188044/kendall-hopkins)
	// and was published under Creative Commons License with attribution.  Thanks Kendall!
	protected function prettyPrint( $json ) {
	    $result = '';
	    $level = 0;
	    $prev_char = '';
	    $in_quotes = false;
	    $ends_line_level = NULL;
	    $json_length = strlen( $json );
	    for( $i = 0; $i < $json_length; $i++ ) {
	        $char = $json[$i];
	        $new_line_level = NULL;
	        $post = "";
	        if( $ends_line_level !== NULL ) {
	            $new_line_level = $ends_line_level;
	            $ends_line_level = NULL;
	        }
	        if( $char === '"' && $prev_char != '\\' ) {
	            $in_quotes = !$in_quotes;
	        } 
			else if( ! $in_quotes ) {
	            switch( $char ) {
	                case '}': case ']':
	                    $level--;
	                    $ends_line_level = NULL;
	                    $new_line_level = $level;
	                    break;

	                case '{': case '[':
	                    $level++;
	                case ',':
	                    $ends_line_level = $level;
	                    break;

	                case ':':
	                    $post = " ";
	                    break;

	             case " ": case "\t": case "\n": case "\r":
	                    $char = "";
		                $ends_line_level = $new_line_level;
		                $new_line_level = NULL;
		                break;
		        }
		    }
		    if( $new_line_level !== NULL ) {
		        $result .= "\n".str_repeat( "\t", $new_line_level );
		    }
		    $result .= $char.$post;
		    $prev_char = $char;
		}
	    return $result;
	}
	
	public function sendHeaders ($status) {
		$report = $status.' '.(isset(self::$codes[$status]) ? self::$codes[$status] : '');
		$this->log(LOG_INFO, "$this->requestmethod /$this->uri completed $report - time taken {$this->timer->mark('seconds')}");
		header(HTTP_PROTOCOL.' '.($this->suppress ? '200 OK' : $report));
		header('Content-type: '.$this->accept);
		header('Cache-Control: no-store');
		header('X-SkySQL-API-Version: '._API_VERSION_NUMBER);
	}
	
	public function log ($severity, $message) {
		$prefix = "[$this->clientip] [$this->micromarker] ";
		syslog($severity, $prefix.$message);
	}
}
