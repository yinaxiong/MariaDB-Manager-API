#!/bin/bash

# script takes taskID (rowid) as parameter and finds out how many steps comprise the command in question
steps=$(echo "SELECT COUNT(StepID) FROM CommandStep, CommandExecution WHERE CommandExecution.rowid = "$1" AND CommandStep.CommandID = CommandExecution.CommandID ORDER BY StepOrder;" | sqlite3 $2)

# set command state to "running"
echo 'UPDATE CommandExecution SET State = 2 WHERE rowid = '$1';' | sqlite3 $2

# loop and update StepIndex from 1 to number of steps comprising the chosen command
step=0
while [ $step -lt $steps ]; do
 	let step+=1	
	echo 'step: '$step
	# this updates the Command execution to the current step
	echo 'UPDATE CommandExecution SET StepIndex = '$step' WHERE rowid = '$1';' | sqlite3 $2
	
	# this updates the Node state (as would be done by each step)
	state=`echo 'SELECT NodeStates.State FROM CommandExecution, CommandStep, Step, NodeStates WHERE CommandExecution.CommandID=CommandStep.CommandID AND CommandStep.StepID = Step.StepID AND Step.Icon = NodeStates.Icon AND StepOrder='$step' AND CommandExecution.rowid='$1';' | sqlite3 $2`
	echo 'state: '$state
	node=`echo 'SELECT NodeID FROM CommandExecution WHERE CommandExecution.rowid='$1';' | sqlite3 $2`
	system=`echo 'SELECT SystemID FROM CommandExecution WHERE CommandExecution.rowid='$1';' | sqlite3 $2`
	echo 'UPDATE Node SET State = '$state' WHERE SystemID='$system' AND NodeID='$node';' | sqlite3 $2
	
	# let's pretend we're doing something...
	sleep 5
done

# update Node to final state, simulating the last step being done successfully
# basically, transitions "...ing" states to the next logical state
case $state in
	4) state=5;;	# stopping->stopped
	6) state=3;;	# isolating->offline
	7) state=2;;	# recovering->online
	8) state=3;;	# restoring backup->offline
	9) state=3;;	# backing up->offline
	10) state=2;;	# starting->online
	11) state=1;;	# promoting->master
	12) state=3;;	# synchronizing->offline
esac

echo 'last state: '$state
echo 'UPDATE Node SET State = '$state' WHERE SystemID='$system' AND NodeID='$node';' | sqlite3 $2

# set command state to "done" and set completion time stamp
time=$(date +"%Y-%m-%d %H:%M:%S")
echo "UPDATE CommandExecution SET Completed = '"$time"', State = 5 WHERE rowid = '$1';" | sqlite3 $2

