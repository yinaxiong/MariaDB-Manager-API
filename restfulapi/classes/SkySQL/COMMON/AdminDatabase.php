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
 * The AdminDatabase class wraps a PDO instance and sets some defaults.
 * 
 */

namespace SkySQL\COMMON;

use PDO;
use PDOException;
use SQLite3;
use SkySQL\COMMON\CACHE\CachedSingleton;

if (basename(@$_SERVER['REQUEST_URI']) == basename(__FILE__)) die ('This software is for use within a larger system');

class AdminDatabase extends APIDatabase {
    protected static $instance = null;
	
	protected function checkAndConnect ($dbconfig) {
		$dboparts = explode(':', $dbconfig['pdoconnect']);
		$dbdirectory = dirname(@$dboparts[1]);
		if ('sqlite' != $dboparts[0] OR 2 > count($dboparts)) {
			$error = sprintf('Configuration for Admin DB at %s should contain a PDO connection string for a SQLite database',_API_INI_FILE_LOCATION);
		}
		elseif (!file_exists($dbdirectory) OR !is_dir($dbdirectory) OR !is_writeable($dbdirectory)) {
			$error = "Database directory $dbdirectory does not exist or is not writeable - please check existence, permissions and SELinux constraints";
		}
		elseif (file_exists($dboparts[1])) {
			if (!is_writeable($dboparts[1])) $error = 'Database file exists but is not writeable';
		}
		else {
			$sqlfile = dirname(__FILE__).'/AdminDatabase.sql';
			$nocomment = preg_replace('#/\*.*?\*/#s', '', file_get_contents($sqlfile));
			$sqldb = new SQLite3($dboparts[1]);
			$sqldb->exec('BEGIN EXCLUSIVE TRANSACTION');
			$sqldb->exec($nocomment);
			$sqldb->exec('COMMIT TRANSACTION');
			$sqldb->close();
			CachedSingleton::deleteAll();
		}
		if (isset($error)) throw new PDOException($error);
		$pdo = new PDO($dbconfig['pdoconnect'], $dbconfig['user'], $dbconfig['password']);
		return $pdo;
	}
	
	public function upgrade () {
		$this->query('create unique index if not exists SystemNameIDX ON System (SystemName)');
		$this->upgradeBackupTable();
		$this->upgradeNodeTable();
		$this->upgradeNodeCommandTable();
	}

	protected function upgradeBackupTable () {
		$pragma = $this->query("PRAGMA table_info('Backup')");
		foreach ($pragma->fetchAll() as $field) {
			$fieldnames[$field->name] = $field;
		}
		if (!isset($fieldnames['TaskID'])) {
			$this->query("alter table Backup add TaskID int default 0");
		}
	}
	
	protected function upgradeNodeTable () {
		$pragma = $this->query("PRAGMA table_info('Node')");
		foreach ($pragma->fetchAll() as $field) {
			$fieldnames[$field->name] = $field;
		}
		if (!isset($fieldnames['ScriptRelease'])) {
			$this->query("alter table Node add ScriptRelease varchar(20) default ('1.0')");
		}
		if (!isset($fieldnames['DBType'])) {
			$this->query("alter table Node add DBType varchar(50) default ('MariaDB')");
		}
		if (!isset($fieldnames['DBVersion'])) {
			$this->query("alter table Node add DBVersion varchar(20) default ('5.5.35')");
		}
		if (!isset($fieldnames['LinuxName'])) {
			$this->query("alter table Node add LinuxName varchar(50) default ('CentOS')");
		}
		if (!isset($fieldnames['LinuxVersion'])) {
			$this->query("alter table Node add LinuxVersion varchar(20) default ('6.5')");
		}
	}

	protected function upgradeNodeCommandTable () {
		$this->query("update NodeCommands set Steps = 'isolate,restore'
			where Command = 'restore' AND SystemType = 'galera' AND State = 'joined'");
	}

	public static function getInstance () {
		return self::$instance instanceof self ? self::$instance : self::$instance = new self();
	}
}