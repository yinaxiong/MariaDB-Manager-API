<?php

/*
 * Part of the SCDS API.
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
 * Date: February 2013
 * 
 * The Application class within the API implements calls to get application properties
 * 
 */

namespace SkySQL\SCDS\API;

use \PDO;

class Application extends SystemNodeCommon {

	public function setApplicationProperty ($uriparts) {
		$this->appid = (int) $uriparts[1];
		$property = $uriparts[3];
		$value = $this->getParam('PUT', 'value');
		$bind = array(
			':appid' => $this->appid,
			':property' => $property,
			':value' => $value
		);
		$this->startImmediateTransaction();
		$update = $this->db->prepare('UPDATE ApplicationProperties SET Value = :value WHERE ApplicationID = :appid AND Property = :property');
		$update->execute($bind);
		$counter = $update->rowCount();
		if (0 == $counter) {
			$insert = $this->db->prepare('INSERT INTO ApplicationProperties (ApplicationID, Property, Value)
				VALUES (:appid, :property, :value)');
			$insert->execute($bind);
			$this->sendResponse(array('updatecount' => 0,  'insertkey' => $property));
		}
		$this->sendResponse(array('updatecount' => $counter, 'insertkey' => ''));
	}
	
	public function deleteApplicationProperty ($uriparts) {
		$this->appid = (int) $uriparts[1];
		$property = $uriparts[3];
		$delete = $this->db->prepare('DELETE FROM ApplicationProperties WHERE ApplicationID = :appid AND Property = :property');
		$delete->execute(array(
			':appid' => $this->appid,
			':property' => $property
		));
		$counter = $delete->rowCount();
		if ($counter) $this->sendResponse(array('deletecount' => $counter));
		else $this->sendErrorResponse('Delete application property did not match any application property', 404);
	}
	
	public function getApplicationProperty ($uriparts) {
		$this->appid = (int) $uriparts[1];
		$property = urldecode($uriparts[3]);
		$retrieve = $this->db->prepare('SELECT Value FROM ApplicationProperties WHERE ApplicationID = :appid AND Property = :property');
		$retrieve->execute(array(
			':appid' => $this->appid,
			':property' => $property
		));
		$data = $retrieve->fetch(PDO::FETCH_COLUMN);
		if ($data) {
			$this->sendResponse(array('applicationproperty' => array($property => $data)));
		}
		$this->sendErrorResponse('Application Property not found', 404);
	}
}