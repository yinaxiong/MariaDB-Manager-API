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
 * The API class is the starting point, called by the very brief index.php which is the sole
 * entry point for the SCDS API.  Its constructor sets up some standard symbols.  It starts 
 * buffering of output, primarily to be able to control diagnostics, and sets up a simple 
 * autoloader.
 * 
 * The entry point from index.php is the startup method.  It enforces some security checks,
 * and aims to create a good seed for the PHP random number generator.  Standard definitions
 * are pulled in and a timer started for profiling.  A check on server load can be made,
 * and clients asked to back off if load is too high.  An instance of the main controller is
 * obtained and error handling set up.  Normally the controller is invoked, with the doControl
 * method.
 * 
 * The trace static method is a utility for debugging and error logging purposes.
 * 
 */

namespace SkySQL\SCDS\API;

use \PDOException;
use \Exception;
use SkySQL\COMMON\Profiler;
use SkySQL\COMMON\ErrorRecorder;

if (!function_exists('apache_request_headers')) require ('apache_request_headers.php');

class API {
	private static $instance = null;
	protected $timer = 0;
	protected $ipaddress = '';
	
	public static function getInstance () {
	    return (self::$instance instanceof self) ? self::$instance : (self::$instance = new self());
	}
	
	protected function __construct () {
		// Prevent diagnostic output leaking out
		ob_start();
		ob_implicit_flush(false);
		
		// Setting of defined symbols
		define('ABSOLUTE_PATH', str_replace('\\', '/', dirname(__FILE__)));
		if (!defined('CLASS_BASE')) define ('CLASS_BASE', ABSOLUTE_PATH);
		define('HTTP_PROTOCOL', isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP 1.1');
		
		// Set up a simple class autoloader
		spl_autoload_register(array(__CLASS__, 'simpleAutoload'));
	}
	
	public function startup ($runController=false, $controllerClass='Request') {

		$protects = array('_REQUEST', '_GET', '_POST', '_COOKIE', '_FILES', '_SERVER', '_ENV', 'GLOBALS', '_SESSION');

		foreach ($protects as $protect) {
			if ( in_array($protect , array_keys($_REQUEST)) ||
			in_array($protect , array_keys($_GET)) ||
			in_array($protect , array_keys($_POST)) ||
			in_array($protect , array_keys($_COOKIE)) ||
			in_array($protect , array_keys($_FILES))) {
				die('Invalid Request.');
			}
		}
		
		clearstatcache();
		
		$stat = @stat(__FILE__);
		if (empty($stat) OR !is_array($stat)) $stat = array(php_uname());
		mt_srand(crc32(microtime().implode('|', $stat)));

		require_once (CLASS_BASE.'/configs/definitions.php');

		//require_once (CLASS_BASE.'/bootstrap/objectcache.php');
		$this->timer = new Profiler();

		$max_load = defined('MAX_LOAD') ? (float) MAX_LOAD : 0;
		if (function_exists('sys_getloadavg')) {
			$load = sys_getloadavg();
			if ($max_load AND $load[0] > $max_load) {
				$retry = 60.0 * (mt_rand(75, 150)/100.0);
				header ('Retry-After: '.(int)$retry);
				header(HTTP_PROTOCOL.' 503 Too busy, try again later');
				die(HTTP_PROTOCOL.' 503 Server too busy. Please try again later.');
			}
		}

		try {
			$controller = call_user_func(array(__NAMESPACE__.'\\'.$controllerClass,'getInstance'));
			$errorhandler = ErrorRecorder::getInstance();
			set_error_handler(array($errorhandler, 'PHPerror'));
			register_shutdown_function(array($errorhandler, 'PHPFatalError'));
			if ($runController) $controller->doControl();
		}
		catch (Exception $e) {
			echo 'Unhandled error: '.$e->getMessage();
		}
		catch (PDOException $pe) {
			echo 'Unhandled error: '.$pe->getMessage();
		}
	}

	public function getElapsed () {
		return $this->timer->getElapsed();
	}

	public static function simpleAutoload ($classname) {
		$classname = str_replace('\\', '/', $classname);
		if (is_readable(CLASS_BASE.'/customclasses/'.$classname.'.php')) {
			require_once(CLASS_BASE.'/customclasses/'.$classname.'.php');
			return true;
		}
		if (is_readable(CLASS_BASE.'/classes/'.$classname.'.php')) {
			require_once(CLASS_BASE.'/classes/'.$classname.'.php');
			return true;
		}
		return false;
	}
	
	public function getIP () {
		if ($this->ipaddress) return $this->ipaddress;
	    $ip = false;
	    if (!empty($_SERVER['HTTP_CLIENT_IP'])) $ip = $_SERVER['HTTP_CLIENT_IP'];
	    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
	        $ips = explode (', ', $_SERVER['HTTP_X_FORWARDED_FOR']);
	        if ($ip != false) {
	            array_unshift($ips,$ip);
	            $ip = false;
	        }
	        $count = count($ips);
	        // Exclude IP addresses that are reserved for LANs
	        for ($i = 0; $i < $count; $i++) {
	            if (!preg_match("/^(10|172\.16|192\.168)\./i", $ips[$i])) {
	                $ip = $ips[$i];
	                break;
	            }
	        }
	    }
	    $this->ipaddress = (false == $ip AND isset($_SERVER['REMOTE_ADDR'])) ? $_SERVER['REMOTE_ADDR'] : $ip;
	    return $this->ipaddress;
	}

	public static function trace ($error=true) {
	    static $counter = 0;
		$html = '';
		foreach(debug_backtrace() as $back) {
		    if (isset($back['file']) AND $back['file']) {
			    $html .= '<br />'.$back['file'].':'.$back['line'];
			}
		}
		if ($error) $counter++;
		if (1000 < $counter) {
		    echo $html;
		    die (T_('Program killed - Probably looping'));
        }
		return $html;
	}
}