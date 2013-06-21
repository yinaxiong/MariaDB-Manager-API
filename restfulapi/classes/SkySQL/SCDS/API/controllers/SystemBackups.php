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
 * The SystemBackups class within the API implements request to do with backups.
 * 
 * The getSystemsBackups method requires a system ID, and has optional parameters
 * of from and/or to date; it returns information about existing backups.
 * 
 * The makeSystemBackup method instigates a backup.
 */

namespace SkySQL\SCDS\API\controllers;

use SkySQL\SCDS\API\models\Backup;

class SystemBackups extends ImplementAPI {
	protected $errors = array();

	public function getSystemBackups ($uriparts) {
		$systemid = (int) $uriparts[1];
		$limit = $this->getParam('GET', 'limit', 10);
		$offset = $this->getParam('GET', 'offset', 0);
		$fromdate = $this->getDate('from');
		$todate = $this->getDate('to');
		if (count($this->errors)) {
			$this->sendErrorResponse($this->errors, 400);
			exit;
		}
		list($total, $backups) = Backup::getSelectedBackups($systemid, $fromdate, $todate, $limit, $offset);
        $this->sendResponse(array('total' => $total, 'backups' => $this->filterResults($backups)));
	}
	
	protected function getDate ($datename) {
		$date = $this->getParam('GET', $datename);
		if ($date) {
			$time = strtotime($date);
			if ($time) return date('Y-m-d H:i:s', $time);
			else $this->errors[] = "Invalid $datename date: $date";
		}
	}
	
	public function updateSystemBackup ($uriparts) {
		$systemid = (int) $uriparts[1];
		$backupid = (int) $uriparts[3];
		$backup = new Backup($systemid, $backupid);
		$backup->update();
	}
	
	public function makeSystemBackup ($uriparts) {
		$systemid = (int) $uriparts[1];
		$backup = new Backup($systemid);
		$backup->insert();
	}

	public function getBackupStates () {
        $this->sendResponse(array("backupStates" => Backup::getBackupStates()));
	}
}