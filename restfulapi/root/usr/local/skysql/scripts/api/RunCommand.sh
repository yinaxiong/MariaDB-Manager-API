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
# This script is called by the SkySQL Manager API to run jobs remotely on a node of a given cluster.
#
# Parameters:
# $1 The ID of the job that is to be run
# $2 A comma separated list of steps (each being a script)
# $3 The hostname for the API
# $4 Parameters to be passed on to step scripts
# $5 The IP of the node on which to run the command
# $6 The path of the log file

export api_host=$3

taskid=$1
steps=$2
node_ip=$5
params=$4
log_file=$6

scripts_dir=`dirname $0`
cd $scripts_dir

index=1
for stepscript in ${steps//,/ } # Iterating the list of steps for the command
do
        # Setting current step for the command
        ./restfulapi-call.sh "PUT" "task/$taskid" "stepindex=$index"
	
	# Checking if step is executed from API node
	if [ -f "./steps/$stepscript.sh" ]; then
		# Executing step locally
		sh ./steps/$stepscript.sh $node_ip $taskid $params >> /var/log/skysql-test.log
		return=$?
	else
	        # Executing step remotely
		return=`ssh -q skysqlagent@$node_ip \
		"sudo /usr/local/sbin/skysql/NodeCommand.sh $stepscript $taskid $api_host $params"`
	fi

        if [ "$return" != "0" ]; then
                break
        fi

        let index+=1
done

if [ "$return" == "0" ]; then
        cmdstate='done'  # Done
else
        cmdstate='error'  # Error
fi

time=$(date +%s)
# Updating the state of command execution to finished (either successfully or with errors)
./restfulapi-call.sh "PUT" "task/$taskid" "completed=@$time&state=$cmdstate"
