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
 * The Schedules class handles schedule related requests.
 * 
 */

namespace SkySQL\SCDS\API\controllers;

use SkySQL\SCDS\API\models\Schedule;

class Schedules extends TaskScheduleCommon {
	protected $defaultResponse = 'schedule';
	
	public function __construct ($controller) {
		parent::__construct($controller);
		Schedule::checkLegal();
	}

	public function getMultipleSchedules ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, '', true, 'fields');
		list($total, $schedules) = Schedule::select($this);
		$this->sendResponse(array('total' => $total, 'schedules' => $this->filterResults($schedules)));
	}
	
	public function getOneSchedule ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, '', false, 'fields');
		$schedule = Schedule::getByID((int) $uriparts[1]);
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
	
	public function getSelectedSchedules ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, '', false, 'fields');
		list($total, $schedules) = Schedule::select($this, trim(@$uriparts[1]));
		$this->sendResponse(array('total' => $total, 'schedules' => $this->filterResults($schedules)));
	}
	
	public function updateSchedule ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'Insert-Update', false, 'Fields for a schedule resource');
		$schedule = Schedule::getByID((int) $uriparts[1]);
		if (!$schedule) $this->sendResponse(array('updatecount' => 0, 'insertkey' => 0));
		if ($schedule->atjobnumber) exec ("atrm $schedule->atjobnumber");
		$schedule->setPropertiesFromParams();
		if ($schedule->icalentry) {
			$schedule->processCalendarEntry();
			$this->setRunAt($schedule);
			//if ($schedule->isDue()) $this->runScheduleNow($schedule);
		}
		$schedule->update();
	}
	
	public function deleteOneSchedule ($uriparts, $metadata='') {
		if ($metadata) return $this->returnMetadata ($metadata, 'Delete-Count');
		$schedule = Schedule::getByID((int) $uriparts[1]);
		if (!$schedule) $this->sendErrorResponse(sprintf("Schedule with ID '%s' does not exist", $uriparts[1]), 404);
		if ($schedule->atjobnumber) exec ("atrm $schedule->atjobnumber");
		$schedule->delete();
	}
}