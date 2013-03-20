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

abstract class ImplementAPI {
	protected $db = null;
	protected $requestor = null;

	public function __construct ($requestor) {
		$this->db = AdminDatabase::getInstance();
		$this->requestor = $requestor;
	}
	
	protected function getParam ($arrname, $name, $def=null, $mask=0) {
		return $this->requestor->getParam($arrname, $name, $def, $mask);
	}
	
	protected function filterResults ($results) {
		$filter = $this->getParam('GET', 'show');
		if ($filter) {
			$filterwords = explode(',', $filter);
			foreach ($results as $key=>$value) $filtered[$key] = $this->filterWords($value, $filterwords);
			return $filtered;
		}
		else return $results;
	}
	
	protected function filterWords ($value, $words) {
		foreach ($words as $word) if (isset($value[$word])) $hits[] = $value[$word];
		return empty($hits) ? null : (1 < count($hits) ? $hits : $hits[0]);
	}
	
	protected function sendResponse ($body='', $status=200, $content_type='application/json') {
		return $this->requestor->sendResponse($body, $status, $content_type);
	}

	protected function sendErrorResponse ($errors, $status=200, $content_type='application/json') {
		return $this->requestor->sendErrorResponse($errors, $status, $content_type);
	}
	
	protected function log ($data) {
		$this->requestor->log($data);
	}
}