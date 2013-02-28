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
 */

namespace SkySQL\SCDS\API;

use SkySQL\COMMON\AdminDatabase;
use \PDO as PDO;

abstract class ImplementAPI {
	protected $db = null;

	public function __construct () {
		$this->db = AdminDatabase::getInstance();
	}
	
	protected function sendResponse ($body='', $status=200, $content_type='application/json') {
		return Request::getInstance()->sendResponse($body, $status, $content_type);
	}

	protected function sendErrorResponse ($errors, $status=200, $content_type='application/json') {
		return Request::getInstance()->sendErrorResponse($errors, $status, $content_type);
	}
	
	protected function log ($data) {
		Request::getInstance()->log($data);
	}
}