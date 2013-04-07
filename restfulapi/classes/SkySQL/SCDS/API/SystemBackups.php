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
 * The SystemBackups class within the API implements request to do with backups.
 * 
 * The getSystemsBackups method requires a system ID, and has optional parameters
 * of from and/or to date; it returns information about existing backups.
 * 
 * The makeSystemBackup method instigates a backup.
 */

namespace SkySQL\SCDS\API;

use \PDO;
use \PDOException;

class SystemBackups extends ImplementAPI {
	protected $errors = array();

	public function getSystemBackups ($uriparts) {
		$systemid = (int) $uriparts[1];
		$limit = $this->getParam('GET', 'limit', 10);
		$offset = $this->getParam('GET', 'offset', 0);
		$fromdate = $this->getDate('from');
		$todate = $this->getDate('to');
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
		if (count($this->errors)) {
			$this->sendErrorResponse($this->errors, 400);
			exit;
		}
		$conditions = implode(' AND ', $where);
		$mainquery .= $conditions;
		if ($limit) {
			$totaller = $this->db->prepare('SELECT COUNT(*) FROM Backup WHERE '.$conditions);
			$totaller->execute($bind);
			$results['total'] = $totaller->fetch(PDO::FETCH_COLUMN);
			$mainquery .= " LIMIT $limit OFFSET $offset";
		}
		$statement = $this->db->prepare($mainquery);
		$statement->execute($bind);
		$results['backups'] = $statement->fetchALL(PDO::FETCH_ASSOC);
        $this->sendResponse(array('result' => $results));
	}
	
	protected function getDate ($datename) {
		$date = $this->getParam('GET', $datename);
		if ($date) {
			$time = strtotime($date);
			if ($time) return date('d M Y H:i:s');
			else $this->errors[] = "Invalid $datename date: $date";
		}
	}
	
	public function makeSystemBackup ($uriparts) {
		$systemid = (int) $uriparts[1];
		$node = $this->getParam('POST', 'node', 0);
		if (!$node) $errors[] = 'No value provided for node when requesting system backup';
		$level = $this->getParam('POST', 'level', 0);
		if (!$level) $errors[] = 'No value provided for level when requesting system backup';
		$parent = $this->getParam('POST', 'parent');
		if (isset($errors)) $this->sendErrorResponse($errors, 400);
		$query = $this->db->prepare("INSERT INTO Backup (SystemID, NodeID, BackupLevel, Started, ParentID)
			VALUES(:systemid, :nodeid, :level, datetime('now'), :parent");
		try {
			$query->execute(array(
				':systemid' => $systemid,
				':nodeid' => $node,
				':level' => $level,
				':parent' => $parent
			));
			$result['id'] = $this->db->lastInsertId();
			// Extra work for incremental backup
			if (2 == $level) {
				$getlog = $this->db->prepare('SELECT MAX(Started), BinLog AS binlog FROM Backup 
					WHERE SystemID = :systemid AND NodeID = :nodeid AND BackupLevel = 1');
				$getlog->execute(array(
				':systemid' => $systemid,
				':nodeid' => $node
				));
				$result['binlog'] = $getlog->fetch(PDO::FETCH_COLUMN);
			}
			$this->sendResponse(array('result' => $result));
		}
		catch (PDOException $pe) {
			$this->sendErrorResponse("Failed backup request, system ID $systemid, node ID $node, level $level, parent $parent", 500, $pe);
		}
	}
	
	public function updateSystemBackup ($uriparts) {
		$systemid = (int) $uriparts[1];
		$backupid = (int) $uriparts[3];
		$state = $this->getParam('PUT', 'state', 0);
		$size = $this->getParam('PUT', 'size', 0);
		$storage = $this->getParam('PUT', 'storage');
		$binlog = $this->getParam('PUT', 'binlog');
		$log = $this->getParam('PUT', 'log');
		$restored = strtolower($this->getParam('PUT', 'restored'));
		if ($state) {
			$sets[] = 'State = :state';
			$bind[':state'] = $state;
		}
		if ($size) {
			$sets[] = 'Size = :size';
			$bind[':size'] = $size;
		}
		if ($storage) {
			$sets[] = 'Storage = :storage';
			$bind[':storage'] = $storage;
		}
		if ($binlog) {
			$sets[] = 'BinLog = :binlog';
			$bind[':binlog'] = $binlog;
		}
		if ($log) {
			$sets[] = 'Log = :log';
			$bind[':log'] = $log;
		}
		if ('yes' == $restored) {
			$sets[] = "Restored = datetime('now')";
		}
		if (isset($sets)) {
			$bind[':systemid'] = $systemid;
			$bind[':backupid'] = $backupid;
			$update = $this->db->prepare('UPDATE Backup SET '.implode(', ', $sets).' 
				WHERE SystemID = :systemid AND BackupID = :backupid');
			$update->execute($bind);
			$counter = $update->rowCount();
		}
		else $counter = 0;
		$this->sendResponse(array('updatecount' => $counter, 'insertkey' => 0));
	}
	
	public function getBackupStates () {
		$query = $this->db->query('SELECT State AS state, Description AS description FROM BackupStates');
        $this->sendResponse(array("backupStates" => $query->fetchAll(PDO::FETCH_ASSOC)));
	}
}