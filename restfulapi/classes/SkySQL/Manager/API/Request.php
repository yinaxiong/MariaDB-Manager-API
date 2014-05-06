<?php

/*
 ** Part of the MariaDB Manager API.
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
 * Copyright 2013-14 (c) SkySQL Corporation Ab
 * 
 * Author: Martin Brampton
 * Date started: June 2013
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

namespace SkySQL\Manager\API;

use \PDOException;
use SkySQL\COMMON\ErrorRecorder;
use SkySQL\COMMON\Diagnostics;
use SkySQL\COMMON\AdminDatabase;
use SkySQL\COMMON\MonitorDatabase;
use SkySQL\Manager\API\controllers\Metadata;

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

	// Neither crl (Creole for MariaDB KB) nor mml (Manula Markup Language) are
	//  real mime types, but they are used here for convenience in metadata production.
	protected static $suffixes = array(
		'html' => 'text/html',
		'json' => 'application/json',
		'mml' => 'application/mml',
		'crl' => 'application/crl',
		'txt' => 'text/plain'
	);
	
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
	protected $authcode = null;
	protected $errors = array();
	protected $runupgrade = false;
	
	protected function __construct() {
		$this->timer = new aliroProfiler();
		$this->micromarker = $this->timer->getMicroSeconds();
		$this->clientip = API::getIP();
        $this->config = $this->readAndCheckConfig();
		define ('_SKYSQL_API_CACHE_DIRECTORY', rtrim(@$this->config['cache']['directory'],'/').'/');
		define ('_SKYSQL_API_OBJECT_CACHE_TIME_LIMIT', $this->config['cache']['timelimit']);
		define ('_SKYSQL_API_OBJECT_CACHE_SIZE_LIMIT', $this->config['cache']['sizelimit']);
		$this->uri = $this->getURI();
		$this->getHeaders();
		$this->checkHeaders();
		$this->processRequestParameters();
		if (@$this->config['logging']['verbose']) {
			ini_set('display_errors', 1);
			error_reporting(-1);
		}
		$this->handleAccept();
		$suppressor = $this->getParam($this->requestmethod, 'suppress_response_codes');
		if (true === $suppressor OR 'true' == $suppressor) $this->suppress = true;
		$this->getQueryString();
	}
	
	abstract protected function processRequestParameters();
	
	protected function checkHeaders () {
		foreach ($this->headers as $name=>$value) {
			$stdname = str_replace(' ','-',ucwords(strtolower(str_replace(array('-','_'),' ',$name))));
			if ($name != $stdname) {
				unset($this->headers[$name]);
				$this->headers[$stdname] = $value;
			}
		}
		$this->requestversion = number_format((float)$this->getParam($this->headers, 'X-Skysql-Api-Version', _API_VERSION_NUMBER), 1, '.', '');
		if (!in_array($this->requestversion, explode(',', API::trimCommaSeparatedList(_API_LEGAL_VERSIONS)))) {
			$this->errors[] = sprintf("API Version of '%s' is not supported, legal values are '%s'", $this->requestversion, _API_LEGAL_VERSIONS);
		}
		$matches = array();  // Unnecessary, just to keep netbeans hints at bay
		if (preg_match('/api\-auth\-([0-9]+)\-([0-9a-z]{32,32})/', @$this->headers['Authorization'], $matches)) {
			if (isset($matches[1]) AND isset($matches[2])) {
				if (isset($this->config['apikeys'][$matches[1]])) {
					$this->apikeyid = $matches[1];
					$this->authcode = $matches[2];
				}
			}
		}
		if (!empty($this->headers['Content-Type'])) {
			switch (strtolower($this->headers['Content-Type'])) {
				case 'application/x-www-form-urlencoded': 
					$this->urlencoded = true;
					break;
				case 'application/json':
					$this->urlencoded = false;
					break;
				default:
					$this->errors[] = sprintf('Content-Type header of %s is not supported', $this->headers['Content-Type']);
			}
		}
		else $this->urlencoded = true;
		if (isset($this->headers['Accept-Charset'])) {
			$parts = explode(':', $this->headers['Accept-Charset'], 2);
			if (isset($parts[1])) {
				$charsets = array_map('trim', explode(',', strtolower($parts[1])));
				if (!in_array('utf-8', $charsets)) $this->errors[] = sprintf("Accept-Charset header '%s' does not include utf-8", $parts[1]);
			}
		}
	}
	
	protected function decodeJsonOrQueryString ($string) {
		$dejson = json_decode($string, true);
		$dequery = array();  // Unnecessary - just to keep Netbeans hints at bay
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
			$newdata = array();  // Unnecessary - avoids Netbeans hint
			$this->parse_str($querystring, $newdata);
			foreach ($newdata as $name=>$value) $data[$name] = $value;
		}
	}
	
	public function getHeader ($name) {
		return isset($this->headers[$name]) ? $this->headers[$name] : null;
	}
	
	public function compareVersion ($version, $operation) {
		return version_compare($this->requestversion, $version, $operation);
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
		$sepindex = explode('api.php', $sepquery[0]);
		$afterindex = trim(end($sepindex), '/');
		$sepapi = explode('/', $afterindex);
		$lastpartnumber = count($sepapi) - 1;
		$sepapi[$lastpartnumber] = $this->getSuffix($sepapi[$lastpartnumber]);
		$baseparts = RequestParser::getInstance()->getBaseParts();
		$dir[0] = $_SERVER['SERVER_NAME'];
		while (count($sepapi) AND !in_array($sepapi[0], $baseparts)) $dir[] = array_shift($sepapi);
		$this->baseurl = implode('/', $dir);
		return count($sepapi) ? implode('/',$sepapi) : $afterindex;
	}
	
	protected function getSuffix($lastpart) {
		foreach (array_keys(self::$suffixes) as $suffix) {
			$slen = strlen($suffix) + 1;
			if (substr($lastpart,-$slen) == '.'.$suffix) {
				$this->suffix = $suffix;
				return substr($lastpart,0,-$slen);
			}
		}
		return $lastpart;
	}
	
	protected function handleAccept () {
		if (isset(self::$suffixes[$this->suffix])) $this->accept = self::$suffixes[$this->suffix];
		else {
			$accepts = $this->getParam($this->requestmethod, '_accept', @$_SERVER['HTTP_ACCEPT']);
			$this->accept = stripos($accepts, 'application/json') !== false ? 'application/json' : (stripos($accepts, 'text/plain') !== false ? 'text/plain' : 'text/html');
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
		if (count($this->errors)) $this->sendErrorResponse($this->errors, 501);
		if ($this->runupgrade) {
			AdminDatabase::getInstance()->upgrade();
			MonitorDatabase::getInstance()->upgrade();
			$this->sendResponse('OK');
		}
		$uriparts = array_map('urldecode', explode('/', $this->uri));
		$parser = RequestParser::getInstance();
		// Method sendOptions sends answer, does not return to caller
		if ('OPTIONS' == $this->requestmethod) $parser->sendOptions($uriparts);
		$link = $parser->getLinkByURI($uriparts, $this->requestmethod);
		if ($link) {
			try {
				if ('metadata' != $uriparts[0] AND 'userdata' != $uriparts[0] AND 'apidate' != $uriparts[0]) $this->checkSecurity();
				$this->log(LOG_INFO, "$this->requestmethod /$this->uri".($this->suffix ? '.'.$this->suffix : ''));
				if (@$this->config['logging']['verbose']) {
					if (count($_POST)) $this->log(LOG_DEBUG, print_r($_POST,true));
					if (count($_GET)) $this->log(LOG_DEBUG, print_r($_GET,true));
					if (!empty($this->putdata)) $this->log(LOG_DEBUG, print_r($this->putdata,true));
				}
				if ('Request' == $link['class']) {
					$object = $this;
					$class = get_class();
				}
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
	
	protected function getConfigField ($uriparts) {
		if (isset($uriparts[2])) {
			$this->sendResponse(array($uriparts[2] => (empty($this->config[$uriparts[1]][$uriparts[2]]) ? null : $this->config[$uriparts[1]][$uriparts[2]])));
		}
		$this->sendResponse(array($uriparts[1] => (empty($this->config[$uriparts[1]]) OR is_array($this->config[$uriparts[1]])) ? null : $this->config[$uriparts[1]]));
	}
	
	protected function checkSecurity () {
		$headertime = isset($this->headers['Date']) ? strtotime($this->headers['Date']) : 0;
		if ($headertime > time()+300 OR $headertime < time()-900) {
			$this->log(LOG_ERR, 'Auth error - Header time: '.($headertime ? $headertime : '*zero*').' actual time: '.time());
			$this->sendErrorResponse('Date header out of range '.(empty($this->headers['Date']) ? '*empty*' : $this->headers['Date']).', current '.date('r'), 401);
		}
		if (!is_null($this->apikeyid)) {
			$this->apikey = $this->config['apikeys'][$this->apikeyid];
			$checkstring = \md5($this->uri.$this->apikey.$this->headers['Date']);
			if ($this->authcode == $checkstring) return;
		}
		$this->log(LOG_ERR, 'Auth error - Header authorization: '.@$this->headers['Authorization'].' calculated auth: '.@$checkstring.' Based on URI: '.$this->uri.' key: '.@$this->config['apikeys'][$this->apikeyid].' Date: '.$this->headers['Date']);
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
			array('fields','fieldselect','limit','offset','suppress_response_codes','querystring','_method', '_accept', 'uri1', 'uri2', 'uri3', 'uri4'));
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
	            if (!is_numeric($result) AND !$this->isVersion($result)) {
	            	if (get_magic_quotes_gpc() AND !($mask & _MOS_NOSTRIP)) $result = stripslashes($result);
					if (!(bool) preg_match('//u', $result)) $this->sendErrorResponse('Request contained one or more characters that are not UTF-8', 501);
	                if (!($mask&_MOS_ALLOWRAW) AND is_numeric($def)) $result = $def;
	            }
	        }
	    }
	    return isset($result) ? $result : $def;
	}
	
	protected function isVersion ($field) {
		return preg_match('/^[0-9]+(\.[0-9]+)*$/', $field);
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
		if ('text/plain' != $this->accept AND !function_exists('json_encode')) $this->sendErrorResponse("The API is unable to function because PHP JSON functions are not available", 500);
		if ('text/plain' == $this->accept) $body = $this->plainTextResponse($body);
		$this->sendHeaders($status, $requestURI);
		if ('HEAD' != $this->requestmethod) {
			if ('text/plain' == $this->accept) echo $body;
			else $this->addResponseInformationAndSend(is_array($body) ? $body : array('result' => $body), $status);
		}
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
		if (@$this->config['debug']['showheaders']) $body['responseheaders'] = $this->responseheaders;
		if (@$this->config['debug']['reflectheaders']) $body['requestheaders'] = $this->headers;
		if ($this->suppress OR @$this->config['debug']['showhttpcode'] OR 'text/html' == $this->accept) {
			$body['httpcode'] = 'text/html' == $this->accept ? $status.' '.@self::$codes[$status] : $status;
		}
		$charset = $this->getHeader('Accept-Charset');
		// Only PHP 5.4 allows JSON_UNESCAPED_UNICODE which would allow escaping to be avoided if client accepts UTF-8
		if (function_exists('json_encode')) {
			$output = ($charset AND false === strpos($charset,'*') AND false === stripos($charset,'utf-8')) ? json_encode($body) : json_encode($body);
		}
		else $output = '{"errors":["The API is unable to function because PHP JSON functions are not available"]}';
		echo 'application/json' == $this->accept ? $output : $this->prettyPage(nl2br(str_replace("\t", "&nbsp;&nbsp;&nbsp;&nbsp;", $this->prettyPrint($output))));
	}
	
	protected function plainTextResponse ($body) {
		$fieldselect = explode('~', $this->getParam($this->requestmethod, 'fieldselect'));
		while (count($fieldselect) AND (is_object($body) OR is_array($body))) {
			if ('' !== ($selector = array_shift($fieldselect))) {
				$bodarray = (array) $body;
				if (isset($bodarray[$selector])) $body = $bodarray[$selector];
				else $this->sendErrorResponse("The fieldselect parameter does not match the structure of the response", 400);
			}
		}
		return (is_object($body) OR is_array($body)) ? $this->nestedImplode($body) : $body;
	}
	
	protected function nestedImplode ($arrayorobject) {
		$array = is_object($arrayorobject) ? get_object_vars($arrayorobject) : $arrayorobject;
		foreach ($array as $name=>$value) {
			if (!is_null($value) AND !is_scalar($value)) $array[$name] = $this->nestedImplode($value);
		}
		return $this->isAssociative($array) ? $this->collapseAssociativeArray($array) : implode(',', $array);
	}
	
	protected function isAssociative ($array) {
		$difference = array_diff(range(0, count($array) - 1), array_keys($array));
		return empty($array) ? false : !empty($difference);
	}
	
	protected function collapseAssociativeArray ($array) {
		array_walk($array, array($this, 'keyValueWithColonBetween'));
		return '('.implode(',', array_values($array)).')';
	}
	
	protected function keyValueWithColonBetween (&$value, $key) {
		$value = "$key:$value";
	}
	
	public function sendErrorResponse ($errors, $status, $exception=null) {
		$this->sendHeaders($status);
		if ('HEAD' != $this->requestmethod) {
			if ('text/plain' == $this->accept) echo implode(',', (array) $errors);
			else $this->addResponseInformationAndSend(array('errors' => (array) $errors), $status);
		}

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
		$prefix .= is_null($this->apikeyid) ? "[unknown] " : "[$this->apikeyid] ";
		$prefix .= "[$this->micromarker] ";
		syslog($severity, $prefix.$message);
	}
	
	public function logTime ($text='') {
		$this->log(LOG_INFO, "Time taken $text {$this->timer->mark('seconds')}");
	}
}
