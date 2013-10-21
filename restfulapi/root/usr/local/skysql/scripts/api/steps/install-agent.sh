#!/bin/sh
#
#  Part of MariaDB Manager API
#
# This file is distributed as part of SkySQL Manager.  It is free
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
# Copyright 2013 (c) SkySQL Ab
#
# Author: Marcos Amaral
# Date: July 2013
#
#
# This script is responsible for the installation of the MariaDB-Manager-GREX package
# on the target node.
#
# Parameters:
# $1 IP of the node
# $2 TaskID for the invoking Task
# $3 Other parameters (root_password is necessary)

nodeip=$1
taskid=$2
params=$(echo $3 | tr "&" "\n")

# Parameter parsing and validation
for param in $params
do
        param_name=$(echo $param | cut -d = -f 1)
        param_value=$(echo $param | cut -d = -f 2)

        if [[ "$param_name" == "rootpassword" ]]; then
                rootpwd=$param_value
        fi
done

if [[ "$rootpwd" == "" ]]; then
        echo "Error: system password parameter not defined."
        exit 1
fi

scripts_installed=0;

# Checking if SkySQL Remote Execution Scripts are installed
ssh_return=$(ssh_agent_command "$nodeip" \
	"sudo /usr/local/sbin/skysql/NodeCommand.sh test $taskid $api_host")
if [[ "$ssh_return" == "0" ]]; then
	echo "Info: MariaDB Manager API Agent already installed."
	scripts_installed=1;
fi

# Copying repository information to node
ssh_put_file "$nodeip" "steps/repo/MariaDB.repo" "/etc/yum.repos.d/MariaDB.repo"
ssh_put_file "$nodeip" "steps/repo/SkySQL.repo" "/etc/yum.repos.d/SkySQL.repo"
ssh_put_file "$nodeip" "steps/repo/Percona.repo" "/etc/yum.repos.d/Percona.repo"

ssh_command "$nodeip" "yum -y clean all"
if [[ !scripts_installed ]]; then
	ssh_command "$nodeip" "yum -y install MariaDB-Manager-GREX"
else
	ssh_command "$nodeip" "yum -y update MariaDB-Manager-GREX"
fi

# Getting current node systemid and nodeid
task_json=$(api_call "GET" "task/$taskid" "fields=systemid,nodeid")
task_fields=$(echo $task_json | sed 's|{"task":{||' | sed 's|}}||')

system_id=$(echo $task_fields | awk 'BEGIN { RS=","; FS=":" } \
        { gsub("\"", "", $0); if ($1 == "systemid") print $2; }')
node_id=$(echo $task_fields | awk 'BEGIN { RS=","; FS=":" } \
        { gsub("\"", "", $0); if ($1 == "nodeid") print $2; }')

# Updating node state
(api_call "PUT" "system/$system_id/node/$node_id" "state=connected")
if [[ $? != 0 ]]; then
	echo "Error: Failed to update the node state."
	set_error "Error: Failed to update the node state."
	exit 1
fi

echo "Info: SkySQL Galera remote execution agent successfully installed."
