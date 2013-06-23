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
 * The CacheDiskStorage class implements disk caching, and is
 * derived from the Aliro class aliroCacheDiskStorage which is GNU GPL code
 * that that is copyrighted by Aliro Software Limited (http://aliro.org).
 * 
 */

namespace SkySQL\COMMON\CACHE;

if (basename(@$_SERVER['REQUEST_URI']) == basename(__FILE__)) die ('This software is for use within a larger system');

define ('_BLOCK_PHP_EXECUTION_HEADER', "<?php die('Cache is private') ?>");

abstract class aliroCacheStorage {
	protected $sizelimit = 0;
	protected $timeout = 0;

	public function __construct ($sizelimit, $timeout) {
		$this->sizelimit = $sizelimit;
		$this->timeout = $timeout;
	}

	public function storeData ($id, $data) {
		return true;
	}

	public function getData ($id) {}

	public function delete ($id) {}

	public function deleteAll () {}

	public function getBasePath () {}

	public function setTimeout ($timeout) {
		$this->timeout = $timeout;
	}

	protected function T_ ($string) {
		return function_exists('T_') ? T_($string) : $string;
	}

	protected function checkSize ($data, $id, $reportSizeError=true) {
		if (strlen($data) > $this->sizelimit) {
			if ($reportSizeError) trigger_error(sprintf($this->T_('Cache failed on size limit, ID %s, actual size %s, limit %s'), $id, strlen($data), $this->sizelimit));
			$this->delete($id);
			return false;
		}
		else return true;
	}

	protected function extractObject ($string, $time_limit=0) {
		$s = substr($string, 0, -32);
		$object = ($s AND (md5($s) == substr($string, -32))) ? unserialize($s) : null;
		if (is_object($object)) {
			$time_limit = $time_limit ? $time_limit : $this->timeout;
			$stamp = @$object->aliroCacheTimer;
			if ((time() - abs($stamp)) <= $time_limit) return $stamp > 0 ? $object : @$object->aliroCacheData;
		}
		return null;
	}
}

class CacheDiskStorage extends aliroCacheStorage {

	public function getBasePath () {
		return _SKYSQL_API_CACHE_DIRECTORY;
	}

	public function storeData ($id, $data, $reportSizeError=true) {
		if (!_SKYSQL_API_CACHE_DIRECTORY) return false;
		$dir = dirname($id);
		clearstatcache();
		if (!file_exists($dir)) @mkdir($dir, 0777, true);
		return $this->checkSize($data, $id, $reportSizeError) AND is_writeable(dirname($id)) ? @file_put_contents($id.'.php', _BLOCK_PHP_EXECUTION_HEADER.$data, LOCK_EX) : false;
	}

	public function getData ($id, $time_limit=0) {
		if (_SKYSQL_API_CACHE_DIRECTORY AND file_exists($id.'.php') AND ($string = @file_get_contents($id.'.php'))) {
			$dataparts = explode(_BLOCK_PHP_EXECUTION_HEADER, $string);
			return $this->extractObject (end($dataparts), $time_limit);
		}
		return null;
	}

	public function delete ($id) {
		if (_SKYSQL_API_CACHE_DIRECTORY) @unlink($id.'.php');
	}

	public function deleteAll () {
		// ??
	}
}