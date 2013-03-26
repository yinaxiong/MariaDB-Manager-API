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
 * The Request class is the main controller for the API.
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

final class Request {
	protected static $instance = null;
	
	// Longer URI patterns must precede similar shorter ones for correct functioning
	protected static $uriTable = array(
		array('class' => 'Systems', 'method' => 'getSystemProperty', 'uri' => 'system/[0-9]+/property/[A-Za-z0-9]+', 'http' => 'GET'),
		array('class' => 'Systems', 'method' => 'setSystemProperty', 'uri' => 'system/[0-9]+/property/[A-Za-z0-9]+', 'http' => 'PUT'),
		array('class' => 'Systems', 'method' => 'deleteSystemProperty', 'uri' => 'system/[0-9]+/property/[A-Za-z0-9]+', 'http' => 'DELETE'),
		array('class' => 'SystemBackups', 'method' => 'updateSystemBackup', 'uri' => 'system/[0-9]+/backup/[0-9]+', 'http' => 'PUT'),
		array('class' => 'SystemBackups', 'method' => 'getSystemBackups', 'uri' => 'system/[0-9]+/backup', 'http' => 'GET'),
		array('class' => 'SystemBackups', 'method' => 'makeSystemBackup', 'uri' => 'system/[0-9]+/backup', 'http' => 'POST'),
		array('class' => 'SystemBackups', 'method' => 'getBackupStates', 'uri' => '/backupstate', 'http' => 'GET'),
		array('class' => 'Monitors', 'method' => 'monitorData', 'uri' => 'system/[0-9]+/node/[0-9]+/monitor/[0-9]+/data', 'http' => 'GET'),
		array('class' => 'Monitors', 'method' => 'monitorData', 'uri' => 'system/[0-9]+/monitor/[0-9]+/data', 'http' => 'GET'),
		array('class' => 'Monitors', 'method' => 'storeMonitorData', 'uri' => 'system/[0-9]+/node/[0-9]+/monitor/[0-9]+/data', 'http' => 'POST'),
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
		array('class' => 'Tasks', 'method' => 'runCommand', 'uri' => 'command/(start|stop|restart|isolate|recover|promote)', 'http' => 'POST'),
		array('class' => 'Tasks', 'method' => 'getOneTask', 'uri' => 'task/[0-9]+', 'http' => 'GET'),
		array('class' => 'Tasks', 'method' => 'getTasks', 'uri' => 'task', 'http' => 'GET'),
		array('class' => 'RunSQL', 'method' => 'runQuery', 'uri' => 'runsql', 'http' => 'GET'),
		array('class' => 'Monitors', 'method' => 'getMonitorClasses', 'uri' => 'monitorclass/.+', 'http' => 'GET'),
		array('class' => 'Monitors', 'method' => 'getMonitorClasses', 'uri' => 'monitorclass', 'http' => 'GET'),
		array('class' => 'Monitors', 'method' => 'updateMonitorClass', 'uri' => 'monitorclass/[0-9]+', 'http' => 'PUT'),
		array('class' => 'Monitors', 'method' => 'deleteMonitorClass', 'uri' => 'monitorclass/[0-9]+', 'http' => 'DELETE'),
		array('class' => 'Monitors', 'method' => 'createMonitorClass', 'uri' => 'monitorclass', 'http' => 'PUT'),
		
	);
	
	protected static $uriTablePrepared = false;
	protected static $uriregex = '([0-9a-zA-Z_\-\.\~\*\(\)]*)';
	
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

	protected $config = array();
	protected $uri = '';
	protected $headers = array();
	protected $requestmethod = '';
	protected $requestviapost = false;
	protected $putdata = '';
	protected $rfcdate = '';
	protected $authorization = '';
	protected $accept = '';
	protected $suffix = '';
	
	protected function __construct() {
		if (!self::$uriTablePrepared) {
			foreach (self::$uriTable as &$uridata) {
				$parts = explode('/', trim($uridata['uri'],'/'));
				foreach ($parts as $part) $uridata['uriparts'][] = str_replace('*', self::$uriregex, $part);
			}
			self::$uriTablePrepared = true;
		}
        $this->config = parse_ini_file('/etc/scdsapi/api.ini', true);
		$sepquery = explode('?', $_SERVER['REQUEST_URI']);
		$sepindex = explode('index.php', $sepquery[0]);
		$sepapi = explode('/api/', trim(end($sepindex), '/'));
		$this->uri = trim(end($sepapi), '/');
		$periodpos = strrpos($this->uri, '.');
		if (false !== $periodpos) {
			$suffixpos = strlen($this->uri) - $periodpos;
			if (0 < $suffixpos AND 6 > $suffixpos) {
				if (1 < $suffixpos) $this->suffix = substr($this->uri,-($suffixpos-1));
				$this->uri = substr($this->uri,0,-$suffixpos);
			}
		}
		$this->headers = apache_request_headers();
		if ('PUT' == $_SERVER['REQUEST_METHOD']) {
			$rawput = file_get_contents("php://input");
			$this->putdata = json_decode($rawput, true);
			if (is_null($this->putdata)) $this->putdata = $rawput;
		}
		if ('POST' == $_SERVER['REQUEST_METHOD'] AND isset($_POST['_method'])) {
			$this->requestviapost = true;
			$this->requestmethod = $_POST['_method'];
		}
		else $this->requestmethod = $_SERVER['REQUEST_METHOD'];
		$this->rfcdate = $this->getParam($this->requestmethod, '_rfcdate', @$this->headers['Date']);
		$this->authorization = $this->getParam($this->requestmethod, '_authorization', @$this->headers['Authorization']);
		if ('json' == $this->suffix) $this->accept = 'application/json';
		else {
			$accepts = $this->getParam($this->requestmethod, '_accept', @$_SERVER['HTTP_ACCEPT']);
			$this->accept = stripos($accepts, 'application/json') !== false ? 'application/json' : 'text/html';
		}
	}
	
