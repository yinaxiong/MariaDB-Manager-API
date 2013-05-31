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
use SkySQL\SCDS\API\Request;
use PDO;

abstract class EntityModel {
	
	public static function getAll () {
		$getall = AdminDatabase::getInstance()->prepare(sprintf(static::$selectAllSQL, self::getSelects(), ''));
		$getall->execute();
		return $getall->fetchAll(PDO::FETCH_CLASS|PDO::FETCH_PROPS_LATE, static::$classname, static::$getAllCTO);
	}
	
	public static function getByID ($keyvalues) {
		$request = Request::getInstance();
		if (count($keyvalues) != count(static::$keys)) $request->sendErrorResponse('Number of values given does not match number of keys', 500);
		$select = AdminDatabase::getInstance()->prepare(sprintf(static::$selectSQL, self::getSelects()));
		foreach (static::$keys as $name=>$sqlname) {
			if (empty($keyvalues[$name])) $request->sendErrorResponse("No key value given for $sqlname as key to select items");
			$bind[":$name"] = $keyvalues[$name];
		}
		$select->execute($bind);
		return $select->fetchAll(PDO::FETCH_CLASS|PDO::FETCH_PROPS_LATE, static::$classname, static::$getAllCTO);
	}
	
	public static function select () {
		list($where, $bind) = self::wheresAndBinds();
		$database = AdminDatabase::getInstance();
		if ($where) {
			$sql = sprintf(static::$selectAllSQL, self::getSelects(), ' WHERE '.implode(' AND ', $where));
			$select = $database->prepare($sql);
			$select->execute($bind);
		}
		else {
			$sql = sprintf(static::$selectAllSQL, self::getSelects(), '');
			$select = $database->query($sql);
		}
		return $select->fetchAll(PDO::FETCH_CLASS|PDO::FETCH_PROPS_LATE, static::$classname, static::$getAllCTO);
	}
	
	public function update ($alwaysrespond=true) {
		list( , , $setter, $bind) = $this->settersAndBinds(__FUNCTION__);
		if (!empty($setter)) {
			$this->validateUpdate($bind, $setter);
			$sql = sprintf(static::$updateSQL, implode(', ', $setter));
			$database = AdminDatabase::getInstance();
			$update = $database->prepare($sql);
			$update->execute($bind);
			$database->commitTransaction();
			$counter = $update->rowCount();
			if ($counter OR $alwaysrespond) Request::getInstance()->sendResponse(array('updatecount' => $counter, 'insertkey' => 0));
			return 0;
		}
	}
	
	public function insert ($alwaysrespond=true) {
		list($insname, $insvalue, , $bind) = $this->settersAndBinds(__FUNCTION__);
		if (!$this->keyComplete()) $this->makeNewKey($bind);
		$this->setDefaults($bind, $insname, $insvalue);
		$this->validateInsert($bind, $insname, $insvalue);
		$fields = implode(',',$insname);
		$values = implode(',',$insvalue);
		$database = AdminDatabase::getInstance();
		$insert = $database->prepare(sprintf(static::$insertSQL, $fields, $values));
		$insert->execute($bind);
		$database->commitTransaction();
		if ($alwaysrespond) Request::getInstance()->sendResponse(
			array('updatecount' => 0,  'insertkey' => $this->insertedKey($database->lastInsertId()))
		);
		return $database->lastInsertId();
	}
	
	public function save () {
		list( ,  , $setter, $bind) = $this->settersAndBinds(__FUNCTION__);
		$database = AdminDatabase::getInstance();
		$database->startImmediateTransaction();
		if ($this->keyComplete()) {
			if (!empty($setter)) $counter = $this->update(false);
			else {
				$update = $database->prepare(static::$countSQL);
				$update->execute($bind);
				$counter = $update->fetch(PDO::FETCH_COLUMN);
			}
		}
		if (empty($counter)) $this->insert();
		else Request::getInstance()->sendResponse(array('updatecount' => (empty($setter) ? 0: $counter), 'insertkey' => 0));
	}
	
