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
 * Date: June 2013
 * 
 * The RequestPrototypes class contains the data that defines what requests
 * can be handled by the API.  The information is usually cached by the
 * RequestParser class.
 * 
 * The $uriTable array defines the API RESTful interface, and links calls to
 * classes and methods.  The Request class puts this into effect, with the help
 * of the RequestParser class.
 * 
 */

namespace SkySQL\SCDS\API;

if (basename(@$_SERVER['REQUEST_URI']) == basename(__FILE__)) die ('This software is for use within a larger system');

class RequestPrototypes {
	protected $apibase = array();
	
	protected static $uriTableNeedsFix = true;
	// Longer URI patterns must precede similar shorter ones for correct functioning
	protected static $uriTable = array(
		'GET' => array(
			array('class' => 'Applications', 'method' => 'getApplicationProperty', 'uri' => 'application/<appid>/property/<property>', 'title' => 'Get an Application Property'),
			array('class' => 'SystemProperties', 'method' => 'getSystemProperty', 'uri' => 'system/<systemid>/property/<property>', 'title' => 'Get a System Property'),
			array('class' => 'SystemBackups', 'method' => 'getOneBackup', 'uri' => 'system/<systemid>/backup/<backupid>', 'title' => 'Get a Backup'),
			array('class' => 'SystemBackups', 'method' => 'getSystemBackups', 'uri' => 'system/<systemid>/backup', 'title' => 'Get Backups'),
			array('class' => 'SystemBackups', 'method' => 'getBackupStates', 'uri' => 'backupstate', 'title' => 'Get Backup States'),
			array('class' => 'Monitors', 'method' => 'monitorData', 'uri' => 'system/<systemid>/node/<nodeid>/monitor/<monitor>/data', 'title' => 'Get a Range of Node Monitor Data'),
			array('class' => 'Monitors', 'method' => 'monitorLatest', 'uri' => 'system/<systemid>/node/<nodeid>/monitor/<monitor>/latest', 'title' => 'Get the Latest Node Monitor Data'),
			array('class' => 'Monitors', 'method' => 'monitorData', 'uri' => 'system/<systemid>/monitor/<monitor>/data', 'title' => 'Get a Range of System Monitor Data'),
			array('class' => 'Monitors', 'method' => 'monitorLatest', 'uri' => 'system/<systemid>/monitor/<monitor>/latest', 'title' => 'Get the Latest System Monitor Data'),
			array('class' => 'Monitors', 'method' => 'getRawMonitorData', 'uri' => 'system/<systemid>/node/<nodeid>/monitor/<monitor>/rawdata', 'title' => 'Get Raw Monitor Node Data'),
			array('class' => 'Monitors', 'method' => 'getRawMonitorData', 'uri' => 'system/<systemid>/monitor/<monitor>/rawdata', 'title' => 'Get Raw Monitor System Data'),
			array('class' => 'ComponentProperties', 'method' => 'getComponentPropertyUpdated', 'uri' => 'system/<systemid>/node/<nodeid>/component/<component>/property/<property>/updated', 'title' => 'Get the Last Date a Component Property was Updated'),
			array('class' => 'ComponentProperties', 'method' => 'getComponentProperty', 'uri' => 'system/<systemid>/node/<nodeid>/component/<component>/property/<property>', 'title' => 'Get a Component Property'),
			array('class' => 'ComponentProperties', 'method' => 'getComponentProperties', 'uri' => 'system/<systemid>/node/<nodeid>/component/<component>', 'title' => 'Get All Properties for a Component'),
			array('class' => 'ComponentProperties', 'method' => 'getComponents', 'uri' => 'system/<systemid>/node/<nodeid>/component', 'title' => 'Get All Component Properties'),
			array('class' => 'SystemNodes', 'method' => 'getNodeField1', 'uri' => 'system/<systemid>/node/<nodeid>/field/<fieldname>', 'title' => 'Get a Field from a Node'),
			array('class' => 'SystemNodes', 'method' => 'getNodeField2', 'uri' => 'system/<systemid>/node/<nodeid>/field/<fieldname>/<fieldname>', 'title' => 'Get a Field from a Node'),
			array('class' => 'SystemNodes', 'method' => 'getNodeField3', 'uri' => 'system/<systemid>/node/<nodeid>/field/<fieldname>/<fieldname>/<fieldname>', 'title' => 'Get a Field from a Node'),
			array('class' => 'SystemNodes', 'method' => 'getProcessPlan', 'uri' => 'system/<systemid>/node/<nodeid>/process/<processid>', 'title' => 'Get a Database Process Plan'),
			array('class' => 'SystemNodes', 'method' => 'getSystemNodeProcesses', 'uri' => 'system/<systemid>/node/<nodeid>/process', 'title' => 'Get Database Processes'),
			array('class' => 'SystemNodes', 'method' => 'getSystemNode', 'uri' => 'system/<systemid>/node/<nodeid>', 'title' => 'Get a Node'),
			array('class' => 'SystemNodes', 'method' => 'getSystemAllNodes', 'uri' => 'system/<systemid>/node', 'title' => 'Get All Nodes for a System'),
			array('class' => 'SystemNodes', 'method' => 'nodeStates', 'uri' => 'nodestate/<systemtype>', 'title' => 'Get All Node States for a System Type'),
			array('class' => 'SystemNodes', 'method' => 'nodeStates', 'uri' => 'nodestate', 'title' => 'Get All Node States'),
			array('class' => 'SystemNodes', 'method' => 'getProvisionedNodes', 'uri' => 'provisionednode', 'title' => 'Get Provisioned Nodes'),
			array('class' => 'UserTags', 'method' => 'getUserTags', 'uri' => 'user/<username>/.+tag/.+', 'title' => 'Get Tags for a User'),
			array('class' => 'UserTags', 'method' => 'getAllUserTags', 'uri' => 'user/<username>/.+tag', 'title' => 'Get Tags for a User'),
			array('class' => 'UserProperties', 'method' => 'getUserProperty', 'uri' => 'user/<username>/property/<property>', 'title' => 'Get a User Property'),
			array('class' => 'SystemUsers', 'method' => 'getUserInfo', 'uri' => 'user/<username>', 'title' => 'Get a User'),
			array('class' => 'SystemUsers', 'method' => 'getUsers', 'uri' => 'user', 'title' => 'Get All Users'),
			array('class' => 'Systems', 'method' => 'getSystemField1', 'uri' => 'system/<systemid>/field/<fieldname>', 'title' => 'Get a Field from a System'),
			array('class' => 'Systems', 'method' => 'getSystemField2', 'uri' => 'system/<systemid>/field/<fieldname>/<fieldname>', 'title' => 'Get a Field from a System'),
			array('class' => 'Systems', 'method' => 'getSystemField3', 'uri' => 'system/<systemid>/field/<fieldname>/<fieldname>/<fieldname>', 'title' => 'Get a Field from a System'),
			array('class' => 'Systems', 'method' => 'getSystemProcesses', 'uri' => 'system/<systemid>/process', 'title' => 'Get Database Processes on a System'),
			array('class' => 'Systems', 'method' => 'getSystemData', 'uri' => 'system/<systemid>', 'title' => 'Get a System'),
			array('class' => 'Systems', 'method' => 'getAllData', 'uri' => 'system', 'title' => 'Get All Systems'),
			array('class' => 'Systems', 'method' => 'getSystemTypes', 'uri' => 'systemtype', 'title' => 'Get All System Types'),
			array('class' => 'Buckets', 'method' => 'getData', 'uri' => 'bucket', 'title' => 'Get Data from a Bucket'),
			array('class' => 'Commands', 'method' => 'getStates', 'uri' => 'command/state', 'title' => 'Get Command States'),
			array('class' => 'Commands', 'method' => 'getSteps', 'uri' => 'command/step', 'title' => 'Get Command Steps'),
			array('class' => 'Commands', 'method' => 'getCommands', 'uri' => 'command', 'title' => 'Get Commands'),
			array('class' => 'Schedules', 'method' => 'getOneSchedule', 'uri' => 'schedule/<scheduleid>', 'title' => 'Get a Schedule'),
			array('class' => 'Schedules', 'method' => 'getSelectedSchedules', 'uri' => 'schedule/<daterange>', 'title' => 'Get a Range of Schedules'),
			array('class' => 'Schedules', 'method' => 'getMultipleSchedules', 'uri' => 'schedule', 'title' => 'Get All Schedules'),
			array('class' => 'Tasks', 'method' => 'getOneTask', 'uri' => 'task/<taskid>', 'title' => 'Get a Task'),
			array('class' => 'Tasks', 'method' => 'getSelectedTasks', 'uri' => 'task/<daterange>', 'title' => 'Get a Range of Tasks'),
			array('class' => 'Tasks', 'method' => 'getMultipleTasks', 'uri' => 'task', 'title' => 'Get All Tasks'),
			array('class' => 'RunSQL', 'method' => 'runQuery', 'uri' => 'runsql', 'title' => 'Run a Database Query'),
			array('class' => 'Monitors', 'method' => 'getOneMonitorClass', 'uri' => 'monitorclass/<systemtype>/key/<monitor>', 'title' => 'Get a Monitor'),
			array('class' => 'Monitors', 'method' => 'getMonitorClassesByType', 'uri' => 'monitorclass/<systemtype>', 'title' => 'Get All Monitors for a System Type'),
			array('class' => 'Monitors', 'method' => 'getMonitorClasses', 'uri' => 'monitorclass', 'title' => 'Get All Monitors'),
			array('class' => 'UserData', 'method' => 'getBackupLog', 'uri' => 'userdata/<logtype>', 'title' => 'Get a Backup Log'),
			array('class' => 'Request', 'method' => 'listAPI', 'uri' => 'metadata/apilist', 'title' => 'List API Requests'),
			array('class' => 'Request', 'method' => 'APIDate', 'uri' => 'apidate', 'title' => 'Show the API Code Date'),
			array('class' => 'Metadata', 'method' => 'getEntity', 'uri' => 'metadata/entity/<resource>', 'title' => 'Get Metadata for an Resource'),
			array('class' => 'Metadata', 'method' => 'getEntities', 'uri' => 'metadata/entities', 'title' => 'Get a List of API Resources'),
			array('class' => 'Metadata', 'method' => 'metadataSummary', 'uri' => 'metadata', 'title' => 'Get a Metadata Summary'),
			array('class' => 'Request', 'method' => 'getConfigField', 'uri' => 'config/<configsection>/<configitem>', 'title' => 'Get a Configuration Data Item in a section'),
			array('class' => 'Request', 'method' => 'getConfigField', 'uri' => 'config/<configitem>', 'title' => 'Get a Configuration Data Item (not in section)'),
			),
		'PUT' => array(
			array('class' => 'Applications', 'method' => 'setApplicationProperty', 'uri' => 'application/<appid>/property/<property>', 'title' => 'Create or Update an Application Property'),
			array('class' => 'SystemProperties', 'method' => 'setSystemProperty', 'uri' => 'system/<systemid>/property/<property>', 'title' => 'Create or Update a System Property'),
			array('class' => 'SystemBackups', 'method' => 'updateSystemBackup', 'uri' => 'system/<systemid>/backup/<backupid>', 'title' => 'Update a Backup'),
			array('class' => 'ComponentProperties', 'method' => 'setComponentProperty', 'uri' => 'system/<systemid>/node/<nodeid>/component/<component>/property/<property>', 'title' => 'Create or Update a Component Property'),
			array('class' => 'SystemNodes', 'method' => 'updateSystemNode', 'uri' => 'system/<systemid>/node/<nodeid>', 'title' => 'Update a Node'),
			array('class' => 'UserProperties', 'method' => 'putUserProperty', 'uri' => 'user/<username>/property/<property>', 'title' => 'Create or Update a User Property'),
			array('class' => 'SystemUsers', 'method' => 'putUser', 'uri' => 'user/<username>', 'title' => 'Create or Update a User'),
			array('class' => 'Systems', 'method' => 'updateSystem', 'uri' => 'system/<systemid>', 'title' => 'Update a System'),
			array('class' => 'Schedules', 'method' => 'updateSchedule', 'uri' => 'schedule/<scheduleid>', 'title' => 'Update a Schedule'),
			array('class' => 'Tasks', 'method' => 'updateTask', 'uri' => 'task/<taskid>', 'title' => 'Update a Task'),
			array('class' => 'Monitors', 'method' => 'putMonitorClass', 'uri' => 'monitorclass/<systemtype>/key/<monitor>', 'title' => 'Update a Monitor'),
		),
		'POST'=> array(
			array('class' => 'SystemBackups', 'method' => 'makeSystemBackup', 'uri' => 'system/<systemid>/backup', 'title' => 'Create a Backup'),
			array('class' => 'Monitors', 'method' => 'storeBulkMonitorData', 'uri' => 'monitordata', 'title' => 'Store Bulk Monitor Data'),
			array('class' => 'SystemNodes', 'method' => 'createSystemNodeOnceOnly', 'uri' => 'system/<systemid>/node/factory.*', 'title' => 'Create a Node Once Only'),
			array('class' => 'SystemNodes', 'method' => 'createSystemNode', 'uri' => 'system/<systemid>/node', 'title' => 'Create a Node'),
			array('class' => 'UserTags', 'method' => 'addUserTags', 'uri' => 'user/<username>/.+tag/.+', 'title' => 'Add a User Tag'),
			array('class' => 'SystemUsers', 'method' => 'loginUser', 'uri' => 'user/<username>', 'title' => 'Authenticate a User'),
			array('class' => 'Systems', 'method' => 'createSystemOnceOnly', 'uri' => 'system/factory.*', 'title' => 'Create a System Once Only'),
			array('class' => 'Systems', 'method' => 'createSystem', 'uri' => 'system', 'title' => 'Create a System'),
			array('class' => 'Tasks', 'method' => 'runCommand', 'uri' => 'command/<command>', 'title' => 'Run a Command'),
			array('class' => 'Schedules', 'method' => 'runScheduledCommand', 'uri' => 'schedule/<scheduleid>', 'title' => 'Run a Command on a Schedule'),
		),
		'DELETE' => array(			
			array('class' => 'Applications', 'method' => 'deleteApplicationProperty', 'uri' => 'application/<appid>/property/<property>', 'title' => 'Delete an Application Property'),
			array('class' => 'SystemProperties', 'method' => 'deleteSystemProperty', 'uri' => 'system/<systemid>/property/<property>', 'title' => 'Delete a System Property'),
			array('class' => 'ComponentProperties', 'method' => 'deleteComponentProperty', 'uri' => 'system/<systemid>/node/<nodeid>/component/<component>/property/<property>', 'title' => 'Delete a Component Property'),
			array('class' => 'ComponentProperties', 'method' => 'deleteComponentProperties', 'uri' => 'system/<systemid>/node/<nodeid>/component/<component>/', 'title' => 'Delete All Properties for a Component'),
			array('class' => 'ComponentProperties', 'method' => 'deleteComponents', 'uri' => 'system/<systemid>/node/<nodeid>/component', 'title' => 'Delete All Component Properties'),
			array('class' => 'SystemNodes', 'method' => 'killSystemNodeProcess', 'uri' => 'system/<systemid>/node/<nodeid>/process/<processid>', 'title' => 'Kill a Database Process'),
			array('class' => 'SystemNodes', 'method' => 'deleteSystemNode', 'uri' => 'system/<systemid>/node/<nodeid>', 'title' => 'Delete a Node'),
			array('class' => 'UserTags', 'method' => 'deleteUserTags', 'uri' => 'user/<username>/.+tag/.+/.+', 'title' => 'Delete Tags for a User'),
			array('class' => 'UserTags', 'method' => 'deleteUserTags', 'uri' => 'user/<username>/.+tag/.+', 'title' => 'Delete Tags for a User'),
			array('class' => 'UserTags', 'method' => 'deleteUserTags', 'uri' => 'user/<username>/.+tag', 'title' => 'Delete Tags for a User'),
			array('class' => 'UserProperties', 'method' => 'deleteUserProperty', 'uri' => 'user/<username>/property/<property>', 'title' => 'Delete a User Property'),
			array('class' => 'SystemUsers', 'method' => 'deleteUser', 'uri' => 'user/<username>', 'title' => 'Delete a User'),
			array('class' => 'Systems', 'method' => 'deleteSystem', 'uri' => 'system/<systemid>', 'title' => 'Delete a System'),
			array('class' => 'Schedules', 'method' => 'deleteOneSchedule', 'uri' => 'schedule/<scheduleid>', 'title' => 'Delete a Schedule'),
			array('class' => 'Tasks', 'method' => 'cancelOneTask', 'uri' => 'task/<taskid>', 'title' => 'Cancel a Task'),
			array('class' => 'Monitors', 'method' => 'deleteMonitorClass', 'uri' => 'monitorclass/<systemtype>/key/<monitor>', 'title' => 'Delete a Monitor'),
		)
	);
	
