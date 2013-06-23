#!/bin/bash
#
# Launch a command for node management
#
# This script calls the main run script, puts it into background, and returns its PID
#
$1 "$2" "$3" "$4" "$5" "$6" "$7" > /dev/null 2>&1 &
echo $!

