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
 * The NodeState class models a node state, information that is provided by the monitor.
 * 
 */

namespace SkySQL\SCDS\API\models;

class NodeState extends EntityModel {
	protected static $setkeyvalues = true;
	
	protected static $classname = __CLASS__;
	protected $ordinaryname = 'node state';
	
	protected static $updateSQL = 'UPDATE NodeStates SET %s WHERE State = :state';
	protected static $countSQL = 'SELECT COUNT(*) FROM NodeStates WHERE State = :state';
	protected static $insertSQL = 'INSERT INTO NodeStates (%s) VALUES (%s)';
	protected static $deleteSQL = 'DELETE FROM NodeStates WHERE State = :state';
	protected static $selectSQL = 'SELECT %s FROM NodeStates WHERE State = :state';
	protected static $selectAllSQL = 'SELECT %s FROM NodeStates %s ORDER BY State';
	
	protected static $getAllCTO = array('state');
	
	protected static $keys = array(
		'state' => 'State'
	);

	protected static $fields = array(
		'description' => array('sqlname' => 'Description', 'default' => ''),
		'icon' => array('sqlname' => 'Icon', 'default' => '')
	);
	
	public function __construct ($state) {
		$this->state = $state;
	}
}
