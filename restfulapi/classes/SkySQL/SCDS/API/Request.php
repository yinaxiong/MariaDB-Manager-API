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

final class Request {
	protected static $instance = null;
	
	// Longer URI patterns must precede similar shorter ones for correct functioning
	protected static $uriTable = array(
		array('class' => 'Systems', 'method' => 'getSystemProperty', 'uri' => 'system/[0-9]+/property/[A-Za-z0-9]+', 'http' => 'GET'),
		array('class' => 'Systems', 'method' => 'setSystemProperty', 'uri' => 'system/[0-9]+/property/[A-Za-z0-9]+', 'http' => 'PUT'),
		array('class' => 'Systems', 'method' => 'deleteSystemProperty', 'uri' => 'system/[0-9]+/property/[A-Za-z0-9]+', 'http' => 'DELETE'),
		array('class' => 'SystemBackups', 'method' => 'getSystemBackups', 'uri' => 'system/[0-9]+/backup', 'http' => 'GET'),
		array('class' => 'SystemBackups', 'method' => 'makeSystemBackup', 'uri' => 'system/[0-9]+/backup', 'http' => 'POST'),
		array('class' => 'SystemBackups', 'method' => 'getBackupStates', 'uri' => '/backupstate', 'http' => 'GET'),
		array('class' => 'SystemNodes', 'method' => 'getSystemMonitor', 'uri' => 'system/[0-9]+/node/[0-9]+/monitor/[0-9]+', 'http' => 'GET'),
		array('class' => 'SystemNodes', 'method' => 'getSystemNode', 'uri' => 'system/[0-9]+/node/[0-9]+', 'http' => 'GET'),
		array('class' => 'SystemUsers', 'method' => 'createSystemUser', 'uri' => 'user/.*', 'http' => 'PUT'),
		array('class' => 'SystemUsers', 'method' => 'deleteSystemUser', 'uri' => 'user/.*', 'http' => 'DELETE'),
		array('class' => 'SystemUsers', 'method' => 'loginSystemUser', 'uri' => 'user/.*', 'http' => 'POST'),
		array('class' => 'SystemUsers', 'method' => 'getSystemUsers', 'uri' => 'user', 'http' => 'GET'),
		array('class' => 'Systems', 'method' => 'getSystemData', 'uri' => 'system/[0-9]+', 'http' => 'GET'),
		array('class' => 'Systems', 'method' => 'getAllData', 'uri' => 'system', 'http' => 'GET'),
		array('class' => 'Buckets', 'method' => 'getData', 'uri' => 'bucket', 'http' => 'GET'),
		array('class' => 'Commands', 'method' => 'getStates', 'uri' => 'command/state', 'http' => 'GET'),
		array('class' => 'Commands', 'method' => 'getSteps', 'uri' => 'command/step', 'http' => 'GET'),
		array('class' => 'Commands', 'method' => 'getCommands', 'uri' => 'command', 'http' => 'GET'),
		array('class' => 'RunSQL', 'method' => 'runQuery', 'uri' => 'runsql', 'http' => 'GET'),
		
	);
	
	protected static $apikeys = array(
		'1' => '1f8d9e040e65d7b105538b1ed0231770'
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
	
	protected $uri = '';
	protected $headers = array();
	
	protected function __construct() {
		if (!self::$uriTablePrepared) {
			foreach (self::$uriTable as &$uridata) {
				$parts = explode('/', trim($uridata['uri'],'/'));
				foreach ($parts as $part) $uridata['uriparts'][] = str_replace('*', self::$uriregex, $part);
			}
			self::$uriTablePrepared = true;
		}
		$sepquery = explode('?', $_SERVER['REQUEST_URI']);
		$sepindex = explode('index.php', $sepquery[0]);
		$sepapi = explode('/api/', trim(end($sepindex), '/'));
		$this->uri = trim(end($sepapi), '/');
		$this->headers = apache_request_headers();
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
			$object = new $class();
			$method = $link['method'];
			$object->$method($uriparts);
			$this->sendErrorResponse('Selected method $method of class $class returned to controller', 500);
		}
		else $this->sendErrorResponse ("Request {$_SERVER['REQUEST_URI']} does not match the API", 404);
	}
	
	protected function checkSecurity () {
		$rfcdate = @$this->headers['Date'];
		$headertime = strtotime($rfcdate);
		if ($headertime > time()+300 OR $headertime < time()-900) {
			$this->log('Header time: '.$headertime.' actual time: '.time());
			$this->sendErrorResponse('Date header out of range', 401);
		}
		$matches = array();
		if (preg_match('/api\-auth\-([0-9]+)\-([0-9a-z]{32,32})/', trim($this->headers['Authorization']), $matches)) {
			$checkstring = \md5($this->uri.self::$apikeys[$matches[1]].$rfcdate);
			if (isset(self::$apikeys[@$matches[1]]) AND @$matches[2] == $checkstring) return;
		}
		$this->log('Header authorization: '.$this->headers['Authorization'].' calculated auth: '.@$checkstring.' Based on URI: '.$this->uri.' key: '.@self::$apikeys[@$matches[1]].' Date: '.$rfcdate);
		$this->sendErrorResponse('Invalid Authorization header', 401);
	}
	
	protected function getLinkByURI ($uriparts) {
		$http = $_SERVER['REQUEST_METHOD'];
		$partcount = count($uriparts);
		foreach (self::$uriTable as $link) {
			if ($http != $link['http']) continue;
			if (count($link['uriparts']) != $partcount) continue;
			$matched = true;
			foreach ($link['uriparts'] as $i=>$part) {
				if (!($part == $uriparts[$i]) AND !$this->uriMatch($part, $uriparts[$i])) {
					$matched = false;
					break;
				}
			}
			if ($matched AND method_exists(__NAMESPACE__.'\\'.$link['class'], $link['method'])) return $link;
		}
		return false;
	}

	protected function uriMatch ($pattern, $actual) {
		return @preg_match("/^$pattern$/", $actual);
	}
	
	// Sends response to API request - data will be JSON encoded if content type is JSON
	public function sendResponse ($body='', $status=200) {
	    $status_header = HTTP_PROTOCOL.' '.$status.' '.(isset(self::$codes[$status]) ? self::$codes[$status] : '');
	    header($status_header);
		$content_type = $this->getContentType();
	    header('Content-type: '.$content_type);
		header('Cache-Control: no-store');
		if (substr($status,0,1) != 2 AND $status != 304) {
			if (empty($body)) $body = $status_header;
			if ('application/json' == $content_type) {
				$body = array('result' => $body, 'httpcode' => $status);
				echo json_encode($body);
				exit;
			}
		}
		echo 'application/json' == $content_type ? json_encode($body) : print_r($body);
		exit;
	}
	
	public function sendErrorResponse ($errors, $status) {
		$data = empty($errors) ? '' : array('errors' => (array) $errors);
		if ($data AND 'text/html' == $this->getContentType()) {
			$statusname = @self::$codes[$status];
			$text = "<p>Error(s) accompanying return code $status $statusname:";
			foreach ($data['errors'] as $error) $text .= '</br>'.$error;
			$data = $text.'</p>';
		}
		$this->sendResponse($data, $status);
	}
	
	protected function getContentType () {
		return strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false ? 'application/json' : 'text/html';
	}

	public function log ($data) {
		if (!is_string($data)) $data = serialize($data);
		file_put_contents(API_LOG_DIRECTORY.'/api.log', $data, FILE_APPEND);
	}
}
