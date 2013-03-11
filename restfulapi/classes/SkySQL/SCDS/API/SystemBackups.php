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
		$fromdate = $this->getDate('from');
		$todate = $this->getDate('to');
		$query = "SELECT rowid AS id, NodeID as node, BackupLevel AS level, State AS status,
			Size AS size, Started AS started, Updated AS updated, Restored AS restored,
			Storage AS storage, Log AS log, ParentID AS parent FROM Backup WHERE ";
		$where[] = "SystemID=$systemid";
		if ($fromdate) $where[] = "started >= :fromdate";
		if ($todate) $where[] = "started <= :todate";
		if (count($this->errors)) {
			$this->sendErrorResponse($this->errors, 400);
			exit;
		}
		$statement = $this->db->prepare($query.implode(' AND ', $where));
		if ($fromdate) $statement->bindValue(':fromdate', $fromdate);
		if ($todate) $statement->bindValue(':todate', $todate);
        $this->sendResponse(array("backups" => $statement->fetchALL(PDO::FETCH_ASSOC)));
	}
	
	protected function getDate ($datename) {
		if (isset($_GET[$datename])) {
			$date = urldecode($_GET[$datename]);
			$time = strtotime($date);
			if ($time) return date('d M Y H:i:s');
			else $this->errors[] = "Invalid $datename date: $date";
		}
	}
	
	public function makeSystemBackup ($uriparts) {
		$systemid = (int) $uriparts[1];
		if (isset($_POST['node'])) $node = (int) $_POST['node'];
		else $errors[] = 'No value provided for node when requesting system backup';
		if (isset($_POST['level'])) $level = (int) $_POST['level'];
		else $errors[] = 'No value provided for level when requesting system backup';
		if (isset($_POST['parent'])) $parent = (int) $_POST['parent'];
		else $errors[] = 'No value provided for parent when requesting system backup';
		if (isset($errors)) $this->sendErrorResponse($errors, 400);
		$query = $this->db->prepare("INSERT INTO Backup (SystemID, NodeID, BackupLevel, Started, ParentID)
			VALUES(:systemid, :nodeid, :level, :started, :parent");
		try {
			$query->execute(array(
				':systemid' => $systemid,
				':nodeid' => $node,
				':level' => $level,
				':started' => $time,
				':parent' => $parent
			));
			$rowid = $this->db->lastInsertId();
		}
		catch (PDOException $p) {
			$this->sendErrorResponse("Failed backup request, system ID $systemid, node ID $node, level $level, parent $parent", 500, $pe);
		}
	}
	
	public function getBackupStates () {
		$query = $this->db->query('SELECT State AS state, Description AS description FROM BackupStates');
        $this->sendResponse(array("backupStates" => $query->fetchAll(PDO::FETCH_ASSOC)));
	}
}