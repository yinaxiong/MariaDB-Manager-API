#!/bin/bash

# script takes taskID (rowid) as parameter and runs the steps that comprise the command in question
steps=$(echo "SELECT Steps FROM Commands, CommandExecution WHERE CommandExecution.rowid = "$1" AND Commands.CommandID = CommandExecution.CommandID;" | sqlite3 $2)
echo 'stepIDs: '$steps

# set command state to "Running"
echo 'UPDATE CommandExecution SET State = 2 WHERE rowid = '$1';' | sqlite3 $2

# get parameters to pass to step scripts
params=`echo 'SELECT SystemID, NodeID, UserID, Params FROM CommandExecution WHERE CommandExecution.rowid = '$1';' | sqlite3 $2`
params=${params//|/ }

# loop through stepIDs comprising the command
index=1
for stepID in ${steps//,/ }
do
	# update CommandExecution to the current step, so the UI can advance the progress bar
	echo 'UPDATE CommandExecution SET StepIndex = '$index' WHERE rowid = '$1';' | sqlite3 $2
	
	# get the name of the step script to run next
	script=`echo 'SELECT Script FROM Step WHERE StepID ='$stepID';' | sqlite3 $2`'.sh'

	# run the script and exit if an error occurs
	fullpath=`dirname $0`"/steps/$script $params"
	echo 'running: '$fullpath
	sh $fullpath		>> /var/log/SDS.log 2>&1
	status=$?
	if [ $status -ne 0 ]; then
		break
	fi

 	let index+=1
done

if [ $status -eq 0 ]; then
	cmdstate=5  # Done
else 
	cmdstate=6  # Error
fi

echo 'final state: '$cmdstate

# set command state to "Done" or "Error" and set completion time stamp
time=$(date +"%Y-%m-%d %H:%M:%S")
echo "UPDATE CommandExecution SET Completed = '"$time"', State = '"$cmdstate"' WHERE rowid = '$1';" | sqlite3 $2

