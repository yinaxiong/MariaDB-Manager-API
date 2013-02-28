<?php

/*
 * Part of the SCDS API.
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
 * Date: February 2013
 * 
 * The Profiler class provides simple mechanisms for timing operations.
 * 
 */

namespace SkySQL\COMMON;

class Profiler {
    private $start=0;
    private $prefix='';

    function __construct ( $prefix='' ) {
        $this->start = microtime(true);
        $this->prefix = $prefix;
    }

	public function reset () {
		$this->start = microtime(true);
	}

    public function mark( $label ) {
        return sprintf ( "\n$this->prefix %.3f $label", microtime(true) - $this->start );
    }

    public function getElapsed () {
    	return microtime(true) - $this->start;
    }
}
