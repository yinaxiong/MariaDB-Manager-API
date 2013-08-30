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
 * The Command class is not instantiated, but is used for checking.
 * 
 */

namespace SkySQL\SCDS\API\models;

class Schedule extends EntityModel {
	protected static $setkeyvalues = false;
	
	protected static $classname = __CLASS__;

	protected $ordinaryname = 'command';
	protected static $headername = 'Command';
	
	protected static $getAllCTO = array('command');
	
	protected static $keys = array(
		'scheduleid' => array('sqlname' => 'Command', 'type'  => 'int')
	);
	
	protected static $fields = array(
		'systemid' => array('sqlname' => 'SystemID', 'type'  => 'int', 'default' => 0, 'insertonly' => true),
		'nodeid' => array('sqlname' => 'NodeID', 'type'  => 'int', 'default' => 0, 'insertonly' => true),
		'username' => array('sqlname' => 'UserName', 'type'  => 'varchar', 'default' => '', 'insertonly' => true),
		'command' => array('sqlname' => 'Command', 'default' => '', 'insertonly' => true),
		'level' => array('sqlname' => 'BackupLevel', 'type'  => 'int', 'default' => 0, 'insertonly' => true),
		'parameters' => array('sqlname' => 'Params', 'type'  => 'text', 'default' => '', 'insertonly' => true),
		'icalentry' => array('sqlname' => 'iCalEntry', 'type' => 'text', 'default' => 'running'),
		'nextstart' => array('sqlname' => 'NextStart', 'default' => '', 'insertonly' => true),
		'atjobnumber' => array('sqlname' => 'ATJobNumber', 'default' => 0, 'insertonly' => true),
		'created' => array('sqlname' => 'Created', 'insertonly' => true),
		'updated' => array('sqlname' => 'Updated', 'insertonly' => true)
	);
}
