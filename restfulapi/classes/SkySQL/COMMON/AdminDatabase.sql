/* Recently added changes requiring DB upgrade:
** 
** Added Decimals to Monitor table - default 0
**
**/

CREATE TABLE SystemCommands ( 
	Command varchar(40), /* Name of the command */ 
	State varchar(20), /* System state */
	Description varchar(255), /* Textual description */ 
	UIOrder smallint, /* Display order in UI */ 
	Steps varchar(255) /* Comma separated list of step IDs */ 
);
insert into SystemCommands (Command, State, Description, UIOrder, Steps) values ('stop', 'running', 'Stop System', 2, 'stop');
insert into SystemCommands (Command, State, Description, UIOrder, Steps) values ('restart', 'running', 'Restart System', 3, 'stop,start');
insert into SystemCommands (Command, State, Description, UIOrder, Steps) values ('start', 'stopped', 'Start System', 1, 'start');

CREATE TABLE NodeCommands ( 
	Command		varchar(40),	/* Name of the command */ 
	SystemType	varchar(20),	/* Type of system e.g. galera or aws */
	State		varchar(20),	/* System state */
	Description varchar(255),	/* Textual description */ 
	UIOrder		smallint,		/* Display order in UI */ 
	Steps		varchar(255)	/* Comma separated list of step IDs */ 
);

insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('connect', 'provision', 'created', 'Set up communications to node', 1, 'setup-ssh, register, install-agent');
insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('connect', 'provision', 'unconnected', 'Set up communications to node', 1, 'setup-ssh, register, install-agent');
insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('probe', 'provision', 'connected', 'Probe node to determine software configuration', 1, 'probe');
insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('probe', 'provision', 'incompatible', 'Probe node to determine software configuration', 1, 'probe');
insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('provision', 'provision', 'unprovisioned', 'Install a database on the node', 1, 'install-packages, configure');


insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('start', 'aws', 'provisioned', 'Start Provisioned Node', 3, 'start');
insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('restore', 'aws', 'provisioned', 'Restore Provisioned Node', 3, 'restore');

insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('stop', 'aws', 'master', 'Stop Master Node', 2, 'stop');
insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('restart', 'aws', 'master', 'Restart Master Node', 3, 'stop,start');

insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('stop', 'aws', 'slave', 'Stop Slave Node', 2, 'stop');
insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('restart', 'aws', 'slave', 'Restart Slave Node', 3, 'stop,start');
insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('promote', 'aws', 'slave', 'Promote Slave Node', 6, 'promote');
insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('backup', 'aws', 'slave', 'Backup Online Slave Node', 1, 'isolate,backup,promote');
insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('restore', 'aws', 'slave', 'Restore Online Slave Node', 2, 'isolate,restore,synchronize');

insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('backup', 'aws', 'offline', 'Backup Offline Slave Node', 1, 'backup');
insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('restore', 'aws', 'offline', 'Restore Offline Slave Node', 2, 'restore');

insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('start', 'aws', 'stopped', 'Start Stopped Node', 1, 'start');

insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('stop', 'aws', 'error', 'Stop Node in Error', 2, 'stop');
insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('restart', 'aws', 'error', 'Restart Node in Error', 3, 'restart');


insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('start', 'galera', 'provisioned', 'Start Provisioned Node', 3, 'start');
insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('restore', 'galera', 'provisioned', 'Restore Provisioned Node', 3, 'restore');

insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('stop', 'galera', 'isolated', 'Stop Node when Isolated', 3, 'stop');
insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('restart', 'galera', 'isolated', 'Restart Node when Isolated', 3, 'stop,start');
insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('rejoin', 'galera', 'isolated', 'Rejoin Node when Isolated', 3, 'stop,start');
/* insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('rejoin', 'galera', 'isolated', 'Rejoin Node when Isolated', 3, 'recover'); */
insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('backup', 'galera', 'isolated', 'Backup Node when Isolated', 3, 'backup');
insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('restore', 'galera', 'isolated', 'Restore Node when Isolated', 3, 'restore');

insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('start', 'galera', 'down', 'Start Node from Down', 3, 'start');

insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('stop', 'galera', 'incorrectly-joined', 'Stop Incorrectly Joined Node', 3, 'stop');
insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('restart', 'galera', 'incorrectly-joined', 'Restart Incorrectly Joined Node', 3, 'stop,start');

insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('stop', 'galera', 'open', 'Stop Node when Open', 3, 'stop');
insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('restart', 'galera', 'open', 'Restart Node when Open', 3, 'stop,start');

insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('stop', 'galera', 'primary', 'Stop Node when Primary', 3, 'stop');
insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('restart', 'galera', 'primary', 'Restart Node when Primary', 3, 'stop,start');

insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('stop', 'galera', 'joiner', 'Stop Node when Joiner', 3, 'stop');
insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('restart', 'galera', 'joiner', 'Restart Node when Joiner', 3, 'stop,start');

insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('stop', 'galera', 'joined', 'Stop Node when Joined', 3, 'stop');
insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('restart', 'galera', 'joined', 'Restart Node when Joined', 3, 'stop,start');
insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('isolate', 'galera', 'joined', 'Take Joined Node out of Replication', 3, 'isolate');
insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('backup', 'galera', 'joined', 'Backup Joined Node', 3, 'isolate,backup,stop,start');
/* insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('backup', 'galera', 'joined', 'Backup Joined Node', 3, 'isolate,backup,recover'); */
insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('restpre', 'galera', 'joined', 'Restore Joined Node', 3, 'isolate,restore,stop,start');
/* insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('restore', 'galera', 'joined', 'Restore Joined Node', 3, 'isolate,restore,recover'); */

insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('stop', 'galera', 'synced', 'Stop Node when Synced', 3, 'stop');
insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('restart', 'galera', 'synced', 'Restart Node when Synced', 3, 'stop,start');

insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('stop', 'galera', 'donor', 'Stop Donor Node', 3, 'stop');
insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('restart', 'galera', 'donor', 'Restart Donor Node', 3, 'stop,start');

/* End of new tables */

/*
** System level description.
**
** The provisioning will create a single row for the system with a SkySQL
** generated SystemID, this is essentially the serial number of the
** installation.
*/

create table System (
	SystemID		integer PRIMARY KEY AUTOINCREMENT,	/* SystemID allocated by provisioning */
	SystemType		varchar(20),						/* Type of system e.g. galera or aws */
	SystemName		varchar(80),						/* User defined system name */
	InitialStart	datetime,							/* Time of first system boot */
	LastAccess		datetime,							/* Last time admin access to system */
	Updated			datetime,							/* Last time System record was updated */
	State			varchar(20),						/* The current state of the system */
	/* The User Name and Password pairs here are system wide defaults that can be overriden in individual nodes */
	DBUserName	varchar(50),							/* DB User Name for general queries, processlist etc */
	DBPassword	varchar(50),							/* DB Password for general queries, processlist etc */
	RepUserName	varchar(50),							/* DB User Name for replication */
	RepPassword	varchar(50)								/* DB Password for replication */
);

/*
** A system property table for storing general system related information such
** as number or size of backup storage.
*/
create table SystemProperties (
	SystemID 	int,								/* SystemID allocated by provisioning */
	Property	varchar(40),						/* Name of property */
	Updated		datetime,							/* Date/time stamp for last updated */
	Value		text								/* Property value */
);
create unique index SystemPropertyIDX ON SystemProperties (SystemID, Property);

/*
** An application property table for storing arbitrary application data
*/
CREATE TABLE ApplicationProperties (
	ApplicationID int,								/* ApplicationID allocated by System Manager */ 
	Property varchar(40),							/* Name of property */
	Updated		datetime,							/* Date/time stamp for last updated */
	Value text										/* Property value */
);
create unique index ApplicationPropertyIDX on ApplicationProperties (ApplicationID, Property);

