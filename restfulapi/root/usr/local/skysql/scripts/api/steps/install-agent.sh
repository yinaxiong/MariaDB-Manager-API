#!/bin/bash
#
# This file is distributed as part of the MariaDB Enterprise.  It is free
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
# Copyright 2012-2014 SkySQL Ab
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

nodeip="$1"
taskid="$2"
params="$3"

# Parameter parsing and validation
oldIFS=$IFS
IFS='&'
set $params
while [[ $# > 0 ]]; do
        param_name="${1%%=*}"
        param_value="${1#*=}"

        if [[ "$param_name" == "rootpassword" ]]; then
                rootpwd=$param_value
        fi
        if [[ "$param_name" == "sshkey" ]]; then
                sshkey=$param_value
        fi

        shift
done
IFS=$oldIFS

if [[ "$sshkey" != "" ]]; then
        ssh_key_file=$(mktemp /tmp/sshrsa.XXXXXXXX)
        echo "$sshkey" > $ssh_key_file
else
        if [[ "$rootpwd" == "" ]]; then
                logger -p user.error -t MariaDB-Manager-Task \
                        "Error: neither system root password nor ssh key was provided."
                set_error "Error: neither system root password nor ssh key was provided."
                exit 1
        fi
fi

trap cleanup SIGTERM
cleanup() {
        ssh_command "$nodeip" "rpm -q MariaDB-Manager-GREX; \
                if [[ $? == 0 ]]; then yum -y remove MariaDB-Manager-GREX; fi"
				exit 1
}

scripts_installed=0;

# Checking if SkySQL Remote Execution Scripts are installed
ssh_return=$(ssh_agent_command "$nodeip" \
	"sudo /usr/local/sbin/skysql/NodeCommand.sh test $taskid $api_host")
if [[ "$ssh_return" == "0" ]]; then
        logger -p user.info -t MariaDB-Manager-Task "Info: MariaDB Manager API Agent already installed."
	scripts_installed=1;
fi


# Generating and copying internal repository information to node
sed -e "s/###API-HOST###/$api_host/" steps/repo/MariaDB-Manager.repo > /tmp/MariaDB-Manager-$$.repo
$(ssh_put_file "$nodeip" "/tmp/MariaDB-Manager-$$.repo" "/etc/yum.repos.d/MariaDB-Manager.repo")
ssh_err_code=$?
if [[ "$ssh_err_code" != "0" ]]; then
        logger -p user.error -t MariaDB-Manager-Task "Failed to write MariaDB-Manager repository file"
        set_error "Failed to install MariaDB-Manager Repository"
        exit 1
fi
rm -f /tmp/MariaDB-Manager-$$.repo

ssh_command "$nodeip" "yum -y clean all"
if [[ "$scripts_installed" != "0" ]]; then
	ssh_command "$nodeip" "yum -y update MariaDB-Manager-GREX --disablerepo=* --enablerepo=MariaDB-Manager"
else
	ssh_command "$nodeip" "yum -y install MariaDB-Manager-GREX --disablerepo=* --enablerepo=MariaDB-Manager"
	
	# Generating an API key for the node
	newKey=$(echo $RANDOM$(date)$RANDOM | md5sum | cut -f1 -d" ")

	# Determining the id of the key to be generated
	gen_key_id=10
	while read x; do
		if [[ "$x" -ge "$gen_key_id" ]]; then
			gen_key_id=$((x+1));
		fi
	done <<<"$(sed -n '/\[apikeys\]/,$p' /etc/skysqlmgr/api.ini | tail -n +2 | \
		sed '/^$/,$d;/^\[/,$d' | awk -F "=" '{ gsub(" ", "", $1); print $1 }')"

	keyString="$gen_key_id = \"$newKey\""

	# Registering it in api.ini
	grep "^${gen_key_id} = \"" /etc/skysqlmgr/api.ini &>/dev/null
	if [ "$?" != "0" ] ; then
		sed -i "/^\[apikeys\]$/a $keyString" /etc/skysqlmgr/api.ini
	fi

	# Generating credentials.ini file on remote server
	ssh_command "$nodeip" "echo \"$gen_key_id:$newKey\" > /usr/local/sbin/skysql/credentials.ini"
fi

# Check to see if the node date/time is in sync
localdate=$(date -u +%s)
remdate=$(ssh_command "$nodeip" "date -u +%s")
datediff=$((localdate - remdate))
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

# Deleting temp ssh key file
if [[ -f $ssh_key_file ]] ; then
	rm -f $ssh_key_file
fi

logger -p user.info -t MariaDB-Manager-Task "Info: SkySQL Galera remote execution agent successfully installed."
exit 0
