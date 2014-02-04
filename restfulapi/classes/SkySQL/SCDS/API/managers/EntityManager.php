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
 * 
 * The EntityManager class provides common resources for managers of the
 * collectives of entities managed by the API.
 * 
 */

namespace SkySQL\SCDS\API\managers;

use SkySQL\COMMON\CACHE\CachedSingleton;


abstract class EntityManager extends CachedSingleton {
	protected $maincache = array();
	protected $simplecache = array();
	
	final private function getKeys () {
		
	}
	
	public function getByID () {
		$data = $this->maincache;
		foreach (func_get_args() as $arg) {
			if (!isset($data[$arg])) return null;
			$data = $data[$arg];
		}
		return $data;
	}

	public function getAll () {
		return array_values($this->maincache);
	}
}
