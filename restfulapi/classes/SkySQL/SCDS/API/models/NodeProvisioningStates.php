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
 * The NodeStates class operates as a finite state machine to model possible node states
 * and the transitions between them.
 * 
 */

namespace SkySQL\SCDS\API\models;

use LogicException;
use DomainException;

class NodeProvisioningStates {}

interface NodeProvisioningState {
	public function make($state);
    public function makeCreated();
    public function makeConnected();
    public function makeUnconnected();
    public function makeUnprovisioned();
    public function makeProvisioned();
    public function makeIncompatible();
    public function makeUnmanaged();
}

abstract class NodeNullState implements NodeProvisioningState {
	// These are all possible actions, and may be overridden in subclasses
	// The values are permanent for a particular class
	protected static $makeCreated = false;
	protected static $makeConnected = false;
	protected static $makeUnconnected = false;
	protected static $makeUnprovisioned = false;
	protected static $makeProvisioned = false;
	protected static $makeIncompatible = false;
	protected static $makeUnmanaged = false;
	
	// These are other static data items, and their names must be listed in
	// the method allActions to exclude them from possible actions.
	// Property "actions" is computed by checking all static properties
	// and excluding the listed static items.
	protected static $actions = null;
	
	// These properties can vary for a particular object
	protected $previous = null;
	
	public static function create ($state) {
		$class = __NAMESPACE__.'\\Node'.ucfirst($state);
		if (class_exists($class, false)) return new $class();
		else throw new LogicException("No state called $state exists, cannot start from it");
	}
	
	public function stateName () {
		$classparts = explode('\\', get_class($this));
		return end($classparts);
	}
	
	public function possibleActions () {
		// Set the list of possible names if not already set
		if (is_null(self::$actions)) self::$actions = $this->allActions();
		foreach (self::$actions as $name) {
			// This will find only actions that are true i.e. possible
			if (!empty(static::$$name)) $actions[] = $name;
		}
		return isset($actions) ? implode(',',$actions) : 'None';
	}
	
	protected function allActions () {
		// All actions are all static properties, except those listed
		// This gives a list of all properties except excluded static properties
		return array_diff(array_keys(get_class_vars(__CLASS__)),array('actions'));
	}
	
	protected function doAction ($method, $newstate) {
		if (static::$$method) {
			if (!is_object($newstate)) {
				$class = __NAMESPACE__.'\\'.$newstate;
				if (!class_exists($class, false)) throw new LogicException("No class '$class' exists, even though method exists");
				$newstate = new $class();
			}
			$newstate->previous = $this;
			return $newstate;
		}
		else {
	        throw new DomainException("Cannot do $method when in {$this->stateName()} state");
		}
	}
	
	public function make ($state) {
		$method = 'make'.ucfirst($state);
		if (method_exists($this, $method)) return $this->$method();
		else throw new DomainException("No state called $state exists, cannot transition to it");
	}
	
    public function makeCreated () {
		return $this->doAction(__FUNCTION__, 'NodeCreated');
    }
	
    public function makeConnected () {
		return $this->doAction(__FUNCTION__, 'NodeConnected');
    }
	
    public function makeUnconnected () {
		return $this->doAction(__FUNCTION__, 'NodeUnconnected');
    }
	
	public function makeUnprovisioned () {
		return $this->doAction(__FUNCTION__, 'NodeUnprovisioned');
	}
	
	public function makeProvisioned () {
		return $this->doAction(__FUNCTION__, 'NodeProvisioned');
	}
	
	public function makeIncompatible () {
		return $this->doAction(__FUNCTION__, 'NodeIncompatible');
	}
	
	public function makeUnmanaged () {
		return $this->doAction(__FUNCTION__, 'NodeSlaveOffline');
	}
}

class NodeCreated extends NodeNullState implements NodeProvisioningState {
	protected static $makeConnected = true;
	protected static $makeUnconnected = true;
}

class NodeConnected extends NodeNullState implements NodeProvisioningState {
	protected static $makeUnprovisioned = true;
	protected static $makeProvisioned = true;
	protected static $makeIncompatible = true;
	protected static $makeUnmanaged = true;
}

class NodeUnconnected extends NodeNullState implements NodeProvisioningState {
	protected static $makeConnected = true;
	protected static $makeUnconnected = true;
}

class NodeUnprovisioned extends NodeNullState implements NodeProvisioningState {
	protected static $makeConnected = true;
	protected static $makeUnconnected = true;
	protected static $makeUnprovisioned = true;
	protected static $makeProvisioned = true;
	protected static $makeIncompatible = true;
	protected static $makeUnmanaged = true;
}

class NodeProvisioned extends NodeNullState implements NodeProvisioningState {
	protected static $makeUnprovisioned = true;
	protected static $makeProvisioned = true;
}

class NodeIncompatible extends NodeNullState implements NodeProvisioningState {
	protected static $makeUnprovisioned = true;
	protected static $makeProvisioned = true;
	protected static $makeIncompatible = true;
	protected static $makeUnmanaged = true;
}

class NodeUnmanaged extends NodeNullState implements NodeProvisioningState {
	protected static $makeConnected = true;
	protected static $makeUnconnected = true;
	protected static $makeUnprovisioned = true;
	protected static $makeProvisioned = true;
	protected static $makeIncompatible = true;
	protected static $makeUnmanaged = true;
}
