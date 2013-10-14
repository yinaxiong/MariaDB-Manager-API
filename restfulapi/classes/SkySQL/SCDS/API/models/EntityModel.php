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
 * The EntityModel class provides common resources for models of the
 * entities managed by the API.
 * 
 */

namespace SkySQL\SCDS\API\models;

use SkySQL\COMMON\AdminDatabase;
use SkySQL\SCDS\API\API;
use SkySQL\SCDS\API\Request;
use PDO;

abstract class EntityModel {
	
	protected static $validationerrors = array();
	
	protected $bind = array();
	protected $setter = array();
	protected $insname = array();
	protected $insvalue = array();
	protected $keydata = array();
	
	public static function getAll () {
		$getall = AdminDatabase::getInstance()->prepare(sprintf(static::$selectAllSQL, self::getSelects(), ''));
		$getall->execute();
		$entities = $getall->fetchAll(PDO::FETCH_CLASS|PDO::FETCH_PROPS_LATE, static::$classname, static::$getAllCTO);
		foreach ($entities as &$entity) $entity = self::fixDate($entity);
		return $entities;
	}
	
	public static function getByID () {
		$request = Request::getInstance();
		$classname = static::$headername;
		$actualcount = func_num_args();
		$keycount = count(static::$keys);
		if ($actualcount != $keycount) {
			$request->sendErrorResponse(sprintf("Keys array passed to getByID in class '%s' has %d values, should be %d", $classname, $actualcount, $keycount), 500);
		}
		$select = AdminDatabase::getInstance()->prepare(sprintf(static::$selectSQL, self::getSelects()));
		$iarg = 0;
		foreach (static::$keys as $name=>$about) {
			$arg = func_get_arg($iarg++);
			if (!$arg) $request->sendErrorResponse(sprintf("No key value given in class '%s' for '%s' as key to select item", $classname, $about['sqlname']), 400);
			$bind[":$name"] = $arg;
		}
		$select->execute($bind);
		// ->fetchObject has problems, inadvisable to use
		$entities = $select->fetchAll(PDO::FETCH_CLASS|PDO::FETCH_PROPS_LATE, static::$classname, static::$getAllCTO);
		$entity = $entities ? $entities[0] : null;
		if ($entity) {
			if (method_exists($entity, 'derivedFields')) $entity->derivedFields();
			return self::fixDate($entity);
		}
		return null;
	}
	
	protected static function fixDate (&$entity) {
		foreach (static::$fields as $name=>$about) {
			if (!empty($entity->$name) AND 'datetime' == @$about['validate']) $entity->$name = date('r', strtotime($entity->$name));
		}
		return $entity;
	}
	
	public function withDateFix () {
		return self::fixDate($this);
	}
	
	// Unclear whether this is needed - can use ::getByID to obtain a fully populated object
	public function loadData () {
		$loader = AdminDatabase::getInstance()->prepare(sprintf(static::$selectSQL, self::getSelects()));
		foreach (array_keys(static::$keys) as $key) $bind[":$key"] = $this->$key; 
		$loader->execute((array) @$bind);
		$data = $loader->fetch();
		if ($data) foreach (get_object_vars($data) as $name=>$value) $this->$name = $value;
		self::fixDate($this);
	}

	public static function select () {
		$args = func_get_args();
		$controller = array_shift($args);
		list($where, $bind) = self::wheresAndBinds($controller, $args);
		$database = AdminDatabase::getInstance();
		$conditions = empty($where) ? '' : ' WHERE '.implode(' AND ', $where);
		$counter = $database->prepare(static::$countAllSQL.$conditions);
		$counter->execute($bind);
		$total = $counter->fetch(PDO::FETCH_COLUMN);
		$select = $database->prepare(sprintf(static::$selectAllSQL, self::getSelects(), $conditions).self::limitsClause($controller));
		$select->execute($bind);
		$entities = $select->fetchAll(PDO::FETCH_CLASS|PDO::FETCH_PROPS_LATE, static::$classname, static::$getAllCTO);
		foreach ($entities as &$entity) {
			if (method_exists($entity, 'derivedFields')) $entity->derivedFields();
			$entity = self::fixDate($entity);
		}
		return array($total, $entities);
	}
	
	protected static function limitsClause ($controller) {
		$limit = $controller->getLimit();
		return $limit ? " LIMIT $limit OFFSET {$controller->getOffset()}" : '';
	}
	
