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
		array('class' => 'Monitors', 'method' => 'storeMonitorData', 'uri' => 'system/[0-9]+/node/[0-9]+/monitor/.+/data', 'http' => 'POST'),
		array('class' => 'Monitors', 'method' => 'storeMonitorData', 'uri' => 'system/[0-9]+/monitor/.+/data', 'http' => 'POST'),
		array('class' => 'Monitors', 'method' => 'storeBulkMonitorData', 'uri' => 'monitordata', 'http' => 'POST'),
		array('class' => 'SystemNodes', 'method' => 'getProcessPlan', 'uri' => 'system/[0-9]+/node/[0-9]+/process/[0-9]+', 'http' => 'GET'),
		array('class' => 'SystemNodes', 'method' => 'killSystemNodeProcess', 'uri' => 'system/[0-9]+/node/[0-9]+/process/[0-9]+', 'http' => 'DELETE'),
		array('class' => 'SystemNodes', 'method' => 'getSystemNodeProcesses', 'uri' => 'system/[0-9]+/node/[0-9]+/process', 'http' => 'GET'),
		array('class' => 'SystemNodes', 'method' => 'getSystemNode', 'uri' => 'system/[0-9]+/node/[0-9]+', 'http' => 'GET'),
		array('class' => 'SystemNodes', 'method' => 'putSystemNode', 'uri' => 'system/[0-9]+/node/[0-9]+', 'http' => 'PUT'),
		array('class' => 'SystemNodes', 'method' => 'deleteSystemNode', 'uri' => 'system/[0-9]+/node/[0-9]+', 'http' => 'DELETE'),
		array('class' => 'SystemNodes', 'method' => 'getSystemAllNodes', 'uri' => 'system/[0-9]+/node', 'http' => 'GET'),
		array('class' => 'SystemNodes', 'method' => 'putSystemNode', 'uri' => 'system/[0-9]+/node', 'http' => 'PUT'),
		array('class' => 'SystemNodes', 'method' => 'nodeStates', 'uri' => 'nodestate/.+', 'http' => 'GET'),
		array('class' => 'SystemNodes', 'method' => 'nodeStates', 'uri' => 'nodestate', 'http' => 'GET'),
		array('class' => 'UserTags', 'method' => 'getUserTags', 'uri' => 'user/.+/.+tag/.+', 'http' => 'GET'),
		array('class' => 'UserTags', 'method' => 'getAllUserTags', 'uri' => 'user/.+/.+tag', 'http' => 'GET'),
		array('class' => 'UserTags', 'method' => 'addUserTags', 'uri' => 'user/.+/.+tag/.+', 'http' => 'POST'),
		array('class' => 'UserTags', 'method' => 'deleteUserTags', 'uri' => 'user/.+/.+tag/.+/.+', 'http' => 'DELETE'),
		array('class' => 'UserTags', 'method' => 'deleteUserTags', 'uri' => 'user/.+/.+tag/.+', 'http' => 'DELETE'),
		array('class' => 'UserTags', 'method' => 'deleteUserTags', 'uri' => 'user/.+/.+tag', 'http' => 'DELETE'),
		array('class' => 'UserProperties', 'method' => 'putUserProperty', 'uri' => 'user/.*/property/.*', 'http' => 'PUT'),
		array('class' => 'UserProperties', 'method' => 'deleteUserProperty', 'uri' => 'user/.*/property/.*', 'http' => 'DELETE'),
		array('class' => 'SystemUsers', 'method' => 'getUserInfo', 'uri' => 'user/.*', 'http' => 'GET'),
		array('class' => 'SystemUsers', 'method' => 'putUser', 'uri' => 'user/.*', 'http' => 'PUT'),
		array('class' => 'SystemUsers', 'method' => 'deleteUser', 'uri' => 'user/.*', 'http' => 'DELETE'),
		array('class' => 'SystemUsers', 'method' => 'loginUser', 'uri' => 'user/.*', 'http' => 'POST'),
		array('class' => 'SystemUsers', 'method' => 'getUsers', 'uri' => 'user', 'http' => 'GET'),
		array('class' => 'Systems', 'method' => 'getSystemProcesses', 'uri' => 'system/[0-9]+/process', 'http' => 'GET'),
		array('class' => 'Systems', 'method' => 'getSystemData', 'uri' => 'system/[0-9]+', 'http' => 'GET'),
		array('class' => 'Systems', 'method' => 'putSystem', 'uri' => 'system/[0-9]+', 'http' => 'PUT'),
		array('class' => 'Systems', 'method' => 'deleteSystem', 'uri' => 'system/[0-9]+', 'http' => 'DELETE'),
		array('class' => 'Systems', 'method' => 'getAllData', 'uri' => 'system', 'http' => 'GET'),
		array('class' => 'Systems', 'method' => 'getSystemTypes', 'uri' => 'systemtype', 'http' => 'GET'),
		array('class' => 'Buckets', 'method' => 'getData', 'uri' => 'bucket', 'http' => 'GET'),
		array('class' => 'Commands', 'method' => 'getStates', 'uri' => 'command/state', 'http' => 'GET'),
		array('class' => 'Commands', 'method' => 'getSteps', 'uri' => 'command/step', 'http' => 'GET'),
		array('class' => 'Commands', 'method' => 'getCommands', 'uri' => 'command', 'http' => 'GET'),
		array('class' => 'Tasks', 'method' => 'runCommand', 'uri' => 'command/.+', 'http' => 'POST'),
		array('class' => 'Tasks', 'method' => 'runScheduledCommand', 'uri' => 'task/[0-9]+', 'http' => 'POST'),
		array('class' => 'Tasks', 'method' => 'getOneTask', 'uri' => 'task/[0-9]+', 'http' => 'GET'),
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
		array('class' => 'Request', 'method' => 'listAPI', 'uri' => 'metadata/apilist', 'http' => 'GET'),
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
	protected $rfcdate = '';
	protected $authorization = '';
	protected $accept = '';
	protected $suffix = '';
	protected $suppress = false;
	
	protected function __construct() {
		$this->timer = new aliroProfiler();
		$this->micromarker = $this->timer->getMicroSeconds();
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
	
	protected function readAndCheckConfig () {
		if (!is_readable(_API_INI_FILE_LOCATION)) {
			$this->fatalError(sprintf('No readable API configuration file at %s', _API_INI_FILE_LOCATION));
		}
        $config = parse_ini_file(_API_INI_FILE_LOCATION, true);
		if (!$config) $this->fatalError(sprintf('Could not parse configuration file at %s', _API_INI_FILE_LOCATION));
		if (empty($config['apikeys'])) $this->fatalError(sprintf('Configuration at %s does not specify any API Keys', _API_INI_FILE_LOCATION));
		if (empty($config['logging']['directory'])) $this->warnings[] = sprintf('Configuration at %s does not specify a logging directory', _API_INI_FILE_LOCATION);
		elseif (!is_writeable($config['logging']['directory'])) $this->warnings[] = sprintf('Logging directory %s is not writeable, cannot write log, please check existence, permissions, SELinux',$config['logging']['directory']);
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
			parse_str($querystring, $newdata);
			foreach ($newdata as $name=>$value) $data[$name] = $value;
		}
	}
	
	protected function fatalError ($error, $status=500) {
			error_log($error);
			$this->log($error);
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
		$this->log(date('Y-m-d H:i:s')." $this->requestmethod request on /$this->uri\n".($this->suffix ? ' with suffix '.$this->suffix : ''));
		if ('yes' == @$this->config['logging']['verbose']) {
			if (count($_POST)) $this->log(print_r($_POST,true));
			if (count($_GET)) $this->log(print_r($_GET,true));
			if (!empty($this->putdata)) $this->log(print_r($this->putdata,true));
		}
		$uriparts = explode('/', $this->uri);
		if ('metadata' != $uriparts[0]) $this->checkSecurity();
		$link = $this->getLinkByURI($uriparts);
		if ($link) {
			try {
				if ('Request' == $link['class']) $object = $this;
				else {
					$class = __NAMESPACE__.'\\controllers\\'.$link['class'];
					if (!class_exists($class)) {
						$this->sendErrorResponse("Request $this->uri no such class as $class", 404);
					}
					$factory = $link['class'].'Factory';
					if (method_exists($class, $factory)) $object = call_user_func (array($class, $factory), $uriparts, $this);
					else $object = new $class($this);
				}
				$method = $link['method'];
				if (!method_exists($object, $method)) {
					$this->sendErrorResponse("Request $this->uri no such method as $method in class $class", 404);
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
	
	protected function checkSecurity () {
		$headertime = strtotime($this->rfcdate);
		if ($headertime > time()+300 OR $headertime < time()-900) {
			$this->log('Header time: '.($headertime ? $headertime : '*zero*').' actual time: '.time()."\n");
			$this->sendErrorResponse('Date header out of range '.(empty($this->rfcdate) ? '*empty*' : $this->rfcdate).', current '.date('r'), 401);
		}
		$matches = array();
		if (preg_match('/api\-auth\-([0-9]+)\-([0-9a-z]{32,32})/', $this->authorization, $matches)) {
			if (isset($matches[1]) AND isset($matches[2])) {
				if (isset($this->config['apikeys'][$matches[1]])) {
					$checkstring = \md5($this->uri.$this->config['apikeys'][@$matches[1]].$this->rfcdate);
					if ($matches[2] == $checkstring) return;
				}
			}
		}
		$this->log('Header authorization: '.$this->authorization.' calculated auth: '.@$checkstring.' Based on URI: '.$this->uri.' key: '.@$this->config['apikeys'][@$matches[1]].' Date: '.$this->rfcdate."\n");
		$this->sendErrorResponse('Invalid Authorization header', 401);
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
			array('fields','limit','offset','suppress_response_codes','querystring','_method', '_accept', '_rfcdate', '_authorization'));
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
					$result = urldecode($result);
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
		if ($this->suppress) $body['httpcode'] = $status;
		if (count((array) $this->warnings)) {
			$body['warnings'] = (array) $this->warnings;
			foreach ((array) $this->warnings as $warning) $this->log($warning."\n");
		}
		$this->sendHeaders($status);
		echo 'application/json' == $this->accept ? json_encode($body) : print_r($body, true);
		exit;
	}
	
	public function sendErrorResponse ($errors, $status, $exception=null) {
		foreach ((array) $errors as $error) $this->log($error."\n");
		$this->sendHeaders($status);
		if ('text/html' == $this->accept) {
			$statusname = @self::$codes[$status];
			$errortext = implode('<br />', (array) $errors);
			if (empty($errortext)) $errortext = '*none*';
			$text = "<p>Error(s) accompanying return code $status $statusname:<br />$errortext</p>";
			if (count((array) $this->warnings)) {
				$text .= '<p>Warning(s) noted:<br />'.implode('<br />', (array) $this->warnings);
				foreach ((array) $this->warnings as $warning) $this->log($warning."\n");
			}
		}
		$recorder = ErrorRecorder::getInstance();
		$recorder->recordError('Sent error response: '.$status, md5(Diagnostics::trace()), implode("\r\n", (array) $errors), $exception);
		if ('application/json' == $this->accept) {
			$body['errors'] = (array) $errors;
			if ($this->suppress) $body['httpcode'] = $status;
			if (count($this->warnings)) {
				$body['warnings'] = (array) $this->warnings;
				foreach ((array) $this->warnings as $warning) $this->log($warning."\n");
			}
			echo json_encode($body);
		}
		else echo $text;
		exit;
	}
	
	public function sendHeaders ($status) {
		$this->log("Time to handle request {$this->timer->mark('seconds')}\n");
		$this->log("HTTP Response: $status\n");
		header(HTTP_PROTOCOL.' '.($this->suppress ? '200 OK' : $status.' '.(isset(self::$codes[$status]) ? self::$codes[$status] : '')));
		header('Content-type: '.$this->accept);
		header('Cache-Control: no-store');
		header('X-SkySQL-API-Version: '._API_VERSION_NUMBER);
	}
	
	public function log ($data) {
		if (isset($this->config['logging']['directory']) AND is_writeable($this->config['logging']['directory'])) {
			$logfile = $this->config['logging']['directory']."/api.log";
			if (!file_exists($logfile) OR is_writeable($logfile)) {
				if (!is_string($data)) $data = serialize($data);
				file_put_contents($logfile, "[$this->micromarker] $data", FILE_APPEND);
			}
		}
	}
}
