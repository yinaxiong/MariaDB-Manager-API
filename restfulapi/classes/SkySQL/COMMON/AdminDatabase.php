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

class AdminDatabase {
	protected static $instance = null;
	protected $pdo = null;
	protected $sql = '';
	protected $trace = '';
	protected $lastcall = '';
	
	protected function __construct () {
		$this->pdo = new PDO(ADMIN_DATABASE_CONNECTION, ADMIN_DATABASE_USER, ADMIN_DATABASE_PASSWORD);
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
	}
	
	public function __call($name, $arguments) {
		$this->lastcall = $name;
		return call_user_func_array(array($this->pdo, $name), $arguments);
	}
	
	public function prepare () {
		$arguments = func_get_args();
		$this->sql = $arguments[0];
		$this->trace = Diagnostics::trace();
		$this->lastcall = 'prepare';
		return call_user_func_array(array($this->pdo, 'prepare'), $arguments);
	}
	
	public function query () {
		$arguments = func_get_args();
		$this->sql = $arguments[0];
		$this->trace = Diagnostics::trace();
		$this->lastcall = 'query';
		return call_user_func_array(array($this->pdo, 'query'), $arguments);
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
}