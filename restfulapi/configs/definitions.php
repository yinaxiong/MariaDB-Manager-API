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

define ('ADMIN_DATABASE_CONNECTION', 'sqlite:/usr/local/skysql/SQLite/AdminConsole/admin'); // The admin DB
define ('ADMIN_DATABASE_USER', ''); // Admin DB user - not required for SQLite
define ('ADMIN_DATABASE_PASSWORD', ''); // Admin DB password - not required for SQLite
define ('API_LOG_DIRECTORY', '/usr/local/skysql/log'); // Directory for log file