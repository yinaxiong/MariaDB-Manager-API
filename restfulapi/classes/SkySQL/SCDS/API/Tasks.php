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

use PDO;

class Tasks extends ImplementAPI {
	protected static $base = 'SELECT CE.TaskID AS id, CE.NodeID AS node, CE.CommandID AS command, 
			CE.Params AS params, CE.StepIndex AS stepindex, CE.State AS status, CE.UserID AS user, CE.Start AS start, 
			CE.Completed AS end FROM CommandExecution AS CE';
	
	public function getOneOrMoreTasks ($uriparts) {
		$sql = self::$base;
		$bind = array();
		$taskid = isset($uriparts[1]) ? (int) $uriparts[1] : 0;
		if ($taskid) {
			$where[] = 'CE.TaskID = :taskid';
			$bind[':taskid'] = $taskid;
		}
		$state = $this->getParam('GET', 'state', 0);
		if ($state) {
			$where[] = 'CE.State = :state';
			$bind[':state'] = $state;
		}
		$group = $this->getParam('GET', 'group');
		if ($group) {
			$sql .= ' INNER JOIN Commands AS C ON CE.CommandID = C.CommandID';
			$where[] = 'C.UIGroup = :group';
			$bind[':group'] = $group;
			$node = $this->getParam('GET', 'nodeid', 0);
			if ($node) {
				$where[] = 'CE.NodeID = :nodeid';
				$bind[':nodeid'] = $node;
			}
		}
var_dump($where);
		if (isset($where)) $sql .= ' WHERE '.implode(' AND ', $where);
		$statement = $this->db->prepare($sql);
		$statement->execute($bind);
		$this->queryResults($statement);
	}
	
	protected function queryResults ($statement) {
		$this->sendResponse(array('task' => $this->filterResults((array) $statement->fetchAll(PDO::FETCH_ASSOC))));
	}
	
	public function runCommand ($uriparts) {
		$command = urldecode($uriparts[1]);
		$commandid = $this->getCommandID($command);
var_dump($command,$commandid);
		$systemid = $this->getParam('POST', 'systemid', 0);
		$nodeid = $this->getParam('POST', 'nodeid', 0);
		$userid = $this->getUserID();
		if ($systemid AND $nodeid AND $userid) {
			$params = urldecode($this->getParam('POST', 'params'));
			$insert = $this->db->prepare("INSERT INTO CommandExecution 
				(SystemID, NodeID, CommandID, Params, Start, Completed, StepIndex, State, UserID) 
				VALUES (:systemid, :nodeid, :commandid, :params, datetime('now'), :completed, :stepindex, :state, :userid)");
			$insert->execute(array(
				':systemid' => $systemid,
				':nodeid' => $nodeid,
				':commandid' => $commandid,
				':params' => $params,
				':completed' => null,
				':stepindex' => 0,
				':state' => 0,
				':userid' => $userid
			));
        	$rowID = $this->db->lastInsertId();
        	$cmd = $this->config['shell']['path']."RunCommand.sh $rowID \"".$this->config['database']['path'].'" > /dev/null 2>&1 &';
        	exec($cmd);
        	$this->getOneOrMoreTasks(array(1 => $rowID));
		}
		else $this->sendErrorResponse('Must supply valid system ID and username to run command');
	}
	
	protected function getCommandID ($command) {
		$getter = $this->db->prepare('SELECT CommandID FROM Commands WHERE Name LIKE :command');
		$getter->execute(array(':command' => $command));
		$id = $getter->fetch(PDO::FETCH_COLUMN);
		if (!$id) $this->sendErrorResponse("Apparently valid command $command not found in Commands table", 500);
		return $id;
	}
	
	protected function getUserID () {
		$username = $this->getParam('POST', 'username');
		$select = $this->db->prepare('SELECT UserID FROM Users WHERE UserName = :username');
		$select->execute(array(':username' => $username));
		return $select->fetch(PDO::FETCH_COLUMN);
	}
	
}