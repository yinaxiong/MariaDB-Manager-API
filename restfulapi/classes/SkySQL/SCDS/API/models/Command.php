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
 * Date: May 2013
 * 
 * The Command class is used only to manage command requests, and does not refer
 * to a specific stored entity.
 * 
 */

namespace SkySQL\SCDS\API\models;

class Command extends EntityModel {
	protected static $setkeyvalues = false;
	
	protected static $getAllCTO = array('command');
	
	protected static $keys = array(
		'command' => array('sqlname' => 'Command', 'type' => 'varchar', 'desc'  => 'Name of the Command')
	);
	
	protected static $fields = array(
		'systemid' => array('sqlname' => 'SystemID', 'type'  => 'int', 'desc' => 'ID for the System', 'default' => 0, 'insertonly' => true),
		'nodeid' => array('sqlname' => 'NodeID', 'type'  => 'int', 'desc' => 'ID for the Node', 'default' => 0, 'insertonly' => true),
		'username' => array('sqlname' => 'UserName', 'type'  => 'varchar', 'desc' => 'Username for user who issued command', 'default' => '', 'insertonly' => true),
		'parameters' => array('sqlname' => 'Params', 'type'  => 'text', 'desc' => 'Parameters for the command scripts', 'default' => '', 'insertonly' => true),
		'icalentry' => array('sqlname' => 'iCalEntry', 'type' => 'varchar', 'desc' => 'For a scheduled command, iCalendar Entry', 'default' => '', 'insertonly' => true),
		'state' => array('sqlname' => 'State', 'type' => 'int', 'desc' => 'State of node if command is to run', 'default' => ''),
		'steps' => array('sqlname' => 'Steps', 'type' => 'varchar', 'desc' => 'Command steps if command is to run', 'default' => 0)
	);
	
	public function __construct ($command) {
		$this->command = $command;
	}
}