	public static function getInstance() {
		return self::$instance instanceof self ? self::$instance : self::$instance = new self();
	}

	public function doControl () {
		$this->checkSecurity();
		$uriparts = explode('/', $this->uri);
		$link = $this->getLinkByURI($uriparts);
		if ($link) {
			$class = __NAMESPACE__.'\\'.$link['class'];
			if (!class_exists($class)) {
				$this->sendErrorResponse("Request {$_SERVER['REQUEST_URI']} no such class as $class", 404);
			}
			$object = new $class($this);
			$method = $link['method'];
			if (!method_exists($object, $method)) {
				$this->sendErrorResponse("Request {$_SERVER['REQUEST_URI']} no such method as $method in class $class", 404);
			}
			try {
				$object->$method($uriparts);
				$this->sendErrorResponse('Selected method $method of class $class returned to controller', 500);
			}
			catch (PDOException $pe) {
				$this->sendErrorResponse('Unexpected database error: '.$pe->getMessage(), 500, $pe);
			}
		}
		else $this->sendErrorResponse ("Request {$_SERVER['REQUEST_URI']} with HTTP request $this->requestmethod does not match the API", 404);
	}
	
	protected function checkSecurity () {
		$headertime = strtotime($this->rfcdate);
		if ($headertime > time()+300 OR $headertime < time()-900) {
			$this->log('Header time: '.$headertime.' actual time: '.time());
			$this->sendErrorResponse('Date header out of range '.$this->rfcdate.', current '.date('r'), 401);
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
		if ($arrname != $this->requestmethod) return $def;
		if ($this->requestviapost) $arr =& $_POST;
		elseif ('GET' == $arrname) $arr =& $_GET;
		elseif ('POST' == $arrname) $arr =& $_POST;
		elseif ('PUT' == $arrname) {
			if (!is_array($this->putdata)) return $this->putdata;
			$arr =& $this->putdata;
		}
		if (strlen($this->requestmethod > 4 AND 'POST' == substr($this->requestmethod,0,4))) {
			if ($arrname == substr($this->requestmethod,4)) $arr =& $_POST;
			else return $def;
		}
		
		$result = $def;
	    if (isset($arr[$name])) {
	        if (is_array($arr[$name])) foreach ($arr[$name] as $key=>$element) {
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
	    return $result;
	}

	// Sends response to API request - data will be JSON encoded if content type is JSON
	public function sendResponse ($body='', $status=200) {
		$status_header = $this->sendHeaders($status);
		if (empty($body)) $body = $status_header;
		if ('application/json' == $this->accept) {
			if (!is_array($body) OR 0 != count($body)) $body = array('result' => $body, 'httpcode' => $status);
			echo json_encode($body);
			exit;
		}
		echo print_r($body, true);
		exit;
	}
	
	public function sendErrorResponse ($errors, $status, $exception=null) {
		$status_header = $this->sendHeaders($status);
		if (empty($errors)) $errors = $status_header;
		if ($errors AND 'text/html' == $this->accept) {
			$statusname = @self::$codes[$status];
			$text = "<p>Error(s) accompanying return code $status $statusname:";
			foreach ((array) $errors as $error) $text .= '</br>'.$error;
			$text .= '</p>';
		}
		//$data = empty($errors) ? '' : array('errors' => (array) $errors);
		$recorder = ErrorRecorder::getInstance();
		$recorder->recordError('Sent error response: '.$status, md5(Diagnostics::trace()), implode("\r\n", (array) $errors), $exception);
		if ('application/json' == $this->accept) {
			echo json_encode(array('errors' => (array) $errors, 'httpcode' => $status));
			exit;
		}
		echo print_r($text, true);
		exit;
	}
	
	protected function sendHeaders ($status) {
		$status_header = HTTP_PROTOCOL.' '.$status.' '.(isset(self::$codes[$status]) ? self::$codes[$status] : '');
		header($status_header);
		header('Content-type: '.$this->accept);
		header('Cache-Control: no-store');
		return $status_header;
	}
	
	public function log ($data) {
		if (!is_string($data)) $data = serialize($data);
		file_put_contents($this->config['logging']['directory'].'/api.log', $data, FILE_APPEND);
	}
}
