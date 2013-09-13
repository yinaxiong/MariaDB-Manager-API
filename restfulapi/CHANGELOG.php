<?php

/*
 * Change log for the SkySQL Manager API
 * 
 * 13 September 2012, 09:40 BST
 * 
 * Fix faulty mechanism for filtering "fields".  Fix error in reporting node
 * command/taskid state.
 * 
 * 11 September 2013, 12:00 BST
 * 
 * Change API version to 0.8; add code release date, visible at ../metadata;
 * improve links within metadata pages and add summary page; correct mistakes in
 * HTML for node update parameters and simplify parameter descriptions for nodes 
 * and users.
 * 
 * 10 September 2013, 18:00 BST
 * 
 * Modify presentation of results for monitorclasses to be more consistent - 
 * group results by systemtype.  It may be necessary to clear cache data to 
 * ensure monitorclass functions work correctly.  Fix small bugs.
 * 
 * 9 September 2013, 17:00 BST
 * 
 * Changes to NodeCommands table and contents.  To keep your existing database,
 * please delete this table and recreate using the CREATE and INSERT statements
 * in ../classes/SkySQL/COMMON/AdminDatabase.sql
 * 
 * More checks on the config file to give user friendly errors.
 * 
 * Modified Node state update to support monitor providing stateid (integer)
 * rather than state (string).
 * 
 * Change ../nodestates and ../nodestates/{systemtype} to return node states by
 * system type.
 * 
 * Minimise required parameters.
 * 
 * Changes to HTML System Form to avoid sending URI elements as POST data.  Other
 * pages to follow.
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

