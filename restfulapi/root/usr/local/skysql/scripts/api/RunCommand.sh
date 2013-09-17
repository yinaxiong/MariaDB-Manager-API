#!/bin/bash
#
# This script is called by the SkySQL Manager API to run jobs within the systems under control.
#
# Parameters passed are:
# $1 The ID of the job that is to be run
# $2 A comma separated list of steps (each being a script)
# $3 The hostname for the API
# $4 Parameters to be passed on to step scripts
# $5 IP address for the node
# $6 Log file
#
taskid=$1
steps=$2
hostname=$3
params=${4//|/ }
#
# Function to call the API
# Parameters: Hostname for API location, method, URI, query string
#
function callapi () {
	local RFCDATE=`date -R`
	local URI=$(echo -n $3 | sed 's;/*\(.*\)/*;\1;')
	local APIKEY="1f8d9e040e65d7b105538b1ed0231770"
	local MD5CHK=$(echo -n "$URI$APIKEY$RFCDATE" | md5sum | awk '{ print $1 }')
	httpcode=`curl -s -o /dev/null -w "%{http_code}" --request $2 -H "Date:$RFCDATE" -H "Authorization:api-auth-1-$MD5CHK" -H "Accept:application/json" --data "$4" http://$1/$URI`
}

status=1
index=1
for stepscript in ${steps//,/ }
do
	# update CommandExecution to the current step, so the UI can advance the progress bar
	callapi "$hostname" "PUT" "task/$taskid" "stepindex=$index"
	
	# run the script and exit if an error occurs
	fullpath=`dirname $0`"/steps/$stepscript.sh $params"
	sh $fullpath >> $6 2>&1
	status=$?
	echo "Status after step $status" >> $6
	if [ $status -ne 0 ]; then
		break
	fi
 	let index+=1
done

if [ $status -eq 0 ]; then
	cmdstate="done"  # Done
else 
	cmdstate="error"  # Error
fi

# set command state to "Done" or "Error" and set completion time stamp
time=$(date +"%Y-%m-%d %H:%M:%S")
callapi "$hostname" "PUT" "task/$taskid" "completed=$time&state=$cmdstate&stepindex=0"
