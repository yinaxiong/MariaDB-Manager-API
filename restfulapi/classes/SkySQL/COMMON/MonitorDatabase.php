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
use SkySQL\SCDS\API\Request;
use SQLite3;

if (basename(@$_SERVER['REQUEST_URI']) == basename(__FILE__)) die ('This software is for use within a larger system');

class MonitorDatabase extends APIDatabase {
    protected static $instance = null;
	
	protected function checkAndConnect ($dbconfig) {
		$dboparts = explode(':', $dbconfig['monconnect']);
		$dbdirectory = dirname(@$dboparts[1]);
		if ('sqlite' != $dboparts[0] OR 2 > count($dboparts)) {
			$error = sprintf('Configuration for Monitor DB at %s should contain a PDO connection string for a SQLite database',_API_INI_FILE_LOCATION);
		}
		elseif (!file_exists($dbdirectory) OR !is_dir($dbdirectory) OR !is_writeable($dbdirectory)) {
			$error = "Database directory $dbdirectory does not exist or is not writeable - please check existence, permissions and SELinux constraints";
		}
		elseif (file_exists($dboparts[1])) {
			if (!is_writeable($dboparts[1])) $error = 'Database file exists but is not writeable';
		}
		else {
			$sqlfile = dirname(__FILE__).'/MonitorDatabase.sql';
			$nocomment = preg_replace('#/\*.*?\*/#s', '', file_get_contents($sqlfile));
			$sqldb = new SQLite3($dboparts[1]);
			$sqldb->exec('BEGIN EXCLUSIVE TRANSACTION');
			$sqldb->exec($nocomment);
			$sqldb->exec('COMMIT TRANSACTION');
			$sqldb->close();
		}
		if (isset($error)) throw new PDOException($error);
		$pdo = new PDO($dbconfig['monconnect'], $dbconfig['monuser'], $dbconfig['monpassword']);
		return $pdo;
	}
	
	public static function getInstance () {
		return self::$instance instanceof self ? self::$instance : self::$instance = new self();
	}
	public function createMonitoringTable ($systemid, $nodeid) {
		$name = $this->makeTableName($systemid, $nodeid);
		$sql = <<<CREATE
				
create table if not exists $name (
	MonitorID	int,		/* ID number for monitor class */
	Value		int,		/* Value for the observation */
	Stamp		int,		/* Date/Time this value was observed, unix time */
	Repeats		int			/* Number of repeated observations same value */
);
CREATE INDEX {$name}IDX ON $name (MonitorID, Stamp);

CREATE;
		
		try {
			$this->query($sql);
			return $name;
		}
		catch (PDOException $pe) {
			Request::getInstance()->sendErrorResponse("Needed to create table $name but failed on {$pe->getMessage()})", 500, $pe);
			$this->rollbackTransaction();
		}
	}
	
	public function existsMonitoringTable ($systemid, $nodeid) {
		$name = $this->makeTableName($systemid, $nodeid);
		$check = $this->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name");
		$check->execute(array(':name' => $name));
		$result = $check->fetchColumn();
		return $result ? true : false;
	}
	
	public function makeTableName ($systemid, $nodeid) {
		return ($systemid) ? 'MonitorDataS'.sprintf('%06d', $systemid).'N'.sprintf('%06d', $nodeid) : 'MonitorData';
	}
	
	public function splitMonitorData () {
		if ($this->existsMonitoringTable(0, 0)) {
			$this->beginExclusiveTransaction();
			$nodes = $this->getAllNodeTables();
			foreach ($nodes as $node) {
				$name = $this->createMonitoringTable($node->systemid, $node->nodeid);
				$insert = $this->prepare("INSERT INTO $name (MonitorID, Value, Stamp, Repeats) 
					SELECT MonitorID, Value, Stamp, Repeats FROM MonitorData WHERE SystemID = :systemid AND NodeID = :nodeid");
				$insert->execute(array(
					':systemid' => $node->systemid,
					':nodeid' => $node->nodeid
				));
			}
			$this->query("DROP TABLE MonitorData");		
			$this->commitTransaction();
		}
	}
	
	protected function getAllNodeTables () {
			$getnodes = $this->query("SELECT SystemID AS systemid, NodeID AS nodeid FROM MonitorData GROUP BY SystemID, NodeID");
			return $getnodes->fetchAll();
	}
	
	public function rescaleMonitorData ($monitorid, $multiplier) {
		$nodes = $this->getAllNodeTables();
		$this->beginExclusiveTransaction();
		foreach ($nodes as $node) {
			$name = $this->createMonitoringTable($node->systemid, $node->nodeid);
			$update = $this->prepare("UPDATE $name SET Value = Value * :multiplier WHERE MonitorID = :monitorid");
			$update->execute(array(
				':multiplier' => $multiplier,
				':monitorid' => $monitorid
			));
			$this->commitTransaction();
		}
	}
}