#!/bin/bash
#
# This file is distributed as part of the MariaDB Manager.  It is free
# software: you can redistribute it and/or modify it under the terms of the
# GNU General Public License as published by the Free Software Foundation,
# version 2.
#
# This program is distributed in the hope that it will be useful, but WITHOUT
# ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
# FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
# details.
#
# You should have received a copy of the GNU General Public License along with
# this program; if not, write to the Free Software Foundation, Inc., 51
# Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
#
# Copyright 2014 SkySQL Corporation Ab
#
# Author: Massimo Siani
# Date: May 2014
#
#
# Triggers the update for a node with Script Release = 1.0.
#
# Parameters:
# $1 IP of the node
# $2 TaskID for the invoking Task

cmd_logger_info () {
	logger -p user.info -t MariaDB-Manager-Remote "$1"
}
cmd_logger_error () {
        logger -p user.error -t MariaDB-Manager-Remote "$1"
}

cmd_logger_info "Starting the upgrade from Script Release 1.0"

current_dir=$(pwd)
cd $(dirname $0)

if [[ ! -f "upgrade.sh" ]] ; then
	cmd_logger_error "Upgrade script not found in $(pwd): skipping upgrade"
	exit 1
fi

mv upgrade.sh /usr/local/sbin/skysql/steps/
mv mysql-config.sh /usr/local/sbin/skysql/
cd /usr/local/sbin/skysql/
/usr/local/sbin/skysql/steps/upgrade.sh
return=$?

cd $current_dir

cmd_logger_info "Upgrade ended with code $return"

exit 0