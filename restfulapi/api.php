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
 * The API class is the starting point, called by the very brief index.php which is the sole
 * entry point for the SkySQL Manager API.  Its constructor sets up some standard symbols.  It starts 
 * buffering of output, primarily to be able to control diagnostics, and sets up a simple 
 * autoloader.
 * 
 * The entry point from index.php is the startup method.  It enforces some security checks,
 * and aims to create a good seed for the PHP random number generator.  Standard definitions
 * are pulled in.  A check on server load can be made, and clients asked to back off if load
 * is too high.  An instance of the main controller is obtained and error handling set up.  
 * Normally the controller is invoked, with the doControl method.
 * 
 * The trace static method is a utility for debugging and error logging purposes.
 * 
 */

namespace SkySQL\SCDS\API;

use PDOException;
use Exception;
use SkySQL\COMMON\ErrorRecorder;

define ('_API_VERSION_NUMBER','0.7');
define ('_API_INI_FILE_LOCATION', '/etc/skysqlmgr/api.ini');
define ('_API_BASE_FILE', __FILE__);

if (!function_exists('apache_request_headers')) require ('includes/apache_request_headers.php');

// Translation function - yet to be implemented
function T_ ($string) {
	return $string;
}

class API {
	private static $instance = null;
	protected static $ipaddress = '';
	
	/*
	public static $backupstates = array(
		'scheduled' => array('description' => 'Scheduled', 'icon' => 'scheduled'),
		'running' => array('description' => 'Running', 'icon' => 'running'),
		'paused' => array('description' => 'Paused', 'icon' => 'paused'),
		'stopped' => array('description' => 'Stopped', 'icon' => 'stopped'),
		'done' => array('description' => 'Done', 'icon' => 'done'),
		'error' => array('description' => 'Error', 'icon' => 'error')
	);
	*/
	
	public static $systemtypes = array(
		'aws' => array(
			'nodetranslator' => array(
				'Master' => 'master',
				'Started' => 'slave',
				'Slave' => 'slave',
				'not running' => 'stopped'
			),
			'description' => 'Amazon AWS based System',
			'nodestates' => array(
				'master' => array('stateid' => 1, 'description' => 'Master', 'icon' => 'master'),
				'slave' => array('stateid' => 2, 'description' => 'Slave Online', 'icon' => 'slave'),
				'offline' => array('stateid' => 3, 'description' => 'Slave Offline', 'icon' => 'offline'),
				'stopped' => array('stateid' => 5, 'description' => 'Slave Stopped', 'icon' => 'stopped'),
				'error' => array('stateid' => 13, 'description' => 'Slave Error', 'icon' => 'error'),
				'standalone' => array('stateid' => 18, 'description' => 'Standalone Database', 'icon' => 'node'),
			)
		),
		'galera' => array(
			'nodetranslator' => array(
				'Master' => 'master',
				'Started' => 'slave',
				'not runnng' => 'stopped'
			),
			'description' => 'Galera multi-master System',
			'nodestates' => array(
				'down' => array('stateid' => 100, 'description' => 'Down', 'icon' => 'stopped'),
				'open' => array('stateid' => 101, 'description' => 'Open', 'icon' => 'starting'),
				'primary' => array('stateid' => 102, 'description' => 'Primary', 'icon' => 'master'),
				'joiner' => array('stateid' => 103, 'description' => 'Joiner', 'icon' => 'promoting'),
				'joined' => array('stateid' => 104, 'description' => 'Joined', 'icon' => 'master'),
				'synced' => array('stateid' => 105, 'description' => 'Synced', 'icon' => 'master'),
				'donor' => array('stateid' => 106, 'description' => 'Donor', 'icon' => 'master'),
				'isolated' => array('stateid' => 99, 'description' => 'Isolated', 'icon' => 'isolated')
			)
		)
	);
	
	public static $backupstates = array(
		'scheduled' => array('description' => 'Scheduled'),
		'running' => array('description' => 'Running'),
		'paused' => array('description' => 'Paused'),
		'stopped' => array('description' => 'Stopped'),
		'done' => array('description' => 'Done'),
		'error' => array('description' => 'Error')
	);
	
