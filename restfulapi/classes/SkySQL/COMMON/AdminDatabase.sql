/* Recently added changes requiring DB upgrade:
** 
** Added Decimals to Monitor table - default 0
**
**/

CREATE TABLE SystemCommands ( 
Command varchar(40), /* Name of the command */ 
State varchar(20), /* System state */
Description varchar(255), /* Textual description */ 
Icon varchar(200), /* Name of icon */
UIOrder smallint, /* Display order in UI */ 
UIGroup varchar(40), /* Display group in UI */ 
Steps varchar(255) /* Comma separated list of step IDs */ 
);
insert into SystemCommands (Command, State, Description, Icon, UIOrder, UIGroup, Steps) values ('stop', 'running', 'Stop System', 'stop', 2, 'control', 'stop');
insert into SystemCommands (Command, State, Description, Icon, UIOrder, UIGroup, Steps) values ('restart', 'running', 'Restart System', 'restart', 3, 'control', 'stop,start');
insert into SystemCommands (Command, State, Description, Icon, UIOrder, UIGroup, Steps) values ('start', 'stopped', 'Start System', 'start', 1, 'control', 'start');

CREATE TABLE NodeCommands ( 
Command varchar(40), /* Name of the command */ 
State varchar(20), /* System state */
Description varchar(255), /* Textual description */ 
Icon varchar(200), /* Name of icon */
UIOrder smallint, /* Display order in UI */ 
UIGroup varchar(40), /* Display group in UI */ 
Steps varchar(255) /* Comma separated list of step IDs */ 
);
insert into NodeCommands (Command, State, Description, Icon, UIOrder, UIGroup, Steps) values ('stop', 'master', 'Stop Master Node', 'stop', 2, 'control', 'stop');
insert into NodeCommands (Command, State, Description, Icon, UIOrder, UIGroup, Steps) values ('stop', 'slave', 'Stop Slave Node', 'stop', 2, 'control', 'stop');
insert into NodeCommands (Command, State, Description, Icon, UIOrder, UIGroup, Steps) values ('restart', 'master', 'Restart Master Node', 'stop', 3, 'control', 'stop,start');
insert into NodeCommands (Command, State, Description, Icon, UIOrder, UIGroup, Steps) values ('restart', 'slave', 'Restart Slave Node', 'restart', 3, 'control', 'stop,start');
insert into NodeCommands (Command, State, Description, Icon, UIOrder, UIGroup, Steps) values ('promote', 'slave', 'Promote Slave Node', 'promote', 6, 'control', 'promote');
insert into NodeCommands (Command, State, Description, Icon, UIOrder, UIGroup, Steps) values ('backup', 'slave', 'Backup Online Slave Node', 'backup', 1, 'backup', 'isolate,backup,promote');
insert into NodeCommands (Command, State, Description, Icon, UIOrder, UIGroup, Steps) values ('restore', 'slave', 'Restore Online Slave Node', 'stop', 2, 'backup', 'isolate,restore,synchronize');
insert into NodeCommands (Command, State, Description, Icon, UIOrder, UIGroup, Steps) values ('backup', 'offline', 'Backup Offline Slave Node', 'backup', 1, 'backup', 'backup');
insert into NodeCommands (Command, State, Description, Icon, UIOrder, UIGroup, Steps) values ('restart', 'offline', 'Restore Offline Slave Node', 'stop', 2, 'backup', 'restore');
insert into NodeCommands (Command, State, Description, Icon, UIOrder, UIGroup, Steps) values ('start', 'stopped', 'Start Stopped Node', 'start', 1, 'control', 'start');
insert into NodeCommands (Command, State, Description, Icon, UIOrder, UIGroup, Steps) values ('start', 'error', 'Stop Node in Error', 'stop', 2, 'control', 'stop');
insert into NodeCommands (Command, State, Description, Icon, UIOrder, UIGroup, Steps) values ('restart', 'error', 'Restart Node in Error', 'restart', 3, 'control', 'restart');

/* End of new tables */

/*
** System level description.
**
** The provisioning will create a single row for the system with a SkySQL
** generated SystemID, this is essentially the serial number of the
** installation.
*/

create table System (
	SystemID		int PRIMARY KEY,				/* SystemID allocated by provisioning */
	SystemType		varchar(20),					/* Type of system e.g. galera or aws */
	SystemName		varchar(80),					/* User defined system name */
	InitialStart	datetime,						/* Time of first system boot */
	LastAccess		datetime,							/* Last time admin access to system */
	State			varchar(20)						/* The current state of the system */
);

/*
** A system property table for storing general system related information such
** as number or size of backup storage.
*/
create table SystemProperties (
	SystemID 	int,								/* SystemID allocated by provisioning */
	Property	varchar(40),
	Value		text
);
create unique index SystemPropertyIDX ON SystemProperties (SystemID, Property);

