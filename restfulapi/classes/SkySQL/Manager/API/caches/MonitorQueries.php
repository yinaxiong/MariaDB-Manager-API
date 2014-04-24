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
 * Date: December 2013
 * 
 * The MonitorQueries class provides a cache of requests for monitor data, so
 * that an If-Modified-Since request can be handled effectively when the same
 * request is repeated.
 * 
 */

namespace SkySQL\Manager\API\caches;

use SkySQL\COMMON\CACHE\CachedSingleton;
use SkySQL\COMMON\CACHE\SimpleCache;

class MonitorQueries {
	protected static $instance = null;
	
	protected $cache = null;
	protected $queries = array();

	public static function getInstance () {
		return self::$instance instanceof self ? self::$instance : self::$instance = new self(); // parent::getCachedSingleton(__CLASS__);
	}
	
	protected function __construct () {
		$this->cache = new SimpleCache('MonitorQueries');
	}
	
	public function newQuery ($monitorid, $systemid, $nodeid, $finish, $count, $interval, $average) {
		$newdata = serialize((string) $finish);
		$this->cache->save($newdata, "$monitorid-$systemid-$nodeid-$count-$interval");
		// $this->queries[$finish][$monitorid][$systemid][$nodeid][$count][$interval][$average] = 1;
		// $this->cacheNow();
	}
	
	public function hasBeenDone ($monitorid, $systemid, $nodeid, $finish, $count, $interval, $average) {
		$cached = $this->cache->get("$monitorid-$systemid-$nodeid-$count-$interval");
		return ((int) unserialize($cached) == (int) $finish);
		// return isset($this->queries[$finish][$monitorid][$systemid][$nodeid][$count][$interval][$average]);
	}
	
	public function newData ($monitorids, $systemid, $nodeid, $timestamp) {
		return;
		foreach (array_keys($this->queries) as $finish) {
			if ($finish < (time() - 3600*24)) unset($this->queries[$finish]);
			elseif ($timestamp < $finish) foreach ($monitorids as $monitorid) {
				if (isset($this->queries[$finish][$monitorid][$systemid][$nodeid])) {
					unset($this->queries[$finish][$monitorid][$systemid][$nodeid]);
				}
			}
		}
		$this->cacheNow();
	}
}