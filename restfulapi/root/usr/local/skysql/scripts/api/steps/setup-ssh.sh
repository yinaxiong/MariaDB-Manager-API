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

# Parameter parsing and validation
for param in $params
do
        param_name=`echo $param | cut -d = -f 1`
        param_value=`echo $param | cut -d = -f 2`

        if [ "$param_name" == "rootpassword" ]; then
                rootpwd=$param_value
        fi
done

# Hide the parameter string that contains the password
./restfulapi-call.sh "PUT" "task/$taskid" "parameters=*****"

if [ "$rootpwd" == "" ]; then
        echo "Error: system root password parameter not defined."
        exit 1
fi

# Adding node ip to ssh hosts list
ssh-keyscan "$nodeip" >> /var/www/.ssh/known_hosts 2>/dev/null

# Checking ssh connectivity
sshpass -p "$rootpwd" ssh root@"$nodeip" "exit" > /dev/null 2>/tmp/setup-ssh.$$.log
if [ $? != 0 ]; then
	a=`cat /tmp/setup-ssh.$$.log`
	echo "Error: cannot connect to target node $nodeip. $a"
	./restfulapi-call.sh "PUT" "task/$taskid" "errormessage=Unable to connect: $a"
	rm -f /tmp/setup-ssh.$$.log
	exit 1
fi

# Creating skysqlagent user and ssh credentials directory
sshpass -p "$rootpwd" ssh root@"$nodeip" "useradd skysqlagent; mkdir -p /home/skysqlagent/.ssh"

# Setting up credentials on the node
sshpass -p "$rootpwd" scp /var/www/.ssh/id_rsa.pub root@"$nodeip":/home/skysqlagent/.ssh/id_rsa.pub
sshpass -p "$rootpwd" ssh root@"$nodeip" \
	"cd /home/skysqlagent/.ssh/; cat id_rsa.pub >> authorized_keys; \
	chown -R skysqlagent.skysqlagent /home/skysqlagent/.ssh/; chmod 600 authorized_keys"

# Setting up skysqlagent sudoer permissions
sshpass -p "$rootpwd" ssh root@"$nodeip" \
	"echo \"skysqlagent ALL=NOPASSWD: /usr/local/sbin/skysql/NodeCommand.sh\" >> /etc/sudoers; \
	sed \"s/.*Defaults.*requiretty.*/Defaults     !requiretty/\" /etc/sudoers > /etc/sudoers.tmp; \
	mv /etc/sudoers.tmp /etc/sudoers"

exit 0
