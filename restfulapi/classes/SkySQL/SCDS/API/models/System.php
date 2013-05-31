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
 * The System class models a cluster of database servers (nodes).
 * 
 */

namespace SkySQL\SCDS\API\models;

class System extends EntityModel {
	protected static $setkeyvalues = true;
	
	protected static $classname = __CLASS__;

	protected $ordinaryname = 'system';
	
	protected static $updateSQL = 'UPDATE System SET %s WHERE SystemID = :system';
	protected static $countSQL = 'SELECT COUNT(*) FROM System WHERE SystemID = :system';
	protected static $insertSQL = 'INSERT INTO System (%s) VALUES (%s)';
	protected static $deleteSQL = 'DELETE FROM System WHERE SystemID = :system';
	protected static $selectSQL = 'SELECT %s FROM System WHERE SystemID = :system';
	protected static $selectAllSQL = 'SELECT %s FROM System %s';
	
	protected static $getAllCTO = array('system');
	
	protected static $keys = array(
		'system' => 'SystemID'
	);

	protected static $fields = array(
		'name' => array('sqlname' => 'SystemName', 'default' => ''),
		'startDate' => array('sqlname' => 'InitialStart', 'default' => ''),
		'lastAccess' => array('sqlname' => 'LastAccess', 'default' => ''),
		'state' => array('sqlname' => 'State', 'default' => '')
	);
	
	public function __construct ($systemid) {
		$this->system = $systemid;
	}
	
	protected function validateInsert (&$bind, &$insname, &$insvalue) {
		if (empty($bind[':name'])) {
			$bind[':name'] = 'System '.sprintf('%06d', $this->system);
			$insname[] = 'SystemName';
			$insvalue[] = ':name';
		}
		if (empty($bind[':startDate'])) {
			$bind[':startDate'] = date('Y-m-d H:i:s');
			$insname[] = 'InitialStart';
			$insvalue[] = ':startDate';
		}
		if (empty($bind[':lastAccess'])) {
			$bind[':lastAccess'] = date('Y-m-d H:i:s');
			$insname[] = 'LastAccess';
			$insvalue[] = ':lastAccess';
		}
	}
	
	protected function validateUpdate (&$bind, &$setters) {
		if (isset($bind[':startDate'])) $bind[':startDate'] = date('Y-m-d H:i:s', strtotime($bind[':startDate']));
		if (isset($bind[':lastAccess'])) $bind[':lastAccess'] = date('Y-m-d H:i:s', strtotime($bind[':lastAccess']));
	}

	protected function insertedKey ($insertid) {
		return $this->system;
	}
}
