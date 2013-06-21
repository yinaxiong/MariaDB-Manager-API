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
 * Date: June 2013
 * 
 * The Metadata class provides information about the API
 * 
 */

namespace SkySQL\SCDS\API\controllers;

use SkySQL\COMMON\AdminDatabase;
use stdClass;

final class Metadata extends ImplementAPI {
	private static $ignores = array('EntityModel','NodeStates');
	
	public function listAPI ($uriTable) {
		foreach ($uriTable as $entry) {
			$result[] = array (
				'http' => $entry['http'],
				'uri' => $entry['uri'],
				'method' => $entry['method']
			);
		}
		if ('application/json' == $this->accept) $this->sendResponse($result);
		else {
			echo $this->listAPIHTML($result);
			exit;
		}
	}

	public function getEntities () {
		$entities = $this->getAllModels();
		if (empty($entities)) $this->sendErrorResponse('No entities found',404);
		else {
			if ('application/json' == $this->accept) {
				$entobject = new stdClass();
				foreach ($entities as $entity) {
					$low = strtolower($entity);
				$entobject->$entity = "http://{$_SERVER['SERVER_NAME']}/metadata/entity/$low.json";
				} 
				$this->sendResponse(array('entities' => $entobject));
			}
			else {
				echo $this->entitiesHTML($entities);
				exit;
			}
		}
	}
	
	public function getEntity ($uriparts) {
		$entity = urldecode($uriparts[2]);
		$model = $this->findEntity($entity);
		$modelclass = 'SkySQL\\SCDS\\API\\models\\'.$model;
		if (!$model OR !class_exists($modelclass)) {
			$this->sendErrorResponse("No entity $entity", 404);
		}
		$pragma = AdminDatabase::getInstance()->query("PRAGMA table_info($model)");
		call_user_func(array($modelclass, 'setMetadataFromDB'), (array) $pragma->fetchAll());
		if ('application/json' == $this->accept) {
			$metadata = call_user_func(array($modelclass, 'getMetadataJSON'));
			$this->sendResponse(array(strtolower($model).'_metadata' => $metadata, 'return' => "http://{$_SERVER['SERVER_NAME']}/metadata/entities.json"));
		}
		else {
			echo call_user_func(array($modelclass, 'getMetadataHTML'));
			exit;
		}
	}
	
	protected function getAllModels () {
		$files = scandir(dirname(dirname(__FILE__)).'/models');
		if ($files) foreach ($files as $file) {
			$parts = explode('.',$file);
			if (2 == count($parts) AND 'php' == $parts[1] AND !in_array($parts[0], self::$ignores)) {
				$entities[] = $parts[0];
			}
		}
		return (array) @$entities;
	}
	
	protected function findEntity ($name) {
		$models = $this->getAllModels();
		foreach ($models as $model) {
			if (0 == strcasecmp($model,$name)) return $model;
		}
		return false;
	}
	
	protected function entitiesHTML ($entities) {
		$ehtml = '';
		foreach ($entities as $entity) {
			$elower = strtolower($entity);
			$ehtml .= <<<ONE_ENTITY
			<p>
				<a href="/metadata/entity/$elower.html">$entity</a>
			</p>

ONE_ENTITY;
		
		}
		return <<<ENTITIES
		
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN"
    "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" >
  <head>
    <title>SkySQL Manager - API Entities</title>
  </head>
  <body>
    <h3>API Entities (Resources)</h3>
	$ehtml
  </body>
</html>		
		
ENTITIES;
		
	}
	
	protected function listAPIHTML ($calls) {
		$lhtml = '';
		foreach ($calls as $call) $lhtml .= <<<LINK
				
				<tr>
					<td>{$call['uri']}</td>
					<td>{$call['http']}</td>
					<td>{$call['method']}</td>
				</tr>
				
LINK;
		
		return <<<API
		
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN"
    "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" >
  <head>
    <title>SkySQL Manager - API Calls</title>
  </head>
  <body>
    <h3>API Calls</h3>
	<table>
	$lhtml
	</table>
  </body>
</html>		
		
API;
		
	}
}
