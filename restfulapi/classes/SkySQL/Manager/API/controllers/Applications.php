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
 * Date: February 2013
 * 
 * The Application class within the API implements calls to get application properties
 * 
 */

namespace SkySQL\Manager\API\controllers;

use SkySQL\Manager\API\managers\ApplicationPropertyManager;
use SkySQL\Manager\API\models\Property;

class Applications extends SystemNodeCommon {
	protected $defaultResponse = 'applicationproperty';

	public function __construct ($controller) {
		parent::__construct($controller);
		Property::checkLegal();
	}

	public function setApplicationProperty ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'Insert-Update', false, 'value');
		$this->appid = (int) $uriparts[1];
		$property = $uriparts[3];
		$value = $this->getParam('PUT', 'value');
		ApplicationPropertyManager::getInstance()->setProperty($this->appid, $property, $value);
	}
	
	public function deleteApplicationProperty ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'Delete-Count', false, '');
		$this->appid = (int) $uriparts[1];
		$property = $uriparts[3];
		ApplicationPropertyManager::getInstance()->deleteProperty($this->appid, $property);
	}
	
	public function getApplicationProperty ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, '', false, '');
		$this->appid = (int) $uriparts[1];
		$property = $uriparts[3];
		return ApplicationPropertyManager::getInstance()->getProperty($this->appid, $property);
	}
}