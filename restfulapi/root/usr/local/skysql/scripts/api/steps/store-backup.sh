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
# Author: Massimo Siani
# Date: March 2014
#
#
# This script pulls a backup file from a data node using rsync.
#
# Parameters:
# $1 IP of the node
# $2 TaskID for the invoking Task

nodeip="$1"
taskid="$2"
params="$3"

logger -p user.info -t MariaDB-Manager-Task "Getting backup file from node $nodeip"

# Getting current node systemid
task_json=$(api_call "GET" "task/$taskid" "fields=systemid")
json_error "$task_json"
if [[ "$json_err" != "0" ]]; then
        logger -p user.error -t MariaDB-Manager-Task "Error: Unable to determine System ID."
        set_error "Error: Unable to determine System ID."
        exit 1
fi

task_fields=$(echo $task_json | sed -e 's/{"task":{//' -e 's/}.*}//')

system_id=$(echo $task_fields | awk 'BEGIN { RS=","; FS=":" } \
        { gsub("\"", "", $0); if ($1 == "systemid") print $2; }')

# Getting backup info
api_args=( querystring="taskid=$taskid" fields="backupid,backupurl,log" )
backup_json=$(api_call "GET" "system/$system_id/backup" "${api_args[@]}")
json_error "$backup_json"
if [[ "$json_err" != "0" ]]; then
        logger -p user.error -t MariaDB-Manager-Task "Error: Unable to get node information from API."
        set_error "Error: Unable to get node information from API."
        exit 1
fi

lookup_no_items=$(echo $backup_json | sed -e 's/{"total":"//' -e 's/","backups".*//')

if [[ "$lookup_no_items" != "1" ]]; then
	logger -p user.error -t MariaDB-Manager-Task "Error: Got invalid node information from API."
        set_error "Error: Got invalid node information from API."
        exit 1
fi

backup_fields=$(echo $backup_json | sed -e 's/.*"backups":\[{//' -e 's/}\].*//')

backup_id=$(echo $backup_fields | awk 'BEGIN { RS=","; FS=":" } \
        { gsub("\"", "", $0); if ($1 == "backupid") print $2; }')
backup_file=$(echo $backup_fields | awk 'BEGIN { RS=","; FS=":" } \
        { gsub("\"", "", $0); if ($1 == "backupurl") print $2; }')
log_file=$(echo $backup_fields | awk 'BEGIN { RS=","; FS=":" } \
        { gsub("\"", "", $0); if ($1 == "log") print $2; }')

# Getting backups directory
config_json=$(api_call "GET" "config/backups/path")
json_error "$config_json"
if [[ "$json_err" != "0" ]]; then
	errorMessage="Error: Unable to determine the backup folder. Check your configuration file."
        logger -p user.error -t MariaDB-Manager-Task "$errorMessage"
        set_error "$errorMessage"
        exit 1
fi
backups_path=$(echo $config_json | sed -e 's/{"path":"//' -e 's/",".*//')
backups_path=${backups_path//\\/}

# Getting remote backups directory
config_json=$(api_call "GET" "config/backups/remotepath")
json_error "$config_json"
if [[ "$json_err" != "0" ]]; then
	errorMessage="Error: Unable to determine the remote backup folder. Check your configuration file."
        logger -p user.error -t MariaDB-Manager-Task "$errorMessage"
        set_error "$errorMessage"
        exit 1
fi
backups_remotepath=$(echo $config_json | sed -e 's/{"path":"//' -e 's/",".*//')
backups_remotepath=${backups_remotepath//\\/}


#logger -p user.info -t MariaDB-Manager-Task "store-backup - log_file: $log_file"

rsync_return=$(rsync_get_file "$nodeip" "${backups_remotepath}/${backup_file}.tgz" "${backups_path}/${backup_file}.tgz")
rsync_err_code=$?
if [[ "$rsync_err_code" != "0" ]]; then
        logger -p user.error -t MariaDB-Manager-Task "Failed to get backup file from data node $nodeip."
        set_error "Failed to get backup file from data node $nodeip"
        exit 1
fi

logger -p user.info -t MariaDB-Manager-Task "store-backup - backup file stored: ${backups_path}/${backup_file}.tgz"

ssh_return=$(ssh_agent_command "$nodeip" "rm -f \"${backups_remotepath}/${backup_file}.tgz\"")
ssh_err_code=$?
if [[ "$ssh_err_code" != "0" ]]; then
	logger -p user.error -t MariaDB-Manager-Task "Failed to delete backup file on data node $nodeip."
        set_error "Failed to delete backup file on data node $nodeip"
        exit 1
fi
