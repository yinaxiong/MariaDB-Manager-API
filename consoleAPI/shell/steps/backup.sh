#!/bin/sh
echo 'Backup:' `date` 
echo Params: $* 
/usr/local/skysql/skysql_aws/backup/backup.sh $* 
result=$?
echo 'exit:' `date` 
exit $result
