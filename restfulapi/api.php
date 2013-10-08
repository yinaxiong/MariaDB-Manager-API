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

define ('_API_VERSION_NUMBER','0.8');
define ('_API_SYSTEM_NAME', 'MariaDB-Manager-API');
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
	private static $keyname = '';
	
	public static $systemtypes = array(
		'aws' => array(
			'nodetranslator' => array(
				'Master' => 'master',
				'Started' => 'slave',
				'Slave' => 'slave',
				'not running' => 'stopped'
			),
			'description' => 'Amazon AWS based System',
			'systemstates' => array(
				'created' => array('description' => 'Initial state of new system'),
				'running' => array('description' => 'Normally running system')
			),
			'nodestates' => array(
				'provisioned' => array('stateid' => 10001, 'description' => 'Has agent, scripts, database'),
				'master' => array('stateid' => 1, 'description' => 'Master'),
				'slave' => array('stateid' => 2, 'description' => 'Slave Online'),
				'offline' => array('stateid' => 3, 'description' => 'Slave Offline'),
				'stopped' => array('stateid' => 5, 'description' => 'Slave Stopped'),
				'error' => array('stateid' => 13, 'description' => 'Slave Error'),
				'standalone' => array('stateid' => 18, 'description' => 'Standalone Database'),
			),
			'onecommandpersystem' => false
		),
		'galera' => array(
			'nodetranslator' => array(
				'Master' => 'master',
				'Started' => 'slave',
				'not runnng' => 'stopped'
			),
			'description' => 'Galera multi-master System',
			'systemstates' => array(
				'created' => array('description' => 'Initial state of new system'),
				'down' => array('description' => 'No nodes within system are joined'),
				'running' => array('description' => 'Cluster with 3 or more nodes, and all nodes are joined'),
				'available' => array('description' => 'Cluster has 3 or more joined nodes, none incorrectly joined'),
				'limited-availability' => array('description' => 'System has 1 or 2 nodes that are joined'),
				'inconsistent' => array('description' => 'System has one or more nodes incorrectly joined')
			),
			'nodestates' => array(
				'provisioned' => array('stateid' => 10001, 'description' => 'Has agent, scripts, database'),
				'down' => array('stateid' => 100, 'description' => 'Down'),
				'open' => array('stateid' => 101, 'description' => 'Open', 'protected' => true),
				'primary' => array('stateid' => 102, 'description' => 'Primary', 'protected' => true),
				'joiner' => array('stateid' => 103, 'description' => 'Joiner', 'protected' => true),
				'joined' => array('stateid' => 104, 'description' => 'Joined', 'protected' => true),
				'synced' => array('stateid' => 105, 'description' => 'Synced', 'protected' => true),
				'donor' => array('stateid' => 106, 'description' => 'Donor', 'protected' => true),
				'isolated' => array('stateid' => 99, 'description' => 'Isolated'),
				'incorrectly-joined' => array('stateid' => 98, 'description' => 'Incorrectly Joined')
			),
			'onecommandpersystem' => 'start,restart'
		)
	);
	
	public static $provisionstates = array(
		'created' => array('description' => 'Initial state of node'),
		'connected' => array('description' => 'Has agent installed'),
		'unconnected' => array('description' => 'Agent installation failed'),
		'unprovisioned' => array('description' => 'No database installed'),
		'incompatible' => array('description' => 'Automatic provisioning blocked')
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
		'running' => array('description' => 'Running', 'finished' => false),
		'paused' => array('description' => 'Paused - unclear how this could happen', 'finished' => false),
		'stopped' => array('description' => 'Stopped - unclear what this is', 'finished' => true),
		'done' => array('description' => 'Done - normal completion', 'finished' => true),
		'error' => array('description' => 'Error - command failed', 'finished' => true),
		'cancelled' => array('description' => 'Cancelled by request', 'finished' => true),
		'missing' => array('description' => 'The command is not finished and is no longer visible', 'finished' => true)
	);
	
	// To be removed - system state is now type dependent
	public static $systemstates = array(
		'running' => array('description' => 'System Running'),
		'stopping' => array('description' => 'System Stopping'),
		'stopped' => array('description' => 'System Stopped'),
		'starting' => array('description' => 'System Starting')
	);

	public static $commandsteps = array(
		'start' => array('description' => 'Start node up, start replication'),
		'stop' => array('description' => 'Stop replication, shut node down'),
		'isolate' => array('description' => 'Take node out of replication'),
		'recover' => array('description' => 'Put node back into replication'),
		'promote' => array('description' => 'Promote a slave to master'),
		'synchronize' => array('description' => 'Synchronize a node'),
		'backup' => array('description' => 'Backup a node'),
		'restore' => array('description' => 'Restore a node'),
		'restart' => array('description' => 'Restart a node from error state'),
		'setup-ssh' => array('description' => 'Establish SSH communications to new node'),
		'register' => array('description' => 'No idea what this does'),
		'install-agent' => array('description' => 'Install the agent that allows running of commands on nodes'),
		'probe' => array('description' => 'Explore what services are already installed on a new node'),
		'install-packages' => array('description' => 'Install the packages needed for managing the new node'),
		'configure' => array('description' => 'Presumably this does some configuration')
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
		$apitimestamp = filemtime(ABSOLUTE_PATH.'/CHANGELOG.php');
		define ('_API_CODE_ISSUE_DATE', date('D, j F Y H:i', $apitimestamp));
		
		define('HTTP_PROTOCOL', isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP 1.1');
		
		// Set up a simple class autoloader
		spl_autoload_register(array(__CLASS__, 'simpleAutoload'));
		openlog(_API_SYSTEM_NAME, LOG_ODELAY, LOG_USER);
	}
	
	public function startup ($runController=false) {
		
		date_default_timezone_set('UTC');

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
		return array_merge(array(self::$keyname => (string) $key), $data);
	}
	
	public static function mergeStates ($states, $keyname='state') {
		self::$keyname = $keyname;
		return array_map(array(__CLASS__,'merger'), $states, array_keys($states));
	}
	
	public static function trimCommaSeparatedList ($list) {
		return implode(',', array_map('trim', explode(',', $list)));
	}
	
	public static function unfinishedCommandStates () {
		foreach (self::$commandstates as $state=>$about) if (!$about['finished']) $unfinished[] = $state;
		return isset($unfinished) ? "'".implode("','", $unfinished)."'" : '';
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
