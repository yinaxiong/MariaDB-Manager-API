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
	protected $allmonitors = array();
	protected $monitorsbyid = array();
	
	protected function __construct () {
		$this->allmonitors = Monitor::getAll();
		foreach ($this->allmonitors as $monitor) {
			$this->monitors[$monitor->systemtype][$monitor->monitor] = $monitor;
			$this->monitorsbyid[$monitor->monitorid] = $monitor;
		}
	}
	
	public static function getInstance () {
		return self::$instance instanceof self ? self::$instance : self::$instance = parent::getCachedSingleton(__CLASS__);
	}
	
	public function getByType ($systemtype) {
		return isset($this->monitors[$systemtype]) ? array_values($this->monitors[$systemtype]): array();
	}
	
	public function getByID ($systemtype, $id) {
		return isset($this->monitors[$systemtype][$id]) ? $this->monitors[$systemtype][$id] : null;
	}
	
	public function getByMonitorID ($id) {
		return isset($this->monitorsbyid[$id]) ? $this->monitorsbyid[$id] : null;
	}
	
	public function getAll () {
		return $this->allmonitors;
	}
	
	public function putMonitor ($systemtype, $key) {
		$monitor = new Monitor($systemtype, $key);
		$monitor->save();
	}
	
	public function deleteMonitor ($systemtype, $id) {
		$monitor = new Monitor($systemtype, $id);
		if (isset($this->monitors[$systemtype][$id])) unset($this->monitors[$systemtype][$id]);
		$monitor->delete();
	}
}