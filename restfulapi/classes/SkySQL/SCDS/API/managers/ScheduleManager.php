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
 * The ScheduleManager class caches all Schedule objects and manipulates them
 * 
 */

namespace SkySQL\SCDS\API\managers;

use SkySQL\SCDS\API\models\Schedule;

class ScheduleManager extends EntityManager {
	protected static $instance = null;
	protected $schedules = array();
	
	protected function __construct () {
		$schedules = Schedule::getAll();
		foreach ($schedules as $schedule) $this->schedules[$schedule->scheduleid] = $schedule;
	}
	
	public static function getInstance () {
		return self::$instance instanceof self ? self::$instance : self::$instance = parent::getCachedSingleton(__CLASS__);
	}
	
	public function getByID ($id) {
		return isset($this->schedules[$id]) ? $this->schedules[$id] : null;
	}
	
	public function getAll () {
		return $this->schedules;
	}
	
	public function createSchedule () {
		$schedule = new Schedule();
		$schedule->insert();
		// Above method does not return - clears cache, sends a response and exits
	}
	
	public function updateSchedule ($id) {
		$schedule = new Schedule($id);
		$schedule->update();
		// Above method does not return - clears cache, sends a response and exits
	}
	
	public function deleteSchedule ($id) {
		$schedule = new Schedule($id);
		if (isset($this->schedules[$id])) unset($this->schedules[$id]);
		$schedule->delete();
		// Above method does not return - clears cache, sends a response and exits
	}
}