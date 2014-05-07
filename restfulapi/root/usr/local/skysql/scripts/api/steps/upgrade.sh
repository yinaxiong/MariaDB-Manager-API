#!/bin/bash
#
# This file is distributed as part of MariaDB Manager.  It is free
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
# Triggers the upgrade mechanism if the data node software does not support it.
#
# Parameters:
# $1 The node IP
# $2 The Task ID

. ./functions.sh

cmd_logger_info () {
	logger -p user.info -t MariaDB-Manager-Remote "$1"
}
cmd_logger_error () {
	logger -p user.error -t MariaDB-Manager-Remote "$1"
}

nodeIP="$1"
taskid="$2"

cmd_logger_info "The node $nodeIP does not support upgrades, triggering the upgrade from the Manager node"

# Copy the files on the remote node
ssh_agent_command "$nodeIP" "mkdir -p ~/tmp"
[[ "$?" != "0" ]] && cmd_logger_error "Cannot create a temp folder on the data node $nodeIP" && exit 1
ssh_agent_put_file "$nodeIP" "./steps/upgradefirst.sh" "~/tmp/upgradefirst.sh"
[[ "$?" != "0" ]] && cmd_logger_error "Cannot copy the upgradefirst script on the data node $nodeIP" && exit 1
ssh_agent_put_file "$nodeIP" "./steps/upgradenode.sh" "~/tmp/upgrade.sh"
[[ "$?" != "0" ]] && cmd_logger_error "Cannot copy the upgradenode script on the data node $nodeIP" && exit 1

cmd_logger_info "Upgrade scripts copied to the data node $nodeIP"

cmd_logger_info "Starting the upgrade of the data node"

# Run the remote script
./RunCommand.sh "$taskid" "../../../../../home/skysqlagent/tmp/upgradefirst" "$nodeIP"
[[ "$?" != "0" ]] && cmd_logger_error "Upgrade failed on the data node $nodeIP" && exit 1
