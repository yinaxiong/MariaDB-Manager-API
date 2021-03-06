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
 * The SystemBackups class within the API implements request to do with backups.
 * 
 * The getSystemsBackups method requires a system ID, and has optional parameters
 * of from and/or to date; it returns information about existing backups.
 * 
 * The makeSystemBackup method instigates a backup.
 */

namespace SkySQL\Manager\API\controllers;

use SkySQL\Manager\API\models\Backup;

class SystemBackups extends ImplementAPI {
	protected $defaultResponse = 'schedule';
	protected $errors = array();

	public function getSystemBackups ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, '', true, 'from, to, fields');
		$this->keydata['systemid'] = (int) $uriparts[1];
		$fromdate = $this->getDate('from');
		$todate = $this->getDate('to');
		if (count($this->errors)) {
			$this->sendErrorResponse($this->errors, 400);
			exit;
		}
		list($total, $backups) = Backup::select($this, $fromdate, $todate);
        $this->sendResponse(array('total' => $total, 'backups' => $this->filterResults($backups)));
	}
	
	public function getOneBackup ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, '', false, 'fields');
		$systemid = (int) $uriparts[1];
		$backupid = (int) $uriparts[3];
		$backup = Backup::getByID($systemid,$backupid);
		if (!$backup) $this->sendErrorResponse(sprintf("No backup with systemid '%d' and backupid '%d'", $systemid, $backupid), 404);
		$this->sendResponse(array('backup' => $this->filterSingleResult($backup)));
	}
	
	protected function getDate ($datename) {
		$date = $this->getParam('GET', $datename);
		if ($date) {
			$time = strtotime($date);
			if ($time) return date('Y-m-d H:i:s', $time);
			else $this->errors[] = "Invalid $datename date: $date";
		}
	}
	
	public function updateSystemBackup ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'Insert-Update', false, 'Fields from backup resource');
		$systemid = (int) $uriparts[1];
		$backupid = (int) $uriparts[3];
		$backup = new Backup($systemid, $backupid);
		$backup->update();
	}
	
	public function makeSystemBackup ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'Insert-Update', false, 'Fields from backup resource');
		$systemid = (int) $uriparts[1];
		$backup = new Backup($systemid);
		$backup->insert();
	}

	public function getBackupStates ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'backupstates', true);
        $this->sendResponse(array("backupStates" => $this->filterResults(Backup::getBackupStates())));
	}
}