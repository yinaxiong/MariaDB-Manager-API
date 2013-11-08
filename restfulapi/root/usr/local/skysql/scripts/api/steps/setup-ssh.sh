#!/bin/sh
#
#  Part of SkySQL Manager API
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
# This script sets up SSH communications between the management node and the 
# target node.
#
# Parameters:
# $1 IP of the node
# $2 TaskID for the invoking Task
# $3 Other parameters (root_password is necessary)

nodeip=$1
taskid=$2
params=$(echo $3 | tr "&" "\n")

logger -p user.info -t MariaDB-Manager-Task "Setup ssh access for node $nodeip"

# Parameter parsing and validation
for param in $params
do
        param_name=$(echo $param | cut -d = -f 1)
        param_value=$(echo $param | cut -d = -f 2)

        if [[ "$param_name" == "rootpassword" ]]; then
                rootpwd=$param_value
        fi
done

# Attempt to hide the parameter string that contains the password
api_call "PUT" "task/$taskid" "parameters=*****"

if [[ "$rootpwd" == "" ]]; then
        logger -p user.error -t MariaDB-Manager-Task "Error: system root password parameter not defined."
	set_error "Error: system root password parameter not defined."
        exit 1
fi

# Adding node ip to ssh hosts list (if there is no entry)
scan_results=$(ssh-keygen -F $nodeip)
if [[ "$scan_results" == "" ]]; then
	ssh-keyscan "$nodeip" >> /var/www/.ssh/known_hosts
fi

# Checking if node is already prepared for command execution
# (on a subshell to catch exits)
$(ssh_agent_command "$nodeip" "exit 0")
ssh_err_code=$?
if [[ "$?" == "0" ]]; then
        logger -p user.info -t MariaDB-Manager-Task "Info: ssh login already setup in target node."
	exit 0
fi

ssh_return=$(ssh_command "$nodeip" "exit 0")
if [[ "$ssh_return" != "0" ]]; then
        logger -p user.info -t MariaDB-Manager-Task "Info: ssh root login failed for $nodeip."
        set_error "Unable to login as root user"
        exit 1
fi


# Creating skysqlagent user and ssh credentials directory
ssh_return=$(ssh_command "$nodeip" "useradd skysqlagent; mkdir -p /home/skysqlagent/.ssh")
if [[ "$ssh_return" != "0" ]]; then
	logger -p user.error -t MariaDB-Manager-Task "Error: Unable to create agent user."
	set_error "Failed to create agent user 'skysqlagent'"
	exit 1
fi

# Setting up credentials on the node
ssh_return=$(ssh_put_file "$nodeip" "/var/www/.ssh/id_rsa.pub" "/home/skysqlagent/.ssh/id_rsa.pub")
if [[ "$?" != "0" ]]; then
	logger -p user.error -t MariaDB-Manager-Task "Failed to install file public key for node $nodeip."
	set_error "Failed to install public key."
	exit 1
fi

ssh_command "$nodeip" \
	"cd /home/skysqlagent/.ssh/; cat id_rsa.pub >> authorized_keys; \
	chown -R skysqlagent.skysqlagent /home/skysqlagent/.ssh/; chmod 600 authorized_keys"

# Setting up skysqlagent sudoer permissions
ssh_return=$(ssh_command "$nodeip" \
	"cat /etc/sudoers | \
	grep -q \"^skysqlagent ALL=NOPASSWD: /usr/local/sbin/skysql/NodeCommand.sh\"; \
	if [ \$? == 1 ]; then \
		echo \"skysqlagent ALL=NOPASSWD: /usr/local/sbin/skysql/NodeCommand.sh\" >> /etc/sudoers; \
	fi; \
	sed \"s/.*Defaults.*requiretty.*/Defaults     !requiretty/\" /etc/sudoers > /etc/sudoers.tmp && \
	mv /etc/sudoers.tmp /etc/sudoers")
if [[ "$ssh_return" != "0" ]]; then
	logger -p user.error -t MariaDB-Manager-Task "Error: Failed to edit sudoers file."
	set_error "Failed to setup sudoers file."
	exit 1
fi

logger -p user.info -t MariaDB-Manager-Task "Info: SSH successfully set up."
exit 0
