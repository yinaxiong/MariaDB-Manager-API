#!/bin/sh
echo 'Promote:' `date` 
echo Params: $* 
/usr/local/skysql/skysql_aws/control/promote.sh $* 
echo 'exit:' `date` 
exit 0
