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
 * The MonitorManager class caches all Monitor classes and manipulates them
 * 
 */

namespace SkySQL\SCDS\API\managers;

use SkySQL\SCDS\API\models\Monitor;

class MonitorManager extends EntityManager {
	protected static $instance = null;
	protected $monitors = array();
	
	protected function __construct () {
		foreach (Monitor::getAll() as $monitor) {
			$this->monitors[$monitor->id] = $monitor;
		}
	}
	
	public static function getInstance () {
		return self::$instance instanceof self ? self::$instance : self::$instance = new self();
	}
	
	public function getByName ($name) {
		foreach ($this->monitors as $monitor) {
			if (0 == strcasecmp(substr($monitor->name,0,strlen($name)), $name)) return $monitor;
		}
		return null;
	}
	
	public function getByID ($id) {
		return isset($this->monitors[$id]) ? $this->monitors[$id] : null;
	}
	
	public function getAll () {
		return array_values($this->monitors);
	}
	
	public function createMonitor () {
		$this->clearCache();
		$monitor = new Monitor(null);
		$monitor->insert();
	}
	
	public function updateMonitor ($id) {
		$this->clearCache();
		$monitor = new Monitor($id);
		$monitor->update();
	}
	
	public function deleteMonitor ($id) {
		$monitor = new Monitor($id);
		if (isset($this->monitors[$id])) unset($this->monitors[$id]);
		$this->clearCache();
		$monitor->delete();
	}
}