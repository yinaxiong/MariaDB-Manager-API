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
 * The Schedules class handles schedule related requests.
 * 
 */

namespace SkySQL\SCDS\API\controllers;

use SkySQL\SCDS\API\models\Schedule;
use SkySQL\SCDS\API\managers\ScheduleManager;

class Schedules extends TaskScheduleCommon {
	
	public function __construct ($controller) {
		parent::__construct($controller);
		Schedule::checkLegal();
	}

	public function getMultipleSchedules () {
		list($total, $schedules) = Schedule::select($this);
		$this->sendResponse(array('total' => $total, 'schedules' => $this->filterResults($schedules)));
	}
	
	public function getOneSchedule ($uriparts) {
		$schedule = ScheduleManager::getInstance()->getByID((int) $uriparts[1]);
		if ($schedule) {
			if ($this->ifmodifiedsince < strtotime($schedule->updated)) $this->modified = true;
			if ($this->ifmodifiedsince AND !$this->modified) {
				header (HTTP_PROTOCOL.' 304 Not Modified');
				exit;
			}
		}
		else $this->sendErrorResponse(sprintf("No schedule with scheduleid '%d'", (int) $uriparts[1]), 404);
		$this->sendResponse(array('schedule' => $this->filterSingleResult($schedule)));
	}
	
	public function getSelectedSchedules ($uriparts) {
		list($total, $schedules) = Schedule::select($this, trim(@$uriparts[1]));
		$this->sendResponse(array('total' => $total, 'schedules' => $this->filterResults($schedules)));
	}
	
	public function updateSchedule ($uriparts) {
		$manager = ScheduleManager::getInstance();
		$schedule = $manager->getByID((int) $uriparts[1]);
		if (!$schedule) $this->sendResponse(array('updatecount' => 0, 'insertkey' => 0));
		if ($schedule->atjobnumber) exec ("atrm $schedule->atjobnumber");
		$schedule->setPropertiesFromParams();
		if ($schedule->icalentry) {
			$schedule->processCalendarEntry();
			$this->setRunAt($schedule);
			//if ($schedule->isDue()) $this->runScheduleNow($schedule);
		}
		$manager->updateSchedule((int) $uriparts[1]);
	}
	
	public function deleteOneSchedule ($uriparts) {
		$schedule = ScheduleManager::getInstance()->getByID((int) $uriparts[1]);
		if ($schedule->atjobnumber) exec ("atrm $schedule->atjobnumber");
		ScheduleManager::getInstance()->deleteSchedule((int) $uriparts[1]);
	}
}