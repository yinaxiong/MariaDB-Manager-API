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
 * Date: February 2013
 * 
 * Definitions required for PHP code in MariaDB Enterprise.
 */

namespace SkySQL\Manager;

define ('_API_INI_FILE_LOCATION', '/etc/mariadbmanager/manager.ini');

define ('_SKYSQL_API_OBJECT_CACHE_TIME_DEFAULT', 3600);
define ('_SKYSQL_API_OBJECT_CACHE_SIZE_DEFAULT', 500000);
define ('_SKYSQL_API_MONITOR_INTERVAL_DEFAULT', 1800);
define ('_SKYSQL_API_MONITOR_COUNT_DEFAULT', 15);

// Can be used with the getParam method of Request class
define( '_MOS_NOTRIM', 0x0001 );  		// prevent getParam trimming input
define( '_MOS_ALLOWHTML', 0x0002 );		// cause getParam to allow HTML - purified on user side
define( '_MOS_ALLOWRAW', 0x0004 );		// suppresses forcing of integer if default is numeric
define( '_MOS_NOSTRIP', 0x0008 );		// suppress stripping of magic quotes
