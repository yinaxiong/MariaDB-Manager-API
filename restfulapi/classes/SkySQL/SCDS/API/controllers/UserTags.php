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
 * Date: February 2013
 */

namespace SkySQL\SCDS\API\controllers;

use SkySQL\SCDS\API\managers\UserManager;
use SkySQL\SCDS\API\managers\SystemManager;
use SkySQL\SCDS\API\managers\NodeManager;
use SkySQL\SCDS\API\managers\MonitorManager;
use SkySQL\SCDS\API\models\UserTag;
use SkySQL\SCDS\API\models\User;
use stdClass;

abstract class UserTags extends ImplementAPI {
	protected $username = '';
	protected $tagtype = '';
	protected $tagname = '';
	
	public function __construct ($controller) {
		parent::__construct($controller);
		UserTag::checkLegal();
	}
	
	public static function UserTagsFactory ($uriparts, $controller) {
		$tagtype = ucfirst(trim(substr($uriparts[2], 0, -3)));
		$class = __CLASS__.$tagtype;
		if (class_exists($class)) {
			$object = new $class($controller);
			return $object;
		}
		else $controller->sendErrorResponse("Incorrect tag type $tagtype, no class to handle it", 400);
	}
	
	protected function analyseURI ($uriparts) {
		$this->username = $uriparts[1];
		$user = UserManager::getInstance()->getByName($this->username);
		if (!($user instanceof User)) $this->sendErrorResponse("Username $this->username not recognised", 400);
		$this->tagtype = substr($uriparts[2], 0, -3);
		$this->tagname = @$uriparts[3];
	}
	
	public function getAllUserTags ($uriparts) {
		$this->analyseURI($uriparts);
		$tagnames = UserTag::getTagNames($this->username, $this->tagtype);
		foreach ($tagnames as $this->tagname) $tags[$this->tagname] = $this->getTags();
		$this->sendResponse(array('tags' => (array) @$tags));
	}

	public function getUserTags ($uriparts) {
		$this->analyseURI($uriparts);
        $this->sendResponse(array('tags' => $this->getTags()));
	}
	
	protected function getTags () {
		return UserTag::getTags($this->username, $this->tagtype, $this->tagname);
	}
	
	public function deleteUserTags ($uriparts) {
		$this->analyseURI($uriparts);
		$tag = @$uriparts[4];
		$counter = UserTag::deleteTag($this->username, $this->tagtype, $this->tagname, $tag);
		if ($counter) $this->sendResponse(array('deletecount' => $counter));
		else $this->sendErrorResponse("Delete did not match any user tags", 404);
	}

	public function addUserTags ($uriparts) {
		$this->analyseURI($uriparts);
		$tags = (array) $this->getParam('POST', 'tag', array());
		UserTag::insertTags($this->username, $this->tagtype, $this->tagname, $tags);
		$this->sendResponse("Add user monitor tags processed successfully");
	}
}

class UserTagsSimple extends UserTags {}

class UserTagsMonitor extends UserTags {
	
	protected function getTags () {
		$tags = UserTag::getTags($this->username, $this->tagtype, $this->tagname);
		foreach ($tags as $tag) {
			if (preg_match('/([0-9]+):([0-9]+):([a-z]+)/', $tag, $matches)) {
				$fulltag = $this->makeFullTag($matches[1],$matches[2],$matches[3]);
				if ($this->isTagValid($fulltag)) {
					$fulltags[] = $fulltag;
					continue;
				}
			}
			UserTag::deleteTag($this->username, $this->tagtype, $this->tagname, $tag);
		}
		return (array) @$fulltags;
	}
	
	protected function makeFullTag ($systemid, $nodeid, $monitor) {
		$fulltag = new stdClass();
		$fulltag->systemid = $systemid;
		$fulltag->nodeid = $nodeid;
		$fulltag->monitor = $monitor;
		return $fulltag;
	}
	
	protected function  isTagValid ($tag) {
		$system = SystemManager::getInstance()->getByID($tag->systemid);
		if (!$system) return false;
		if ($tag->nodeid AND !NodeManager::getInstance()->getByID($tag->systemid, $tag->nodeid)) return false;
		if (!MonitorManager::getInstance()->getByID($system->systemtype, $tag->monitor)) return false;
		return true;
	}

	public function addUserTags ($uriparts) {
		$this->analyseURI($uriparts);
		$systemids = (array) $this->getParam('POST', 'systemid', array());
		$nodeids = (array) $this->getParam('POST', 'nodeid', array());
		$monitors = (array) $this->getParam('POST', 'monitor', array());
		if (count($systemids) AND count($systemids) == count($nodeids) AND count($systemids) == count($monitors)) {
			foreach ($systemids as $sub=>$systemid) {
				$fulltag = $this->makeFullTag($systemid, $nodeids[$sub], $monitors[$sub]);
				if ($this->isTagValid($fulltag)) $tags[] = "$systemid:{$nodeids[$sub]}:{$monitors[$sub]}";
				else $errors[] = "Monitor tag with system ID $systemid, node ID {$nodeids[$sub]}, monitor key {$monitors[$sub]} is not valid";
			}
		}
		if (isset($errors)) $this->sendErrorResponse(array('errors' => $errors), 400);
		UserTag::insertTags($this->username, $this->tagtype, $this->tagname, $tags);
		$this->sendResponse("Add user monitor tags processed successfully");
	}
}