/*
** An application property table for storing arbitrary application data
*/
CREATE TABLE ApplicationProperties (
	ApplicationID int, /* ApplicationID allocated by System Manager */ 
	Property varchar(40), 
	Value text
);
create unique index ApplicationPropertyIDX on ApplicationProperties (ApplicationID, Property);

insert into ApplicationProperties values (1, 'maxBackupCount', '1,3,5,10');
insert into ApplicationProperties values (1, 'maxBackupSize', '5,10,15,20,25');
insert into ApplicationProperties values (1, 'monitorInterval', '5,10,15,30,60,120,300');

/*
** Set of rows, one per node within the system. Created by the provisioning system
** at first boot of the system.
*/
create table Node (
	NodeID		int,			/* Node Id within system */
	SystemID	int,			/* Which system ID is this node in */
	NodeName	varchar(80),	/* User defined system name */
	State		varchar(20),	/* Current state of the node */
	Hostname	varchar(255),	/* Internal hostname of the node */
	PublicIP	varchar(45),	/* Current public IP address of node */
	PrivateIP	varchar(45),	/* Current private IP address of the node*/
	Port		int,			/* Port number for database access */
	InstanceID	varchar(20),	/* The EC2 instance ID of the node */
	DBUserName	varchar(50),
	DBPassword	varchar(50)
);
create unique index SystemNodeIDX on Node (SystemID, NodeID);

/*
** One row per node, contains the volatile node data that is maintained by the
** pacemaker environment and driven from Amazon resources.
*/
create view NodeData AS SELECT NodeID, SystemID, Hostname, PublicIP, PrivateIP, InstanceID, DBUserName AS Username, DBPassword AS passwd FROM Node;	

create trigger update_node_id instead of update on NodeData 
begin 
update Node set PublicIP = new.PublicIP, PrivateIP = new.PrivateIP where SystemID = old.SystemID and NodeID = old.NodeID;
end;

/*
** Log of commands executed on a node.
*/
create table Task (
	TaskID			integer PRIMARY KEY AUTOINCREMENT,
	SystemID		int,					/* SystemID of the system */
	NodeID			int,					/* NodeID executed on */
	PrivateIP		varchar(45),			/* Private IP address of the node when task was started*/
	BackupID		int,					/* For backup, the ID of the backup record */
	UserName		varchar(40),			/* UserName that requested the command execution */
	Command			varchar(40),			/* Command executed */
	Steps			varchar(255),			/* Comma separated list of step IDs */
	Params			text,					/* Parameters for Command */
	Started			datetime,				/* Timestamp at start of execution */
    PID				int,					/* Process ID for running script */
	Completed		datetime,				/* Timestamp on completion, this will be
											* NULL for commands that are in progress
											*/
	StepIndex		smallint default 0,		/* Index of step being executed in CommandStep */
	State			varchar(20)				/* Command state */
);

/*
** Scheduled tasks.
*/
create table Schedule (
	ScheduleID		integer PRIMARY KEY AUTOINCREMENT,
	SystemID		int,					/* SystemID of the system */
	NodeID			int,					/* NodeID executed on */
	UserName		varchar(40),			/* UserName that requested the command execution */
	Command			varchar(40),			/* Command executed */
	BackupLevel		int,					/* Whether a full=1 or incremental=2 backup, if applicable */
	Params			text,					/* Parameters for Command */
	iCalEntry		text,					/* Schedule details, if scheduled */
	NextStart		datetime,				/* Time for next scheduled start */
	ATJobNumber		int,					/* Linux AT job number */
	Created			datetime,				/* Timestamp when created */
	Updated			datetime				/* Timestamp when last updated */
);

create table Monitor (
	MonitorID		integer PRIMARY KEY autoincrement,		/* ID for Monitor */
	SystemType		varchar(20),				/* System type handled - e.g. aws or galera */
	Monitor			varchar(40),				/* Short name of monitor */
	Name			varchar(80),				/* Displayed name of this monitor */
	SQL				text,						/* SQL to run on MySQL to get the current
												* value of the monitor. */
	Description		varchar(255),				/* tooltip description of monitor */
	Decimals		int default 0,				/* Number of decimal places (can be negative) */
	ChartType		varchar(40),				/* type of chart to be used for rendering */
	delta			boolean,					/* This monitor reports delta values */
	MonitorType		varchar(20),				/* Type of the monitor */
	SystemAverage	boolean,					/* If value is averaged over nodes */
	Interval		int,						/* monitoring interval in seconds */
	Unit			varchar(40)					/* unit of measurement for data */
);

create unique index MonitorSystemIDX on Monitor (SystemType,Monitor);

