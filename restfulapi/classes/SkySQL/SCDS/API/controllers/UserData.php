<?php

/*
 ** Part of the SkySQL Manager API.
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
 * Copyright 2013 (c) SkySQL Ab
 * 
 * Author: Martin Brampton
 * Date: February 2013
 * 
 * The Commands class within the API implements the fetching of commands, command 
 * steps or command states.
 */

namespace SkySQL\SCDS\API\controllers;

use SkySQL\SCDS\API\models\Backup;
use SkySQL\SCDS\API\managers\NodeManager;

class UserData extends ImplementAPI {

	public function getBackupLog ($uriparts) {
		$field = $uriparts[1];
		$systemid = $this->getParam($this->requestmethod, 'systemid', 0);
		$backupid = $this->getParam($this->requestmethod, 'backupid', 0);
		$backup = Backup::getByID($systemid, $backupid);
		if (!($backup instanceof Backup) OR empty($backup->$field)) $this->sendErrorResponse('Not Found', 404);
		$node = NodeManager::getInstance()->getByID($backup->systemid, $backup->nodeid);
		if (empty($node)) $this->sendErrorResponse('Not Found', 404);
		$filepath = $backup->$field;
		$log = shell_exec("ssh root@$node->privateip \"cat $filepath\"");
		if (empty($log)) $this->sendErrorResponse('Not Found', 404);
		$this->downloadHeaders($log, $field);
		echo $log;
		exit;
	}
	
	protected function downloadHeaders ($data, $filename) {
		// Do IE specific things
		if (isset($_SERVER['HTTP_USER_AGENT']) AND
	    (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false)) {
	    	$filename = urlencode($filename);
	    }
	    else $filename = str_replace(' ', '+', $filename);

	    // Suppress output compression, can be harmful
		if (ini_get('zlib.output_compression')) ini_set('zlib.output_compression', 'Off');

		header("Content-Type: application/log; charset=utf-8");
		header("Expires: -1");
		header("Content-Disposition: attachment; filename=\"$filename\"");
		header("Content-Transfer-Encoding: binary");
		header('Content-Length: '.strlen($data));
	}
}
