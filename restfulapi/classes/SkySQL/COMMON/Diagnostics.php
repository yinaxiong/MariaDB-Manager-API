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
 * Date: March 2013
 * 
 * The Diagnostics class provides useful methods for fault finding.
 * 
 * The trace static method is a utility for debugging and error logging purposes.
 * 
 */

namespace SkySQL\COMMON;

class Diagnostics {
	
	public static function trace ($error=true) {
	    static $counter = 0;
		$html = '';
		foreach(debug_backtrace() as $back) {
		    if (isset($back['file']) AND $back['file']) {
			    $html .= "\n".$back['file'].':'.$back['line'];
			}
		}
		if ($error) $counter++;
		if (1000 < $counter) {
		    echo $html;
		    die (T_('Program killed - Probably looping'));
        }
		return $html;
	}
}