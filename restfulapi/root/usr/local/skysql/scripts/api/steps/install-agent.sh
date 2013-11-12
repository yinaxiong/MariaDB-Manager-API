#!/bin/bash
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

. ./functions.sh

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
        logger -p user.error -t MariaDB-Manager-Task "Error: system password parameter not defined."
        exit 1
fi

scripts_installed=0;

# Checking if SkySQL Remote Execution Scripts are installed
ssh_return=$(ssh_agent_command "$nodeip" \
	"sudo /usr/local/sbin/skysql/NodeCommand.sh test $taskid $api_host")
if [[ "$ssh_return" == "0" ]]; then
        logger -p user.info -t MariaDB-Manager-Task "Info: MariaDB Manager API Agent already installed."
	scripts_installed=1;
fi


# Generating and copying internal repository information to node
sed -e "s/###API-HOST###/$api_host/" steps/repo/SkySQL.repo > /tmp/SkySQL.repo
$(ssh_put_file "$nodeip" "/tmp/SkySQL.repo" "/etc/yum.repos.d/SkySQL.repo")
ssh_err_code=$?
if [[ "$ssh_err_code" != "0" ]]; then
        logger -p user.error -t MariaDB-Manager-Task "Failed to write SkySQL repository file"
        set_error "Failed to install SkySQL Repository"
        exit 1
fi
rm -f /tmp/SkySQL.repo

ssh_command "$nodeip" "yum -y clean all"
if [[ "$scripts_installed" == "0" ]]; then
	ssh_command "$nodeip" "yum -y install MariaDB-Manager-GREX --disablerepo=* --enablerepo=skysql"
else
	ssh_command "$nodeip" "yum -y update MariaDB-Manager-GREX --disablerepo=* --enablerepo=skysql"
fi

# Check to see if the node date/time is in sync
localdate=$(date -u "+%Y%m%d%H%M%S")
remdate=$(sshpass -p "$rootpwd" ssh root@"$nodeip" "date -u +%Y%m%d%H%M%S")
datediff=$(expr "$localdate" - "$remdate")
if [[ "$datediff" -gt "30" || "$datediff" -lt "-30" ]]; then
	set_error "Node date is more than 30 seconds adrift from the server"
	logger -p user.error -t MariaDB-Manager-Task "Node date is more than 30 seconds adrift from the server"
	exit 1
fi

# Getting current node systemid and nodeid
task_json=$(api_call "GET" "task/$taskid" "fields=systemid,nodeid")
json_error "$task_json"
if [[ "$json_err" != "0" ]]; then
	logger -p user.error -t MariaDB-Manager-Task "Error: Unable to determine System ID and Node ID."
	set_error "Error: Unable to determine System and Node ID."
	exit 1
fi

# We have a very simple JSON return to parse, since we asked for two specific fields
# both are which are guaranteed to be numeric, therefore there is no need to worry
# about spaces and quotes in the return data. So we can use a very simplistic approach
# to the parsing.
task_fields=$(echo $task_json | sed -e 's/{"task":{//' -e 's/}}//')

system_id=$(echo $task_fields | awk 'BEGIN { RS=","; FS=":" } \
        { gsub("\"", "", $0); if ($1 == "systemid") print $2; }')
node_id=$(echo $task_fields | awk 'BEGIN { RS=","; FS=":" } \
        { gsub("\"", "", $0); if ($1 == "nodeid") print $2; }')

# Check to see if the node is really in a position to run the scripts, i.e. connected
ssh_return=$(ssh_agent_command "$nodeip" \
	"sudo /usr/local/sbin/skysql/NodeCommand.sh test $taskid $api_host")
if [[ "$ssh_return" != "0" ]]; then
	set_error "Agent script installation failed."
	logger -p user.error -t MariaDB-Manager-Task "Error: Failed to install agent scripts."
	exit 1
fi

# Updating node state
state_json=$(api_call "PUT" "system/$system_id/node/$node_id" "state=connected")
if [[ $? != 0 ]]; then
	logger -p user.error -t MariaDB-Manager-Task "Error: Failed to update the node state."
	set_error "Failed to update the node state."
	exit 1
fi
json_error "$state_json"
if [[ "$json_err" != "0" ]]; then
	logger -p user.error -t MariaDB-Manager-Task "Error: Failed to update the node state."
	set_error "Failed to update the node state."
	exit 1
fi

logger -p user.info -t MariaDB-Manager-Task "Info: SkySQL Galera remote execution agent successfully installed."
exit 0
