
/*
** Schedule entries are used to trigger future and recurring events 
*/
create table Schedule (
	EventType	smallint,	/* type of schedule (Command, Notification, Monitor etc.) */
	EventID		smallint,	/* ID of the associated event */
	UserID		smallint,	/* UserID that created the schedule */
	AccessLevel smallint,	/* Public, Protected, Private, Hidden */
	EventStart		datetime,	/* Start of schedule */
	EventDuration	datetime, 	/* Duration of schedule */
	RepeatEnd		datetime,	/* End of Schedule */
	RepeatPattern	varchar(40),	/* Repeat pattern: 0-x, day/week/month/year */
	RepeatEvery		smallint,	/* 1-x */
	RepeatOccurrence	varchar(40),	/* 1-12, 1-31, Sun-Sat, First/Second/Third/Fourth/Last Sun-Sat/Day/Weekday/WeekendDay */
	State		smallint	/* state of schedule (Active, Suspended, Deleted) */
);

create table ScheduleType (
	Type	smallint,	/* type of schedule (Command, Notification, Monitor etc.) */
	Description varchar(255)
);

insert into ScheduleType values (1, 'Command');
insert into ScheduleType values (2, 'Backup');
insert into ScheduleType values (3, 'Notification');
insert into ScheduleType values (4, 'Monitor');

create table ScheduleState (
	State	smallint	/* state of schedule (Active, Suspended, Deleted) */
);

insert into ScheduleState values (1);	/* Active */
insert into ScheduleState values (2);	/* Suspended */
insert into ScheduleState values (3);	/* Deleted */

create table AccessLevel (
	Level	smallint,	/* type of access (Public, Protected, Private, Hidden) */
	Description varchar(255)
);

insert into AccessLevel values (1, 'Public');
insert into AccessLevel values (2, 'Protected');
insert into AccessLevel values (3, 'Private');
insert into AccessLevel values (4, 'Hidden');


/*
** Determine the set of commands that are available in a given state of a node.
** entries exists for a (State, CommandID) pair only when a command is valid
** in the given state.
*/
create table ValidCommands (
	State		smallint,	/* Current state */
	CommandID	smallint	/* Command */
);

insert into ValidCommands values (1, 2);
insert into ValidCommands values (1, 3);
/* insert into ValidCommands values (1, 4); */
insert into ValidCommands values (2, 2);
insert into ValidCommands values (2, 3);
/* insert into ValidCommands values (2, 4); */
insert into ValidCommands values (2, 6);
insert into ValidCommands values (2, 9);
insert into ValidCommands values (2, 10);
/* insert into ValidCommands values (3, 5); */
insert into ValidCommands values (3, 7);
insert into ValidCommands values (3, 8);
insert into ValidCommands values (5, 1);
insert into ValidCommands values (13, 2);
insert into ValidCommands values (13, 3);
insert into ValidCommands values (14, 2);
insert into ValidCommands values (14, 3);
insert into ValidCommands values (16, 1);

/* Commands for a standalone server */
insert into ValidCommands values (18, 2);
insert into ValidCommands values (18, 3);
insert into ValidCommands values (18, 7);
insert into ValidCommands values (18, 8);

/*
** Total set of commands within the system. These are the high level control
** elements within the system.
*/
create table Commands (
	CommandID	integer primary key autoincrement,
	Name		varchar(40),	/* Short name of the command */
	Description	varchar(255),	/* Textual description */
	Icon		varchar(200),	/* Name of icon */
	UIOrder		smallint,		/* Display order in UI */
	UIGroup		varchar(40),	/* Display group in UI */
	Steps		varchar(255)	/* Comma separated list of step IDs */
);

insert into Commands (CommandID, Name, Description, Icon, UIOrder, UIGroup, Steps) values (1, 'Start', 'Start node', 'start', 1, 'control', '1');
insert into Commands (CommandID, Name, Description, Icon, UIOrder, UIGroup, Steps) values (2, 'Stop', 'Stop node', 'stop', 2, 'control', '2');
insert into Commands (CommandID, Name, Description, Icon, UIOrder, UIGroup, Steps) values (3, 'Restart', 'Restart node', 'restart', 3, 'control', '2,1');
insert into Commands (CommandID, Name, Description, Icon, UIOrder, UIGroup, Steps) values (4, 'Isolate', 'Isolate node', 'isolate', 4, 'control', '3');
insert into Commands (CommandID, Name, Description, Icon, UIOrder, UIGroup, Steps) values (5, 'Recover', 'Recover node', 'recover', 5, 'control', '6,4');
insert into Commands (CommandID, Name, Description, Icon, UIOrder, UIGroup, Steps) values (6, 'Promote', 'Promote slave to master', 'promote', 6, 'control', '5');
insert into Commands (CommandID, Name, Description, Icon, UIOrder, UIGroup, Steps) values (7, 'Backup', 'Backup offline node', 'backup', 1, 'backup', '7');
insert into Commands (CommandID, Name, Description, Icon, UIOrder, UIGroup, Steps) values (8, 'Restore', 'Restore offline node', 'restore', 2, 'backup', '8');
insert into Commands (CommandID, Name, Description, Icon, UIOrder, UIGroup, Steps) values (9, 'Backup', 'Backup online node', 'backup', 1, 'backup', '3,7,5');
insert into Commands (CommandID, Name, Description, Icon, UIOrder, UIGroup, Steps) values (10, 'Restore', 'Restore online node', 'restore', 2, 'backup', '3,8,6');