	public function update ($alwaysrespond=true) {
		$this->settersAndBinds(__FUNCTION__);
		if (!empty($this->setter)) {
			$this->validateUpdate();
			$database = AdminDatabase::getInstance();
			$update = $database->prepare(sprintf(static::$updateSQL, implode(', ', $this->setter)));
			$update->execute($this->bind);
			$counter = $update->rowCount();
			$database->commitTransaction();
			if ($counter) $this->clearCache(true);
			if ($counter OR $alwaysrespond) Request::getInstance()->sendResponse(array('updatecount' => $counter, 'insertkey' => 0));
		}
		if ($alwaysrespond) Request::getInstance()->sendResponse(array('updatecount' => 0, 'insertkey' => 0));
		return 0;
	}
	
	public function insert ($alwaysrespond=true) {
		$this->settersAndBinds(__FUNCTION__);
		$this->setDefaults();
		$this->validateInsert();
		$database = AdminDatabase::getInstance();
		$insert = $database->prepare(sprintf(static::$insertSQL, implode(',',$this->insname), implode(',',$this->insvalue)));
		$insert->execute($this->bind);
		$insertkey = $this->insertedKey($database->lastInsertId());
		$database->commitTransaction();
		$this->clearCache(true);
		if ($alwaysrespond) Request::getInstance()->sendResponse(array('updatecount' => 0,  'insertkey' => $insertkey));
		else return $insertkey;
	}
	
	public function save () {
		$this->settersAndBinds(__FUNCTION__);
		$database = AdminDatabase::getInstance();
		$database->beginImmediateTransaction();
		if (!empty($this->setter)) $counter = $this->update(false);
		else {
			$update = $database->prepare(static::$countSQL);
			$update->execute($this->bind);
			$counter = $update->fetch(PDO::FETCH_COLUMN);
		}
		if (empty($counter)) $this->insert();
		else Request::getInstance()->sendResponse(array('updatecount' => (empty($this->setter) ? 0: $counter), 'insertkey' => null));
	}
	
	public function delete () {
		$this->settersAndBinds(__FUNCTION__);
		$delete = AdminDatabase::getInstance()->prepare(static::$deleteSQL);
		$delete->execute($this->bind);
		$counter = $delete->rowCount();
		if ($counter) $this->clearCache(true);
		$request = Request::getInstance();
		if ($counter) $request->sendResponse(array('deletecount' => $counter));
		else $request->sendErrorResponse("Delete $this->ordinaryname did not match any $this->ordinaryname", 404);
	}
	
	protected function clearCache ($immediate=false) {
		if (isset(static::$managerclass)) {
			$manager = call_user_func(array(static::$managerclass,'getInstance'));
			$manager->clearCache($immediate);
		}
	}
	
	protected function setDefaultDate ($name) {
		if (empty($this->bind[":$name"])) {
			$this->setInsertValue($name, date('Y-m-d H:i:s'));
		}
	}
	
	protected function setCorrectFormatDate ($name) {
		$bindname = ":$name";
		if (!empty($this->bind[$bindname])) {
			$unixtime = strtotime($this->bind[$bindname]);
			$this->bind[$bindname] = self::formatDate($unixtime);
		}
	}
	
	protected static function formatDate ($unixtime) {
		return date('Y-m-d H:i:s', ($unixtime ? $unixtime : time()));
	}
	
	protected function setCorrectFormatDateWithDefault ($name) {
		$this->setCorrectFormatDate($name);
		$this->setDefaultDate($name);
	}
	
	protected function setDefaults () {}
	
	protected function insertedKey ($insertid) {
		return $insertid;
	}
	
	protected function validateInsert () {}
	
	protected function validateUpdate () {}
	
	public static function checkLegal ($extras=array()) {
		$request = Request::getInstance();
		$okfields = array_merge(array_keys(static::$fields), array_keys(static::$keys), (array) $extras);
		$illegals = array_diff($request->getAllParamNames($request->getMethod()),$okfields);
		if (count($illegals)) {
			$illegalist = implode (', ', $illegals);
			$request->sendErrorResponse("Parameter(s) $illegalist not recognised",400);
		}
	}
	
	public function setPropertiesFromParams () {
		$this->settersAndBinds('insert');
	}
	
