#!/bin/sh
#
# This file is distributed as part of the MariaDB Enterprise.  It is free
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
# Author: Marcos Amaral
# Date: November 2013
#
#
# This script sends all processes associated with a task the TERM signal.
#
# Parameters:
# $1 Task PID

p_PID=$1

# Getting list of child processes
list_PID=$p_PID
child_list=$(ps -o pid --ppid $p_PID --no-headers | sed -e "s/^ *//" | tr '\n' ',' | sed -e "s/,$//")

while [ ! -z "$child_list" ]
do
        list_PID="$list_PID,$child_list"
        child_list=$(ps -o pid --ppid $child_list --no-headers | \
                sed -e "s/^ *//" | tr '\n' ',' | sed -e "s/,$//")
done

# Sending all processes the TERM signal
kill_list=$(echo $list_PID | sed -e "s/,/ /g")
kill -s TERM $kill_list

