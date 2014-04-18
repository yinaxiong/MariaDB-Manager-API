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
 * The StandardRequest class is the main controller for the API when the request
 * is made by a standard HTTP request.  It specialises the abstract Request class.
 * 
 */

namespace SkySQL\Manager\API;

if (basename(@$_SERVER['REQUEST_URI']) == basename(__FILE__)) die ('This software is for use within a larger system');

final class StandardRequest extends Request {
	protected static $requestclass = __CLASS__;
	protected static $instance = null;
	
	public static function getInstance() {
		return self::$instance instanceof self ? self::$instance : self::$instance = new self();
	}
	
	protected function __construct () {
		$this->requestmethod = $_SERVER['REQUEST_METHOD'];
		parent::__construct();
	}
	
	protected function getHeaders () {
		$this->headers = apache_request_headers();
	}
	
	protected function processRequestParameters () {
		$rawparameters = file_get_contents("php://input");
		if ('PUT' == $this->requestmethod OR 'DELETE' == $this->requestmethod) {
			if ('1.0' == $this->requestversion) {
				$parameters = $this->decodeJsonOrQueryString($rawparameters);
				if (is_array($parameters)) $this->putdata = $parameters;
			}
			else {
				if ($this->urlencoded) parse_str($rawparameters, $this->putdata);
				else $this->putdata = json_decode($rawparameters, true);
			}
		}
		elseif ('POST' == $this->requestmethod AND !$this->urlencoded) {
			$_POST = json_decode($rawparameters, true);
		}
	}
}
