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
 * Date: June 2013
 * 
 * The SystemProperties class within the API implements calls to handle
 * system properties.
 * 
 */

namespace SkySQL\SCDS\API\controllers;

use SkySQL\SCDS\API\managers\SystemManager;
use SkySQL\SCDS\API\managers\SystemPropertyManager;
use SkySQL\SCDS\API\models\Property;

class SystemProperties extends SystemNodeCommon {
	
	public function __construct ($controller) {
		parent::__construct($controller);
		Property::checkLegal();
	}
	
	public function setSystemProperty ($uriparts) {
		$property = $this->checkSystemIDGetProperty($uriparts);	// Sets $this->systemid;
		$value = $this->getParam('PUT', 'value');
		SystemPropertyManager::getInstance()->setProperty($this->systemid, $property, $value);
	}
	
	public function deleteSystemProperty ($uriparts) {
		$property = $this->checkSystemIDGetProperty($uriparts);	// Sets $this->systemid;
		SystemPropertyManager::getInstance()->deleteProperty($this->systemid, $property);
	}
	
	public function getSystemProperty ($uriparts) {
		$property = $this->checkSystemIDGetProperty($uriparts);	// Sets $this->systemid;
		return SystemPropertyManager::getInstance()->getProperty($this->systemid, $property);
	}
	
	protected function checkSystemIDGetProperty ($uriparts) {
		$this->systemid = (int) $uriparts[1];
		if (SystemManager::getInstance()->getByID($this->systemid)) return $uriparts[3];
		$this->sendErrorResponse("No system with ID $this->systemid", 404);
	}
}