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

namespace SkySQL\SCDS\API\models;

use SkySQL\SCDS\API\Request;
use SkySQL\SCDS\API\API;

abstract class TaskScheduleCommon extends EntityModel {
	
	public static function select () {
		list($total, $entities) = call_user_func_array('parent::select', func_get_args());
		foreach ($entities as &$entity) $entity = self::formatParameters($entity);
		return array($total, $entities);
	}
	
	public static function getByID () {
		$entity = call_user_func_array('parent::getByID', func_get_args());
		return self::formatParameters(@$entity);
	}
	
	protected static function formatParameters ($entity) {
		if (!empty($entity->parameters)) {
			$parmarray = self::getParameterArray($entity);
			if (Request::getInstance()->compareVersion('1.0', 'gt')) $entity->parameters = (array) @$parmarray;
			else {
				foreach ($parmarray as $name=>$value) $parmparts[] = "$name=$value";
				$entity->parameters = implode('|', (array) @$parmparts);
			}
		}
		return $entity;
	}
	
	public static function getParameterArray ($entity) {
		if (!empty($entity->parameters)) {
			$parmarray = json_decode($entity->parameters, true);
			if (!$parmarray) {
				$key = Request::getInstance()->getAPIKey();
				$pairs = explode('|', $entity->parameters);
				foreach ($pairs as $pair) {
					$parts = explode('=', $pair, 2);
					if (isset($parts[1])) {
						$value = in_array($parts[0], API::$encryptedfields) ? EncryptionManager::decryptOneField($parts[1], $key) : $parts[1];
						$parmarray[$parts[0]] = $value;
					}
				}
			}
		}
		return (array) @$parmarray;
	}
}
