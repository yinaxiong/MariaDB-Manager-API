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
 * The SystemProperties class within the API implements calls to handle
 * system properties.
 * 
 */

namespace SkySQL\SCDS\API\controllers;

use SkySQL\SCDS\API\managers\NodeManager;
use SkySQL\SCDS\API\managers\ComponentPropertyManager;
use SkySQL\SCDS\API\models\Property;

class ComponentProperties extends SystemNodeCommon {
	protected $nodeid = 0;
	protected $component = '';
	
	public function __construct ($controller) {
		parent::__construct($controller);
		Property::checkLegal();
	}
	
	public function setComponentProperty ($uriparts) {
		$property = $this->checkNodeIDGetProperty($uriparts);	// Sets $this->systemid;
		$value = $this->getParam('PUT', 'value');
		ComponentPropertyManager::getInstance()->setComponentProperty($this->systemid, $this->nodeid, $this->component, $property, $value);
	}
	
	public function deleteComponentProperty ($uriparts) {
		$property = $this->checkNodeIDGetProperty($uriparts);	// Sets $this->systemid;
		ComponentPropertyManager::getInstance()->deleteComponentProperty($this->systemid, $this->nodeid, $this->component, $property);
	}
	
	public function getComponentProperty ($uriparts) {
		$property = $this->checkNodeIDGetProperty($uriparts);	// Sets $this->systemid;
		return ComponentPropertyManager::getInstance()->getComponentProperty($this->systemid, $this->nodeid, $this->component, $property);
	}
	
	protected function checkNodeIDGetProperty ($uriparts) {
		$this->systemid = (int) $uriparts[1];
		$this->nodeid = (int) $uriparts[3];
		$this->component = urldecode($uriparts[5]);
		if (NodeManager::getInstance()->getByID($this->systemid, $this->nodeid)) return urldecode($uriparts[7]);
		$this->sendErrorResponse(sprintf("No node with System ID '%s' and Node ID '%s'", $this->systemid, $this->nodeid), 404);
	}
}