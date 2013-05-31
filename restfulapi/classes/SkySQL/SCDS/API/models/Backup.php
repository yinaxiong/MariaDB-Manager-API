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
 * Date: May 2013
 * 
 * The Backup class models a backup of a system.
 * 
 */

namespace SkySQL\SCDS\API\models;

use PDO;
use SkySQL\COMMON\AdminDatabase;
use SkySQL\SCDS\API\API;
use SkySQL\SCDS\API\Request;

class Backup extends EntityModel {
	protected static $setkeyvalues = true;
	
	protected static $classname = __CLASS__;
	protected $ordinaryname = 'backup';
	
	protected static $updateSQL = 'UPDATE Backup SET %s WHERE SystemID = :system AND BackupID = :id';
	protected static $countSQL = 'SELECT COUNT(*) FROM Backup WHERE SystemID = :system AND BackupID = :id';
	protected static $insertSQL = 'INSERT INTO Backup (%s) VALUES (%s)';
	protected static $deleteSQL = 'DELETE FROM Backup WHERE SystemID = :system AND BackupID = :id';
	protected static $selectSQL = 'SELECT %s FROM Backup WHERE SystemID = :system AND BackupID = :id';
	protected static $selectAllSQL = 'SELECT %s FROM Backup ORDER BY SystemID, BackupID';
	
	protected static $getAllCTO = array('id');
	
	protected static $keys = array(
		'system' => 'SystemID',
		'id' => 'BackupID'
	);

	protected static $fields = array(
		'node' => array('sqlname' => 'NodeID', 'default' => 0),
		'level' => array('sqlname' => 'BackupLevel', 'default' => 0),
		'parent' => array('sqlname' => 'ParentID', 'default' => 0),
		'state' => array('sqlname' => 'State', 'default' => 0),
		'started' => array('sqlname' => 'Started', 'default' => ''),
		'updated' => array('sqlname' => 'Updated', 'default' => ''),
		'restored' => array('sqlname' => 'Restored', 'default' => ''),
		'size' => array('sqlname' => 'Size', 'default' => 0),
		'storage' => array('sqlname' => 'Storage', 'default' => ''),
		'binlog' => array('sqlname' => 'BinLog', 'default' => ''),
		'log' => array('sqlname' => 'Log', 'default' => '')
	);
	
	public function __construct ($systemid, $backupid=0) {
		$this->system = $systemid;
		$this->id = $backupid;
	}
	
	public static function getBackupStates () {
		return API::mergeStates(API::$backupstates);
	}

	protected function keyComplete () {
		return $this->id ? true : false;
	}
	
	protected function makeNewKey (&$bind) {
		$highest = AdminDatabase::getInstance()->prepare('SELECT MAX(BackupID) FROM Backup WHERE SystemID = :system');
		$highest->execute(array(':system' => $this->system));
		$this->id = 1 + (int) $highest->fetch(PDO::FETCH_COLUMN);
		$bind[':id'] = $this->id;
	}

	protected function insertedKey ($insertid) {
		return $this->id;
	}

	protected function validateInsert (&$bind, &$insname, &$insvalue) {
		if (empty($bind[':node'])) $errors[] = 'No value provided for node when requesting system backup';
		if (empty($bind[':level'])) $errors[] = 'No value provided for level when requesting system backup';
		elseif ($bind[':level'] != 1 AND $bind[':level'] != 2) $errors[] = "Level given {$bind[':level']}, must be 1 or 2 (full or incremental)";
		if (isset($errors)) Request::getInstance()->sendErrorResponse($errors, 400);
		if (2 == $bind[':level']) {
			$getlog = AdminDatabase::getInstance()->prepare('SELECT MAX(Started), BinLog AS binlog FROM Backup 
				WHERE SystemID = :systemid AND NodeID = :nodeid AND BackupLevel = 1');
			$getlog->execute(array(
			':systemid' => $this->system,
			':nodeid' => $bind[':node']
			));
			$bind[':binlog'] = $getlog->fetch(PDO::FETCH_COLUMN);
		}
	}

	public static function getSelectedBackups ($systemid, $fromdate, $todate, $limit, $offset) {
		$mainquery = "SELECT rowid AS id, NodeID as node, BackupLevel AS level, State AS status,
			Size AS size, Started AS started, Updated AS updated, Restored AS restored,
			Storage AS storage, Log AS log, ParentID AS parent FROM Backup WHERE ";
		$where[] = "SystemID = :systemid";
		$bind[':systemid'] = $systemid;
		if ($fromdate) {
			$where[] = "started >= :fromdate";
			$bind[':fromdate'] = $fromdate;
		}
		if ($todate) {
			$where[] = "started <= :todate";
			$bind[':todate'] = $todate;
		}
		$conditions = implode(' AND ', $where);
		$mainquery .= $conditions;
		$database = AdminDatabase::getInstance();
		if ($limit) {
			$totaller = $database->prepare('SELECT COUNT(*) FROM Backup WHERE '.$conditions);
			$totaller->execute($bind);
			$total = $totaller->fetch(PDO::FETCH_COLUMN);
			$mainquery .= " LIMIT $limit OFFSET $offset";
		}
		$statement = $database->prepare($mainquery);
		$statement->execute($bind);
		$backups = $statement->fetchALL(PDO::FETCH_ASSOC);
		return array(($limit ? $total : count($backups)), $backups);
	}
}