/* AWS monitors */
insert into Monitor (SystemType, Monitor, Name, Decimals, SQL, Description, ChartType, delta, MonitorType, SystemAverage, Interval, Unit) values ('aws', 'connections', 'Connections', 0, 'select variable_value from global_status where variable_name = "THREADS_CONNECTED";', '', 'LineChart', 0, 'SQL', 0, 30, null);
insert into Monitor (SystemType, Monitor, Name, Decimals, SQL, Description, ChartType, delta, MonitorType, SystemAverage, Interval, Unit) values ('aws', 'traffic', 'Network Traffic', 0, 'select round(sum(variable_value) / 1024) from global_status where variable_name in ("BYTES_RECEIVED", "BYTES_SENT");', '', 'LineChart', 1, 'SQL', 0, 30, 'kB/min');
insert into Monitor (SystemType, Monitor, Name, Decimals, SQL, Description, ChartType, delta, MonitorType, SystemAverage, Interval, Unit) values ('aws', 'availability', 'Availability', 2, 'select 100;', '', 'LineChart', 0, 'SQL', 1, 30, '%');
insert into Monitor (SystemType, Monitor, Name, Decimals, SQL, Description, ChartType, delta, MonitorType, SystemAverage, Interval, Unit) values ('aws', 'nodestate', 'Node State', 0, 'crm status bynode', '', null, 0, 'CRM', 0, 30, null);
insert into Monitor (SystemType, Monitor, Name, Decimals, SQL, Description, ChartType, delta, MonitorType, SystemAverage, Interval, Unit) values ('aws', 'capacity', 'Capacity', 2, 'select round(((select variable_value from global_status where variable_name = "THREADS_CONNECTED") * 100) / variable_value) from global_variables where variable_name = "MAX_CONNECTIONS";', '', null, 0, 'SQL', 1, 30, '%');
insert into Monitor (SystemType, Monitor, Name, Decimals, SQL, Description, ChartType, delta, MonitorType, SystemAverage, Interval, Unit) values ('aws', 'hoststate', 'Host State', 0, '', '', null, 0, 'PING', 0, 30, null);

/* Galera monitors */
insert into Monitor (SystemType, Monitor, Name, Decimals, SQL, Description, ChartType, delta, MonitorType, SystemAverage, Interval, Unit) values ('galera', 'connections', 'Connections', 0, 'select variable_value from global_status where variable_name = "THREADS_CONNECTED";', '', 'LineChart', 0, 'SQL', 0, 30, null);
insert into Monitor (SystemType, Monitor, Name, Decimals, SQL, Description, ChartType, delta, MonitorType, SystemAverage, Interval, Unit) values ('galera', 'traffic', 'Network Traffic', 0, 'select round(sum(variable_value) / 1024) from global_status where variable_name in ("BYTES_RECEIVED", "BYTES_SENT");', '', 'LineChart', 1, 'SQL', 0, 30, 'kB/min');
insert into Monitor (SystemType, Monitor, Name, Decimals, SQL, Description, ChartType, delta, MonitorType, SystemAverage, Interval, Unit) values ('galera', 'availability', 'Availability', 2, 'select 100;', '', 'LineChart', 0, 'SQL', 1, 30, '%');
insert into Monitor (SystemType, Monitor, Name, Decimals, SQL, Description, ChartType, delta, MonitorType, SystemAverage, Interval, Unit) values ('galera', 'capacity', 'Capacity', 2, 'select round(((select variable_value from global_status where variable_name = "THREADS_CONNECTED") * 100) / variable_value) from global_variables where variable_name = "MAX_CONNECTIONS";', '', null, 0, 'SQL', 1, 30, '%');
insert into Monitor (SystemType, Monitor, Name, Decimals, SQL, Description, ChartType, delta, MonitorType, SystemAverage, Interval, Unit) values ('galera', 'hoststate', 'Host State', 0, '', '', null, 0, 'PING', 0, 30, null);
insert into Monitor (SystemType, Monitor, Name, Decimals, SQL, Description, ChartType, delta, MonitorType, SystemAverage, Interval, Unit) values ('galera', 'nodestate', 'NodeState', 0, 'select 100 + variable_value from global_status where variable_name = "WSREP_LOCAL_STATE" union select 107 limit 1;', '', null, 0, 'SQL_NODE_STATE', 1, 30, null);
insert into Monitor (SystemType, Monitor, Name, Decimals, SQL, Description, ChartType, delta, MonitorType, SystemAverage, Interval, Unit) values ('galera', 'clustersize', 'Cluster Size', 0, 'select variable_value from global_status where variable_name = "WSREP_CLUSTER_SIZE";', 'Number of nodes in the cluster', 'LineChart', 0, 'SQL', 1, 30, null);
insert into Monitor (SystemType, Monitor, Name, Decimals, SQL, Description, ChartType, delta, MonitorType, SystemAverage, Interval, Unit) values ('galera', 'reppaused', 'Replication Paused', 2, 'select variable_value * 100 from global_status where variable_name = "WSREP_FLOW_CONTROL_PAUSED";', 'Percentage of time for which replication was paused', 'LineChart', 0, 'SQL', 1, 30, '%');
insert into Monitor (SystemType, Monitor, Name, Decimals, SQL, Description, ChartType, delta, MonitorType, SystemAverage, Interval, Unit) values ('galera', 'parallelism', 'Parallelism', 0, 'select variable_value from global_status where variable_name = "WSREP_CERT_DEPS_DISTANCE";', 'Average No. of parallel transactions', 'LineChart', 0, 'SQL', 1, 30, null);
insert into Monitor (SystemType, Monitor, Name, Decimals, SQL, Description, ChartType, delta, MonitorType, SystemAverage, Interval, Unit) values ('galera', 'recvqueue', 'Avg Receive Queue', 0, 'select variable_value from global_status where variable_name = "WSREP_LOCAL_RECV_QUEUE_AVG";', 'Average receive queue length', 'LineChart', 0, 'SQL', 1, 30, null);
insert into Monitor (SystemType, Monitor, Name, Decimals, SQL, Description, ChartType, delta, MonitorType, SystemAverage, Interval, Unit) values ('galera', 'flowcontrol', 'Flow Controlled', 0, 'select variable_value from global_status where variable_name = "WSREP_FLOW_CONTROL_SENT";', 'Flow control messages sent', 'LineChart', 1, 'SQL', 0, 30, null);
insert into Monitor (SystemType, Monitor, Name, Decimals, SQL, Description, ChartType, delta, MonitorType, SystemAverage, Interval, Unit) values ('galera', 'sendqueue', 'Avg Send Queue', 0, 'select variable_value from global_status where variable_name = "WSREP_LOCAL_SEND_QUEUE_AVG";', 'Average length of send queue', 'LineChart', 0, 'SQL', 1, 30, null);

