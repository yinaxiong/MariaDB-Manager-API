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
 * The NodeStates class operates as a finite state machine to model possible node states
 * and the transitions between them.
 * 
 */

namespace SkySQL\SCDS\API\models;

interface NodeState {
    public function stopNode();
    public function startNode();
    public function isolateNode();
    public function replicateNode();
    public function backupNode();
    public function restoreNode();
	public function endTransition();
	public function cancelTransition();
}

abstract class NodeNullState implements NodeState {
	// These are all possible actions, and may be overridden in subclasses
	// The values are permanent for a particular class
	protected static $stopNode = false;
	protected static $startNode = false;
	protected static $isolateNode = false;
	protected static $replicateNode = false;
	protected static $backupNode = false;
	protected static $restoreNode = false;
	
	// These are other static data items, and their names must be listed in
	// the method allActions to exclude them from possible actions.
	// Property "transitions" is permanent for a particular class.
	protected static $transitions = false;
	// Property "actions" is computed by checking all static properties
	// and excluding the listed static items.
	protected static $actions = null;
	
	// These properties can vary for a particular object
	protected $transitioning = false;
	protected $previous = null;
	
	public function stateName () {
		$class = get_class($this);
		return $this->transitioning ? "Transitioning into $class" : $class;
	}
	
	public function possibleActions () {
		if ($this->transitioning) return "End of transition actions only\n";
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
		return array_diff(array_keys(get_class_vars(__CLASS__)),array('transitions','actions'));
	}
	
	protected function transitions () {
		return static::$transitions;
	}
	
	protected function doAction ($method, $newstate) {
		if (static::$$method AND !$this->transitioning) {
	        $newstate = is_object($newstate) ? $newstate : new $newstate;
			if ($newstate->transitions()) {
				$newstate->transitioning = true;
				$newstate->previous = $this;
				$class = get_class($newstate);
				echo "Transitioning into $class by ";
			}
			echo "Doing $method\n";
			return $newstate;
		}
		else {
	        throw new LogicException("Cannot do $method when in {$this->stateName()} state\n");
		}
	}
	
	public function endTransition () {
		if (!$this->transitioning) throw new LogicException ("Cannot end, not transitioning");
		$this->transitioning = false;
		$class = get_class($this);
		echo "Completed transition into $class\n";
		return $this;
	}
	
	public function cancelTransition () {
		if (!$this->transitioning) throw new LogicException ("Cannot cancel, not transitioning");
		$this->transitioning = false;
		$class = get_class($this);
		echo "Cancelled transition into $class\n";
		return $this->previous;
	}
	
    public function stopNode () {
		return $this->doAction(__FUNCTION__, 'NodeSlaveStopped');
    }
	
    public function startNode () {
		return $this->doAction(__FUNCTION__, 'NodeSlaveOffline');
    }
	
    public function isolateNode () {
		return $this->doAction(__FUNCTION__, 'NodeSlaveOffline');
    }
	
	public function replicateNode () {
		return $this->doAction(__FUNCTION__, 'NodeSlaveOnline');
	}
	
	public function promoteNode () {
		return $this->doAction(__FUNCTION__, 'NodeMaster');
	}
	
	public function backupNode () {
		return $this->doAction(__FUNCTION__, 'NodeSlaveOffline');
	}
	
	public function restoreNode () {
		return $this->doAction(__FUNCTION__, 'NodeSlaveOffline');
	}
}

class NodeMaster extends NodeNullState implements NodeState {
	protected static $stopNode = true;
}

class NodeSlaveOnline extends NodeNullState implements NodeState {
	protected static $isolateNode = true;
	protected static $stopNode = true;
	protected static $promoteNode = true;
}

class NodeSlaveOffline extends NodeNullState implements NodeState {
	protected static $stopNode = true;
	protected static $replicateNode = true;
	protected static $backupNode = true;
	protected static $restoreNode = true;
}

class NodeSlaveStopped extends NodeNullState implements NodeState {
	protected static $startNode = true;
}

class NodeSlaveError extends NodeNullState implements NodeState {
	protected static $stopNode = true;
}

class TestNodeStateSequence {
    protected $state;
	
    public function __construct($initialstate) {
        $this->state = new $initialstate;
		echo "Started sequence in state $initialstate\n";
    }

	public function getState () {
		echo "State is {$this->state->stateName()}\n";
		echo "Possible actions are {$this->state->possibleActions()}\n";
	}

    public function testSequence($steps) {
		try {
			foreach ($steps as $step) {
				$this->state = $this->state->$step();
				$this->getState();
			}
		}
        catch (LogicException $e) {
			echo "Failed to do step $step because {$e->getMessage()}\n";
		}
		echo "Completed all steps\n";
    }
}
