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
 * Date: February 2013
 * 
 * The Request class is the main controller for the API.
 * 
 * The $uriTable array defines the API RESTful interface, and links calls to
 * classes and methods.  The Request class puts this into effect.
 * 
 * The constructor splits up the model URIs into their constituent parts.
 * 
 * The doControl method first fetches the request URI and extracts the relevant
 * part, then explodes it into parts.  Then the request URI is compared with 
 * the model URIs to find what is to be done (or an error return).
 * 
 * The sendResponse method is provided for the benefit of the classes that 
 * implement the API.
 * 
 */

namespace SkySQL\SCDS\API;

if (basename(@$_SERVER['REQUEST_URI']) == basename(__FILE__)) die ('This software is for use within a larger system');

final class StandardRequest extends Request {
	protected static $requestclass = __CLASS__;
	protected static $instance = null;
	
	public static function getInstance() {
		return self::$instance instanceof self ? self::$instance : self::$instance = new self();
	}
	
	protected function __construct () {
		$this->getHeaders();
		if ('PUT' == $_SERVER['REQUEST_METHOD'] OR 'DELETE' == $_SERVER['REQUEST_METHOD']) {
			$rawput = file_get_contents("php://input");
			$dejson = json_decode($rawput, true);
			if (false === stripos($rawput, '=')) $dequery = $rawput;
			else {
				if ('application/x-www-form-urlencoded' == @$this->headers['Content-Type']) {
					parse_str($rawput, $dequery);
				}
				else $this->parse_str($rawput, $dequery);
			}
			$this->putdata = (is_null($dejson) OR (is_array($dequery) AND count($dejson) < count($dequery))) ? $dequery : $dejson;
			if (is_array($this->putdata)) foreach ($this->putdata as $key=>$value) {
				if (is_null($value)) $this->putdata[$key] = '';
			}
		}
		$this->requestmethod = $_SERVER['REQUEST_METHOD'];
		parent::__construct();
	}
	
	protected function getHeaders () {
		$this->headers = apache_request_headers();
	}
}
