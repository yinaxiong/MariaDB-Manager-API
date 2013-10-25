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
# $3 The hostname for the API - unused
# $4 Parameters to be passed on to step scripts
# $5 The IP of the node on which to run the command
# $6 The path of the log file - unused
#
# NB Paramter 3, api_host is not used any more as the idea has been superceeded
# in favour of dynamically defining it. This works better when mutliple network
# interfaces are in use.
# Also argument 6 is no longer used as all logging is done to syslog

if [[ $# -lt 5 ]]; then
	logger -p user.error -t MariaDB-Manager-Task \
		"RunCommand: Unexpected number of arguments $#. Called with $*"
	api_call "PUT" "task/$taskid" "completed=@$(date +%s)&state=error&errormessage=Incorrect parameter count"
	exit 1
fi

export taskid="$1"
steps="$2"
node_ip="$5"
params="$4"

src_ip=$(ip route get $node_ip | awk '{ for (i = 0; i < NF; i++) if ( $(i) == "src" ) print $(i+1); }')
export api_host=$src_ip

scripts_dir=$(dirname $0)
cd $scripts_dir

. ./restfulapicredentials.sh
. ./functions.sh

index=1
for stepscript in ${steps//,/ } # Iterating the list of steps for the command
do
        # Setting current step for the command
        api_call "PUT" "task/$taskid" "stepindex=$index"

	# Checking if step is executed from API node
	if [[ -f "./steps/$stepscript.sh" ]]; then
		# Executing step locally
		bash ./steps/$stepscript.sh "$node_ip" "$taskid" "$params" \
					>/tmp/step.$$.log 2>&1
		return=$?
		logger -p user.info -t MariaDB-Manager-Task -f /tmp/step.$$.log
		rm -f /tmp/step.$$.log
	else
	        # Executing step remotely
		return=$(ssh_agent_command "$node_ip" \
		"sudo /usr/local/sbin/skysql/NodeCommand.sh $stepscript $taskid $api_host $params")
		ssh_exit_code=$?
		if [[ $ssh_exit_code != 0 ]]; then
			logger -p user.error -t MariaDB-Manager-Task "$return"
		fi
	fi

        if [[ "$return" != "0" ]]; then
                break
        fi

        let index+=1
done

if [[ "$return" == "0" ]]; then
        cmdstate='done'
else
        cmdstate='error'
	logger -p user.error -t MariaDB-Manager-Task "Task $taskid: Execution of command failed in step $stepscript."
fi

time=$(date +%s)
# Updating the state of command execution to finished (either successfully or with errors)
api_call "PUT" "task/$taskid" "completed=@$time&state=$cmdstate"