	public static $commandstates = array(
		'scheduled' => array('description' => 'Scheduled', 'icon' => 'scheduled'),
		'running' => array('description' => 'Running', 'icon' => 'running'),
		'paused' => array('description' => 'Paused', 'icon' => 'paused'),
		'stopped' => array('description' => 'Stopped', 'icon' => 'stopped'),
		'done' => array('description' => 'Done', 'icon' => 'done'),
		'error' => array('description' => 'Error', 'icon' => 'error')
	);
	
	public static $nodestates = array(
		'master' => array('stateid' => 1, 'description' => 'Master', 'icon' => 'master'),
		'slave' => array('stateid' => 2, 'description' => 'Slave Online', 'icon' => 'slave'),
		'offline' => array('stateid' => 3, 'description' => 'Slave Offline', 'icon' => 'offline'),
		'stopping' => array('stateid' => 4, 'description' => 'Slave Stopping', 'icon' => 'stopping'),
		'stopped' => array('stateid' => 5, 'description' => 'Slave Stopped', 'icon' => 'stopped'),
		'isolating' => array('stateid' => 6, 'description' => 'Slave Isolating', 'icon' => 'isolating'),
		'recovering' => array('stateid' => 7, 'description' => 'Slave Recovering', 'icon' => 'recovering'),
		'restoring' => array('stateid' => 8, 'description' => 'Slave Restoring Backup', 'icon' => 'restoring'),
		'backingup' => array('stateid' => 9, 'description' => 'Slave Backing Up', 'icon' => 'backingup'),
		'starting' => array('stateid' => 10, 'description' => 'Slave Starting', 'icon' => 'starting'),
		'promoting' => array('stateid' => 11, 'description' => 'Slave Promoting', 'icon' => 'promoting'),
		'synchronizing' => array('stateid' => 12, 'description' => 'Slave Synchronizing', 'icon' => 'synchronizing'),
		'error' => array('stateid' => 13, 'description' => 'Slave Error', 'icon' => 'error'),
		'standalone' => array('stateid' => 18, 'description' => 'Standalone Database', 'icon' => 'node')
	);

	public static $systemstates = array(
		'running' => array('description' => 'System Running', 'icon' => 'system'),
		'stopping' => array('description' => 'System Stopping', 'icon' => 'sys_stopping'),
		'stopped' => array('description' => 'System Stopped', 'icon' => 'sys_stopped'),
		'starting' => array('description' => 'System Starting', 'icon' => 'sys_starting')
	);

	public static $commandsteps = array(
		'start' => array('icon' => 'starting', 'description' => 'Start node up, start replication'),
		'stop' => array('icon' => 'stopping', 'description' => 'Stop replication, shut node down'),
		'isolate' => array('icon' => 'isolating', 'description' => 'Take node out of replication'),
		'recover' => array('icon' => 'recovering', 'description' => 'Put node back into replication'),
		'promote' => array('icon' => 'promoting', 'description' => 'Promote a slave to master'),
		'synchronize' => array('icon' => 'synchronizing', 'description' => 'Synchronize a node'),
		'backup' => array('icon' => 'backingup', 'description' => 'Backup a node'),
		'restore' => array('icon' => 'restoring', 'description' => 'Restore a node'),
		'restart' => array('icon' => 'starting', 'description' => 'Restart a node from error state')
	);
	
	public static function getInstance () {
	    return (self::$instance instanceof self) ? self::$instance : (self::$instance = new self());
	}
	
