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

# Hide the parameter string that contains the password
api_call "PUT" "task/$taskid" "parameters=*****"

if [[ "$rootpwd" == "" ]]; then
        echo "Error: system root password parameter not defined."
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
(ssh_agent_command "$nodeip" "exit")
if [[ $? == 0 ]]; then
	echo "Info: ssh login already setup in target node."
	exit 0
fi

# Creating skysqlagent user and ssh credentials directory
ssh_command "$nodeip" "useradd skysqlagent; mkdir -p /home/skysqlagent/.ssh"

# Setting up credentials on the node
ssh_put_file "$nodeip" "/var/www/.ssh/id_rsa.pub" "/home/skysqlagent/.ssh/id_rsa.pub"
ssh_command "$nodeip" \
	"cd /home/skysqlagent/.ssh/; cat id_rsa.pub >> authorized_keys; \
	chown -R skysqlagent.skysqlagent /home/skysqlagent/.ssh/; chmod 600 authorized_keys"

# Setting up skysqlagent sudoer permissions
ssh_command "$nodeip" \
	"cat /etc/sudoers | \
	grep -q \"^skysqlagent ALL=NOPASSWD: /usr/local/sbin/skysql/NodeCommand.sh\"; \
	if [ \$? == 1 ]; then \
		echo \"skysqlagent ALL=NOPASSWD: /usr/local/sbin/skysql/NodeCommand.sh\" >> /etc/sudoers; \
	fi; \
	sed \"s/.*Defaults.*requiretty.*/Defaults     !requiretty/\" /etc/sudoers > /etc/sudoers.tmp; \
	mv /etc/sudoers.tmp /etc/sudoers"

echo "Info: SSH successfully set up."
