<?php

/*
 * Change log for the MariaDB Manager API
 * 
 * 12 May 2014, 11:15
 * 
 * Fix bug in updating schedules.
 * 
 * 
 * 9 May 2014, 11:40
 * 
 * Following discussions, implement revised upgrade logic, so that no action is
 * taken on a node that is still at a provisioning stage, but a node that is
 * recorded as having an older than current release will only offer an upgrade
 * command as the sole runnable command.  If the node is not already down (or
 * just provisioned) the upgrade command will include a "stop" step.
 * 
 * 
 * 7 May 2014, 17:15
 * 
 * Add stop,upgrade,start instead of just upgrade when node release behind API
 * 
 * 
 * 6 May 2014, 15:00
 * 
 * Add extra fields for Nodes into HTML front end
 * Fix fault that rejected version number updates to Nodes
 * 
 * 
 * 5 May 2014, 12:50
 * 
 * Add connect to Galera commands that must be exclusive across a system
 * Wrap run command operations in a database transaction
 * Fix bug in Task update logic
 * 
 * 
 * 24 April 2014, 07:35
 * 
 * Incorporate new encryption logic to remove requirement for mcrypt
 * Extend caching functionality to support if modified since monitor data
 * 
 *
 * 16 April 2014, 14:45
 * 
 * New format manager.ini replaces api.ini and provides for all components.
 * Standardisation of API component property names.
 * 
 * 
 * 6 April 2014, 19:40
 * 
 * Provide advanced fieldselect parameter for text/plain requests
 * 
 * 
 * 1 April 2014, 06:30
 * 
 * Add upgrade for Backup table
 * Split table upgrades into a method per table
 * 
 * 
 * 31 March 2014, 14:50
 *
 * Redirect /MariaDBManager to Tomcat
 *
 * 
* 30 March 2014, 09:45
 * 
 * Implement automatic insertion of upgrade step at start of any command when
 * the node is at an earlier release than the API.
 * 
 * 
 * 26 March 2014, 11:30
 * 
 * Ignore duplicate data entries from monitor data, accept only the last
 * Add TaskID to Backup records
 * Add backups section to api.ini and make configuration items available via API
 * Allow access to individual top level fields of System or Node, and support plain text
 * When JSON functions are not available send prepared JSON error message if client wants JSON
 * Modified API properties to support version display
 * 
 * 
 * 26 February 2014, 09:40
 * 
 * Fix faults that arise in some situations with monitoring data
 * 
 * 
 * 21 February 2014, 12:00
 * 
 * Change headers to corporate standard
 * Add checks on getByID operations to ensure appropriate error messages
 * Provide 201 return code for new properties (API version > 1.0)
 * Improve information provided in logging
 * Removed test key from api.ini
 * Bug fixes
 * 
 * 
 * 5 February 2014, 14:00
 * 
 * Remove option JSON_UNESCAPED_UNICODE (only available PHP >= 5.4.0)
 * 
 * 
 * 5 February 2014, 11:45
 * 
 * Internal Changes
 *
 * Added class MonitorQueries to cache the results of requests for monitor data
 * Started to move encryption code into its own class, EncryptionManager
 * Introduced NodeCommandManager to cache commands for nodes, and NodeCommand class as model object for commands
 * Split monitor data into one database per system/node
 * Tidied HTTP request header handling and introduced some validity checks
 * Made caching managers of model objects (e.g. System, Node) internal to the model classes
 * Make caching of model objects more consistent with more common code
 * Remove redundant class properties
 * Make abstract class methods final where not intended to be overriden
 * 
 * 
 * External - developments
 * 
 * Added interface for Post Once Exactly (POE) for System and Node (not fully tested)
 * Send HTTP return code 201 and Location header when new System or Node created (API version > 1.0)
 * Allow PUT or POST to provide either JSON or URL encoded parameters (API version > 1.0)
 * Require "Content-Type" header for JSON PUT or POST (API version > 1.0)
 * Added support for Creole metadata; extended provision of metadata; add more descriptions for metadata
 * Accept bulk node data from monitor with single systemid and nodeid parameter (API version < 1.0)
 * Support If-Modified-Since for monitor data requests
 * Round times in monitor data requests to the value of the monitoring interval
 * Change monitor form to match monitor data changes
 * 
 * External - fixes
 * 
 * Suppressed non-JSON error messages from the ErrorRecorder class when there is a database error
 * Added validation that requests consist of correct UTF-8 characters
 * Extend node check for duplicate IP address to take account of port
 * Prevent task state from being set to missing or cancelled if already error or done
 * 
 * 
 * 28 November 2013, 11:20 GMT
 * 
 * Complete removal of hostname (for the API) from api.ini
 * Fix bug in requests involving <taskid>
 * Change HTML front end to use e.g. <systemid> instead of {systemid}
 * 
 * 
 * 26 November 2013, 16:30 GMT
 * 
 * Implement command cancellation
 * Improvements to metadata, mainly for reference manual
 * 
 * 
 * 25 November 2013, 11:50 GMT
 * 
 * Fix bug in getUsers caused by metadata automation
 * 
 * 
 * 22 November 2013, 17:00 GMT
 * 
 * Fix bug in display of Resource Tables (e.g. System, Node) for front end HTML
 *  introduced while adding automation for reference manual
 * Additional automation for reference manual
 * 
 * 
 * 22 November 2013, 10:40 GMT
 * 
 * Minor changes to abort messages
 * Add .mml to metadata to automate reference manual production
 * Add showheaders option in api.ini to include response headers in body of response
 * Attempt to incorporate BZR version into code
 * Remove privateip, level, parentid from Command object; change step to steps
 * Support "steps" parameter for run a command - block running if steps do not match - comma separated list
 * Change the HTML front end to send responses in HTML (prettified JSON)
 * 
 * 
 * 15 November 2013, 14:50 GMT
 * 
 * Amend list of states for Galera nodes to match the Monitor
 * Add "rejoin" to the commands that can only be running once on a system
 * Improve some error messages by referring to nodes by System and Node Name
 * Correct bug in Application Property related SQL
 * Add API information to cached Component Properties
 * Serve all component property information from cache, remove SQL request
 * Remove obsolete backup parameters from HTML for running command (Parent ID, Level)
 * 
 * 
 * 13 November 2013, 12:40 GMT
 * 
 * Changes and additions to the text and appearance of the HTML front end
 * Fix error in the database specification of the "Capacity" monitor
 * Improve trapping of diagnostic output
 * Set character set to UTF-8 in the content type header
 * Fix bug in averaged monitor data introduced by redesign of data handling
 * Allow passwords to have leading or trailing spaces
 * 
 * 
 * 1 November 2013, 08:35 GMT
 * 
 * Fix bug in PUT data handling.
 * 
 * 
 * 31 October 2013, 18:00 GMT
 * 
 * Fix fault introduced by tidy up of response sending
 * Fix problem in analysis of schedule rules
 * Introduce simple test for presence of JSON functions (required)
 * 
 * 
 * 31 October 2013, 12:05 GMT
 * 
 * Prevent encrypted fields being written to the Task record
 * Suppress direct reporting of PHP errors
 * Capture all diagnostics and include in response as property "diagnostics"
 * Wrap database table creation commands in a transaction
 * Suppress running a schedule immediately if next date in the past
 * Trim decrypted fields of right hand low character padding
 * URL decode PUT data if Content-Tpe is set to application/x-www-form-urlencoded
 * 
 * 
 * 30 October 2013, 11:40 GMT
 * 
 * Changed API version in headers to 1.0
 * Added /apidate request (no security) to return datestamp of this file.
 * Fixed bug in handling of empty sshkey or rootpassword parameters
 * Fixed problem of System name not defaulting correctly
 * 
 * 
 * 29 October 2013, 13:30 GMT
 * 
 * Added code to handle encrypted parameters - rootpassword and sshkey
 * 
 * 
 * 28 October 2013, 21:45 GMT
 * 
 * Fix error in earlier JavaScript change that broke HTML front end
 * Remove API interface for storing single monitor item - use bulk add
 * Change implementation of get last monitor data to use caching logic
 * 
 * 
 * 28 October 2013, 17:45 GMT
 * 
 * Fix bug in return message handling and "showhttpcode" handling
 * Change index on MonitorData to hold more fields for performance
 * Change JavaScript RFC Date routine to handle hours offset correctly.
 * 
 * 
 * 28 October 2013, 10:30 GMT
 * 
 * Block scheduled comand if node no longer exists or potentially conflicting
 *	command is running
 * Centralise URL decoding of URI fields
 * Complete scaling of monitor data
 * Block second run of scheduled command at roughly the same time
 * Limit system types to "galera"
 * Suppress transaction rollback in database destructor - bug in PHP 5.3.3
 * Radically modify handling of latest monitor data for performance
 * Add ability to GET User Properties
 * Resolve problem with totally unrecognised calls giving security error
 * Add debug "reflectheaders" and "showhttpcode" options for api.ini
 * Get all headers for tunnel request, instead of only those in the form
 * Add support for "fields" parameter to practically all GET requests
 * Fix problems with schedules requiring immediate execution
 * Send HTTP 404 for missing backups, tasks, schedules, not null data
 * Fix problem with "updated" field not being updated on PUT requests
 * 
 * 
 * 21 October 2013, 22:40 BST
 * 
 * Fix /provisionednode call to implement system/node logic for DB credentals
 * 
 * 
 * 21 October 2013, 13:45 BST
 * 
 * Introduce validation for system and node DB credentials, based on system type
 * Fix bug in delete node
 * Remove symbolic links
 * 
 * 
 * 18 October 2013, 12:10 BST
 * 
 * Tighten restriction on duplicate Private IP for nodes to cover all nodes.
 * 
 * 
 * 18 October 2013, 00:01 BST
 * 
 * Add ScheduleManager class to cache Schedules
 * Improve parameter validation for commands.
 * 
 * 
 * 16 October 2013, 17:50 BST
 * 
 * Fix problem with bulk monitor updates and limit on substitutions
 * Moved ErrorLog to start of SQL for Admin DB creation so it is available to
 *	record any errors in the rest of the SQL
 * Introduce cache manager for Schedules; fix various issues for update and
 *	deletion of schedule records
 * Improve quoting of parameters when running commands (following Kolbe Kegel)
 * 
 * 
 * 16 October 2013, 09:30 BST
 * 
 * Correct mistake in Monitor table where 'null' should be NULL
 * Add lastmonitored (date-time) to system and node records
 * Modify iCalEntry processing to accept any newline or | as line separator,
 *	store always as CRLF between lines
 * Minor bug fixes, tidy up
 * 
 * 
 * 15 October 2013, 12:00 BST
 * 
 * Added 'provisioned' to node states where node cannot be deleted
 * Added 'firewall-setup' as new command step (affects NodeCommands table)
 * Removed 'register' step, no longer required
 * Implemented mechanism to return nulls for monitor average data where raw data is null
 * Provided If-Modified-Since capability to GET requests on /monitorclasses
 * Fixed bug in treatment of command for non-existent node
 * Fixed bugs in date handling including Bugzilla 229
 * 
 * 
 * 14 October 2013, 16:30 BST
 * 
 * Bring system states into line with documentation
 * Introduce full range of Galera monitors
 * Make API HTML responses into correct HTML (not just text)
 * Allow components to have zero node ID, or zero node and system ID
 * Internal improvements; bug fixes.
 * 
 * 
 * 8 October 2013, 11:00 BST
 * 
 * Switch to using UTC internally for the API and the Admin DB.
 * Check the same Private IP is not reused within a system.
 * Protect "live" nodes from deletion.  Correct bugs.
 * 
 * 4 October 2013, 10:15
 * 
 * Fix bug that allowed monitor to bypass checks on provisioning state
 * transitions.
 * 
 *  
 * 3 October 2013, 15:45
 * 
 * Fix typo in command steps in NodeCommands table.
 * 
 * 
 * 3 October 2013, 11:40 BST
 * 
 * Aligned Galera node and system states with the document "Node States, System
 * States and Node State Monitor Algorithm".  The only exceptions are that the
 * API has states as all lower case and uses hyphens in place of underscores.
 * Please note that there is currently no logic for handling system commands.
 * Introduced automatic date stamp based on the date stamp of the CHANGELOG file;
 * see ../metadata to find the date stamp of the API.
 * Fixed bug so property of finished is included in latest task of nodes.
 * 
 * 
 * 2 October 2013, 14:45 BST
 * 
 * Limit restriction on running a new command across all nodes to start
 * or restart.  Restrict commands to only one at once per node.  
 * Develop scheduling internal logic.
 * 
 * 
 * 1 October 2013, 17:00
 * 
 * Fix bug in AdminDatabase with wrong case for CachedSingleton.
 * Add preliminary UserData operations for retrieving backup logs.
 * Restore logging directory to model api.ini for transition of scripts.
 * 
 * 
 * 1 October 2013, 12:45 BST
 * 
 * Trim any spaces from lists of steps.  Replace "recover" by "stop,start" to
 * bypass Galera issue (requres replacement of NodeCommands table).  Fix lack
 * of "finished" in Task record returned after command.
 * 
 * 
 * 1 October 2013, 10:15 BST
 * 
 * Added new command steps for provisioning. 
 * Extended descriptions of command states.
 * Amended node "commands" logic to include provisioning commands.
 * Added "finished" field to Task to indicate a command has finished.
 * 
 * 
 * 30 September 2013, 18:05 BST
 * 
 * Added option to cancel a task by calling ../task/{taskid} with DELETE
 * request.  Added incorrectly-joined state.  Fixed bug.
 * 
 * 30 September 2013, 14:00 BST
 * 
 * Fix bugs.  Put correct steps for provisioning commands.  Add ErrorMessage
 * field into Task record.  Add If-Modified-Since for Schedules.
 * 
 * 
 * 30 September 2013, 11:15 BST
 * 
 * Provide for 'connect', 'probe', 'provision' commands in the API logic
 * and the HTML.  Preliminary implementation.
 * 
 * 30 September 2013, 10:15 BST
 * 
 * Provide read, update, delete capabilities for Schedule records.
 * Extend cached monitor data provision for the monitor to cater for system
 * level changes.  Add validation of username submitted with a command.
 * Adjust HTML to allow schedule handling, remove state from node creation.
 * 
 * 27 September 2013, 18:00 BST
 * 
 * Provide /provisionednode for the monitor, with If-Modified-Since behaviour.
 * 
 * 
 * 27 September 2013, 15:30 BST
 * 
 * Fix bug in return of last task for a system.  Change sequence of return
 * of backups and tasks to be newest first.
 * 
 * 
 * 26 September 2013, 09:30 BST
 * 
 * Bug fix for clearing caches on new database.  Suppress system state
 * checking - needs new code to handle system type dependency.
 * 
 * 25 September 2013, 17:15 BST
 * 
 * Bug fixes.  Added provisioning node states; added system states.
 * Finite state machine to model provisioning states and police transitions.
 * 
 * 
 * 25 September 2013, 09:30 BST
 * 
 * Moved logging to syslog. Placed restriction on simultaneous commands.
 * Made SystemID automatically allocated, change create method to POST.
 * Added last updated date/time stamp for all properties.  Added ScheduleID
 * to Task record.  Renamed Storage to BackupURL in Backup record.
 * Changed data returned for systems to provide complete last Task instead
 * of current TaskID and Command.  Bug fixes.
 * 
 * 
 * 19 September 2013, 12:30 BST
 * 
 * Implement If-Modified-Since for single system, node, task entities.
 * 
 * 
 * 18 September 2013, 17:15 BST
 * 
 * Numerous database changes - see email earlier today.
 * Implement component properties.  Requires new table:
 * create table ComponentProperties (
 *	ComponentID	varchar(40),
 *	Property	varchar(40),
 *	Value		text
 *	);
 * Removed all references to Icon field, no longer required.  Removed all
 * reference to UIGroup field, no longer required.  
 * Changed node handling so ID is automatically generated and never reused.
 * Calls to API are changed as a result.
 * Added code for scheduling, but not ready for use yet.
 * Bug fixes, including problem of PHP parsing of query strings performing 
 * urldecode where not appropriate.
 * 
 * 
 * 14 September 2013, 10:20 BST
 * 
 * When receiving PUT data fields, convert null to empty string.
 * Preparations for introducing Schedule entities; code preparatory to
 * supporting component properties in nodes (no change to interface yet).
 * 
 * 13 September 2013, 09:40 BST
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

