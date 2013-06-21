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
 * The Node class models a node in a System, which is a cluster of database servers.
 * 
 */

namespace SkySQL\SCDS\API\models;

use PDO;
use SkySQL\COMMON\AdminDatabase;
use SkySQL\SCDS\API\API;

class Node extends EntityModel {
	protected static $setkeyvalues = true;
	
	protected static $classname = __CLASS__;
	protected static $managerclass = 'SkySQL\\SCDS\\API\\managers\\NodeManager';

	protected $ordinaryname = 'node';
	protected static $headername = 'Node';
	
	protected static $updateSQL = 'UPDATE Node SET %s WHERE SystemID = :systemid AND NodeID = :nodeid';
	protected static $countSQL = 'SELECT COUNT(*) FROM Node WHERE SystemID = :systemid AND NodeID = :nodeid';
	protected static $insertSQL = 'INSERT INTO Node (%s) VALUES (%s)';
	protected static $deleteSQL = 'DELETE FROM Node WHERE SystemID = :systemid AND NodeID = :nodeid';
	protected static $selectSQL = 'SELECT %s FROM Node WHERE SystemID = :systemid AND NodeID = :nodeid';
	protected static $selectAllSQL = 'SELECT %s FROM Node %s ORDER BY SystemID, NodeID';
	
	protected static $getAllCTO = array('systemid', 'nodeid');
	
	protected static $keys = array(
		'systemid' => array('sqlname' => 'SystemID', 'type' => 'int'),
		'nodeid' => array('sqlname' => 'NodeID', 'type' => 'int')
	);

	protected static $fields = array(
		'name' => array('sqlname' => 'NodeName', 'default' => ''),
		'state' => array('sqlname' => 'State', 'default' => 'offline', 'validate' => 'nodestate'),
		'hostname' => array('sqlname' => 'Hostname', 'default' => ''),
		'publicip' => array('sqlname' => 'PublicIP', 'default' => '', 'validate' => 'ipaddress'),
		'privateip' => array('sqlname' => 'PrivateIP', 'default' => '', 'validate' => 'ipaddress'),
		'instanceid' => array('sqlname' => 'InstanceID', 'default' => ''),
		'dbusername' => array('sqlname' => 'DBUserName', 'default' => ''),
		'dbpassword' => array('sqlname' => 'DBPassword', 'default' => '')
	);
	
	protected static $derived = array(
		'commands' => array('type' => 'array', 'desc' => 'Names of commands that could be run in the present node state'),
		'monitorlatest' => array('type' => 'object', 'desc' => 'Latest value for node for each monitor'),
		'command' => array('type' => 'varchar', 'desc' => 'Name of the command currently running on the node'),
		'taskid' => array('type' => 'int', 'desc' => 'ID number of the task running on the node')
	);
	
	public function __construct ($systemid, $nodeid=0) {
		$this->systemid = $systemid;
		$this->nodeid = $nodeid;
	}
	
	protected function keyComplete () {
		return $this->nodeid ? true : false;
	}
	
	protected function makeNewKey () {
		$highest = AdminDatabase::getInstance()->prepare('SELECT MAX(NodeID) FROM Node WHERE SystemID = :systemid');
		$highest->execute(array(':systemid' => $this->systemid));
		$this->nodeid = 1 + (int) $highest->fetch(PDO::FETCH_COLUMN);
		$this->bind[':nodeid'] = $this->nodeid;
	}
	
	protected function setDefaults () {
		if (empty($this->bind[':name'])) {
			$this->setInsertValue('name', 'Node '.sprintf('%06d', $this->nodeid));
		}
	}

	protected function insertedKey ($insertid) {
		return $this->nodeid;
	}
}