	protected function settersAndBinds ($caller) {
		$request = Request::getInstance();
		// Source for data is always provided except for deletes; also delete has no need
		// of setters or binds for fields, only for keys
		if ('delete' != $caller) {
			$source = $request->getMethod();
			foreach (static::$fields as $name=>$about) {
				$bindname = ":$name";
				// insname and insvalue are only needed for inserts, but are set anyway
				$this->insname[] = $about['sqlname'];
				$this->insvalue[] = $bindname;
				if ('insert' == $caller OR empty($about['insertonly'])) {
					if ('insert' == $caller OR !$request->paramEmpty($source, $name) OR !empty($this->$name)) {
						// setter is only needed for updates, but is set anyway
						$this->setter[] = $about['sqlname'].' = '.$bindname;
						if (empty($this->$name)) $this->$name = $this->bind[$bindname] = self::getParam($source, $name, $about);
						else $this->bind[$bindname] = $this->$name;
					}
				}
			}
			if (count(self::$validationerrors)) $request->sendErrorResponse(self::$validationerrors, 400);
		}
		// The static variable $setkeyvalues is used to suppress setting a value for the key where autoincrement is used
		// except in the case where the full key is a mix of a provided value and an increment.
		if (!isset(static::$setkeyvalues)) $request->sendErrorResponse('All entity classes must set the static variable $setkeyvalues',500);
		if (static::$setkeyvalues OR 'insert' != $caller) foreach (static::$keys as $name=>$about) if (!empty($this->$name)) {
			$this->insname[] = $about['sqlname'];
			$bindname = ":$name";
			$this->insvalue[] = $bindname;
			$this->bind[$bindname] = $this->$name;
		}
	}
	
	protected function copyProperties ($from) {
		foreach (array_keys(static::$fields) as $name) if (isset($from->$name)) $this->$name = $from->$name;
	}
	
	protected function setInsertValue ($name, $value) {
		if (isset(static::$fields[$name])) {
			$bindname = ":$name";
			$this->bind[$bindname] = $value;
			$sqlname = static::$fields[$name]['sqlname'];
			$sub = array_search($sqlname, $this->insname);
			if ($sub) unset($this->insname[$sub], $this->insvalue[$sub]);
			$this->insname[] = $sqlname;
			$this->insvalue[] = $bindname;
			$this->setter[] = "$sqlname = $bindname";
		}
		else Request::getInstance()->sendErrorResponse(sprintf("Attempt to set an insert value for '%s' which is not a valid field", $name), 500);
	}
	
	protected function calendarDate () {
		$savezone = date_default_timezone_get();
		date_default_timezone_set('UTC');
		$date = date('Ymd\THis\Z');
		date_default_timezone_set($savezone);
		return $date;
	}
	
	protected static function dateRange ($dates, $datefield, $entitiesname) {
		$selectors = explode(',', $dates);
		$request = Request::getInstance();
		if (2 < count($selectors)) $request->sendErrorResponse(sprintf("Request for %s in date range had more than two comma separated entries", $entitiesname), 400);
		$bindname = ':startdate';
		$condition = "$datefield >= $bindname";
		foreach ($selectors as $selector) {
			if ($selector) {
				$unixtime = strtotime($selector);
				if (false === $unixtime) $request->sendErrorResponse(sprintf("Request for %s in date range contained invalid date '%s'", $entitiesname, $selector), 400);
				if ($unixtime) {
					$bind[$bindname] = date('Y-m-d H:i:s', $unixtime);
					$where[] = $condition;
				}
			}
			$bindname = ':enddate';
			$condition = "$datefield <= $bindname";
		}
		return array((array) @$where, (array) @$bind);
	}

	protected static function wheresAndBinds ($controller, $args) {
		list($where, $bind) = static::specialSelected($args);
		$request = Request::getInstance();
		$source = $request->getMethod();
		foreach ($controller->getKeyData() as $name=>$value) {
			if (isset(static::$keys[$name])) {
				$about = static::$keys[$name];
				$where[] = "{$about['sqlname']} = :$name";
				$bind[":$name"] = $value;
			}
		}
		foreach (static::$fields as $name=>$about) {
			if (!$request->paramEmpty($source, $name)) {
				$bind[":$name"] = self::getParam($source, $name, $about);
				$where[] = "{$about['sqlname']} = :$name";
			}
		}
		if (count(self::$validationerrors)) $request->sendErrorResponse(self::$validationerrors, 400);
		return array($where, $bind);
	}
	
	// Can be overriden by subclasses
	protected static function specialSelected ($args) {
		return array(array(), array());
	}
	
