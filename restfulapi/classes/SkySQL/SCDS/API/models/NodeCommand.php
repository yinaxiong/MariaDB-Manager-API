<?php

/*
 ** Part of the MariaDB Manager API.
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
 * Copyright 2013 (c) SkySQL Corporation Ab
 * 
 * Author: Martin Brampton
 * Date: May 2013
 * 
 * The NodeCommand class is used only to represent node commands
 * 
 */

namespace SkySQL\SCDS\API\models;

use SkySQL\SCDS\API\managers\NodeCommandManager;

class NodeCommand extends EntityModel {
	protected static $setkeyvalues = false;

	protected static $managerclass = 'SkySQL\\SCDS\\API\\managers\\NodeCommandManager';

	protected static $updateSQL = 'UPDATE NodeCommands SET %s WHERE Command = :command AND SystemType = :systemtype AND State = :state';
	protected static $countSQL = 'SELECT COUNT(*) FROM NodeCommands WHERE Command = :command AND SystemType = :systemtype AND State = :state';
	protected static $countAllSQL = 'SELECT COUNT(*) FROM NodeCommands WHERE UIOrder IS NOT NULL';
	protected static $insertSQL = 'INSERT INTO NodeCommands (%s) VALUES (%s)';
	protected static $deleteSQL = 'DELETE FROM NodeCommands WHERE Command = :command AND SystemType = :systemtype AND State = :state';
	protected static $selectSQL = 'SELECT %s FROM NodeCommands WHERE Command = :command AND SystemType = :systemtype AND State = :state';
	protected static $selectAllSQL = 'SELECT %s FROM NodeCommands %s WHERE UIOrder IS NOT NULL ORDER BY SystemType, Command, State';

	protected static $getAllCTO = array('command', 'systemtype', 'state');
	
	protected static $keys = array(
		'command' => array('sqlname' => 'Command', 'desc' => 'Name of the command', 'type'  => 'varchar'),
		'systemtype' => array('sqlname' => 'SystemType', 'desc' => 'Identifier for the System Type for command execution', 'type'  => 'varchar'),
		'state' => array('sqlname' => 'State', 'desc' => 'State of the node for command execution', 'type'  => 'varchar')
	);
	
	protected static $fields = array(
		'description' => array('sqlname' => 'Description', 'desc' => 'Description of the command', 'type'  => 'varchar', 'default' => ''),
		'uiorder' => array('sqlname' => 'UIOrder', 'desc' => 'Ordering for the user interface', 'type'  => 'int', 'default' => 0),
		'steps' => array('sqlname' => 'Steps', 'desc' => 'Command steps, comma separated list of script names', 'type' => 'varchar', 'default' => '')
	);
	
	public function __construct ($command, $systemtype, $state) {
		$this->command = $command;
		$this->systemtype = $systemtype;
		$this->state = $state;
	}
	
	public static function getRunnable ($systemtype, $state) {
		return NodeCommandManager::getInstance()->getRunnable($systemtype, $state);
	}
}
