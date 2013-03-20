#!/bin/sh
echo 'Start:' `date` 
echo Params: $* 
/usr/local/skysql/skysql_aws/control/start.sh $* 
echo 'exit:' `date` 
exit 0
