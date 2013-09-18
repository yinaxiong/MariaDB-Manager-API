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
 * The Command class is not instantiated, but is used for checking.
 * 
 */

namespace SkySQL\SCDS\API\models;

class Schedule extends EntityModel {
	protected static $setkeyvalues = false;
	
	protected static $classname = __CLASS__;

	protected $ordinaryname = 'command';
	protected static $headername = 'Command';
	
	protected static $getAllCTO = array('command');
	
	protected static $keys = array(
		'scheduleid' => array('sqlname' => 'Command', 'type'  => 'int')
	);
	
	protected static $fields = array(
		'systemid' => array('sqlname' => 'SystemID', 'type'  => 'int', 'default' => 0, 'insertonly' => true),
		'nodeid' => array('sqlname' => 'NodeID', 'type'  => 'int', 'default' => 0, 'insertonly' => true),
		'username' => array('sqlname' => 'UserName', 'type'  => 'varchar', 'default' => '', 'insertonly' => true),
		'command' => array('sqlname' => 'Command', 'default' => '', 'insertonly' => true),
		'level' => array('sqlname' => 'BackupLevel', 'type'  => 'int', 'default' => 0, 'insertonly' => true),
		'parameters' => array('sqlname' => 'Params', 'type'  => 'text', 'default' => '', 'insertonly' => true),
		'icalentry' => array('sqlname' => 'iCalEntry', 'type' => 'text', 'default' => 'running'),
		'nextstart' => array('sqlname' => 'NextStart', 'default' => '', 'insertonly' => true),
		'atjobnumber' => array('sqlname' => 'ATJobNumber', 'default' => 0, 'insertonly' => true),
		'created' => array('sqlname' => 'Created', 'insertonly' => true),
		'updated' => array('sqlname' => 'Updated', 'insertonly' => true)
	);
	
	public function __construct ($scheduleid=0) {
		$this->scheduleid = $scheduleid;
	}
	
	public function insertOnCommand ($command) {
		$this->command = $command;
		parent::insert(false);
		self::fixDate($this);
	}

	protected function insertedKey ($insertid) {
		$this->scheduleid = $insertid;
		return $insertid;
	}

	public function updateJobNumber ($number) {
		$database = AdminDatabase::getInstance();
		$update = $database->prepare("UPDATE Schedule SET ATJobNumber = :atjobnumber, NextStart = :nextstart WHERE ScheduleID = :scheduleid");
		$update->execute(array(':atjobnumber' => $number, ':nextstart' => $this->nextstart, ':scheduleid' => $this->scheduleid));
		$this->atjobnumber = $number;
	}
	
	public function makeTask () {
		
	}
	
	protected function validateInsert () {
		$request = Request::getInstance();
		foreach (array('systemid','nodeid','username') as $name) {
			if (empty($this->bind[':'.$name])) $errors[] = "Value for $name is required to schedule a command";
		}
		if (isset($errors)) $request->sendErrorResponse($errors, 400);
		$this->node = NodeManager::getInstance()->getByID($this->bind[':systemid'], $this->bind[':nodeid']);
		if (!$this->node) $request->sendErrorResponse("No node with system ID {$this->bind[':systemid']} and node ID {$this->bind[':nodeid']}", 400);
		if (!$this->icalentry) $request->sendErrorResponse("Cannot create a schedule without an iCalendar specification", 400);
		$this->processCalendarEntry();
		$this->setInsertValue('command', $this->command);
	}
	
	protected function processCalendarEntry () {
		$calines = explode('|', $this->icalentry);
		$lastone = count($calines) - 1;
		foreach ($calines as $i=>$line) {
			$parts = explode(':', $line, 2);
			if (0 == $i AND ('BEGIN' != $parts[0] OR 'VEVENT' != $parts[1])) $errors[] = "iCalendar event should start with BEGIN:VEVENT";
			if ($lastone == $i AND ('END' != $parts[0] OR 'VEVENT' != $parts[1])) $errors[] = "iCalendar event should end with END:VEVENT";
			if ('DTSTART' == $parts[0]) $dtstart = $parts[1];
			elseif ('RRULE' == $parts[0]) $rrule = $parts[1];
		}
		if (empty($dtstart)) {
			$dtstart = $this->calendarDate();
		}
		if (!preg_match('/^\d{8}T\d{6}Z$/', $dtstart)) {
			$errors[] = "Start date $dtstart for schedule incorrectly formatted";
		}
		if (isset($errors)) Request::getInstance()->sendErrorResponse($errors,400);
		$this->updateNextStart($dtstart, $rrule);
		$this->state = 'scheduled';
	}
	
	protected function updateNextStart ($dtstart, $rrule) {
		$event = new When();
		$event->recur($dtstart)->rrule($rrule);
		$this->nextstart = date('Y-m-d H:i:s', $event->nextAfter()->getTimeStamp());
		$this->runatonce = $event->alreadyDue();
	}
	
	// Optional parameters are fromdate and todate, comma separated, in $args[0]
	protected static function specialSelected ($args) {
		$selectors = explode(',', @$args[0]);
		foreach ($selectors as $selector) {
			$unixtime = strtotime($selector);
			if ($unixtime) $dates[] = date('Y-m-d H:i:s', $unixtime);
		}
		if (isset($dates)) {
			$bind[":startdate"] = $dates[0];
			if (1 == count($dates)) {
				$where[] = "started >= :startdate";
			}
			else {
				$where[] = "started >= :startdate AND started <= :enddate";
				$bind[":enddate"] = $dates[1];
			}
		}
		return array((array) @$where, (array) @$bind);
	}
}
