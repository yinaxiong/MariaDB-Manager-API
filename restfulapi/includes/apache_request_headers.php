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
 * The function apache_request_headers is only available when PHP is running as a
 * module.  This replaces it when PHP is running as CGI or e.g. with nginx.  Note
 * that there will need to be mod_rewrite directives to ensure that all the 
 * required headers are transferred into $_SERVER.
 * 
 */

function apache_request_headers() {
	foreach ($_SERVER as $key=>$value) {
		if ('HTTP_' == substr($key,0,5)) $out[substr($key,5)] = $value;
		else $out[$key] = $value;
	}
	return isset($out) ? $out : array();
}
