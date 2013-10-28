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
 * Implements:
 * Obtain node information with GET request to /system/{id}/node/{id}
 * Obtain a monitor with GET request to /system/{id}/node/{id}/monitor/{id}
 */

namespace SkySQL\SCDS\API\controllers;

use PDO;
use stdClass;
use SkySQL\SCDS\API\managers\MonitorManager;
use SkySQL\SCDS\API\managers\SystemManager;
use SkySQL\SCDS\API\managers\NodeManager;
use SkySQL\COMMON\MonitorDatabase;
use SkySQL\SCDS\API\caches\MonitorLatest;

abstract class SystemNodeCommon extends ImplementAPI {
	protected $systemid = 0;
	protected $monitorquery = null;
	
	public function __construct ($controller) {
		parent::__construct($controller);
	}

	protected function getMonitorData ($nodeid=0) {
		list($monitorlatest, $lastupdate, $modified) = MonitorLatest::getInstance()->getMonitorData($this->systemid, $nodeid, $this->ifmodifiedsince);
		if ($modified) $this->modified = true;
		return array($monitorlatest, ($lastupdate ? date('r', $lastupdate) : null));
	}
	
	protected function targetDatabaseQuery ($query, $nodeid) {
		try {
			$system = SystemManager::getInstance()->getByID($this->systemid);
			$node = NodeManager::getInstance()->getByID($this->systemid, $nodeid);
			if (!$node) $this->sendErrorResponse("System $this->systemid and node $nodeid are not valid node identifiers", 400);
			$connection = "mysql:host=$node->privateip;dbname=information_schema";
			if ($node->port) $connection .= ";port=$node->port";
			$username = empty($node->dbusername) ? $system->dbusername : $node->dbusername;
			$password = empty($node->dbpassword) ? $system->dbpassword : $node->dbpassword;
            $this->subjectdb = new PDO($connection, $username, $password);
            $this->subjectdb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->subjectdb->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
			$this->subjectdb->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
			try {
				$statement = $this->subjectdb->prepare($query);
	            $statement->execute();
				$results = $statement->fetchAll();
				return $results;
			}
			catch (PDOException $pe) {
				$this->sendResponse(array('error' => $pe->getMessage()));
			}
        }
        catch (PDOException $pe) {
            $this->sendErrorResponse($pe->getMessage(), 400);
            exit;
        }
	}

	protected function getNodeProcesses ($nodeid) {
		$processes = $this->targetDatabaseQuery('SHOW PROCESSLIST', $nodeid);
		if ($processes) foreach ($processes as &$process) {
			$process->nodeid = $nodeid;
			foreach (get_object_vars($process) as $name=>$value) {
				$lcname = strtolower($name);
				if ($lcname != $name) {
					$process->$lcname = $value;
					unset($process->$name);
				}
			}
		}
		return $processes;
	}
}