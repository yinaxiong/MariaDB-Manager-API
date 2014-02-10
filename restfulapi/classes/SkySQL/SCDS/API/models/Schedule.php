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
 * The Command class is not instantiated, but is used for checking.
 * 
 */

namespace SkySQL\SCDS\API\models;

use DateTime;
use SkySQL\SCDS\API\Request;
use SkySQL\SCDS\API\models\Node;
use SkySQL\COMMON\WHEN\When;
use SkySQL\COMMON\AdminDatabase;

class Schedule extends EntityModel {
	protected static $setkeyvalues = false;
	
	protected static $managerclass = 'SkySQL\\SCDS\\API\\managers\\ScheduleManager';


	protected static $updateSQL = 'UPDATE Schedule SET %s WHERE ScheduleID = :scheduleid';
	protected static $countSQL = 'SELECT COUNT(*) FROM Schedule WHERE ScheduleID = :scheduleid';
	protected static $countAllSQL = 'SELECT COUNT(*) FROM Schedule';
	protected static $insertSQL = 'INSERT INTO Schedule (%s) VALUES (%s)';
	protected static $deleteSQL = 'DELETE FROM Schedule WHERE ScheduleID = :scheduleid';
	protected static $selectSQL = 'SELECT %s FROM Schedule WHERE ScheduleID = :scheduleid';
	protected static $selectAllSQL = 'SELECT %s FROM Schedule %s ORDER BY ScheduleID';

	protected static $getAllCTO = array('command');
	
	protected static $keys = array(
		'scheduleid' => array('sqlname' => 'ScheduleID', 'desc'  => 'ID for the schedule')
	);
	
	protected static $fields = array(
		'systemid' => array('sqlname' => 'SystemID', 'desc' => 'ID for the System on which scheduled command will run', 'default' => 0, 'insertonly' => true),
		'nodeid' => array('sqlname' => 'NodeID', 'desc'  => 'ID for the Node on which to run', 'default' => 0, 'insertonly' => true),
		'username' => array('sqlname' => 'UserName', 'desc'  => 'Username for user who created the schedule', 'default' => ''),
		'command' => array('sqlname' => 'Command', 'desc' => 'The name of the command to be run', 'default' => '', 'insertonly' => true),
		'parameters' => array('sqlname' => 'Params', 'desc'  => 'Parameters for the command scripts', 'default' => ''),
		'icalentry' => array('sqlname' => 'iCalEntry', 'desc' => 'An iCalendar entry defining when the scheduled command will run', 'default' => 'running'),
		'nextstart' => array('sqlname' => 'NextStart', 'desc' => 'The date and time at which the command will next be run', 'default' => '', 'validate' => 'datetime', 'insertonly' => true),
		'atjobnumber' => array('sqlname' => 'ATJobNumber', 'desc' => 'The number of the operation scheduled by the Linux at command', 'default' => 0, 'insertonly' => true),
		'created' => array('sqlname' => 'Created', 'desc' => 'The date and time the schedule was created', 'default' => '', 'validate' => 'datetime', 'insertonly' => true),
		'updated' => array('sqlname' => 'Updated', 'desc' => 'Last date the system record was updated', 'forced' => 'datetime')
	);
	
	protected $runatonce = false;
	protected $node = null;
	
	public function __construct ($scheduleid=0) {
		$this->scheduleid = $scheduleid;
	}
	
	protected function requestURI () {
		return "schedule/$this->scheduleid";
	}
	
	public function insertOnCommand ($command) {
		$this->command = $command;
		parent::insert(false);
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
		$task = new Task();
		$task->copyProperties($this);
		unset($task->updated);
		$task->scheduleid = $this->scheduleid;
		$task->setNodeData($this->systemid, $this->nodeid);
		return $task;
	}
	
	protected function validateInsert () {
		$request = Request::getInstance();
		foreach (array('systemid','nodeid','username') as $name) {
			if (empty($this->bind[':'.$name])) $errors[] = "Value for $name is required to schedule a command";
		}
		if (isset($errors)) $request->sendErrorResponse($errors, 400);
		$this->node = Node::getByID($this->bind[':systemid'], $this->bind[':nodeid']);
		if (!$this->node) $request->sendErrorResponse("No node with system ID {$this->bind[':systemid']} and node ID {$this->bind[':nodeid']}", 400);
		if (!$this->icalentry) $request->sendErrorResponse("Cannot create a schedule without an iCalendar specification", 400);
		$this->processCalendarEntry();
		$this->setInsertValue('command', $this->command);
		$this->setCorrectFormatDateWithDefault('created');
		if ($request->compareVersion('1.0', 'gt')) $this->processParameters();
		else $this->removeSensitiveParameters();
	}
	
	protected function validateUpdate () {
		$this->processCalendarEntry();
		$this->removeSensitiveParameters();
	}
	
	public function processCalendarEntry () {
		$calines = preg_split('/(\R|\|)/', $this->icalentry);
		while (!end($calines)) array_pop($calines);
		$this->icalentry = implode("\r\n", $calines);
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
		$this->updateNextStart($dtstart, @$rrule, @$this->nextstart);
	}
	
	protected function updateNextStart ($dtstart, $rrule, $nextstart) {
		$event = new When();
		$event->recur($dtstart);
		if ($rrule) $event->rrule($rrule);
		$unixtime = $nextstart ? strtotime($nextstart) : false;
		$nextevent = $event->nextAfter($unixtime ? new DateTime("@$unixtime") : null);
		$this->setInsertValue('nextstart', date('Y-m-d H:i:s', ($nextevent instanceof DateTime ? $nextevent->getTimeStamp() : 0)));
		$this->runatonce = $event->alreadyDue();
	}
	
	public function isDue () {
		return $this->runatonce;
	}
	
	// Optional parameters are fromdate and todate, comma separated, in $args[0]
	protected static function specialSelected ($args) {
		return parent::dateRange(@$args[0], 'Updated', 'schedules');
	}
}