	protected static $uriStructuredTable = array();
	
	protected static $fieldregex = array(
		'<appid>' => '[0-9]+',
		'<property>' => '[A-Za-z0-9_\.]+',
		'<systemid>' => '[0-9]+',
		'<backupid>' => '[0-9]+',
		'<nodeid>' => '[0-9]+',
		'<component>' => '[A-Za-z0-9_:\-]+',
		'<processid>' => '[0-9]+',
		'<systemtype>' =>'[A-Za-z0-9]+',
		'<fieldname>' =>'[a-z0-9]+',
		'<username>' => '[A-Za-z0-9_]+',
		'<command>' => '[A-Za-z0-9]+',
		'<scheduleid>' => '[0-9]+',
		'<taskid>' => '[0-9]+',
		'<monitor>' => '([0-9a-zA-Z_\-\.\~\*\(\)]+)',
		'<logtype>' => '(log|binlog)',
		'<resource>' => '[A-Za-z]+',
		'<configsection>' => '[A-Za-z0-9_\.]+',
		'<configitem>' => '[A-Za-z0-9_\.]+',
		'<daterange>' => '.+'
	);
	
	// Probably not needed
	// protected static $uriregex = '([0-9a-zA-Z_\-\.\~\*\(\)]*)';
	
	public function __construct () {
		if (empty(self::$uriStructuredTable)) {
			foreach (self::$uriTable as &$requests) foreach ($requests as &$uridata) {
				$parts = explode('/', trim($uridata['uri'],'/'));
				foreach ($parts as $part) $uridata['uriparts'][] = str_replace(array_keys(self::$fieldregex), array_values(self::$fieldregex), $part);
				$this->apibase[$parts[0]] = 1;
			}
			foreach (self::$uriTable as $requestmethod=>$links) foreach ($links as $link) if (isset($link['uriparts'][0])) {
				$firstpart = array_shift($link['uriparts']);
				self::$uriStructuredTable[$requestmethod][$firstpart][] = $link; 
			}
		}
		self::$uriTableNeedsFix = false;
		$this->apibase = array_keys($this->apibase);
	}
	
	public function getBaseParts () {
		return $this->apibase;
	}
	
	public function getUriTable () {
		return self::$uriTable;
	}
	
	public function getUriStructuredTable () {
		return self::$uriStructuredTable;
	}
	
	public function getFieldRegex () {
		return self::$fieldregex;
	}
}