	protected function __construct () {
		// Prevent diagnostic output leaking out
		ob_start();
		ob_implicit_flush(false);
		
		// Setting of defined symbols
		define('ABSOLUTE_PATH', str_replace('\\', '/', dirname(__FILE__)));
		require_once (ABSOLUTE_PATH.'/configs/definitions.php');
		if (!defined('CLASS_BASE')) define ('CLASS_BASE', ABSOLUTE_PATH);
		define('HTTP_PROTOCOL', isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP 1.1');
		
		// Set up a simple class autoloader
		spl_autoload_register(array(__CLASS__, 'simpleAutoload'));
	}
	
	public function startup ($runController=false) {

		$protects = array('_REQUEST', '_GET', '_POST', '_COOKIE', '_FILES', '_SERVER', '_ENV', 'GLOBALS', '_SESSION');

		// Block some PHP hack attempts
		foreach ($protects as $protect) {
			if ( in_array($protect , array_keys($_REQUEST)) ||
			in_array($protect , array_keys($_GET)) ||
			in_array($protect , array_keys($_POST)) ||
			in_array($protect , array_keys($_COOKIE)) ||
			in_array($protect , array_keys($_FILES))) {
				header(HTTP_PROTOCOL.' 400 Bad Request');
				die(HTTP_PROTOCOL.' 400 Bad Request.');
			}
		}
		
		clearstatcache();

		// Prepare to generate reasonably pseudo-random numbers
		$stat = @stat(__FILE__);
		if (empty($stat) OR !is_array($stat)) $stat = array(php_uname());
		mt_srand(crc32(microtime().implode('|', $stat)));

		//require_once (CLASS_BASE.'/bootstrap/objectcache.php');
		// Do we want to support caching?

		$max_load = defined('MAX_LOAD') ? (float) MAX_LOAD : 0;
		if ($max_load AND function_exists('sys_getloadavg')) {
			$load = sys_getloadavg();
			if ($load[0] > $max_load) {
				$retry = 60.0 * (mt_rand(75, 150)/100.0);
				header ('Retry-After: '.(int)$retry);
				header(HTTP_PROTOCOL.' 503 Too busy, try again later');
				die(HTTP_PROTOCOL.' 503 Server too busy. Please try again later.');
			}
		}

		try {
			$controller = Request::getInstance();
			$errorhandler = ErrorRecorder::getInstance();
			set_error_handler(array($errorhandler, 'PHPerror'));
			register_shutdown_function(array($errorhandler, 'PHPFatalError'));
			if ($runController) $controller->doControl();
		}
		// The request handling code should catch all exceptions
		catch (PDOException $pe) {
			echo 'Unhandled database error: '.$pe->getMessage().API::trace();
		}
		catch (Exception $e) {
			echo 'Unhandled general error: '.$e->getMessage().API::trace();
		}
	}

	public static function merger ($data, $key) {
		return array_merge(array('state' => (string) $key), $data);
	}
	
	public static function mergeStates ($states) {
		return array_map(array(__CLASS__,'merger'), $states, array_keys($states));
	}

	public static function simpleAutoload ($classname) {
		$classname = str_replace('\\', '/', $classname);
		if (is_readable(CLASS_BASE.'/customclasses/'.$classname.'.php')) {
			return require_once(CLASS_BASE.'/customclasses/'.$classname.'.php');
		}
		if (is_readable(CLASS_BASE.'/classes/'.$classname.'.php')) {
			return require_once(CLASS_BASE.'/classes/'.$classname.'.php');
		}
		return false;
	}
	
	public static function getIP () {
		if (self::$ipaddress) return self::$ipaddress;
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
	    self::$ipaddress = (false == $ip AND isset($_SERVER['REMOTE_ADDR'])) ? $_SERVER['REMOTE_ADDR'] : $ip;
	    return self::$ipaddress;
	}

	public static function trace ($error=true) {
	    static $counter = 0;
		$html = '';
		foreach(debug_backtrace() as $back) {
		    if (isset($back['file']) AND $back['file']) {
			    $html .= "\n".$back['file'].':'.$back['line'];
			}
		}
		if ($error) $counter++;
		if (1000 < $counter) {
		    echo $html;
			header(HTTP_PROTOCOL.' 500 Program killed - Probably looping');
			die(' 500 Program killed - Probably looping');
        }
		return $html;
	}
}

// Remove error settings after testing
ini_set('display_errors', 1);
error_reporting(-1);

API::getInstance()->startup(true);
