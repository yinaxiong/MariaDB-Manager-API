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
use SkySQL\SCDS\API\managers\NodeStateManager;
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
	
	public static function getByID ($keyvalues) {
		$request = Request::getInstance();
		if (count($keyvalues) != count(static::$keys)) $request->sendErrorResponse('Number of values given does not match number of keys', 500);
		$select = AdminDatabase::getInstance()->prepare(sprintf(static::$selectSQL, self::getSelects()));
		foreach (static::$keys as $name=>$about) {
			if (empty($keyvalues[$name])) $request->sendErrorResponse("No key value given for {$about['sqlname']} as key to select items", 400);
			$bind[":$name"] = $keyvalues[$name];
		}
		$select->execute($bind);
		// ->fetchObject has problems, inadvisable to use
		$entities = $select->fetchAll(PDO::FETCH_CLASS|PDO::FETCH_PROPS_LATE, static::$classname, static::$getAllCTO);
		return count($entities) ? self::fixDate($entities[0]) : null;
	}
	
	protected static function fixDate ($entity) {
		foreach (static::$fields as $name=>$about) {
			if ('datetime' == @$about['validate']) $entity->$name = date('r', strtotime($entity->$name));
		}
		return $entity;
	}
	
	public static function select () {
		$args = func_get_args();
		$controller = array_shift($args);
		list($where, $bind) = self::wheresAndBinds($controller, $args);
		$database = AdminDatabase::getInstance();
		if ($where) {
			$conditions = implode(' AND ', $where);
			$sql = static::$countAllSQL.' WHERE '.$conditions;
			$count = $database->prepare($sql);
			$count->execute($bind);
			$total = $count->fetch(PDO::FETCH_COLUMN);
			$sql = sprintf(static::$selectAllSQL, self::getSelects(), ' WHERE '.$conditions).self::limitsClause($controller);
			$select = $database->prepare($sql);
			$select->execute($bind);
		}
		else {
			$count = $database->prepare(static::$countAllSQL);
			$count->execute();
			$total = $count->fetch(PDO::FETCH_COLUMN);
			$sql = sprintf(static::$selectAllSQL, self::getSelects(), '').self::limitsClause($controller);
			$select = $database->query($sql);
		}
		return array($total, $select->fetchAll(PDO::FETCH_CLASS|PDO::FETCH_PROPS_LATE, static::$classname, static::$getAllCTO));
	}
	
	protected static function limitsClause ($controller) {
		$limit = $controller->getLimit();
		return $limit ? " LIMIT $limit OFFSET {$controller->getOffset()}" : '';
	}
	
	public function update ($alwaysrespond=true) {
		$this->settersAndBinds(__FUNCTION__);
		if (!empty($this->setter)) {
			$this->validateUpdate();
			$sql = sprintf(static::$updateSQL, implode(', ', $this->setter));
			$database = AdminDatabase::getInstance();
			$update = $database->prepare($sql);
			$update->execute($this->bind);
			$database->commitTransaction();
			$this->clearCache(true);
			$counter = $update->rowCount();
			if ($counter OR $alwaysrespond) Request::getInstance()->sendResponse(array('updatecount' => $counter, 'insertkey' => 0));
		}
		if ($alwaysrespond) Request::getInstance()->sendResponse(array('updatecount' => 0, 'insertkey' => 0));
		return 0;
	}
	
	public function insert ($alwaysrespond=true) {
		$this->settersAndBinds(__FUNCTION__);
		if (!$this->keyComplete()) $this->makeNewKey();
		$this->setDefaults();
		$this->validateInsert();
		$fields = implode(',',$this->insname);
		$values = implode(',',$this->insvalue);
		$database = AdminDatabase::getInstance();
		$insert = $database->prepare(sprintf(static::$insertSQL, $fields, $values));
		$insert->execute($this->bind);
		$database->commitTransaction();
		$this->clearCache(true);
		$insertkey = $this->insertedKey($database->lastInsertId());
		if ($alwaysrespond) Request::getInstance()->sendResponse(array('updatecount' => 0,  'insertkey' => $insertkey));
		else return $insertkey;
	}
	
	public function save () {
		$this->settersAndBinds(__FUNCTION__);
		$database = AdminDatabase::getInstance();
		$database->beginImmediateTransaction();
		if ($this->keyComplete()) {
			if (!empty($this->setter)) $counter = $this->update(false);
			else {
				$update = $database->prepare(static::$countSQL);
				$update->execute($this->bind);
				$counter = $update->fetch(PDO::FETCH_COLUMN);
			}
		}
		if (empty($counter)) $this->insert();
		else Request::getInstance()->sendResponse(array('updatecount' => (empty($this->setter) ? 0: $counter), 'insertkey' => null));
	}
	
	public function delete () {
		$this->settersAndBinds(__FUNCTION__);
		$delete = AdminDatabase::getInstance()->prepare(static::$deleteSQL);
		$delete->execute($this->bind);
		$this->clearCache(true);
		$counter = $delete->rowCount();
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
			$this->bind[$bindname] = date('Y-m-d H:i:s', ($unixtime ? $unixtime : time()));
		}
	}
	
	protected function setCorrectFormatDateWithDefault ($name) {
		$this->setCorrectFormatDate($name);
		$this->setDefaultDate($name);
	}
	
	protected function keyComplete () {
		return true;
	}
	
	protected function makeNewKey () {}
	
	protected function setDefaults () {}
	
	protected function insertedKey ($insertid) {
		return $insertid;
	}
	
	protected function validateInsert () {}
	
	protected function validateUpdate () {}
	
	public static function checkLegal ($extras=array()) {
		$request = Request::getInstance();
		$method = $request->getMethod();
		$fields = array_merge(array_keys(static::$fields), array_keys(static::$keys), (array) $extras);
		$illegals = array_diff($request->getAllParamNames($method),$fields);
		if (count($illegals)) {
			$illegalist = implode (', ', $illegals);
			$request->sendErrorResponse("Parameter(s) $illegalist not recognised",400);
		}
	}
	
	protected function settersAndBinds ($caller) {
		$request = Request::getInstance();
		// Source for data is always provided except for deletes
		if ('delete' != $caller) {
			$source = $request->getMethod();
			foreach (static::$fields as $name=>$about) {
				$this->insname[] = $about['sqlname'];
				$bindname = ":$name";
				$this->insvalue[] = $bindname;
				if ('insert' == $caller OR empty($about['insertonly'])) {
					if ('insert' == $caller OR !$request->paramEmpty($source, $name)) {
						$this->bind[$bindname] = self::getParam($source, $name, $about);
						$this->setter[] = $about['sqlname'].' = '.$bindname;
						$this->$name = $this->bind[$bindname];
					}
				}
			}
			if (count(self::$validationerrors)) $request->sendErrorResponse(self::$validationerrors, 400);
		}
		if (!isset(static::$setkeyvalues)) $request->sendErrorResponse('All entity classes must set the static variable $setkeyvalues',500);
		if (static::$setkeyvalues OR 'insert' != $caller) foreach (static::$keys as $name=>$about) {
			$this->insname[] = $about['sqlname'];
			$bindname = ":$name";
			$this->insvalue[] = $bindname;
			if (isset($this->$name)) $this->bind[$bindname] = $this->$name;
		}
	}
	
	protected function setInsertValue ($name, $value) {
		$bindname = ":$name";
		$this->bind[$bindname] = $value;
		$sqlname = static::$fields[$name]['sqlname'];
		$sub = array_search($sqlname, $this->insname);
		if ($sub) unset($this->insname[$sub], $this->insvalue[$sub]);
		$this->insname[] = $sqlname;
		$this->insvalue[] = $bindname;
	}
	
	protected function calendarDate () {
		$savezone = date_default_timezone_get();
		date_default_timezone_set('UTC');
		$date = date('Ymd\THis\Z');
		date_default_timezone_set($savezone);
		return $date;
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
		$data = Request::getInstance()->getParam($source, $name, $about['default']);
		if (@$about['validate']) {
			$method = $about['validate'];
			if (!self::$method($data)) self::$validationerrors[] = "Field $name with value $data failed validation";
		}
		return $data;
	}
	
	// Validation method for System Type
	protected static function systemtype ($data) {
		return isset(API::$systemtypes['nodetranslator'][$data]);
	}
	
	// Validation method for System State
	protected static function systemstate ($data) {
		return isset(API::$systemstates[$data]);
	}
	
	// Validation method for IP address
	protected static function ipaddress ($data) {
		return filter_var($data, FILTER_VALIDATE_IP);
	}
	
	// Validation method for node state
	protected static function nodestate ($data) {
		return NodeStateManager::getInstance()->getByState($data) ? true : false;
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
