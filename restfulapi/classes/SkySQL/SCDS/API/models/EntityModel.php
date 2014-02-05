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
	
	final public function entityName () {
		$classparts = explode('\\', get_class());
		return end($classparts);
	}

	final public static function getAll ($fromcache=true) {
		if ($fromcache AND isset(static::$managerclass)) {
			$manager = call_user_func(array(static::$managerclass, 'getInstance'));
			if (method_exists($manager, 'getAll')) return $manager->getAll();
		}
		$getall = AdminDatabase::getInstance()->prepare(sprintf(static::$selectAllSQL, self::getSelects(), ''));
		$getall->execute();
		$entities = $getall->fetchAll(PDO::FETCH_CLASS|PDO::FETCH_PROPS_LATE, get_called_class(), static::$getAllCTO);
		foreach ($entities as &$entity) $entity = self::fixDate($entity);
		return $entities;
	}
	
	final public static function getByID () {
		if (isset(static::$managerclass)) {
			$manager = call_user_func(array(static::$managerclass, 'getInstance'));
			if (method_exists($manager, 'getByID')) return call_user_func_array(array($manager,'getByID'), func_get_args());
		}
		$request = Request::getInstance();
		$classname = get_called_class();
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
		$entities = $select->fetchAll(PDO::FETCH_CLASS|PDO::FETCH_PROPS_LATE, $classname, static::$getAllCTO);
		$entity = $entities ? $entities[0] : null;
		if ($entity) {
			if (method_exists($entity, 'derivedFields')) $entity->derivedFields();
			return self::fixDate($entity);
		}
		return null;
	}

	// Method and all calls to it can be removed when API version 1.0 is obsolete
	final protected function removeSensitiveParameters () {
		$request = Request::getInstance();
		if (!empty($this->bind[':parameters'])) {
			$request->parse_str($this->bind[':parameters'], $parray);
			if (count($parray)) {
				foreach (API::$encryptedfields as $field) if (isset($parray[$field])) unset($parray[$field]);
				foreach ($parray as $field=>$value) $newparray[] = "$field=$value";
				$this->bind[':parameters'] = implode('&', (array) @$newparray);
			}
		}
	}
	
	final protected function processParameters () {
		$request = Request::getInstance();
		foreach ($request->getAllParamNames($request->getMethod()) as $paramname) {
			$split = explode('param-', $paramname);
			if (!empty($split[1])) $parameters[$split[1]] = $request->getParam($request->getMethod(), $split[1]);
			$split = explode('xparam-', $paramname);
			if (!empty($split[1])) {
				if ('Schedule' == get_class()) $request->sendErrorResponse("Encrypted parameters are not permitted for scheduled commands", 400);
				$encrypted[$split[1]] = EncryptionManager::decryptOneField($request->getParam($request->getMethod(), $split[1]), $request->getAPIKey());
			}
		}
		if (isset($parameters)) $this->setInsertValue('parameters', json_encode($parameters));
		if (isset($encrypted)) $this->xparameters = json_encode($parameters);
	}

	final private static function fixDate (&$entity) {
		foreach (static::$fields as $name=>$about) {
			if (!empty($entity->$name) AND ('datetime' == @$about['validate'] OR 'datetime' == @$about['forced'])) $entity->$name = date('r', strtotime($entity->$name));
		}
		return $entity;
	}
	
	final public function withDateFix () {
		return self::fixDate($this);
	}
	
	final public static function select () {
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
		$entities = $select->fetchAll(PDO::FETCH_CLASS|PDO::FETCH_PROPS_LATE, get_called_class(), static::$getAllCTO);
		foreach ($entities as &$entity) {
			if (method_exists($entity, 'derivedFields')) $entity->derivedFields();
			$entity = self::fixDate($entity);
		}
		return array($total, $entities);
	}
	
	final private static function limitsClause ($controller) {
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
		$this->validateInsert();
		$database = AdminDatabase::getInstance();
		$insert = $database->prepare(sprintf(static::$insertSQL, implode(',',$this->insname), implode(',',$this->insvalue)));
		$insert->execute($this->bind);
		$insertkey = $this->insertedKey($database->lastInsertId());
		$database->commitTransaction();
		$this->clearCache(true);
		$request = Request::getInstance();
		if ($alwaysrespond) {
			if (version_compare($request->getVersion(), '1.0', 'gt') AND method_exists($this, 'requestURI')) {
				$returncode = 201;
				$requestURI = $this->requestURI();
			}
			else $returncode = 200;
			$request->sendResponse(array('updatecount' => 0,  'insertkey' => $insertkey), $returncode, @$requestURI);
		}
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
	
	public function delete ($alwaysrespond=true) {
		$this->settersAndBinds(__FUNCTION__);
		$delete = AdminDatabase::getInstance()->prepare(static::$deleteSQL);
		$delete->execute($this->bind);
		$counter = $delete->rowCount();
		if ($counter) $this->clearCache(true);
		if ($alwaysrespond) {
		$request = Request::getInstance();
		if ($counter) $request->sendResponse(array('deletecount' => $counter));
			else $request->sendErrorResponse(sprintf("Delete %s did not match any %s", get_class(), get_class()), 404);
		}
	}
	
	final public function getKeys () {
		foreach (array_keys(static::$keys) as $name) {
			if (empty($this->$name)) Request::getInstance()->sendErrorResponse(sprintf("Instance of %s did not have value for key field %s", get_class($this), $name), 500);
			$keydata[] = $this->$name;
		}
		return (array) @$keydata;
	}
	
	final protected function clearCache ($immediate=false) {
		if (isset(static::$managerclass)) {
			$manager = call_user_func(array(static::$managerclass,'getInstance'));
			$manager->clearCache($immediate);
		}
	}
	
	final protected function setDefaultDate ($name) {
		if (empty($this->bind[":$name"])) {
			$this->setInsertValue($name, date('Y-m-d H:i:s'));
		}
	}
	
	final protected function setCorrectFormatDate ($name) {
		$bindname = ":$name";
		if (!empty($this->bind[$bindname])) {
			$unixtime = strtotime($this->bind[$bindname]);
			$this->$name = $this->bind[$bindname] = self::formatDate($unixtime);
		}
	}
	
	final protected static function formatDate ($unixtime) {
		return date('Y-m-d H:i:s', ($unixtime ? $unixtime : time()));
	}
	
	final protected function setCorrectFormatDateWithDefault ($name) {
		$this->setCorrectFormatDate($name);
		$this->setDefaultDate($name);
	}
	
	protected function insertedKey ($insertid) {
		return $insertid;
	}
	
	protected function validateInsert () {}
	
	protected function validateUpdate () {}
	
	final public static function checkLegal ($extras=array()) {
		$request = Request::getInstance();
		$okfields = array_merge(array_keys(static::$fields), array_keys(static::$keys), (array) $extras);
		$illegals = array_diff($request->getAllParamNames($request->getMethod()),$okfields);
		if (count($illegals)) {
			$illegalist = implode (', ', $illegals);
			$request->sendErrorResponse("Parameter(s) $illegalist not recognised",400);
		}
	}
	
	final public function setPropertiesFromParams () {
		$this->settersAndBinds('insert');
	}
	
	final private function settersAndBinds ($caller) {
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
					if ('insert' == $caller OR !$request->paramEmpty($source, $name) OR !empty($about['forced']) OR !empty($this->$name)) {
						// setter is only needed for updates, but is set anyway
						$this->setter[] = $about['sqlname'].' = '.$bindname;
						$this->$name = $this->bind[$bindname] = self::getParam($source, $name, $about, @$this->$name);
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
	
	final public function copyProperties ($from) {
		foreach (array_keys(static::$fields) as $name) if (isset($from->$name)) $this->$name = $from->$name;
	}
	
	final protected function setInsertValue ($name, $value) {
		if (isset(static::$fields[$name])) {
			$bindname = ":$name";
			$this->$name = $this->bind[$bindname] = $value;
			$sqlname = static::$fields[$name]['sqlname'];
			$sub = array_search($sqlname, $this->insname);
			if ($sub) unset($this->insname[$sub], $this->insvalue[$sub]);
			$this->insname[] = $sqlname;
			$this->insvalue[] = $bindname;
			$this->setter[] = "$sqlname = $bindname";
		}
		else Request::getInstance()->sendErrorResponse(sprintf("Attempt to set an insert value for '%s' which is not a valid field", $name), 500);
	}
	
	final protected function calendarDate () {
		$savezone = date_default_timezone_get();
		date_default_timezone_set('UTC');
		$date = date('Ymd\THis\Z');
		date_default_timezone_set($savezone);
		return $date;
	}
	
	final protected static function dateRange ($dates, $datefield, $entitiesname) {
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

	final private static function wheresAndBinds ($controller, $args) {
		// Default return is a pair of empty arrays; otherwise entity specific handling of selector parameters
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
	
	final private static function filterData ($controller, $args) {
	}
	
	// Can be overriden by subclasses
	protected static function specialSelected ($args) {
		return array(array(), array());
	}
	
	final private static function getParam ($source, $name, $about, $priordefault=null) {
		$request = Request::getInstance();
		if (isset($about['forced'])) {
			if ('datetime' == $about['forced']) $data = date('Y-m-d H:i:s');
		}
		$mask = empty($about['mask']) ? 0 : $about['mask'];
		if (!isset($data)) $data = $request->getParam($source, $name, (null === $priordefault ? $about['default'] : $priordefault), $mask);
		if (@$about['validate']) {
			$method = $about['validate'];
			if (method_exists(__CLASS__, $method)) {
				if (!self::$method($data)) self::$validationerrors[] = "Field '$name' with value '$data' failed $method validation";
				if ('datetime' == $method AND $data) $data = self::formatDate(strtotime($data));
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

	final private static function getSelects ($selects=array()) {
		foreach (static::$fields as $name=>$about) $selects[] = "{$about['sqlname']} AS $name";
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
		$entityname = get_called_class();
		list($keyrows, $datarows, $extrarows) = self::getMetadataRows('dataRowHTML');
		if ($extrarows) $extrahtml = <<<EXTRA
				
  <tbody>
    <tr>
      <th class="subhead" scope="rowgroup" colspan="3">Derived information</th>
    </tr>
    $extrarows
   </tbody>
				
EXTRA;

		else $extrahtml = '';
		
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
	
	public static function getMetadataMML () {
		$entityname = get_called_class();
		list($keyrows, $datarows, $extrarows) = self::getMetadataRows('dataRowMML');
		if ($extrarows) $extrarows = <<<EXTRA
| _Derived information_ | | | <br />
$extrarows
				
EXTRA;
		echo <<<ENTITY
		
<div id="markup">
<h3>$entityname</h3>
|_. Field Name |_. Type |_. Description | <br />
| _Key fields_ | | | <br />
$keyrows
| _Data fields_ | | | <br />
$datarows
$extrarows
</div>
		
ENTITY;
				
		exit;
	}
	
	public static function getMetadataCRL () {
		$entityname = get_called_class();
		list($keyrows, $datarows, $extrarows) = self::getMetadataRows('dataRowCRL');
		if ($extrarows) $extrarows = <<<EXTRA
| =**Derived information** |= |= | <br />
$extrarows
				
EXTRA;
		echo <<<ENTITY
		
<div id="markup">
<h3>$entityname</h3>
|=Field Name |=Type |=Description | <br />
| //Key fields// | | | <br />
$keyrows
| //Data fields// | | | <br />
$datarows
$extrarows
</div>
		
ENTITY;
				
		exit;
	}
	
	protected static function getMetadataRows ($rowmethod) {
		$keyrows = $datarows = $extrarows = '';
		foreach (static::$keys as $name=>$key) {
			$description = @$key['desc'];
			$keyrows .= self::$rowmethod($name, @$key['type'], $description);
		}
		foreach (static::$fields as $name=>$field) {
			$description = @$field['desc'];
			$datarows .= self::$rowmethod($name, @$field['type'], $description);
		}
		if (!empty(static::$derived)) foreach (static::$derived as $name=>$field) {
			$description = @$field['desc'];
			$extrarows .= self::$rowmethod($name, @$field['type'], $description);
		}
		return array($keyrows, $datarows, $extrarows);
	}
	
	protected static function dataRowHTML ($name, $type, $description) {
	 return <<<HTMLROW
		<tr>
			<td>$name</td>
			<td>$type</td>
			<td>$description</td>
		</tr>
				
HTMLROW;
			
	}
	
	protected static function dataRowMML ($name, $type, $description) {
		return <<<MMLROW
| $name | $type | $description | <br />
				
MMLROW;
			
		
	}
	
	protected static function dataRowCRL ($name, $type, $description) {
		return <<<MMLROW
| $name | $type | $description | <br />
				
MMLROW;
			
		
	}
}
