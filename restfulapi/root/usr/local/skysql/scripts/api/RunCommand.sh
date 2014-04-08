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
# This script is called by the MariaDB Manager API to run jobs remotely on a node of a given cluster.
#
# Parameters:
# $1 The ID of the job that is to be run
# $2 A comma separated list of steps (each being a script)
# $3 The IP of the node on which to run the command
# $* Parameters to be passed on to step scripts
#

if [[ $# -lt 3 ]]; then
	logger -p user.error -t MariaDB-Manager-Task \
		"RunCommand: Unexpected number of arguments $#. Called with $*"
	api_call "PUT" "task/$taskid" "completed=@$(date +%s)" "state=error" "errormessage=Incorrect parameter count"
	exit 1
fi

export taskid="$1"
steps="$2"
node_ip="$3"
shift 3
params="$@"

src_ip=$(ip route get $node_ip | awk '{ for (i = 0; i < NF; i++) if ( $(i) == "src" ) print $(i+1); }')
export api_host=$src_ip

scripts_dir=$(dirname $0)
cd $scripts_dir

# Getting and defining API credentials
export auth_key_number="2"
export auth_key=$(awk -F " = " '/^2/ { gsub("\"", "", $2); print $2 }' \
        /usr/local/skysql/config/components.ini)

. ./functions.sh

trap die SIGTERM
die() {
        if [[ "$stepscript" != "" ]] && [[ ! -f "./steps/$stepscript.sh" ]]; then
                $(ssh_agent_command "$node_ip" \
                        "sudo /usr/local/sbin/skysql/NodeCommand.sh cancel $taskid $api_host")
        fi

				exit 0
}

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
		"sudo /usr/local/sbin/skysql/NodeCommand.sh $stepscript $taskid $api_host \"$params\"")
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
api_call "PUT" "task/$taskid" "completed=@$time" "state=$cmdstate"
