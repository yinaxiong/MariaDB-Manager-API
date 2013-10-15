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
 * Date: June 2013
 * 
 * The Property class is only used to validate the fields for properties.
 * 
 */

namespace SkySQL\SCDS\API\models;

abstract class Property extends EntityModel {
	protected static $setkeyvalues = true;
	
	protected static $classname = __CLASS__;

	protected $ordinaryname = 'property';
	protected static $headername = 'Property';
	
	protected static $keys = array(
		'systemid' => array('sqlname' => 'SystemID', 'type' => 'int'),
		'username' => array('sqlname' => 'UserName', 'type' => 'varchar'),
		'appid' => array('sqlname' => 'ApplicationID', 'type' => 'int'),
		'property' => array('sqlname' => 'Property', 'type' => 'varchar')
	);

	protected static $fields = array(
		'updated' => array('sqlname' => 'Updated', 'desc' => 'Last date the system record was updated', 'forced' => 'datetime'),
		'value' => array('sqlname' => 'Value', 'type' => 'text', 'desc' => 'Value of a property', 'default' => '')
	);
	
}
