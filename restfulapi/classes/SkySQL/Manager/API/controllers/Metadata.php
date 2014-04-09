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
 * The Metadata class provides information about the API
 * 
 */

namespace SkySQL\Manager\API\controllers;

use SkySQL\COMMON\AdminDatabase;
use stdClass;
use ReflectionMethod;

final class Metadata extends ImplementAPI {
	private static $ignores = array('EntityModel','NodeProvisioningStates','NodeStatesWithTransitions');
	
	public function listAPI ($uriTable, $fieldregex) {
		if ('application/json' == $this->accept) $this->sendResponse($uriTable);
		elseif ('application/mml' == $this->accept) {
			echo $this->listAPIMarkup($uriTable, $fieldregex, 'MML');
			exit;
		}
		elseif ('application/crl' == $this->accept) {
			echo $this->listAPIMarkup($uriTable, $fieldregex, 'CRL');
			exit;
		}
		else {
			echo $this->listAPIHTML($uriTable, $fieldregex);
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
		$entity = $uriparts[2];
		$model = $this->findEntity($entity);
		$modelclass = 'SkySQL\\Manager\\API\\models\\'.$model;
		if (!$model OR !class_exists($modelclass)) {
			$this->sendErrorResponse("No entity $entity", 404);
		}
		$pragma = AdminDatabase::getInstance()->query("PRAGMA table_info($model)");
		call_user_func(array($modelclass, 'setMetadataFromDB'), (array) $pragma->fetchAll());
		if ('application/json' == $this->accept) {
			$metadata = call_user_func(array($modelclass, 'getMetadataJSON'));
			$this->sendResponse(array(strtolower($model).'_metadata' => $metadata, 'return' => "http://{$_SERVER['SERVER_NAME']}/metadata/entities.json"));
		}
		elseif ('application/mml' == $this->accept) {
			echo call_user_func(array($modelclass, 'getMetadataMML'));
		}
		elseif ('application/crl' == $this->accept) {
			echo call_user_func(array($modelclass, 'getMetadataCRL'));
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
		$accept = $this->requestor->getAccept();
		$suffix = 'application/crl' == $accept ? '.crl' : ('application/mml' == $accept ? '.mml': '.html');
		$ehtml = '';
		foreach ($entities as $entity) {
			$elower = strtolower($entity);
			$ehtml .= <<<ONE_ENTITY
			<p>
				<a href="/metadata/entity/{$elower}{$suffix}">$entity</a>
			</p>

ONE_ENTITY;
		
		}
		return <<<ENTITIES
		
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN"
    "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" >
  <head>
    <title>MariaDB Manager - API Entities</title>
  </head>
  <body>
	<a href="/metadata$suffix">Go to metadata home page</a>
    <h3>API Entities (Resources)</h3>
	$ehtml
  </body>
</html>		
		
ENTITIES;
		
	}
	
	protected function listAPIHTML ($calls, $fieldregex) {
		$lhtml = $fhtml = '';
		foreach ($calls as $call) $lhtml .= <<<LINK
				
				<tr>
					<td>{$call['uri']}</td>
					<td>{$call['http']}</td>
					<td>{$call['title']}</td>
				</tr>
				
LINK;
		
		foreach ($fieldregex as $field=>$regex) {
			$entregex = htmlentities($regex);
			$entfield = htmlentities($field);
			$fhtml .= <<<FIELDCHECK
					
				<tr>
					<td>$entfield</td>
					<td>is validated by regular expression:</td>
					<td>$entregex</td>
				</tr>
					
FIELDCHECK;
			
		}
		return $this->callsPage($lhtml, $fhtml);
	}
	
	protected function callsPage ($callhtml, $fieldhtml) {
		$accept = $this->requestor->getAccept();
		$suffix = 'application/crl' == $accept ? '.crl' : ('application/mml' == $accept ? '.mml': '');
		return <<<API
		
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN"
    "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" >
  <head>
    <title>MariaDB Manager - API Calls</title>
  </head>
  <body>
	<a href="/metadata$suffix">Go to metadata home page</a>
    <h3>API Calls</h3>
	<table>
	$callhtml
	</table>
	<h3>Requirements for URI substitutions</h3>
	<table>
	$fieldhtml
	</table>
	<p>
		<a href="/metadata">Go to metadata home page</a>
	</p>
  </body>
</html>		
		
API;
		
	}

	protected function listAPIMarkup ($calls, $fieldregex, $type='MML') {
		$lhtml = $fhtml = '';
		foreach ($calls as $call) {
			$class = __NAMESPACE__.'\\'.$call['class'];
			if (class_exists($class)) {
				$reflectmethod = new ReflectionMethod($class, $call['method']);
				$reflectparms = $reflectmethod->getParameters();
				for ($i = 0; $i < count($reflectparms); $i++) {
					if ('metadata' == $reflectparms[$i]->name) $metaparm = $i;
				}
				if (isset($metaparm)) {
					$factory = $call['class'].'Factory';
					if (method_exists($class, $factory)) $controller = call_user_func (array($class, $factory), array(), $this->requestor);
					else $controller = new $class($this->requestor);
					for ($i = 0; $i < $metaparm; $i++) $args[$i] = array();
					$args[$metaparm] = 'response';
					$response = call_user_func_array(array($controller, $call['method']), $args);
					$args[$metaparm] = 'many';
					$many = call_user_func_array(array($controller, $call['method']), $args);
					$args[$metaparm] = 'parameters';
					$parameters = call_user_func_array(array($controller, $call['method']), $args);
					unset($args, $metaparm);
				}
			}
			if (empty($response)) $response = 'unknown';
			if (empty($many)) $many = 'unknown';
			if (empty($parameters)) $parameters = 'unknown';

			$uri = htmlentities($call['uri']);
			if ('MML' == $type) $lhtml .= <<<LINK_MML

h4. {$call['title']} <br />
<br />
*HTTP Method:* {$call['http']} <br />
*Request URI:* $uri <br />
*Parameters:* $parameters<br />
*Successful Response:* $response <br />
*One or Many Resources:* $many <br />
<br />
			
LINK_MML;

			else $lhtml .= <<<LINK_CRL
					
===={$call['title']}==== <br />
<br />
**HTTP Method:** {$call['http']} <br />
**Request URI:** $uri <br />
**Parameters:** $parameters<br />
**Successful Response:** $response <br />
**One or Many Resources:** $many <br />
<br />
					
LINK_CRL;

			unset($response, $many, $parameters);
		}
		foreach ($fieldregex as $field=>$regex) {
			$entregex = htmlentities($regex);
			$entfield = htmlentities(htmlentities($field));
			$fhtml .= <<<FIELDCHECK
					
| $entfield | is validated by regular expression: | @$entregex@ | <br />
					
FIELDCHECK;
			
		}
		return $this->callsPage($lhtml, $fhtml);
	}
	
	public function metadataSummary () {
		$codedate = _API_CODE_ISSUE_DATE;
		$version = _API_VERSION_NUMBER;
		$accept = $this->requestor->getAccept();
		$suffix = 'application/crl' == $accept ? '.crl' : ('application/mml' == $accept ? '.mml': '');
		echo <<<METADATA
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN"
    "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" >
  <head>
    <title>MariaDB Manager - API Calls</title>
  </head>
  <body>
    <h2>MariaDB Manager - API - Metadata</h2>
	<p>
	This page is generated by version $version of the API, with code issue date $codedate.
	There are other metadata resources available:
	</p>
	<ul>
		<li><a href="/metadata/apilist$suffix">List of legal API calls</a></li>
		<li><a href="/metadata/entities$suffix">List of the main entities handled by the API</a></li>
  </body>
</html>		
				
METADATA;
		
		exit;
	}
}
