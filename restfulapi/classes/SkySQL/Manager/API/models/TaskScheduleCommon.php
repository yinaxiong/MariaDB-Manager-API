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
 * The TaskScheduleCommon class is is the abstract parent of the Task and Schedule model classes.
 * 
 */

namespace SkySQL\Manager\API\models;

use stdClass;
use SkySQL\Manager\API\Request;
use SkySQL\Manager\API\API;
use SkySQL\Manager\API\managers\EncryptionManager;

abstract class TaskScheduleCommon extends EntityModel {
	
	public static function select () {
		list($total, $entities) = call_user_func_array('parent::select', func_get_args());
		foreach ($entities as &$entity) $entity->formatParameters();
		return array($total, $entities);
	}
	
	public static function getByID () {
		$entity = call_user_func_array('parent::getByID', func_get_args());
		if ($entity) $entity->formatParameters();
		return $entity;
	}
	
	public function formatParameters () {
		$parmobject = $this->getParameterObject();
		if (Request::getInstance()->compareVersion('1.0', 'gt')) $this->parameters = $parmobject;
		else {
			foreach ($parmobject as $name=>$value) {
				if ('backup' == $this->command) {
					if ('type' == $name) $parmparts[] = (1 == $value ? 'Full' : 'Incremental');
					if ('parent' == $name) $parmparts[] = $value;
				}
				elseif ('restore' == $this->command) {
					if ('id' == $name) $parmparts[] = $value;
				}
				else $parmparts[] = "$name=$value";
			}
			$this->parameters = implode('|', (array) @$parmparts);
		}
	}
	
	public function getParameterObject () {
		if (empty($this->parameters)) return new stdClass();
		$parmobject = json_decode($this->parameters);
		if (!$parmobject) $parmobject = $this->makeParameterObjectVersion1();
		return $parmobject;
	}
	
	// This can be removed when API 1.0 is eventually considered obsolete and old parameters are purged from DB
	protected function makeParameterObjectVersion1 () {
		$parmobject = new stdClass();
		foreach (explode('|', $this->parameters) as $pair) {
			$parts = explode('=', $pair, 2);
			if (isset($parts[1])) {
				$request = Request::getInstance();
				$method = $request->compareVersion('1.0', 'gt') ? 'decrypt' : 'decryptOneField';
				$value = in_array($parts[0], API::$encryptedfields) ? EncryptionManager::$method($parts[1], $request->getAPIKey()) : $parts[1];
				$property = $parts[0];
				$parmobject->$property = $value;
			}
			elseif ($parts[0]) {
				if (is_numeric($parts[0])) {
					if ('restore' == $this->command) $parmobject->id = (int) $parts[0];
					elseif ('backup' == $this->command) $parmobject->parent = (int) $parts[0];
				}
				elseif ('backup' == $this->command) $this->backupParameterVersion1($parts[0], $parmobject);
			}
		}
		return $parmobject;
	}
	
	// This can be removed when API 1.0 is eventually considered obsolete and old parameters are purged from DB
	protected function backupParameterVersion1 ($parameter, $parmobject) {
		$matches = array(); // Make Netbeans happy, not needed
		if (preg_match('/((Incremental) ([0-9]+)|Full)/i', trim($parameter), $matches) ) {
			if (isset($matches[3])) {
				$parmobject->type = 2;
				$parmobject->parent = (int) $matches[3];
			}
			else $parmobject->type = 1;
		}
		else {
			if (0 == strcasecmp('Incremental', $parameter)) $parmobject->type = 2;
		}
	}
}
