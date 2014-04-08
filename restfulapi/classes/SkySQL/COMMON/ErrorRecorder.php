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
 * Copyright 2013 (c) SkySQL Corporation Ab
 * 
 * Author: Martin Brampton
 * Date: February 2013
 * 
 * ErrorRecorder provides a simple way to log errors to the database.  It
 * will accept a short message, a long message, and optionally an exception as
 * parameters.  It derives for itself the POST and GET data, and also a trace
 * of execution.  The whole is stored as a database record, in a table which
 * is pruned to keep it to a maximum of 7 days so it will not grow too large.
 *
 */

namespace SkySQL\COMMON;
use SkySQL\Manager\API\API;
use SkySQL\Manager\API\Request;

use SkySQL\COMMON\AdminDatabase;
use \PDOException;

if (basename(@$_SERVER['REQUEST_URI']) == basename(__FILE__)) die ('This software is for use within a larger system');

final class ErrorRecorder  {
    protected static $instance = null;
	protected $DBclass = 'aliroCoreDatabase';
	protected $tableName = '#__error_log';
	protected $rowKey = 'id';
	
	protected function __construct () {}

	public static function getInstance () {
	    return (null == self::$instance) ? (self::$instance = new self()) : self::$instance;
	}
	
	protected function T_ ($string) {
		return function_exists('T_') ? T_($string) : $string;
	}

	public function PHPerror ($errno, $errstr, $errfile, $errline, $errcontext) {
		if (E_ERROR === $errno) $errno = $this->T_('Fatal');
		elseif (!($errno & error_reporting())) return;
	    $rawmessage = $this->T_('PHP Error %s: %s in %s at line %s');
	    $message = sprintf($rawmessage, $errno, $errstr, $errfile, $errline);
        $lmessage = $message;
		$variables = $this->arrayContents($errcontext);
		if ($variables) $lmessage .= '; '.$variables;
        $errorkey = "PHP/$errno/$errfile/$errline/$errstr";
	    $this->recordError($message, $errorkey, $lmessage);
	    Request::getInstance()->sendErrorResponse($this->T_('A program fault has been recorded in the log'.$lmessage),500);
	    if ($errno & (E_USER_ERROR|E_COMPILE_ERROR|E_CORE_ERROR|E_ERROR)) die (T_('Serious PHP error - processing halted - see error log for details'));
	}
	
	public function PHPFatalError () {
		$last_error = error_get_last();
		if (E_ERROR === $last_error['type']) $this->PHPerror(E_ERROR, $last_error['message'], $last_error['file'], $last_error['line'], null);
	}
	
	public function arrayContents ($anarray) {
		if (is_array($anarray)) foreach ($anarray as $key=>$value) {
			if (is_object($value)) {
				if (method_exists($value, '__toString')) $message[] = "$key=".(string) $value;
			}
			elseif (is_array($value)) {
				$arraysize = count($value);
				$message[] = "$key($arraysize)=(".$this->arrayContents($value).')';
			}
			else $message[] = "$key=$value";
		}
		return isset($message) ? implode('; ', $message) : '';
	}

	public function recordError ($smessage, $errorkey, $lmessage='', $exception=null) {
		if (!defined('_API_RECORDING_TERMINAL_ERROR')) define ('_API_RECORDING_TERMINAL_ERROR', true);
		error_log($lmessage);
		try {
			$database = AdminDatabase::getInstance();
			if ($exception instanceof PDOException) {
				$sql = $database->getSQL();
				$dberror = $exception->getCode();
				$dbmessage = $exception->getMessage();
				$dbtrace = $database->getTrace();
				$dbcall = $database->getLastCall();
			}
			else $dbcall = $sql = $dberror = $dbmessage = $dbtrace = '';
			$findid = $database->prepare('UPDATE ErrorLog SET timestamp = :timestamp, ip = :ip, referer = :referer, get = :get, post = :post, trace = :trace WHERE errorkey = :errorkey');
			$findid->execute(array(
				':timestamp' => date ('Y-m-d H:i:s'),
				':ip' => API::getIP(),
				':referer' => (empty($_SERVER['HTTP_REFERER']) ? 'Unknown' : $_SERVER['HTTP_REFERER']),
				':get' => @$_SERVER['REQUEST_URI'],
				':post' => base64_encode(serialize($_POST)),
				':trace' => Diagnostics::trace(),
				':errorkey' => $errorkey
			));
			if ($findid->rowCount()) return;
			$insert = $database->prepare('INSERT INTO ErrorLog (timestamp, ip, smessage, lmessage,
				referer, get, post, trace, sql, dberror, dbmessage, dbcall, dbtrace, errorkey)
				VALUES (:timestamp, :ip, :smessage, :lmessage, :referer, :get, :post,
				:trace, :sql, :dberror, :dbmessage, :dbcall, :dbtrace, :errorkey);');
			$insert->execute(array(
				':timestamp' => date ('Y-m-d H:i:s'),
				':ip' => API::getIP(),
				':smessage' => substr($smessage, 0, 250),
				':lmessage' => ($lmessage ? $lmessage : $smessage),
				':referer' => (empty($_SERVER['HTTP_REFERER']) ? 'Unknown' : $_SERVER['HTTP_REFERER']),
				':get' => (string) @$_SERVER['REQUEST_URI'],
				':post' => base64_encode(serialize($_POST)),
				':trace' => Diagnostics::trace(),
				':sql' => $sql,
				':dberror' => $dberror,
				':dbmessage' => $dbmessage,
				':dbcall' => $dbcall,
				':dbtrace' => $dbtrace,
				':errorkey' => $errorkey
			));
			$config = Request::getInstance()->getConfig();
			if (@$config['logging']['erroremail']) {
				$headers = 'From: MariaDB Manager <no-reply@skysql.com>' . "\r\n";
				mail($config['logging']['erroremail'], 'Error: '.$smessage, ($lmessage ? $lmessage : $smessage), $headers);
			}
			$database->query("DELETE FROM ErrorLog WHERE timestamp < datetime('now','-7 day')");
		}
		catch (PDOException $pe) {
			error_log('Unable to record error '.$pe->getMessage());
			exit;
		}
	}
}