#!/bin/bash

# Parameters passed are:
# $1 The ID of the job that is to be run
# $2 A comma separated list of steps (each being a script)
# $3 The hostname for the API
# $4 Parameters to be passed on to step scripts
# $5 IP address for the node
# $6 Log file

dbpath="/usr/local/skysql/SQLite/AdminConsole/admin"
taskid=$1
steps=$2

echo 'steps: '$steps

# get system and node IDs, needed to update the node's state
target=$(echo 'SELECT SystemID, NodeID FROM Task WHERE TaskID = '$taskid';' | sqlite3 $dbpath)
target=(${target//|/ })

# loop through stepIDs comprising the command
index=1
for step in ${steps//,/ }
do
	# simulate running the script
	echo $index') '$step

	# simulate the step updating StepIndex, so the UI can advance the progress bar
	echo 'UPDATE Task SET StepIndex = '$index' WHERE TaskID = '$taskid';' | sqlite3 $dbpath
	
	# simulate the node changing its initial state in response to the step being run
	case $step in
	start) state=starting;;
	stop) state=stopping;;
	isolate) state=isolating;;
	recover) state=recovering;;
	promote) state=promoting;;
	synchronize) state=synchronizing;;
	backup) state=backingup;;
	restore) state=restoring;;
	restart) state=starting;;
	esac
	echo 'UPDATE Node SET State = "'$state'" WHERE SystemID="'${target[0]}'" AND NodeID="'${target[1]}'";' | sqlite3 $dbpath
	echo '   '$step' - initial state: '$state
	
	# simulate spending some time while the step is running
	sleep 5

	# simulate the node changing its final state in response to the step being run
	case $step in
	start) state=slave;;
	stop) state=stopped;;
	isolate) state=offline;;
	recover) state=slave;;
	promote) state=master;;
	synchronize) state=slave;;
	backup) state=offline;;
	restore) state=offline;;
	restart) state=slave;;
	esac
	echo 'UPDATE Node SET State = "'$state'" WHERE SystemID="'${target[0]}'" AND NodeID="'${target[1]}'";' | sqlite3 $dbpath
	echo '   '$step' - final state: '$state
	
 	let index+=1
done

cmdstate="done"
echo 'End of command; final state: '$cmdstate

# set command state to "done" or "error" and set completion time stamp
time=$(date +"%Y-%m-%d %H:%M:%S %z")
echo "UPDATE Task SET Completed = '"$time"', State = '"$cmdstate"' WHERE TaskID = '$taskid';" | sqlite3 $dbpath
