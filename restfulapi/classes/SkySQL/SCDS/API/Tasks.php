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
 * The Tasks class handles task related requests.
 * 
 */

namespace SkySQL\SCDS\API;

use \PDO;
use \PDOException;

class Tasks extends ImplementAPI {
	protected static $base = 'SELECT CE.TaskID AS id, CE.NodeID AS node, CE.CommandID AS command, 
			CE.Params AS params, CE.StepIndex AS stepindex, CE.State AS status, CE.UserID AS user, CE.Start AS start, 
			CE.Completed AS end FROM CommandExecution AS CE';
	
	public function getOneTask ($uriparts) {
		$taskid = $uriparts[1];
		$statement = $this->db->prepare(self::$base.' WHERE TaskID = :taskid');
		$statement->execute(array(':taskid' => $taskid));
		$this->queryResults($statement);
	}
	
	public function getTasks ($uriparts) {
		$sql = self::$base;
		$bind = array();
		$status = $this->getParam('GET', 'status', 0);
		if ($status) {
			$where[] = 'CE.State = :state';
			$bind[':state'] = $status;
		}
		$group = $this->getParam('GET', 'group');
		if ($group) {
			$sql .= ' INNER JOIN Commands AS C ON CE.CommandID = C.CommandID';
			$where[] = 'C.UIGroup = :group';
			$bind[':group'] = $group;
			$node = $this->getParam('GET', 'node', 0);
			if ($node) {
				$where[] = 'CE.NodeID = :nodeid';
				$bind[':nodeid'] = $node;
			}
		}
		if (isset($where)) $sql .= ' WHERE '.implode(' AND ', $where);
		$statement = $this->db->prepare($sql);
		$statement->execute($bind);
		$this->queryResults($statement);
	}
	
	protected function queryResults ($statement) {
		$this->sendResponse(array('tasks' => $statement->fetchAll(PDO::FETCH_ASSOC)));
	}
	
	public function runCommand ($uriparts) {
		$command = urldecode($uriparts[1]);
		$systemid = $this->getParam('POST', 'system', 0);
		$nodeid = $this->getParam('POST', 'node', 0);
		$userid = $this->getParam('POST', 'user', 0);
		if ($systemid AND $nodeid AND $userid) {
			$params = urldecode($this->getParam('POST', 'params'));
			$now = new DateTime("now", new DateTimeZone(API_TIME_ZONE));
			$time = $now->format('Y-m-d H:i:s');
			$insert = $this->db->prepare("INSERT INTO CommandExecution 
				(SystemID, NodeID, CommandID, Params, Start, Completed, StepIndex, State, UserID) 
				VALUES (:systemid, :nodeid, :commandid, :params, :start, :completed, :stepindex, :state, :userid)");
			$insert->execute(array(
				':systemid' => $systemid,
				':nodeid' => $nodeid,
				':commandid' => $commandid,
				':params' => $params,
				':start' => $time,
				':completed' => null,
				':stepindex' => 0,
				':state' => 0,
				':userid' => $userid
			));
        	$rowID = $this->db->lastInsertId();
        	$cmd = API_SHELL_PATH."RunCommand.sh $rowID \"".ADMIN_DATABASE_PATH.'" > /dev/null 2>&1 &';
        	exec($cmd);
        	$this->sendResponse(array('task' => $rowID));
		}
	}
}