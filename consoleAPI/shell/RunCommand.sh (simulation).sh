#!/bin/bash

# script takes TaskID as parameter and runs the steps that comprise the command in question
steps=$(echo "SELECT Steps FROM Commands, CommandExecution WHERE CommandExecution.TaskID = "$1" AND Commands.CommandID = CommandExecution.CommandID;" | sqlite3 $2)
echo 'stepIDs: '$steps

# set command state to "Running"
echo 'UPDATE CommandExecution SET State = 2 WHERE TaskID = '$1';' | sqlite3 $2

# get parameters to pass to step scripts
params=$(echo 'SELECT SystemID, NodeID, UserID, Params FROM CommandExecution WHERE CommandExecution.TaskID = '$1';' | sqlite3 $2)
params=${params//|/ }

# loop through stepIDs comprising the command
simulparams=($params)
index=1
for stepID in ${steps//,/ }
do
	# update CommandExecution to the current step, so the UI can advance the progress bar
	echo 'UPDATE CommandExecution SET StepIndex = '$index' WHERE TaskID = '$1';' | sqlite3 $2
	
	# get the name of the step script to run next
	script=`echo 'SELECT Script FROM Step WHERE StepID ='$stepID';' | sqlite3 $2`'.sh'

	# run the script and exit if an error occurs
	fullpath=`dirname $0`"/steps/$script $params"
	echo 'running: '$fullpath

	case $stepID in
	1) state=10;;	# start->starting
	2) state=4;;	# stop->stopping
	3) state=6;;	# isolate->isolating
	4) state=7;;	# recover->recovering
	5) state=11;;	# promote->promoting
	6) state=12;;	# synchronize->synchronizing
	7) state=9;;	# backup->backingup
	8) state=8;;	# restore->restoring
	esac
	echo 'UPDATE Node SET State = '$state' WHERE SystemID='${simulparams[0]}' AND NodeID='${simulparams[1]}';' | sqlite3 $2
	echo 'stepID '$stepID' start, state: '$state
	
	sleep 5

	case $stepID in
	1) state=2;;	# starting->slave
	2) state=5;;	# stopping->stopped
	3) state=3;;	# isolating->offline
	4) state=2;;	# recovering->slave
	5) state=1;;	# promoting->master
	6) state=2;;	# synchronizing->slave
	7) state=3;;	# backingup->offline
	8) state=3;;	# restoring->offline
	esac
	echo 'UPDATE Node SET State = '$state' WHERE SystemID='${simulparams[0]}' AND NodeID='${simulparams[1]}';' | sqlite3 $2
	echo 'stepID '$stepID' end, state: '$state
	
 	let index+=1
done

cmdstate=5  # Done
echo 'final state: '$cmdstate

# set command state to "Done" or "Error" and set completion time stamp
time=$(date +"%Y-%m-%d %H:%M:%S")
echo "UPDATE CommandExecution SET Completed = '"$time"', State = '"$cmdstate"' WHERE TaskID = '$1';" | sqlite3 $2

