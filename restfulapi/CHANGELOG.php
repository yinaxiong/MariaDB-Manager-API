<?php

/*
 * Change log for the SkySQL Manager API
 * 
 * 2 September 2013, 9:10 am BST
 * 
 * Provide automatic cache clear on new database; add unique ID to log entries; 
 * start improving task HTML page; fix bugs.
 * 
 * 
 * 30 August 2013
 * 
 * Split the database into monitoring data (data only, not monitor classes) and
 * other relatively static data.  This involves extra fields in api.ini to
 * define the new Monitoring database.  To maintain testing requires copying the
 * existing database to the monitoring database.  The former can then have 
 * the table MonitorData deleted, and the latter can have everything except
 * table MonitorData deleted.
 * 
 * There is also a new field in api.ini to identify the file (with path) that
 * is the PHP executable - this will be needed for scheduling.
 * 
 * Bulk data input is available for monitoring data.
 * 
 * When node data is provided commands are no longer just names, but objects
 * with full information including the steps.
 * 
 * Task data now includes the steps that were defined for the command at the
 * time the task was started.
 * 
 * When running a command, there is an optional 'state' parameter - if the
 * node to run the command is not in the specified state, the command will not
 * run and an error will be returned - code 409 Conflict.
 * 
 * User tags are included but not yet fully developed.
 * 
 * Support for scheduling is not available at this point, although some code
 * exists in the release for this purpose.
 * 
 * The HTML forms for monitors and tasks are modified.
 * 
 */

