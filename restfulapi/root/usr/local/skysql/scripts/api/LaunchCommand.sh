#!/bin/bash
#
# Launch a command for node management
#
# This script calls the main run script, puts it into background, and returns its PID
#
cmd="$1"
log="$7"
shift

"$cmd" "$@" >> "${log:-/dev/stderr}" 2>&1 &
echo $!

