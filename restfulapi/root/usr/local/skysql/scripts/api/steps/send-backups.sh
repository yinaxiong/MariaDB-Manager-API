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
# Copyright 2012-2014 SkySQL Corporation Ab
#
# Author: Marcos Amaral
# Date: March 2014
#
#
# This script sends all necessary backup files to a data node.
#
# Parameters:
# $1 IP of the node
# $2 TaskID for the invoking Task
# $3 Other parameters (backup_id is required)

nodeip="$1"
taskid="$2"
params="$3"

logger -p user.info -t MariaDB-Manager-Task "Sending backup files to node $nodeip"

# Getting current node systemid, command
task_json=$(api_call "GET" "task/$taskid" "fields=systemid,command")
json_error "$task_json"
if [[ "$json_err" != "0" ]]; then
        logger -p user.error -t MariaDB-Manager-Task "Error: Unable to determine System ID."
        set_error "Error: Unable to determine System ID."
        exit 1
fi

task_fields=$(echo $task_json | sed -e 's/{"task":{//' -e 's/}.*}//')

system_id=$(echo $task_fields | awk 'BEGIN { RS=","; FS=":" } \
        { gsub("\"", "", $0); if ($1 == "systemid") print $2; }')
command=$(echo $task_fields | awk 'BEGIN { RS=","; FS=":" } \
        { gsub("\"", "", $0); if ($1 == "command") print $2; }')

# TODO: change for new parameters
backup_id=$params

# Getting backups directory
config_json=$(api_call "GET" "config/backups/path")
json_error "$config_json"
if [[ "$json_err" != "0" ]]; then
        logger -p user.error -t MariaDB-Manager-Task "Error: Unable to determine System ID."
        set_error "Error: Unable to determine System ID."
        exit 1
fi

backups_path=$(echo $config_json | sed -e 's/{"path":"//' -e 's/",".*//')
backups_path=${backups_path//\\/}

while [[ "$backup_id" != "0" ]]; do
	# Getting backup info
	backup_json=$(api_call "GET" "system/$system_id/backup/$backup_id" "fields=backupurl,level,parentid")
	json_error "$backup_json"
	if [[ "$json_err" != "0" ]]; then
        	logger -p user.error -t MariaDB-Manager-Task "Error: Unable to get node information from API."
	       	set_error "Error: Unable to get node information from API."
	        exit 1
	fi

	backup_fields=$(echo $backup_json | sed -e 's/{"backup":{//' -e 's/}.*}//')

	backup_file=$(echo $backup_fields | awk 'BEGIN { RS=","; FS=":" } \
       		{ gsub("\"", "", $0); if ($1 == "backupurl") print $2; }')
	level=$(echo $backup_fields | awk 'BEGIN { RS=","; FS=":" } \
        	{ gsub("\"", "", $0); if ($1 == "level") print $2; }')
	parent_id=$(echo $backup_fields | awk 'BEGIN { RS=","; FS=":" } \
        	{ gsub("\"", "", $0); if ($1 == "parentid") print $2; }')

	rsync_return=$(rsync_send_file "$nodeip" "${backups_path}/${backup_file}.tgz" "/var/backups/${backup_file}.tgz")
	rsync_err_code=$?
	if [[ "$rsync_err_code" != "0" ]]; then
       		logger -p user.error -t MariaDB-Manager-Task "Failed to send backup file to data node $nodeip."
	        set_error "Failed to send backup file to data node $nodeip"
       		exit 1
	fi

	logger -p user.info -t MariaDB-Manager-Task "send-backups - backup file ${backups_path}/${backup_file}.tgz sent to $nodeip"

	if [[ "$level" == "2" ]]; then
		backup_id="$parent_id"
	else
		backup_id="0"
	fi
done

logger -p user.info -t MariaDB-Manager-Task "send-backups - all files sent successfully"
