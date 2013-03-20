#!/bin/sh
echo 'Stop:' `date` 
echo Params: $* 
/usr/local/skysql/skysql_aws/control/stop.sh $* 
echo 'exit:' `date` 
exit 0
