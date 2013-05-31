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
	protected $ordinaryname = 'node';
	
	protected static $updateSQL = 'UPDATE Node SET %s WHERE SystemID = :system AND NodeID = :id';
	protected static $countSQL = 'SELECT COUNT(*) FROM Node WHERE SystemID = :system AND NodeID = :id';
	protected static $insertSQL = 'INSERT INTO Node (%s) VALUES (%s)';
	protected static $deleteSQL = 'DELETE FROM Node WHERE SystemID = :system AND NodeID = :id';
	protected static $selectSQL = 'SELECT %s FROM Node WHERE SystemID = :system AND NodeID = :id';
	protected static $selectAllSQL = 'SELECT %s FROM Node %s ORDER BY SystemID, NodeID';
	
	protected static $getAllCTO = array('system', 'id');
	
	protected static $keys = array(
		'system' => 'SystemID',
		'id' => 'NodeID'
	);

	protected static $fields = array(
		'name' => array('sqlname' => 'NodeName', 'default' => ''),
		'state' => array('sqlname' => 'State', 'default' => 0),
		'hostname' => array('sqlname' => 'Hostname', 'default' => ''),
		'publicIP' => array('sqlname' => 'PublicIP', 'default' => ''),
		'privateIP' => array('sqlname' => 'PrivateIP', 'default' => ''),
		'instanceID' => array('sqlname' => 'InstanceID', 'default' => ''),
		'username' => array('sqlname' => 'Username', 'default' => ''),
		'passwd' => array('sqlname' => 'passwd', 'default' => '')
	);
	
	public function __construct ($systemid, $nodeid=0) {
		$this->system = $systemid;
		$this->id = $nodeid;
	}
	
	public static function getNodeStates ($selector) {
		if ($selector) {
			if (preg_match('/[0-9]+/', $selector)) {
				$nodestates = isset(API::$nodestates[(int) $selector]) ? API::merger(API::$nodestates[(int) $selector], $selector) : array();
			}
			else {
				$nodestates = array();
				foreach (API::$nodestates as $state=>$properties) {
					if (0 == strncasecmp($selector, $properties['description'], strlen($selector))) {
						$nodestates[] = API::merger($properties, $state);
					}
				}
			}
		}
		else $nodestates = API::mergeStates(API::$nodestates);
		return $nodestates;
	}
	
	protected function keyComplete () {
		return $this->id ? true : false;
	}
	
	protected function makeNewKey (&$bind) {
		$highest = AdminDatabase::getInstance()->prepare('SELECT MAX(NodeID) FROM Node WHERE SystemID = :system');
		$highest->execute(array(':system' => $this->system));
		$this->id = 1 + (int) $highest->fetch(PDO::FETCH_COLUMN);
		$bind[':id'] = $this->id;
	}
	
	protected function setDefaults (&$bind, &$insname, &$insvalue) {
		if (empty($bind[':name'])) {
			$bind[':name'] = 'Node '.sprintf('%06d', $this->id);
			$insname[] = 'NodeName';
			$insvalue[] = ':name';
		}
	}

	protected function insertedKey ($insertid) {
		return $this->id;
	}
}
