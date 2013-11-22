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
 * Date: May 2013
 * 
 * The BasicCache class provides a general foundation for caching, and is
 * derived from the Aliro class aliroBasicCache which is GNU GPL code
 * that that is copyrighted by Aliro Software Limited (http://aliro.org).
 * 
 */

namespace SkySQL\COMMON\CACHE;

if (basename(@$_SERVER['REQUEST_URI']) == basename(__FILE__)) die ('This software is for use within a larger system');

define ('_API_CACHE_HANDLER_TYPE', 'Disk');

abstract class BasicCache {
	protected $sizelimit = 0;
	protected $timeout = 0;
	protected $handler = null;

	public function __construct () {
		$type = _API_CACHE_HANDLER_TYPE;
		$handlerclass = self::getHandlerClass($type);
		$this->handler = new $handlerclass($this->sizelimit, $this->timeout);
	}
	
	protected static function getHandlerClass ($type) {
		return __NAMESPACE__.'\\Cache'.$type.'Storage';
	}

	protected function getBasePath () {
		return $this->handler->getBasePath();
	}
	
	public function getTimeStamp () {
		return $this->handler->getTimeStamp();
	}

	abstract protected function getCachePath ($name);

	public function store ($object, $cachename='', $reportSizeError=true) {
		$path = $this->getCachePath($this->getCacheName($object, $cachename));
		if (is_object($object)) $object->aliroCacheTimer = time();
		else {
			$givendata = $object;
			$object = new stdClass();
			$object->aliroCacheData = $givendata;
			$object->aliroCacheTimer = -time();
		}
		$s = serialize($object);
		$s .= md5($s);
		$result = $this->handler->storeData ($path, $s, $reportSizeError);
		if (!$result) {
			//trigger_error(sprintf($this->T_('Cache failed on write, report size error %s, class %s, path %s'), (string) $reportSizeError, get_class($object), $path));
			$this->handler->delete($path);
		}
		return $result;
	}

	protected function getCacheName ($object, $cachename) {
		if ($cachename) return $cachename;
		if (is_object($object)) return get_class($object);
		trigger_error($this->T('Attempt to cache non-object without providing a name for the cache'));
	}
	
	protected function T_ ($string) {
		return function_exists('T_') ? T_($string) : $string;
	}
	
	public function retrieve ($class, $time_limit = 0) {
		// $timer = class_exists('aliroProfiler') ? new aliroProfiler() : null;
		$path = $this->getCachePath($class);
		$result = $this->handler->getData($path);
		// if ($result AND $timer) echo "<br />Loaded $class in ".$timer->getElapsed().' secs';
		return $result;
	}
	
	public static function deleteAll () {
		$handlerclass = self::getHandlerClass(_API_CACHE_HANDLER_TYPE);
		call_user_func(array($handlerclass, 'deleteAll'));
	}
}