	public function delete () {
		list ( , , , $bind) = $this->settersAndBinds(__FUNCTION__);
		$delete = AdminDatabase::getInstance()->prepare(static::$deleteSQL);
		$delete->execute($bind);
		$counter = $delete->rowCount();
		$request = Request::getInstance();
		if ($counter) $request->sendResponse(array('deletecount' => $counter));
		else $request->sendErrorResponse("Delete $this->ordinaryname did not match any $this->ordinaryname", 404);
	}
	
	protected function keyComplete () {
		return true;
	}
	
	protected function makeNewKey (&$bind) {}
	
	protected function setDefaults (&$bind, &$insname, &$insvalue) {}
	
	protected function insertedKey ($insertid) {
		return $insertid;
	}
	
	protected function validateInsert (&$bind, &$insname, &$insvalue) {}
	
	protected function validateUpdate (&$bind, &$setters) {}
	
	protected static function checkLegalFields () {
		// Block check until more work can be done
		return;
		$legals = array_merge(array_keys(static::$fields),array_keys(static::$keys));
		$request = Request::getInstance();
		$illegals = array_diff($request->getAllParamNames($request->getMethod()),$legals);
		if (count($illegals)) {
			$illegalist = implode (', ', $illegals);
			$request->sendErrorResponse("Parameter(s) $illegalist not recognised",400);
		}
	}
	
	protected function settersAndBinds ($caller) {
		self::checkLegalFields();
		$bind = $setter = $insname = $insvalue = array();
		$request = Request::getInstance();
		// Source for data is always provided except for deletes
		if ('delete' != $caller) {
			$source = $request->getMethod();
			foreach (static::$fields as $name=>$about) {
				$insname[] = $about['sqlname'];
				$bindname = ":$name";
				$insvalue[] = $bindname;
				if ('insert' == $caller OR empty($about['insertonly'])) {
					if (!$request->paramEmpty($source, $name) AND empty($about['internal'])) {
						$bind[$bindname] = $request->getParam($source, $name, $about['default']);
						$setter[] = $about['sqlname'].' = '.$bindname;
						$this->$name = $bind[$bindname];
					}
				}
			}
		}
		if (!isset(static::$setkeyvalues)) trigger_error('All entity classes must set the static variable $setkeyvalues');
		if (static::$setkeyvalues OR 'insert' != $caller) foreach (static::$keys as $name=>$sqlname) {
			$insname[] = $sqlname;
			$bindname = ":$name";
			$insvalue[] = $bindname;
			if (isset($this->$name)) $bind[$bindname] = $this->$name;
		}
		return array($insname, $insvalue, $setter, $bind);
	}
	
	protected static function wheresAndBinds () {
		self::checkLegalFields();
		$bind = $where = array();
		$request = Request::getInstance();
		$source = $request->getMethod();
		foreach (static::$fields as $name=>$about) {
			$bindname = ":$name";
			if (!$request->paramEmpty($source, $name) AND empty($about['internal'])) {
				$bind[$bindname] = $request->getParam($source, $name, $about['default']);
				$where[] = $about['sqlname'].' = '.$bindname;
			}
		}
		return array($where, $bind);
	}

	protected static function getSelects ($selects=array()) {
		foreach (static::$fields as $name=>$about) $selects[] = $about['sqlname'].' AS '.$name;
		foreach (static::$keys as $name=>$sqlname) $selects[] = "$sqlname AS $name";
		return implode(',', $selects);
	}

	protected function makeRandomString ($length=8) {
		$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!%,-:;@_{}~";
		for ($i = 0, $makepass = '', $len = strlen($chars); $i < $length; $i++) $makepass .= $chars[mt_rand(0, $len-1)];
		return $makepass;
	}
}
