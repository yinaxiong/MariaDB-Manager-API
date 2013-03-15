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
 * Definitions required for PHP code in the SkySQL Cloud Data Suite.
 */

namespace SkySQL\SCDS;

define ('ADMIN_DATABASE_PATH', '/usr/local/skysql/SQLite/AdminConsole/admin'); // The path to the admin DB file
define ('ADMIN_DATABASE_CONNECTION', 'sqlite:'.ADMIN_DATABASE_PATH); // The admin DB connection string
define ('ADMIN_DATABASE_USER', ''); // Admin DB user - not required for SQLite
define ('ADMIN_DATABASE_PASSWORD', ''); // Admin DB password - not required for SQLite
define ('API_LOG_DIRECTORY', '/usr/local/skysql/log'); // Directory for log file
define ('ADMIN_ERROR_NOTIFY_EMAIL', 'martin.brampton@skysql.com');
define ('API_TIME_ZONE', 'Europe/Rome');
define ('API_SHELL_PATH', 'shell/');

// Used with the getParam method of Request class
define( '_MOS_NOTRIM', 0x0001 );  		// prevent getParam trimming input
define( '_MOS_ALLOWHTML', 0x0002 );		// cause getParam to allow HTML - purified on user side
define( '_MOS_ALLOWRAW', 0x0004 );		// suppresses forcing of integer if default is numeric
define( '_MOS_NOSTRIP', 0x0008 );		// suppress stripping of magic quotes
