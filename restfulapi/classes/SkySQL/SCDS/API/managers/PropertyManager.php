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
 * The PropertyManager class is the abstract class that does much of the work for
 * specific property managers.
 * 
 */

namespace SkySQL\SCDS\API\managers;

use SkySQL\COMMON\AdminDatabase;
use SkySQL\SCDS\API\Request;

abstract class PropertyManager extends EntityManager {

	public function setProperty ($id, $property, $value) {
		$bind = $this->makeBind($id, $property);
		$bind[':value'] = $value;
		$database = AdminDatabase::getInstance();
		$database->startImmediateTransaction();
		$update = $database->prepare($this->updateSQL);
		$update->execute($bind);
		$counter = $update->rowCount();
		$request = Request::getInstance();
		if (0 == $counter) {
			$insert = $database->prepare($this->insertSQL);
			$insert->execute($bind);
			$this->finalise();
			$request->sendResponse(array('updatecount' => 0,  'insertkey' => $property));
		}
		$this->finalise();
		$request->sendResponse(array('updatecount' => $counter, 'insertkey' => ''));
	}
	
	protected function finalise () {
		AdminDatabase::getInstance()->commitTransaction();
		$this->clearCache();
	}
	
	public function deleteProperty ($id, $property) {
		$delete = AdminDatabase::getInstance()->prepare($this->deleteSQL);
		$delete->execute($this->makeBind($id, $property));
		$counter = $delete->rowCount();
		$request = Request::getInstance();
		if ($counter) {
			$this->clearCache();
			$request->sendResponse(array('deletecount' => $counter));
		}
		else $request->sendErrorResponse("Delete $this->name property did not match any $this->name property", 404);
	}
	
	public function getProperty ($id, $property) {
		$request = Request::getInstance();
		if (isset($this->properties[$id][$property])) {
			$request->sendResponse(array("{$this->name}property" => array($property => $this->properties[$id][$property])));
		}
		else $request->sendErrorResponse("Unable to find $this->name property called $property for $this->name $id", 404);
	}
	
	public function getAllProperties ($id) {
		foreach ((array) @$this->properties[$id] as $property=>$value) {
			$results[$property] = $value;
		}
		return isset($results) ? $results : null;
	}
	
	public function deleteAllProperties ($id) {
		$delete = AdminDatabase::getInstance()->prepare($this->deleteAllSQL);
		$delete->execute(array(':id' => $id));
	}
	
	protected function makeBind ($id, $property) {
		return array(
			':id' => $id,
			':property' => $property
		);
	}
}