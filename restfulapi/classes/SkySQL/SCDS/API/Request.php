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
	
	protected static $suffixes = array('html', 'json', 'mml', 'crl');
	
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
	protected $headers = array();
	protected $responseheaders = array();
	protected $requestmethod = '';
	protected $requestviapost = false;
	protected $putdata = array();
	protected $accept = '';
	protected $suffix = '';
	protected $suppress = false;
	protected $clientip = '';
	protected $apikey = '';
	protected $apikeyid = null;
	protected $requestversion = '1.0';
	protected $urlencoded = true;
	protected $baseurl = '';
	
	protected function __construct() {
		$this->timer = new aliroProfiler();
		$this->micromarker = $this->timer->getMicroSeconds();
		$this->clientip = API::getIP();
        $this->config = $this->readAndCheckConfig();
		if ('yes' == @$this->config['logging']['verbose']) {
			ini_set('display_errors', 1);
			error_reporting(-1);
		}
		define ('_SKYSQL_API_CACHE_DIRECTORY', rtrim(@$this->config['cache']['directory'],'/').'/');
		define ('_SKYSQL_API_OBJECT_CACHE_TIME_LIMIT', $this->config['cache']['timelimit']);
		define ('_SKYSQL_API_OBJECT_CACHE_SIZE_LIMIT', $this->config['cache']['sizelimit']);
		$this->uri = $this->getURI();
		$this->getSuffix();
		$this->handleAccept();
		$suppressor = $this->getParam($this->requestmethod, 'suppress_response_codes');
		if (true === $suppressor OR 'true' == $suppressor) $this->suppress = true;
		$this->getQueryString();
	}
	
	protected function checkHeaders () {
		$this->requestversion = number_format((float)$this->getParam($this->headers, 'X-Skysql-Api-Version', '1.0'), 1, '.', '');
		if (isset($this->headers['Content-Type'])) {
			switch (strtolower($this->headers['Content-Type'])) {
				case 'application/x-www-form-urlencoded': 
					$this->urlencoded = true;
					break;
				case 'application/json':
					$this->urlencoded = false;
					break;
				default:
					$this->sendErrorResponse(sprintf('Content-Type header of %s is not supported', $this->headers['Content-Type']), 501);
			}
		}
		else $this->urlencoded = true;
		if (isset($this->headers['Accept-Charset'])) {
			$parts = explode(':', $this->headers['Accept-Charset'], 2);
			if (isset($parts[1])) {
				$charsets = array_map('trim', explode(',', strtolower($parts[1])));
				if (!in_array('utf-8', $charsets)) $this->sendErrorResponse (sprintf("Accept-Charset header '%s' does not include utf-8", $parts[1]), 501);
			}
		}
	}
	
	protected function decodeJsonOrQueryString ($string) {
		$dejson = json_decode($string, true);
		if ($this->urlencoded) parse_str($string, $dequery);
		else $this->parse_str($string, $dequery);
		return (is_null($dejson) OR (is_array($dequery) AND count($dejson) < count($dequery))) ? $dequery : $dejson;
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
	
	public function getVersion () {
		return $this->requestversion;
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
		$baseparts = RequestParser::getInstance()->getBaseParts();
		$dir[0] = $_SERVER['SERVER_NAME'];
		while (count($sepapi) AND !in_array($sepapi[0], $baseparts)) $dir[] = array_shift($sepapi);
		$this->baseurl = implode('/', $dir);
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
		elseif ('mml' ==  $this->suffix) $this->accept = 'application/mml';
		elseif ('crl' ==  $this->suffix) $this->accept = 'application/crl';
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
		$uriparts = array_map('urldecode', explode('/', $this->uri));
		$parser = RequestParser::getInstance();
		// Method sendOptions sends answer, does not return to caller
		if ('OPTIONS' == $this->requestmethod) $parser->sendOptions($uriparts);
		$link = $parser->getLinkByURI($uriparts, $this->requestmethod);
		if ($link) {
			try {
				if ('metadata' != $uriparts[0] AND 'userdata' != $uriparts[0] AND 'apidate' != $uriparts[0]) $this->checkSecurity();
				$this->log(LOG_INFO, "$this->requestmethod /$this->uri".($this->suffix ? '.'.$this->suffix : ''));
				if ('yes' == @$this->config['logging']['verbose']) {
					if (count($_POST)) $this->log(LOG_DEBUG, print_r($_POST,true));
					if (count($_GET)) $this->log(LOG_DEBUG, print_r($_GET,true));
					if (!empty($this->putdata)) $this->log(LOG_DEBUG, print_r($this->putdata,true));
				}
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
		else {
			$this->log(LOG_INFO, "$this->requestmethod /$this->uri".($this->suffix ? '.'.$this->suffix : '').' does not match the API');
			$this->sendErrorResponse ("Request $this->uri with HTTP request $this->requestmethod does not match the API", 404);
		}
	}
	
	protected function listAPI () {
		$controller = new Metadata($this);
		$prototypes = new RequestPrototypes();
		$controller->listAPI($prototypes->getUriTable(), $prototypes->getFieldRegex());
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
					$this->apikeyid = $matches[1];
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
					if (!(bool) preg_match('//u', $result)) $this->sendErrorResponse('Request contained one or more characters that are not UTF-8', 501);
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
	
	public function unsetParam ($arrname, $name) {
		$arr = &$this->getArrayFromName($arrname);
		if (is_array($arr) AND isset($arr[$name])) unset($arr[$name]);
	}

	protected function &getArrayFromName ($arrname) {
		if (is_array($arrname)) $arr =& $arrname;
		elseif ($this->requestviapost) $arr =& $_POST;
		elseif ('GET' == $arrname) $arr =& $_GET;
		elseif ('HEAD' == $arrname) $arr =& $_GET;
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
	public function sendResponse ($body='', $status=200, $requestURI='') {
		$this->sendHeaders($status, $requestURI);
		if ('HEAD' != $this->requestmethod) $this->addResponseInformationAndSend(is_array($body) ? $body : array('result' => $body), $status);
		exit;
	}
	
	protected function addResponseInformationAndSend ($body, $status=200) {
		$diagnostics = preg_replace('/[^(\x20-\x7F)]*/','', ob_get_clean());
		if ($diagnostics) $body['diagnostics'] = explode("\n", preg_replace("=<br */?>=i", "\n", $diagnostics));
		foreach ((array) @$body['diagnostics'] as $sub=>$value) if (empty($value)) unset ($body['diagnostics'][$sub]);
		if (count($this->warnings)) {
			$body['warnings'] = (array) $this->warnings;
			foreach ((array) $this->warnings as $warning) $this->log(LOG_WARNING, $warning);
		}
		if ('yes' == @$this->config['debug']['showheaders']) $body['responseheaders'] = $this->responseheaders;
		if ('yes' == @$this->config['debug']['reflectheaders']) $body['requestheaders'] = $this->headers;
		if ($this->suppress OR 'yes' == @$this->config['debug']['showhttpcode'] OR 'text/html' == $this->accept) {
			$body['httpcode'] = 'text/html' == $this->accept ? $status.' '.@self::$codes[$status] : $status;
		}
		$charset = $this->getHeader('Accept-Charset');
		$output = ($charset AND false === strpos($charset,'*') AND false === stripos($charset,'utf-8')) ? json_encode($body) : json_encode($body);
		echo 'application/json' == $this->accept ? $output : $this->prettyPage(nl2br(str_replace("\t", "&nbsp;&nbsp;&nbsp;&nbsp;", $this->prettyPrint($output))));
	}
	
	public function sendErrorResponse ($errors, $status, $exception=null) {
		$this->sendHeaders($status);
		if ('HEAD' != $this->requestmethod) $this->addResponseInformationAndSend(array('errors' => (array) $errors), $status);

		// Record errors in the log and in the ErrorLog table
		foreach ((array) $errors as $error) $this->log(LOG_ERR, $error);
		$recorder = ErrorRecorder::getInstance();
		$recorder->recordError('Sent error response: '.$status, md5(Diagnostics::trace()), implode("\r\n", (array) $errors), $exception);
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

	protected function sendHeaders ($status, $requestURI='') {
		$report = $status.' '.(isset(self::$codes[$status]) ? self::$codes[$status] : '');
		$this->log(LOG_INFO, "$this->requestmethod /$this->uri completed $report - time taken {$this->timer->mark('seconds')}");
		$this->addResponseHeader('X-SkySQL-API-Version: '._API_VERSION_NUMBER);
		$this->addResponseHeader(HTTP_PROTOCOL.' '.(('HEAD' != $this->requestmethod AND $this->suppress) ? '200 OK' : $report));
		if ('HEAD' != $this->requestmethod) {
			$this->addResponseHeader('Content-type: '.$this->accept.'; charset=utf-8');
			$this->addResponseHeader('Cache-Control: no-store');
			if (201 == $status AND $requestURI) {
				$scheme = isset($_SERVER['HTTP_SCHEME']) ? $_SERVER['HTTP_SCHEME'] : ((isset($_SERVER['HTTPS']) AND strtolower($_SERVER['HTTPS'] != 'off')) ? 'https' : 'http');
				$this->addResponseHeader("Location: $scheme://$this->baseurl/$requestURI");
			}
		}
		foreach ($this->responseheaders as $header) header($header);
	}
	
	public function addResponseHeader ($header) {
		$this->responseheaders[] = $header;
	}
	
	public function log ($severity, $message) {
		$prefix = "[$this->clientip] ";
		$prefix .= is_null($this->apikeyid) ? "[null] " : "[$this->apikeyid] ";
		$prefix .= "[$this->micromarker] ";
		syslog($severity, $prefix.$message);
	}
}