	protected static function getParam ($source, $name, $about) {
		$request = Request::getInstance();
		$data = isset($about['forced']) ? $about['forced'] : $request->getParam($source, $name, $about['default']);
		if (@$about['validate']) {
			$method = $about['validate'];
			if (method_exists(__CLASS__, $method)) {
				if (!self::$method($data)) self::$validationerrors[] = "Field '$name' with value '$data' failed $method validation";
				if ('datetime' == $method) $data = self::formatDate(strtotime($data));
			}
			else $request->sendErrorResponse("Field $name specified validation '$method', but no such method exists", 500);
		}
		return $data;
	}
	
	// Validation method for System Type
	protected static function systemtype ($data) {
		return isset(API::$systemtypes[$data]);
	}
	
	// Validation method for System State
	protected static function systemstate ($data) {
		return isset(API::$systemstates[$data]);
	}
	
	// Validation method for IP address
	protected static function ipaddress ($data) {
		return empty($data) ? true : filter_var($data, FILTER_VALIDATE_IP);
	}
	
	// Validation method for backup state
	protected static function backupstate ($data) {
		return isset(API::$backupstates[$data]);
	}
	
	// Validation method for date/time
	protected static function datetime ($data) {
		return empty($data) OR (strtotime($data) ? true : false);
	}

	protected static function getSelects ($selects=array()) {
		foreach (static::$fields as $name=>$about) $selects[] = $about['sqlname'].' AS '.$name;
		foreach (static::$keys as $name=>$about) $selects[] = "{$about['sqlname']} AS $name";
		return implode(',', $selects);
	}

	// Not currently used
	protected function makeRandomString ($length=8) {
		$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!%,-:;@_{}~";
		for ($i = 0, $makepass = '', $len = strlen($chars); $i < $length; $i++) $makepass .= $chars[mt_rand(0, $len-1)];
		return $makepass;
	}
	
	public static function setMetadataFromDB ($fields) {
		foreach ($fields as $field) $bysqlname[$field->name] = $field;
		foreach (static::$keys as &$key) {
			if (isset($bysqlname[$key['sqlname']])) $key['type'] = $bysqlname[$key['sqlname']]->type;
		}
		foreach (static::$fields as &$field) {
			if (isset($bysqlname[$field['sqlname']])) $field['type'] = $bysqlname[$field['sqlname']]->type;
		}
	}
	
	public static function getMetadataJSON () {
		return array('keys' => static::$keys, 'fields' => static::$fields);
	}
	
	public static function getMetadataHTML () {
		$entityname = static::$headername;
		$keyrows = $datarows = $extrarows = $extrahtml = '';
		foreach (static::$keys as $name=>$key) {
			$description = @$key['desc'];
			$keyrows .= <<<KEYROW
		<tr>
			<td>$name</td>
			<td>{$key['type']}</td>
			<td>$description</td>
		</tr>
				
KEYROW;
			
		}
		foreach (static::$fields as $name=>$field) {
			$description = @$field['desc'];
			$datarows .= <<<DATAROW
		<tr>
			<td>$name</td>
			<td>{$field['type']}</td>
			<td>$description</td>
		</tr>
				
DATAROW;
			
		}
		if (!empty(static::$derived)) foreach (static::$derived as $name=>$field) {
			$description = @$field['desc'];
			$extrarows .= <<<EXTRAROW
		<tr>
			<td>$name</td>
			<td>{$field['type']}</td>
			<td>$description</td>
		</tr>
				
EXTRAROW;
			
		}
		if ($extrarows) $extrahtml = <<<EXTRA
				
  <tbody>
    <tr>
      <th class="subhead" scope="rowgroup" colspan="3">Derived information</th>
    </tr>
    $extrarows
   </tbody>
				
EXTRA;

		echo <<<ENTITY
		
<div id="popup">
<h3>$entityname</h3>
<table>
  <thead>
    <tr>
      <th scope="col">Field Name</th>
      <th scope="col">Type</th>
      <th scope="col">Description</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <th class="subhead" scope="rowgroup" colspan="3">Key fields</th>
    </tr>
    $keyrows
  </tbody>
  <tbody>
    <tr>
      <th class="subhead" scope="rowgroup" colspan="3">Data fields</th>
    </tr>
    $datarows
   </tbody>
   $extrahtml
</table>
</div>
		
ENTITY;
				
		exit;
	}
}
