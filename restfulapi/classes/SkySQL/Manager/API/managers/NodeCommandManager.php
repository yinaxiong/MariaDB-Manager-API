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
 * The NodeCommandManager class caches all node command objects and manipulates them
 * 
 */

namespace SkySQL\Manager\API\managers;

use SkySQL\Manager\API\API;
use SkySQL\Manager\API\models\NodeCommand;

class NodeCommandManager extends EntityManager {
	protected static $instance = null;
	
	protected function __construct () {
		$commands = NodeCommand::getAll(false);
		foreach ($commands as $command) if (!('restore' == $command->command AND 'galera' == $command->systemtype AND 'provisioned' == $command->state)) {
			$command->steps = API::trimCommaSeparatedList($command->steps);
			$this->maincache[$command->command][$command->systemtype][$command->state] = $command;
			$this->simplecache[] = $command;
		}
	}
	
	public static function getInstance () {
		return self::$instance instanceof self ? self::$instance : self::$instance = parent::getCachedSingleton(__CLASS__);
	}
	
	public function getAll () {
		return $this->simplecache;
	}
	
	public function getRunnable ($systemtype, $state) {
		$runnable = array();
		foreach ($this->maincache as $subset) {
			if (isset($subset[$systemtype][$state])) $runnable[] = $subset[$systemtype][$state];
			if (isset($subset['provision'][$state])) $runnable[] = $subset['provision'][$state];
		}
		foreach ($runnable as &$command) {
			unset($command->systemtype, $command->uiorder, $command->state);
		}
		return (array) @$runnable;
	}
	
	public function createCommand () {
		throw new Exception ('The ability to create new commands is not yet implemented');
	}
	
	public function updateCommand ($command, $systemtype, $state) {
		throw new Exception ('The ability to update commands is not yet implemented');
	}
	
	public function deleteCommand ($command, $systemtype, $state) {
		throw new Exception ('The ability to delete commands is not yet implemented');
	}
}