insert into ApplicationProperties values (1, 'maxBackupCount', datetime('now', 'localtime'), '1,3,5,10');
insert into ApplicationProperties values (1, 'maxBackupSize', datetime('now', 'localtime'), '5,10,15,20,25');
insert into ApplicationProperties values (1, 'monitorInterval', datetime('now', 'localtime'), '5,10,15,30,60,120,300');

/*
** Set of rows, one per node within the system. Created by the provisioning system
** at first boot of the system.
*/
create table Node (
	NodeID		integer PRIMARY KEY AUTOINCREMENT,			/* Node Id within system */
	SystemID	int,										/* Which system ID is this node in */
	NodeName	varchar(80),								/* User defined system name */
	State		varchar(20),								/* Current state of the node */
	Updated		datetime,									/* Last time Node record was updated */
	Hostname	varchar(255),								/* Internal hostname of the node */
	PublicIP	varchar(45),								/* Current public IP address of node */
	PrivateIP	varchar(45),								/* Current private IP address of the node*/
	Port		int,										/* Port number for database access */
	InstanceID	varchar(20),								/* The EC2 instance ID of the node */
	/* The User Name and Password pairs here override the system defaults, if present */
	DBUserName	varchar(50),								/* DB User Name for general queries, processlist etc */
	DBPassword	varchar(50),								/* DB Password for general queries, processlist etc */
	RepUserName	varchar(50),								/* DB User Name for replication */
	RepPassword	varchar(50)									/* DB Password for replication */
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

create table ComponentProperties (
	ComponentID	varchar(40),						/* ComponentID allocated by System Manager */ 
	Property	varchar(40),						/* Name of property */
	Updated		datetime,							/* Date/time stamp for last updated */
	Value		text								/* Value of property */
);
create unique index ComponentPropertyIDX on ComponentProperties (ComponentID, Property);

/*
** Log of commands executed on a node.
*/
create table Task (
	TaskID			integer PRIMARY KEY AUTOINCREMENT,
	SystemID		int,					/* SystemID of the system */
	NodeID			int,					/* NodeID executed on */
	PrivateIP		varchar(45),			/* Private IP address of the node when task was started*/
	ScheduleID		int,					/* Zero or the ID of the schedule that caused the task */
	BackupID		int,					/* For backup, the ID of the backup record */
	UserName		varchar(40),			/* UserName that requested the command execution */
	Command			varchar(40),			/* Command executed */
	Steps			varchar(255),			/* Comma separated list of step IDs */
	Params			text,					/* Parameters for Command */
	Started			datetime,				/* Timestamp at start of execution */
    PID				int,					/* Process ID for running script */
	Updated			datetime,				/* Last time Task record was updated */
	Completed		datetime,				/* Timestamp on completion, this will be
											* NULL for commands that are in progress
											*/
	StepIndex		smallint default 0,		/* Index of step being executed - refers to the internal commandsteps table */
	State			varchar(20),			/* Command state */
	ErrorMessage	text					/* Message to explain an error condition, if any */
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
	MonitorID		integer PRIMARY KEY AUTOINCREMENT,		/* ID for Monitor */
	SystemType		varchar(20),				/* System type handled - e.g. aws or galera */
	Monitor			varchar(40),				/* Short name of monitor */
	Name			varchar(80),				/* Displayed name of this monitor */
	SQL				text,						/* SQL to run on MySQL to get the current
												* value of the monitor. */
	Description		varchar(255),				/* tooltip description of monitor */
	Decimals		int default 0,				/* Number of decimal places (can be negative) */
	Mapping			text,						/* Mapping for non-numeric monitors - like query string */
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
insert into Monitor (SystemType, Monitor, Name, Decimals, SQL, Description, ChartType, delta, MonitorType, SystemAverage, Interval, Unit) values ('galera', 'nodestate', 'NodeState', 0, 'select 100 + variable_value from global_status where variable_name = "WSREP_LOCAL_STATE" union select 99 limit 1;', '', null, 0, 'SQL_NODE_STATE', 1, 30, null);
insert into Monitor (SystemType, Monitor, Name, Decimals, SQL, Description, ChartType, delta, MonitorType, SystemAverage, Interval, Unit) values ('galera', 'clustersize', 'Cluster Size', 0, 'select variable_value from global_status where variable_name = "WSREP_CLUSTER_SIZE";', 'Number of nodes in the cluster', 'LineChart', 0, 'SQL', 1, 30, null);
insert into Monitor (SystemType, Monitor, Name, Decimals, SQL, Description, ChartType, delta, MonitorType, SystemAverage, Interval, Unit) values ('galera', 'reppaused', 'Replication Paused', 2, 'select variable_value * 100 from global_status where variable_name = "WSREP_FLOW_CONTROL_PAUSED";', 'Percentage of time for which replication was paused', 'LineChart', 0, 'SQL', 1, 30, '%');
insert into Monitor (SystemType, Monitor, Name, Decimals, SQL, Description, ChartType, delta, MonitorType, SystemAverage, Interval, Unit) values ('galera', 'parallelism', 'Parallelism', 0, 'select variable_value from global_status where variable_name = "WSREP_CERT_DEPS_DISTANCE";', 'Average No. of parallel transactions', 'LineChart', 0, 'SQL', 1, 30, null);
insert into Monitor (SystemType, Monitor, Name, Decimals, SQL, Description, ChartType, delta, MonitorType, SystemAverage, Interval, Unit) values ('galera', 'recvqueue', 'Avg Receive Queue', 0, 'select variable_value from global_status where variable_name = "WSREP_LOCAL_RECV_QUEUE_AVG";', 'Average receive queue length', 'LineChart', 0, 'SQL', 1, 30, null);
insert into Monitor (SystemType, Monitor, Name, Decimals, SQL, Description, ChartType, delta, MonitorType, SystemAverage, Interval, Unit) values ('galera', 'flowcontrol', 'Flow Controlled', 0, 'select variable_value from global_status where variable_name = "WSREP_FLOW_CONTROL_SENT";', 'Flow control messages sent', 'LineChart', 1, 'SQL', 0, 30, null);
insert into Monitor (SystemType, Monitor, Name, Decimals, SQL, Description, ChartType, delta, MonitorType, SystemAverage, Interval, Unit) values ('galera', 'sendqueue', 'Avg Send Queue', 0, 'select variable_value from global_status where variable_name = "WSREP_LOCAL_SEND_QUEUE_AVG";', 'Average length of send queue', 'LineChart', 0, 'SQL', 1, 30, null);

create table User (
	UserID		integer PRIMARY KEY AUTOINCREMENT,
	UserName	varchar(40),
	Name		varchar (100),
	Password	varchar(60)
);
create unique index UserNameIDX on User (UserName);

create table UserProperties (
	UserName	varchar(40),						/* UserName of user whose property it is */ 
	Property	varchar(40),						/* Name of property */
	Updated		datetime,							/* Date/time stamp for last updated */
	Value		text								/* Value of property */
);
create unique index UserPropertyIDX on UserProperties (UserName, Property);

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
	BackupID	integer PRIMARY KEY AUTOINCREMENT,		/* Unique identifier for the backup within System ID */
	SystemID	int,									/* System backup was taken on */
	NodeID		int,									/* Node backup was taken on */
	BackupLevel	smallint,								/* full=1 or incremental=2 backup */
	ParentID	int,									/* Parent from which this is incremental */
	State		varchar(20),							/* Backup state */
	Started		datetime,								/* Date when backup was started */
	Updated		datetime,								/* Date of last update of this record during backup */
	Restored	datetime,								/* Date of last restore from this backup */
	Size		int,									/* Size of backup */
	BackupURL	text,									/* URL fro backup storage location */
	BinLog		text,									/* Binlog of backup */
	Log			text									/* URL to Log of backup */
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
