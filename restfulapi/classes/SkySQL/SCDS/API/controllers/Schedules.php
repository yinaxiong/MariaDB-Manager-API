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
 * Date: February 2013
 * 
 * The Schedules class handles schedule related requests.
 * 
 */

namespace SkySQL\SCDS\API\controllers;

use SkySQL\SCDS\API\models\Schedule;

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
		$schedule = Schedule::getByID(array('scheduleid' => (int) $uriparts[1]));
		$this->sendResponse(array('schedule' => $this->filterSingleResult($schedule)));
	}
	
	public function getSelectedSchedules ($uriparts) {
		list($total, $schedules) = Schedule::select($this, trim(urldecode($uriparts[1])));
		$this->sendResponse(array('total' => $total, 'schedules' => $this->filterResults($schedules)));
	}
	
	public function updateSchedule ($uriparts) {
		$schedule = new Schedule((int) $uriparts[1]);
		$schedule->loadData();
		if ($schedule->atjobnumber) exec ("atrm $schedule->atjobnumber");
		$schedule->update(false);
		if ($schedule->icalentry) {
			$this->setRunAt($schedule);
			if ($schedule->isDue()) $this->execute($schedule);
		}
	}
}