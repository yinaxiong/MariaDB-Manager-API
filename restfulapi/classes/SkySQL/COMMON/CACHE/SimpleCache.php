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
 * Date: May 2013
 * 
 * The SimpleCache class provides a straightforward cache, and is
 * derived from the Aliro class aliroSimpleCache which is GNU GPL code
 * that that is copyrighted by Aliro Software Limited (http://aliro.org).
 * 
 */

namespace SkySQL\COMMON\CACHE;

if (basename(@$_SERVER['REQUEST_URI']) == basename(__FILE__)) die ('This software is for use within a larger system');

class SimpleCache extends BasicCache {
	protected $group = '';
	protected $idencoded = '';
	protected $caching = true;
	protected $timeout = _SKYSQL_API_OBJECT_CACHE_TIME_LIMIT;
	protected $sizelimit = _SKYSQL_API_OBJECT_CACHE_SIZE_LIMIT;
	protected $livesite = '';
	
	public function __construct ($group, $maxsize=0, $timeout=0) {
		if ($group) $this->group = $group;
        else trigger_error ('Cannot create cache without specifying group name');
		if ($maxsize) $this->sizelimit = $maxsize;
		if ($timeout) $this->timeout = $timeout;
		parent::__construct();
	}

	protected function getGroupPath () {
		return $this->getBasePath()."html/$this->group/";
	}

	protected function makeDirectory ($dirpath) {
		return new Directory($dirpath);
	}

	protected function getCachePath ($name) {
		return $this->getGroupPath().$name;
	}
	
	public function setTimeout ($timeout) {
		$this->timeout = $timeout;
		$this->handler->setTimeout($timeout);
	}

	public function clean () {
		$path = $this->getGroupPath();
		$dir = $this->makeDirectory($path);
		$dir->deleteFiles();
	}

	public function get ($id) {
		$this->idencoded = $this->encodeID($id);
		return $this->retrieve ($this->idencoded);
	}

	public function save ($data, $id=null, $reportSizeError=true) {
		if ($id) $this->idencoded = $this->encodeID($id);
		return $this->store ($data, $this->idencoded, $reportSizeError);
	}
	
	protected function encodeID ($id) {
		return md5(serialize($id).$this->livesite);
	}
}
