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

class aliroProfiler {
    private $start=0;
    private $prefix='';

    function __construct ( $prefix='' ) {
        $this->start = microtime(true);
        $this->prefix = $prefix;
    }

	public function reset () {
		$this->start = microtime(true);
	}

    public function mark( $label ) {
        return sprintf ( "$this->prefix %.3f $label", microtime(true) - $this->start );
    }

    public function getElapsed () {
    	return microtime(true) - $this->start;
    }
}

abstract class Request {
	
	// Longer URI patterns must precede similar shorter ones for correct functioning
	protected static $uriTable = array(
		array('class' => 'Application', 'method' => 'getApplicationProperty', 'uri' => 'application/[0-9]+/property/[A-Za-z0-9_]+', 'http' => 'GET'),
		array('class' => 'Application', 'method' => 'setApplicationProperty', 'uri' => 'application/[0-9]+/property/[A-Za-z0-9_]+', 'http' => 'PUT'),
		array('class' => 'Application', 'method' => 'deleteApplicationProperty', 'uri' => 'application/[0-9]+/property/[A-Za-z0-9_]+', 'http' => 'DELETE'),
		array('class' => 'Systems', 'method' => 'getSystemProperty', 'uri' => 'system/[0-9]+/property/[A-Za-z0-9_]+', 'http' => 'GET'),
		array('class' => 'Systems', 'method' => 'setSystemProperty', 'uri' => 'system/[0-9]+/property/[A-Za-z0-9_]+', 'http' => 'PUT'),
		array('class' => 'Systems', 'method' => 'deleteSystemProperty', 'uri' => 'system/[0-9]+/property/[A-Za-z0-9_]+', 'http' => 'DELETE'),
		array('class' => 'SystemBackups', 'method' => 'updateSystemBackup', 'uri' => 'system/[0-9]+/backup/[0-9]+', 'http' => 'PUT'),
		array('class' => 'SystemBackups', 'method' => 'getSystemBackups', 'uri' => 'system/[0-9]+/backup', 'http' => 'GET'),
		array('class' => 'SystemBackups', 'method' => 'makeSystemBackup', 'uri' => 'system/[0-9]+/backup', 'http' => 'POST'),
		array('class' => 'SystemBackups', 'method' => 'getBackupStates', 'uri' => '/backupstate', 'http' => 'GET'),
		array('class' => 'Monitors', 'method' => 'monitorData', 'uri' => 'system/[0-9]+/node/[0-9]+/monitor/[0-9]+/data', 'http' => 'GET'),
		array('class' => 'Monitors', 'method' => 'monitorData', 'uri' => 'system/[0-9]+/monitor/[0-9]+/data', 'http' => 'GET'),
		array('class' => 'Monitors', 'method' => 'getRawMonitorData', 'uri' => 'system/[0-9]+/node/[0-9]+/monitor/[0-9]+/rawdata', 'http' => 'GET'),
		array('class' => 'Monitors', 'method' => 'getRawMonitorData', 'uri' => 'system/[0-9]+/monitor/[0-9]+/rawdata', 'http' => 'GET'),
		array('class' => 'Monitors', 'method' => 'storeMonitorData', 'uri' => 'system/[0-9]+/node/[0-9]+/monitor/[0-9]+/data', 'http' => 'POST'),
		array('class' => 'Monitors', 'method' => 'storeMonitorData', 'uri' => 'system/[0-9]+/monitor/[0-9]+/data', 'http' => 'POST'),
		array('class' => 'SystemNodes', 'method' => 'getSystemNode', 'uri' => 'system/[0-9]+/node/[0-9]+', 'http' => 'GET'),
		array('class' => 'SystemNodes', 'method' => 'putSystemNode', 'uri' => 'system/[0-9]+/node/[0-9]+', 'http' => 'PUT'),
		array('class' => 'SystemNodes', 'method' => 'deleteSystemNode', 'uri' => 'system/[0-9]+/node/[0-9]+', 'http' => 'DELETE'),
		array('class' => 'SystemNodes', 'method' => 'getSystemAllNodes', 'uri' => 'system/[0-9]+/node', 'http' => 'GET'),
		array('class' => 'SystemNodes', 'method' => 'nodeStates', 'uri' => 'nodestate/.+', 'http' => 'GET'),
		array('class' => 'SystemNodes', 'method' => 'nodeStates', 'uri' => 'nodestate', 'http' => 'GET'),
		array('class' => 'SystemUsers', 'method' => 'putUserProperty', 'uri' => 'user/.*/property/.*', 'http' => 'PUT'),
		array('class' => 'SystemUsers', 'method' => 'deleteUserProperty', 'uri' => 'user/.*/property/.*', 'http' => 'DELETE'),
		array('class' => 'SystemUsers', 'method' => 'getUserInfo', 'uri' => 'user/.*', 'http' => 'GET'),
		array('class' => 'SystemUsers', 'method' => 'putUser', 'uri' => 'user/.*', 'http' => 'PUT'),
		array('class' => 'SystemUsers', 'method' => 'deleteUser', 'uri' => 'user/.*', 'http' => 'DELETE'),
		array('class' => 'SystemUsers', 'method' => 'loginUser', 'uri' => 'user/.*', 'http' => 'POST'),
		array('class' => 'SystemUsers', 'method' => 'getUsers', 'uri' => 'user', 'http' => 'GET'),
		array('class' => 'Systems', 'method' => 'getSystemData', 'uri' => 'system/[0-9]+', 'http' => 'GET'),
		array('class' => 'Systems', 'method' => 'putSystem', 'uri' => 'system/[0-9]+', 'http' => 'PUT'),
		array('class' => 'Systems', 'method' => 'deleteSystem', 'uri' => 'system/[0-9]+', 'http' => 'DELETE'),
		array('class' => 'Systems', 'method' => 'getAllData', 'uri' => 'system', 'http' => 'GET'),
		array('class' => 'Buckets', 'method' => 'getData', 'uri' => 'bucket', 'http' => 'GET'),
		array('class' => 'Commands', 'method' => 'getStates', 'uri' => 'command/state', 'http' => 'GET'),
		array('class' => 'Commands', 'method' => 'getSteps', 'uri' => 'command/step', 'http' => 'GET'),
		array('class' => 'Commands', 'method' => 'getCommands', 'uri' => 'command', 'http' => 'GET'),
		array('class' => 'Tasks', 'method' => 'runCommand', 'uri' => 'command/(start|stop|restart|isolate|recover|promote|backup|restore)', 'http' => 'POST'),
		array('class' => 'Tasks', 'method' => 'getOneOrMoreTasks', 'uri' => 'task/[0-9]+', 'http' => 'GET'),
		array('class' => 'Tasks', 'method' => 'getOneOrMoreTasks', 'uri' => 'task', 'http' => 'GET'),
		array('class' => 'RunSQL', 'method' => 'runQuery', 'uri' => 'runsql', 'http' => 'GET'),
		array('class' => 'Monitors', 'method' => 'getMonitorClasses', 'uri' => 'monitorclass/.+', 'http' => 'GET'),
		array('class' => 'Monitors', 'method' => 'getMonitorClasses', 'uri' => 'monitorclass', 'http' => 'GET'),
		array('class' => 'Monitors', 'method' => 'updateMonitorClass', 'uri' => 'monitorclass/[0-9]+', 'http' => 'PUT'),
		array('class' => 'Monitors', 'method' => 'deleteMonitorClass', 'uri' => 'monitorclass/[0-9]+', 'http' => 'DELETE'),
		array('class' => 'Monitors', 'method' => 'createMonitorClass', 'uri' => 'monitorclass', 'http' => 'POST'),
		array('class' => 'Request', 'method' => 'listAPI', 'uri' => 'apilist', 'http' => 'GET'),
		
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
	protected $inifile = '/etc/scdsapi/api.ini';
	
	protected function __construct() {
		$this->timer = new aliroProfiler();
		if (!self::$uriTablePrepared) {
			foreach (self::$uriTable as &$uridata) {
				$parts = explode('/', trim($uridata['uri'],'/'));
				foreach ($parts as $part) $uridata['uriparts'][] = str_replace('*', self::$uriregex, $part);
				$this->apibase[$parts[0]] = 1;
			}
			$this->apibase = array_keys($this->apibase);
			self::$uriTablePrepared = true;
		}
        $this->config = parse_ini_file($this->inifile, true);
		$this->getHeaders();
		$this->uri = $this->getURI();
		$this->getSuffix();
		$this->handleAccept();
		if ('true' == $this->getParam($this->requestmethod, 'suppress_response_codes')) $this->suppress = true;
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
	
	public static function getInstance() {
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

	public function doControl () {
		$this->log(date('Y-m-d H:i:s')." $this->requestmethod request on /$this->uri\n".($this->suffix ? ' with suffix '.$this->suffix : ''));
		if ('api' != $this->uri) $this->checkSecurity();
		$uriparts = explode('/', $this->uri);
		$link = $this->getLinkByURI($uriparts);
		if ($link) {
			$class = __NAMESPACE__.'\\'.$link['class'];
			if (!class_exists($class)) {
				$this->sendErrorResponse("Request $this->uri no such class as $class", 404);
			}
			$object = 'Request' == $link['class'] ? $this : new $class($this);
			$method = $link['method'];
			if (!method_exists($object, $method)) {
				$this->sendErrorResponse("Request $this->uri no such method as $method in class $class", 404);
			}
			try {
				$object->$method($uriparts);
				$this->sendErrorResponse('Selected method $method of class $class returned to controller', 500);
			}
			catch (PDOException $pe) {
				$this->sendErrorResponse('Unexpected database error: '.$pe->getMessage(), 500, $pe);
			}
		}
		else $this->sendErrorResponse ("Request $this->uri with HTTP request $this->requestmethod does not match the API", 404);
	}
	
	protected function listAPI () {
		foreach (self::$uriTable as $entry) {
			$result[] = array (
				'http' => $entry['http'],
				'uri' => $entry['uri'],
				'method' => $entry['method']
			);
		}
		$this->sendResponse($result);
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

	public function getParam ($arrname, $name, $def=null, $mask=0) {
		$arr = $this->getArrayFromName($arrname);
		if (!is_array($arr)) return $def;
		if (strlen($this->requestmethod > 4 AND 'POST' == substr($this->requestmethod,0,4))) {
			if ($arrname == substr($this->requestmethod,4)) $arr =& $_POST;
			else return $def;
		}
		
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
	
	public function paramEmpty ($arrname, $name) {
		$arr = $this->getArrayFromName($arrname);
		return (is_array($arr)) ? !isset($arr[$name]) : true;
	}
	
	protected function getArrayFromName ($arrname) {
		if (is_array($arrname)) $arr =& $arrname;
		elseif ($this->requestviapost) $arr =& $_POST;
		elseif ('GET' == $arrname) $arr =& $_GET;
		elseif ('POST' == $arrname) $arr =& $_POST;
		elseif ('PUT' == $arrname) {
			if (!is_array($this->putdata)) return $this->putdata;
			$arr =& $this->putdata;
		}
		return $arr;
	}

	// Sends response to API request - data will be JSON encoded if content type is JSON
	public function sendResponse ($body='', $status=200) {
		if ($this->suppress) $body['httpcode'] = $status;
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
		}
		$recorder = ErrorRecorder::getInstance();
		$recorder->recordError('Sent error response: '.$status, md5(Diagnostics::trace()), implode("\r\n", (array) $errors), $exception);
		if ('application/json' == $this->accept) {
			$body['errors'] = (array) $errors;
			if ($this->suppress) $body['httpcode'] = $status;
			echo json_encode($body);
		}
		else echo $text;
		exit;
	}
	
	protected function sendHeaders ($status) {
		$this->log("Time to handle request {$this->timer->mark('seconds')}\n");
		$this->log("HTTP Response: $status\n");
		header(HTTP_PROTOCOL.' '.($this->suppress ? '200 OK' : $status.' '.(isset(self::$codes[$status]) ? self::$codes[$status] : '')));
		header('Content-type: '.$this->accept);
		header('Cache-Control: no-store');
	}
	
	public function log ($data) {
		if (is_writeable($this->config['logging']['directory'])) {
			//$phpuser = posix_getpwuid(posix_geteuid());
			//$phpusername = isset($phpuser['name']) ? '.'.$phpuser['name'] : '';
			$logfile = $this->config['logging']['directory']."/api.log";
			if (!file_exists($logfile) OR is_writeable($logfile)) {
				if (!is_string($data)) $data = serialize($data);
				file_put_contents($logfile, $data, FILE_APPEND);
			}
		}
	}
}
