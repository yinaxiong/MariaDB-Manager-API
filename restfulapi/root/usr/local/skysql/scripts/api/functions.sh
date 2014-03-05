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
# Date: October 2013
#
#
# This script contains auxiliary functions necessary by the other scripts.
#


# api_call()
# This function is used to make API calls.
#
# Parameters:
# $1: HTTP request type
# $2: API call URI
# $3: API call-specific parameters
api_call() {
        # Getting system date in the required format for authentication
        api_auth_date=`date --rfc-2822`

        # Getting checksum and creating authentication header
        md5_chksum=$(echo -n $2$auth_key$api_auth_date | md5sum | awk '{print $1}')
        api_auth_header="api-auth-$auth_key_number-$md5_chksum"

        # URL for the request
        full_url="http://$api_host/restfulapi/$2"

        # Sending the request
        if [[ $# -ge 3 ]]; then
                if [[ $1 == "GET" ]]; then
                        curl --request GET -H "Date:$api_auth_date" -H "Authorization:$api_auth_header" -H "Accept:application/json" $full_url?$3
                        curl_status=$?
                else
                        curl --request $1 -H "Date:$api_auth_date" -H "Authorization:$api_auth_header" -H "Accept:application/json" --data "$3" $full_url
                        curl_status=$?
                fi
        else
                curl -s --request $1 -H "Date:$api_auth_date" -H "Authorization:$api_auth_header" -H "Accept:application/json" $full_url
                curl_status=$?
        fi

        if [[ "$curl_status" != 0 ]]; then
		case "$curl_status" in

                1)
                        msg="Unsupported protocol"
                        ;;
                2)
                        msg="Failed to connect"
                        ;;
                3)
                        msg="Malformed URL"
                        ;;
                5)
                        msg="Unable to resolve proxy"
                        ;;
                6)
                        msg="Unable to resolve host $api_host"
                        ;;
                7)
                        msg="Failed to connect to host $api_host"
                        ;;
                28)
                        msg="Request timeout"
                        ;;
                22)
                        msg="Page not received"
                        ;;
                *)
                        msg="curl failed with exit code $curl_status"
                esac

                logger -p user.error -t MariaDB-Manager-Task "restfulapi-call: $full_url failed, $msg"
		exit $curl_status
        fi
}

# set_error()
# This function is an alias to invoke an API call to set the error message for the current task.
#
# Parameters:
# $1: HTTP request type
set_error() {
	api_call "PUT" "task/$taskid" "errormessage=$1"
}

# ssh_command()
# This function invokes an ssh command on a specific node using root login.
#
# Parameters:
# $1: node IP
# $2: ssh command
ssh_command() {
	if [[ "$ssh_key_file" != "" ]]; then
		ssh_output=$(ssh -i "$ssh_key_file" root@"$1" "$2" 2>/tmp/ssh_call.$$.log)
		ssh_return=$?
	else
		ssh_output=$(sshpass -p "$rootpwd" ssh root@"$1" "$2" 2>/tmp/ssh_call.$$.log)
		ssh_return=$?
	fi
      if [[ "$ssh_return" != "0" ]]; then
                ssh_error_output=$(cat /tmp/ssh_call.$$.log)
                logger -p user.error -t MariaDB-Manager-Task "Error in ssh connection to $nodeip with root user. $ssh_error_output"
		set_error "Error in ssh connection to $nodeip with root user. $ssh_error_output"
                rm -f /tmp/ssh_call.$$.log
		echo $ssh_return
                exit 1
        elif [[ "$ssh_output" != "" ]]; then
                logger -p user.info -t MariaDB-Manager-Task $ssh_output
        fi
	echo $ssh_output
}

# ssh_put_file()
# This function invokes an scp command to put a file on a specific node using root login.
#       
# Parameters: 
# $1: node IP
# $2: local file path (source)
# $3: remote file path (destination)
ssh_put_file() {
	if [[ "$ssh_key_file" != "" ]]; then
		ssh_output=$(scp -i "$ssh_key_file" "$2" root@"$1":"$3" 2>/tmp/ssh_call.$$.log)
		ssh_return=$?
	else
		ssh_output=$(sshpass -p "$rootpwd" scp "$2" root@"$1":"$3" 2>/tmp/ssh_call.$$.log)
		ssh_return=$?
	fi
	if [[ "$ssh_return" != "0" ]]; then
                ssh_error_output=$(cat /tmp/ssh_call.$$.log)
                logger -p user.error -t MariaDB-Manager-Task "Error in ssh file transfer to $nodeip with root user. $ssh_error_output"
		set_error "Error in ssh file transfer to $nodeip with root user. $ssh_error_output"
                rm -f /tmp/ssh_call.$$.log
		echo $ssh_return
                exit $ssh_return
        elif [[ "$ssh_output" != "" ]]; then
                logger -p user.error -t MariaDB-Manager-Task $ssh_output
        fi
}

# ssh_agent_command()
# This function invokes an ssh command on a specific node using skysqlagent login.
#
# Parameters:
# $1: node IP
# $2: ssh command
ssh_agent_command() {
        ssh_output=$(ssh -q skysqlagent@"$1" "$2" 2>/tmp/ssh_call.$$.log)
	ssh_return=$?
        if [[ "$ssh_return" != "0" ]]; then
                ssh_error_output=$(cat /tmp/ssh_call.$$.log)
                logger -p user.error -t MariaDB-Manager-Task "Error in ssh connection to $1 with skysqlagent user. $ssh_error_output"
		set_error "Error in ssh connection to $1 with skysqlagent user. $ssh_error_output"
                rm -f /tmp/ssh_call.$$.log
		echo $ssh_output
                exit "$ssh_return"
        fi
	echo $ssh_output
}

# json_error
# Look at the JSON return from an API call and process any error information
# contained in that return
#
# Parameters
# $1: The JSON returned from the API call
# 
# Returns
# $json_err:	0 if no error was detected
json_error() {
	if [[ "$1" =~ '{"errors":"' ]] ; then
		error_text=$(sed -e 's/^{"errors":\["//' -e 's/"\]}$//' <<<$1)
		logger -p user.error -t MariaDB-Manager-Task "API call failed: $error_text"
		if [[ "$error_text" =~ "Date header out of range" ]]; then
			logger -p user.error -t MariaDB-Manager-Task "Date and time on the local host must be synchronised with the API host"
		fi
		json_err=1
	else
		json_err=0
	fi
}

export -f api_call
export -f set_error
export -f ssh_command
export -f ssh_put_file
export -f ssh_agent_command
export -f json_error
