#!/bin/sh
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
# This script sets up SSH communications between the management node and the 
# target node.
#
# Parameters:
# $1 IP of the node
# $2 TaskID for the invoking Task
# $3 Other parameters (root_password is necessary)

nodeip="$1"
taskid="$2"
params="$3"

logger -p user.info -t MariaDB-Manager-Task "Setup ssh access for node $nodeip"

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
        $(ssh_command "$nodeip" "userdel -r skysqlagent")
        exit 1
}

# Adding node ip to ssh hosts list (if there is no entry)
scan_results=$(ssh-keygen -F $nodeip)
if [[ "$scan_results" == "" ]]; then
	ssh-keyscan "$nodeip" >> /var/www/.ssh/known_hosts
fi

# Checking if node is already prepared for command execution
# (on a subshell to catch exits)
ssh_return=$(ssh_agent_command "$nodeip" "exit 0")
if [[ "$ssh_return" == "0" ]]; then
        logger -p user.info -t MariaDB-Manager-Task "Info: ssh login already setup in target node."
	exit 0
fi

$(ssh_command "$nodeip" "exit 0")
ssh_error_code=$?
if [[ "$ssh_error_code" != "0" ]]; then
        logger -p user.info -t MariaDB-Manager-Task "Info: ssh root login failed for $nodeip."
        set_error "Unable to login as root user"
        exit 1
fi


# Creating skysqlagent user and ssh credentials directory
$(ssh_command "$nodeip" "useradd skysqlagent; mkdir -p /home/skysqlagent/.ssh")
ssh_error_code=$?
if [[ "$ssh_error_code" != "0" ]]; then
	logger -p user.error -t MariaDB-Manager-Task "Error: Unable to create agent user."
	set_error "Failed to create agent user 'skysqlagent'"
	exit 1
fi

# Setting up credentials on the node
$(ssh_put_file "$nodeip" "/var/www/.ssh/id_rsa.pub" "/home/skysqlagent/.ssh/id_rsa.pub")
ssh_error_code=$?
if [[ "$ssh_error_code" != "0" ]]; then
	logger -p user.error -t MariaDB-Manager-Task "Failed to install file public key for node $nodeip."
	set_error "Failed to install public key."
	exit 1
fi

ssh_command "$nodeip" \
	"cd /home/skysqlagent/.ssh/; cat id_rsa.pub >> authorized_keys; \
	chown -R skysqlagent.skysqlagent /home/skysqlagent/.ssh/; chmod 600 authorized_keys"

# Setting up skysqlagent sudoer permissions
$(ssh_command "$nodeip" \
	"cat /etc/sudoers | \
	grep -q \"^skysqlagent ALL=NOPASSWD: /usr/local/sbin/skysql/NodeCommand.sh\"; \
	if [ \$? == 1 ]; then \
		echo \"skysqlagent ALL=NOPASSWD: /usr/local/sbin/skysql/NodeCommand.sh\" >> /etc/sudoers; \
	fi; \
	sed \"s/.*Defaults.*requiretty.*/Defaults     !requiretty/\" /etc/sudoers > /etc/sudoers.tmp && \
	mv /etc/sudoers.tmp /etc/sudoers")
ssh_error_code=$?
if [[ "$ssh_error_code" != "0" ]]; then
	logger -p user.error -t MariaDB-Manager-Task "Error: Failed to edit sudoers file."
	set_error "Failed to setup sudoers file."
	exit 1
fi

# Deleting temp ssh key file
if [[ -f $ssh_key_file ]] ; then
	rm -f $ssh_key_file
fi

logger -p user.info -t MariaDB-Manager-Task "Info: SSH successfully set up."
exit 0