/*
** Lookup table for the admin console front end that maps node states to icons
** and textual descriptions of the node state.
** This is merely for the convenience of the front end to map node states to UI
** elements.
*/

create table	NodeState (
	State		integer PRIMARY KEY,			/* The node state */
	Description	varchar(255),					/* Textual description of state */
	Icon		varchar(200)					/* Name of the icon to use for this state */
);

insert into NodeState (State, Description, Icon) values (1, 'Master', 'master');
insert into NodeState (State, Description, Icon) values (2, 'Slave Online', 'slave');
insert into NodeState (State, Description, Icon) values (3, 'Slave Offline', 'offline');
insert into NodeState (State, Description, Icon) values (4, 'Slave Stopping', 'stopping');
insert into NodeState (State, Description, Icon) values (5, 'Slave Stopped', 'stopped');
insert into NodeState (State, Description, Icon) values (6, 'Slave Isolating', 'isolating');
insert into NodeState (State, Description, Icon) values (7, 'Slave Recovering', 'recovering');
insert into NodeState (State, Description, Icon) values (8, 'Slave Restoring Backup', 'restoring');
insert into NodeState (State, Description, Icon) values (9, 'Slave Backing Up', 'backingup');
insert into NodeState (State, Description, Icon) values (10, 'Slave Starting', 'starting');
insert into NodeState (State, Description, Icon) values (11, 'Slave Promoting', 'promoting');
insert into NodeState (State, Description, Icon) values (12, 'Slave Synchronizing', 'synchronizing');
insert into NodeState (State, Description, Icon) values (13, 'Slave Error', 'error');
insert into NodeState (State, Description, Icon) values (14, 'System Running', 'system');
insert into NodeState (State, Description, Icon) values (15, 'System Stopping', 'sys_stopping');
insert into NodeState (State, Description, Icon) values (16, 'System Stopped', 'sys_stopped');
insert into NodeState (State, Description, Icon) values (17, 'System Starting', 'sys_starting');
insert into NodeState (State, Description, Icon) values (18, 'Standalone Database', 'node');

create unique index NodeDescriptionIDX on NodeState (Description);

*/
/*
** Description of each step within a compound command. A script is given that
** should be executed at each stage.
*/
create table Step (
	StepID		integer PRIMARY KEY autoincrement,
	Script		varchar(255),	/* Script to execute for this step */
	Icon		varchar(200),	/* Icon to represent the step */
	Description	varchar(255)	/* Textual description */
);

insert into Step (StepID, Script, Icon, Description) values (1, 'start', 'starting', 'Start node up, start replication');
insert into Step (StepID, Script, Icon, Description) values (2, 'stop', 'stopping', 'Stop replication, shut node down');
insert into Step (StepID, Script, Icon, Description) values (3, 'isolate', 'isolating', 'Take node out of replication');
insert into Step (StepID, Script, Icon, Description) values (4, 'recover', 'recovering', 'Put node back into replication');
insert into Step (StepID, Script, Icon, Description) values (5, 'promote', 'promoting', 'Promote a slave to master');
insert into Step (StepID, Script, Icon, Description) values (6, 'synchronize', 'synchronizing', 'Synchronize a node');
insert into Step (StepID, Script, Icon, Description) values (7, 'backup', 'backingup', 'Backup a node');
insert into Step (StepID, Script, Icon, Description) values (8, 'restore', 'restoring', 'Restore a node');

/*
** Lookup table for the admin console front end that maps command states to icons
** and textual descriptions of the command state.
*/
create table CommandStates (
	State		integer PRIMARY KEY,	/* The command state */
	Description	varchar(255),			/* Textual description of state */
	Icon		varchar(200)			/* Name of the icon to use for this state */
);

insert into CommandStates (State, Description, Icon) values (1, 'Scheduled', 'scheduled');
insert into CommandStates (State, Description, Icon) values (2, 'Running', 'running');
insert into CommandStates (State, Description, Icon) values (3, 'Paused', 'paused');
insert into CommandStates (State, Description, Icon) values (4, 'Stopped', 'stopped');
insert into CommandStates (State, Description, Icon) values (5, 'Done', 'done');
insert into CommandStates (State, Description, Icon) values (6, 'Error', 'error');

create table CRMStateMap (
	crmState	varchar(20),
	State		int
);

insert into CRMStateMap values('Master', 1);
insert into CRMStateMap values('Started', 2);
insert into CRMStateMap values('Slave', 2);
insert into CRMStateMap values('not running', 5);

/*
** Lookup table for the admin console front end that maps backup states to icons
** and textual descriptions of the backup state.
*/
create table BackupStates (
	State		smallint,		/* The backup state */
	Description	varchar(255),	/* Textual description of state */
	Icon		varchar(200)	/* Name of the icon to use for this state */
);

insert into BackupStates (State, Description, Icon) values (1, 'Scheduled', 'scheduled');
insert into BackupStates (State, Description, Icon) values (2, 'Running', 'running');
insert into BackupStates (State, Description, Icon) values (3, 'Paused', 'paused');
insert into BackupStates (State, Description, Icon) values (4, 'Stopped', 'stopped');
insert into BackupStates (State, Description, Icon) values (5, 'Done', 'done');
insert into BackupStates (State, Description, Icon) values (6, 'Error', 'error');
