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
 * The AdminDatabase class wraps a PDO instance and sets some defaults.
 * 
 */

namespace SkySQL\COMMON;

use \PDO;
use SkySQL\SCDS\API\Request;

class AdminDatabase {
    protected static $instance = null;
    protected $pdo = null;
    protected $sql = '';
    protected $trace = '';
    protected $lastcall = '';
	protected $transact = false;
	
    protected function __construct () {
        $config = Request::getInstance()->getConfig();
		$dbconfig = $config['database'];
		$this->pdo = new PDO($dbconfig['pdoconnect'], $dbconfig['user'], $dbconfig['password']);
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
	}
	
	public function __destruct () {
		if ($this->transact) $this->rollbackTransaction ();
		$this->pdo = null;
	}
	
	public function __call($name, $arguments) {
		$this->lastcall = $name;
		return call_user_func_array(array($this->pdo, $name), $arguments);
	}
	
	public function prepare () {
		return $this->saveAndCall('prepare', func_get_args());
	}
	
	public function query () {
		return $this->saveAndCall('query', func_get_args());
	}
	
	protected function saveAndCall ($type, $arguments) {
		$this->sql = $arguments[0];
		$this->trace = Diagnostics::trace();
		$this->lastcall = $type;
		return call_user_func_array(array($this->pdo, $type), $arguments);
	}
	
	public function getSQL () {
		return $this->sql;
	}
	
	public function getTrace () {
		return $this->trace;
	}
	
	public function getLastCall () {
		return $this->lastcall;
	}
	
	public static function getInstance () {
		return self::$instance instanceof self ? self::$instance : self::$instance = new self();
	}
	
	public function startImmediateTransaction () {
		$this->query('BEGIN IMMEDIATE TRANSACTION');
		$this->transact = true;
	}
	
	public function commitTransaction () {
		if ($this->transact) $this->query('COMMIT TRANSACTION');
		$this->transact = false;
	}
	
	public function rollbackTransaction () {
		if ($this->transact) $this->query('ROLLBACK TRANSACTION');
		$this->transact = false;
	}
}