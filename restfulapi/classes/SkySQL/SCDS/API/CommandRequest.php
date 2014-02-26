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
 * The CommandRequest class is the main controller for the API, specific to 
 * requests made by directly running PHP and the API.  It specialises the 
 * abstract Request class.
 * 
 */

namespace SkySQL\SCDS\API;

if (basename(@$_SERVER['REQUEST_URI']) == basename(__FILE__)) die ('This software is for use within a larger system');

final class CommandRequest extends Request {
	protected static $requestclass = __CLASS__;
	protected static $instance = null;
	
	protected $suppress = true;
	
	public static function getInstance() {
		return self::$instance instanceof self ? self::$instance : self::$instance = new self();
	}
	
	protected function __construct () {
		global $argv;
		$this->requestmethod = @$argv[1];
		$this->requestviapost = true;
		$this->getHeaders();
		$this->checkHeaders();
		$this->urlencoded = false;
		if (isset($argv[3])) {
			$parameters = $this->decodeJsonOrQueryString($argv[3]);
			if (is_array($parameters)) $_POST = $parameters;
	        $_POST['suppress_response_codes'] = 'true';
		}
		parent::__construct();
	}

	protected function checkSecurity () {
		// Do nothing when called from command line or script
	}

	protected function getHeaders () {
		for ($i = 4; isset($argv[$i]); $i++) {
			$parts = explode(':', $argv[$i], 2);
			if (2 == count($parts)) $this->headers[$parts[0]] = trim($parts[1]);
		}
	}

	protected function sendHeaders ($status, $requestURI='') {
		// Send no headers when called from command line or script
		return HTTP_PROTOCOL.' '.$status.' '.(isset(self::$codes[$status]) ? self::$codes[$status] : '');
	}

	protected function getURI () {
		global $argv;
		return trim(@$argv[2], "/ \t\n\r\0\x0B");
	}
	
	protected function handleAccept () {
		$this->accept = 'application/json';
	}
}