create table User (
	UserID		integer PRIMARY KEY autoincrement,
	UserName	varchar(40),
	Name		varchar (100),
	Password	varchar(60)
);
create unique index UserNameIDX on User (UserName);

create table UserProperties (
	UserName	varchar(40),
	Property	varchar(40),
	Value		text
);

create table UserTag (
	UserName	varchar (40),
	TagType		varchar	(40),
	TagName		varchar	(40),
	Tag			text
);
create unique index UserTagIDX on UserTag (UserName, TagType, TagName, Tag);

create table Assignments (
	id integer NOT NULL PRIMARY KEY AUTOINCREMENT,
	access_type varchar(60) NOT NULL,
	access_id text NOT NULL,
	role varchar(60) NOT NULL
);
create index AccessType on Assignments (access_type, access_id, role);

create table Permissions (
	id integer NOT NULL PRIMARY KEY AUTOINCREMENT,
	role varchar(60) NOT NULL,
	control tinyint unsigned NOT NULL default 0,
	action varchar(60) NOT NULL,
	subject_type varchar(60) NOT NULL,
	subject_id text NOT NULL,
	system smallint unsigned NOT NULL default 0
);
create index RoleType on Permissions (role, action, subject_type, subject_id);
create index SubAction on Permissions (subject_type, action, subject_id);

create table Backup (
	BackupID	integer,		/* Unique identifier for the backup within System ID */
	SystemID	int,			/* System backup was taken on */
	NodeID		int,			/* Node backup was taken on */
	BackupLevel	smallint,		/* full=1 or incremental=2 backup */
	ParentID	int,			/* Parent from which this is incremental */
	State		varchar(20),	/* Backup state */
	Started		datetime,		/* Date when backup was started */
	Updated		datetime,		/* Date of last update of this record during backup */
	Restored	datetime,		/* Date of last restore from this backup */
	Size		int,			/* Size of backup */
	Storage		text,			/* Path to storage location */
	BinLog		text,			/* Binlog of backup */
	Log			text			/* URL to Log of backup */
);

create unique index SystemBackupIDX ON Backup (SystemID, BackupID);

CREATE TABLE ErrorLog (
	id integer NOT NULL PRIMARY KEY AUTOINCREMENT, 
	timestamp datetime NOT NULL, 
	smessage varchar(255) NOT NULL, 
	dbcall varchar(255) NOT NULL DEFAULT '', 
	dberror varchar(255) NOT NULL DEFAULT '', 
	dbmessage text NOT NULL, 
	dbtrace text NOT NULL, 
	sql text NOT NULL, 
	lmessage text NOT NULL, 
	errorkey text NOT NULL, 
	referer text NOT NULL, 
	ip varchar(15) NOT NULL DEFAULT '', 
	get text NOT NULL, 
	post text NOT NULL, 
	trace text NOT NULL 
);
