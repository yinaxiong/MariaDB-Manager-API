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
# Copyright 2012-2014 SkySQL Corporation Ab
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

# Determining Linux distro available on the machine
ssh_return=$(ssh_command "$nodeip" "release_info=\$(cat /etc/*-release); \
        if [[ \$(echo \"\$release_info\" | grep 'Red Hat') != \"\" || \$(echo \"\$release_info\" | grep 'CentOS') != \"\" ]]; then \
                echo \"redhat\"; \
        elif [[ \$(echo \"\$release_info\" | grep 'Ubuntu') != \"\" || \$(echo \"\$release_info\" | grep 'Debian') != \"\" ]]; then \
                echo \"debian\"; \
        fi;")

if [[ "$ssh_return" == "" ]]; then
        logger -p user.error -t MariaDB-Manager-Task "Error: unable to determine target machine OS version."
        set_error "Error: unable to determine target machine OS version."
        exit 1
fi

distro_type="$ssh_return"

trap cleanup SIGTERM
cleanup() {
        ssh_command "$nodeip" "rpm -q MariaDB-Manager-GREX; \
                if [[ $? == 0 ]]; then yum -y remove MariaDB-Manager-GREX; fi"
				exit 1
}

scripts_installed=0;

# Checking if SkySQL Remote Execution Scripts are installed
ssh_return=$(ssh_test_agent_command "$nodeip" "$taskid" "$api_host")
if [[ "$ssh_return" == "0" ]]; then
        logger -p user.info -t MariaDB-Manager-Task "Info: MariaDB Manager API Agent already installed."
	scripts_installed=1;
fi

# Generating and copying internal repository information to node / package installation
if [[ "$distro_type" == "redhat" ]]; then
        sed -e "s/###API-HOST###/$api_host/" steps/repo/MariaDB-Manager.repo > /tmp/MariaDB-Manager-$$.repo
        ssh_return=$(ssh_put_file "$nodeip" "/tmp/MariaDB-Manager-$$.repo" "/etc/yum.repos.d/MariaDB-Manager.repo")
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
        fi
elif [[ "$distro_type" == "debian" ]]; then
        ssh_command "$nodeip" "echo \"deb       http://${api_host}/repo wheezy  main\" >> /etc/apt/sources.list"
        ssh_command "$nodeip" "apt-get update"
	ssh_command "$nodeip" "apt-get -y --force-yes install mariadb-manager-grex"
fi

if [[ "$scripts_installed" == "0" ]]; then
	# Getting API key for GREX from components.ini
	newKey=$(sed -n '/\[apikeys\]/,$p' /etc/mariadbmanager/manager.ini | tail -n +2 | sed '/[;\[]/,$d' | awk -F " = " '/^3/ { gsub("\"", "", $2); print $2 }')

	# Generating credentials.ini file on remote server
	ssh_command "$nodeip" "echo \"3:$newKey\" > /usr/local/sbin/skysql/credentials.ini"
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
task_fields=$(echo $task_json | sed -e 's/{"task":{//' -e 's/}.*}//')

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
if [[ "$distro_type" == "debian" ]]; then
        state_json=$(api_call "PUT" "system/$system_id/node/$node_id" "state=connected" "linuxname=Debian")
        return_status=$?
else
        state_json=$(api_call "PUT" "system/$system_id/node/$node_id" "state=connected")
        return_status=$?
fi
if [[ "$return_status" != "0" ]]; then
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
