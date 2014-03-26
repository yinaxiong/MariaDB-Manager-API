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

use stdClass;
use SkySQL\SCDS\API\Request;
use SkySQL\SCDS\API\API;
use SkySQL\SCDS\API\managers\EncryptionManager;

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
		$parmobject = self::getParameterObject($entity);
		if (Request::getInstance()->compareVersion('1.0', 'gt')) $entity->parameters = $parmobject;
		else {
			foreach ($parmobject as $name=>$value) {
				if ('backup' == $entity->command) {
					if ('type' == $name) $parmparts[] = (1 == $value ? 'Full' : 'Incremental');
					if ('parent' == $name) $parmparts[] = $value;
				}
				elseif ('restore' == $entity->command) {
					if ('id' == $name) $parmparts[] = $value;
				}
				else $parmparts[] = "$name=$value";
			}
			$entity->parameters = implode('|', (array) @$parmparts);
		}
		return $entity;
	}
	
	public static function getParameterObject ($entity) {
		$parmobject = json_decode($entity->parameters, true);
		if (!$parmobject) {
			$parmobject = new stdClass();
			foreach (explode('|', $entity->parameters) as $pair) {
				$parts = explode('=', $pair, 2);
				if (isset($parts[1])) {
					$value = in_array($parts[0], API::$encryptedfields) ? EncryptionManager::decryptOneField($parts[1], Request::getInstance()->getAPIKey()) : $parts[1];
					$property = $parts[0];
					$parmobject->$property = $value;
				}
				elseif ($parts[0]) {
					if (0 == strcasecmp('Full', $parts[0])) $parmobject->type = 1;
					elseif (strcasecmp('Incremental', $parts[0])) $parmobject->type = 2;
					elseif (is_numeric($parts[0])) {
						if ('backup' == $entity->command) $parmobject->parent = (int) $parts[0];
						elseif ('restore' == $entity->command) $parmobject->id = (int) $parts[0];							
					}
				}
			}
		}
		return $parmobject;
	}
}
