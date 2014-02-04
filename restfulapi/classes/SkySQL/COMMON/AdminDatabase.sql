/* Recently added changes requiring DB upgrade:
** 
** Added Decimals to Monitor table - default 0
**
**/

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

insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('connect', 'provision', 'created', 'Set up communications to node', 1, 'setup-ssh, install-agent');
insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('connect', 'provision', 'unconnected', 'Set up communications to node', 1, 'setup-ssh, install-agent');
insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('probe', 'provision', 'connected', 'Probe node to determine software configuration', 1, 'probe');
insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('probe', 'provision', 'incompatible', 'Probe node to determine software configuration', 1, 'probe');
insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('provision', 'provision', 'unprovisioned', 'Install a database on the node', 1, 'install-packages, firewall-setup, configure');


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
insert into NodeCommands (Command, SystemType, State, Description, UIOrder, Steps) values ('restore', 'galera', 'joined', 'Restore Joined Node', 3, 'isolate,restore,stop,start');
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

create table POE (
	uniqid		varchar(100) PRIMARY KEY,				/* Unique ID for Post Once Exactly */
	stamp		datetime							/* Time stamp */
)
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

/* AWS Monitors */
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'aws','connections','Connections','select variable_value from global_status where variable_name = "THREADS_CONNECTED";','','0',NULL,'LineChart','0','SQL','0','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'aws','traffic','Network Traffic','select round(sum(variable_value) / 1024) from global_status where variable_name in ("BYTES_RECEIVED", "BYTES_SENT");','','0',NULL,'LineChart','1','SQL','0','30','kB/min');
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'aws','availability','Availability','select 100;','','2',NULL,'LineChart','0','SQL','1','30','%');
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'aws','nodestate','Node State','crm status bynode','','0',NULL,NULL,'0','CRM','0','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'aws','capacity','Capacity','select round(((select variable_value from global_status where variable_name = "THREADS_CONNECTED") * 100) / variable_value) from global_variables where variable_name = "MAX_CONNECTIONS";','','2',NULL,NULL,'0','SQL','1','30','%');
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'aws','hoststate','Host State','','','0',NULL,NULL,'0','PING','0','30',NULL);

/* Galera Monitors */
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','nodestate','Node State','','The State of the node','0','',NULL,'0','GALERA_STATUS','0','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','ping','Ping','','Whether node can be accessed','0','',NULL,'0','PING','0','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','clustersize','Cluster Size','WSREP_CLUSTER_SIZE','The number of nodes in the cluster','0','',NULL,'0','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','reppaused','Replication Paused','Number(globals.getStatus("WSREP_FLOW_CONTROL_PAUSED")) * 100','Percentage of time for which replication was paused','2','','LineChart','0','JS','1','30','%');
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','parallelism','Parallelism','WSREP_CERT_DEPS_DISTANCE','Average No. of parallel transactions','0','','LineChart','0','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','recvqueue','Avg. Receive Queue','WSREP_LOCAL_RECV_QUEUE_AVG','Average receive queue length','1','','LineChart','0','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','flowcontrol','Flow Controlled','WSREP_FLOW_CONTROL_SENT','Flow control messages sent','0','','LineChart','0','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','sendqueue','Avg. Send Queue','WSREP_LOCAL_SEND_QUEUE_AVG','Average send queue length','1','','LineChart','0','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','failed_writesets','Failed Writesets','WSREP_LOCAL_CERT_FAILURES','No. of writesets with failed certification','1','','LineChart','1','GLOBAL','0','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','aborted_transactions','Aborted Transactions','WSREP_LOCAL_BF_ABORTS','No. of aborted local transactons','1','','LineChart','1','GLOBAL','0','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','capacity','Capacity','(Number(globals.getStatus("THREADS_CONNECTED")) * 100) / Number(globals.getVariable("MAX_CONNECTIONS"))','Percentage threads in use','0','',NULL,'0','JS','0','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','connections','Connections','THREADS_CONNECTED','No. of user connections','0','','LineChart','0','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','traffic','Network Traffic','(Number(globals.getStatus("BYTES_RECEIVED")) + Number(globals.getStatus("BYTES_SENT"))) / 512','Network Traffic','0','','LineChart','1','JS','1','30','Kb/min');
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','availability','Availability','select 100','Node Availability','0','','LineChart','0','SQL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_admin','Admin Commands','COM_ADMIN_COMMANDS','No of admin command','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_assign_keycache','Assigns to keycache','COM_ASSIGN_TO_KEYCACHE','Assignments to the keycache','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_alter_db','Alter Database','COM_ALTER_DB','No. of alter database commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_alter_db_upgrade','Alter Database Upgrade','COM_ALTER_DB_UPGRADE','No. of alter database upgrade commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_alter_event','Alter Event','COM_ALTER_EVENT','No. of alter event commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_alter_function','Alter Function','COM_ALTER_FUNCTION','No. of alter function commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_alter_procedure','Alter Procedure','COM_ALTER_PROCEDURE','No. of alter procedure commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_alter_server','Alter Server','COM_ALTER_SERVER','No. of alter server commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_alter_table','Alter Table','COM_ALTER_TABLE','No. of alter table commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_alter_tablespace','Alter Tablespace','COM_ALTER_TABLESPACE','No. of alter tablespace commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_analyze','Analyze','COM_ANALYZE','No. of Analyze commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_begin','Begin','COM_BEGIN','No. of explicit transactions started','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_binlog','Binlog','COM_BINLOG','No. of binlog commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_call_procedure','Call Procedure','COM_CALL_PROCEDURE','No. of stored procedure calls','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_change_db','Change Database','COM_CHANGE_DB','No. of change database commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_change_master','Change Master','COM_CHANGE_MASTER','No. of replication master changes','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_check','Check commands','COM_CHECK','No. of check commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_checksum','Checksum Commands','COM_CHECKSUM','No. of checksum commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_commit','Commits','COM_COMMIT','No. of transaction commits','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_create_db','Database Creations','COM_CREATE_DB','No. of database creations','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_create_event','Create Event','COM_CREATE_EVENT','No. of create event commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_create_function','Create Function','COM_CREATE_FUNCTION','No. of create function commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_create_index','Create Index','COM_CREATE_INDEX','No. of indexes created','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_create_procedure','Create Procedure','COM_CREATE_PROCEDURE','No. of procedures created','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_create_server','Create Server','COM_CREATE_SERVER','No. of create server commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_create_table','Create Table','COM_CREATE_TABLE','No. of tables created','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_create_trigger','Create Trigger','COM_CREATE_TRIGGER','No. of triggers created','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_create_udf','Create UDF','COM_CREATE_UDF','No. of user defined functions created','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_create_user','Create User','COM_CREATE_USER','No. of new users created','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_create_view','Create View','COM_CREATE_VIEW','No. of veiws created','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_dealloc_sql','Dealloc Sql','COM_DEALLOC_SQL','No. of QL dealloc commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_delete','Delete ','COM_DELETE','No. of delete statements executed','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_delete_multi','Delete Multiple','COM_DELETE_MULTI','No. of multiple delete statements','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_do','Do','COM_DO','No. of do commands executed','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_drop_db','Drop Database','COM_DROP_DB','No. of drop database commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_drop_event','Drop Event','COM_DROP_EVENT','No. of drop event commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_drop_function','Drop Function','COM_DROP_FUNCTION','No. of functions dropped','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_drop_index','Drop Index','COM_DROP_INDEX','No. of indexes dropped','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_drop_procedure','Drop Procedure','COM_DROP_PROCEDURE','No. of procedures dropped','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_drop_server','Drop Server','COM_DROP_SERVER','No. of servers dropped','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_drop_table','Drop Table','COM_DROP_TABLE','No. of tables dropped','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_drop_trigger','Drop Trigger','COM_DROP_TRIGGER','No. of triggers dropped','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_drop_user','Drop User','COM_DROP_USER','No. of users dropped','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_drop_view','Drop View','COM_DROP_VIEW','No. of views dropped','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_empty_query','Empty Queries','COM_EMPTY_QUERY','No. of empty queries','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_execute_sql','Execute SQL','COM_EXECUTE_SQL','No. of execute SQL statements','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_flush','Flush','COM_FLUSH','No. of flush commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_grant','Grants','COM_GRANT','No. of grants executed','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_ha_close','HA Close','COM_HA_CLOSE','No. of HA close commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_ha_open','HA Open','COM_HA_OPEN','No. of HA open commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_ha_read','HA Read','COM_HA_READ','No. of HA read commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_help','Help','COM_HELP','No. of help commands executed','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_insert','Inserts','COM_INSERT','No. of insert statements','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_insert_select','Insert Selects','COM_INSERT_SELECT','No. of insert ... select statements','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_install_plugin','Install Plugin','COM_INSTALL_PLUGIN','No. of installations of plugins','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_kill','Kill Commands','COM_KILL','No. of kill commands executed','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_load','Load Commands','COM_LOAD','No. of load commands executed','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_lock_tables','Lock Tables','COM_LOCK_TABLES','No. of explicit lock table commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_optimize','Optimize Commands','COM_OPTIMIZE','No. of optimize commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_preload_keys','Preload Keys','COM_PRELOAD_KEYS','No. of preload key commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_prepare_sql','Prepare SQL','COM_PREPARE_SQL','No. of prepared SQL commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_purge','Purge Commands','COM_PURGE','No. of purge commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_purge_before_date','Purge Before Commands','COM_PURGE_BEFORE_DATE','No, of purge before date commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_release_savepoint','Release Savepoint','COM_RELEASE_SAVEPOINT','No of release savepoint commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_rename_table','Rename Table','COM_RENAME_TABLE','No. of rename table commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_rename_user','Rename User','COM_RENAME_USER','No. of rename user commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_repair','Repair','COM_REPAIR','No. of repair commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_replace','Replace','COM_REPLACE','No. of replace statements','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_replace_select','Replace Select','COM_REPLACE_SELECT','No. of replace select statements','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_reset','Reset Commands','COM_RESET','No. of reset commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_resignal','Resignal Commands','COM_RESIGNAL','No. of resignal commands executed','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_revoke','Revokes','COM_REVOKE','No. of revoke statements executed','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_revoke_all','Revoke All','COM_REVOKE_ALL','No. of revoke all statements executed','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_rollback','Transaction Rollbacks','COM_ROLLBACK','No. of explicit transaction rollbacks','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_rollback_to_savepoint','Rollback to savepoint','COM_ROLLBACK_TO_SAVEPOINT','No. of rollbacks to a transaction savepoint','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_savepoint','Savepoint','COM_SAVEPOINT','No. of savepoint commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_select','Select Statements','COM_SELECT','No. of select statements','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_set_option','Set Option','COM_SET_OPTION','No. of set option statements','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_signal','Signal','COM_SIGNAL','No. of signal statements','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_authors','Show Authors','COM_SHOW_AUTHORS','No. of show authors commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_binlog_events','Show Binlog Events','COM_SHOW_BINLOG_EVENTS','No. of show binlog events','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_binlogs','Show Binlogs','COM_SHOW_BINLOGS','No. of show binlogs commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_charsets','Show Charsets','COM_SHOW_CHARSETS','No. of show charsets commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_collations','Show Collations','COM_SHOW_COLLATIONS','No. of show collations commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_contributors','Show Contributors','COM_SHOW_CONTRIBUTORS','No. of show Contributors commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_create_db','Show Create Database','COM_SHOW_CREATE_DB','No. of show create database commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_create_event','Show Create Event','COM_SHOW_CREATE_EVENT','No. of show create event commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_create_func','Show Create Function','COM_SHOW_CREATE_FUNC','No. of show create function commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_create_proc','Show Create Procedure','COM_SHOW_CREATE_PROC','No. of show create procedure commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_create_table','Show Create Table','COM_SHOW_CREATE_TABLE','No. of show create table commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_create_trigger','Show Create Trigger','COM_SHOW_CREATE_TRIGGER','No. of show create trigger commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_databases','Show Create Databases','COM_SHOW_DATABASES','Show Create Databases','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_engine_logs','Show Engine Logs','COM_SHOW_ENGINE_LOGS','No. of show engine logs','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_engine_mutex','Show Engine Mutex','COM_SHOW_ENGINE_MUTEX','No. of show engine mutex commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_engine_status','Show Engine Status','COM_SHOW_ENGINE_STATUS','No. of show engine status commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_events','Show Events','COM_SHOW_EVENTS','No. of show events commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_errors','Show Errors','COM_SHOW_ERRORS','No. of show errors commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_fields','Show Fields','COM_SHOW_FIELDS','No. of show fields commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_function_status','Show Function Status','COM_SHOW_FUNCTION_STATUS','No. of show function status commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_grants','Show Grants','COM_SHOW_GRANTS','No. of show grants commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_keys','Show Keys','COM_SHOW_KEYS','No. of show keys commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_master_status','Show Master Status','COM_SHOW_MASTER_STATUS','No. of show master status commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_open_tables','Show Open Tables','COM_SHOW_OPEN_TABLES','No. of show open tables commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_plugins','Show Plugins','COM_SHOW_PLUGINS','No, of show plugins commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_privileges','Show Privileges','COM_SHOW_PRIVILEGES','No. of show privileges commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_procedure_status','Show Procedure Status','COM_SHOW_PROCEDURE_STATUS','No. of show procedure status commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_processlist','Show Processlist','COM_SHOW_PROCESSLIST','No. of show processlist commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_profile','Show Profile','COM_SHOW_PROFILE','No. of show profile commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_profiles','Show Profiles','COM_SHOW_PROFILES','No. of show profiles commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_relaylog_events','Show Relay Log Events','COM_SHOW_RELAYLOG_EVENTS','No. of show relay log events commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_slave_hosts','Show Slave Hosts','COM_SHOW_SLAVE_HOSTS','No. of show slave hosts commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_slave_status','Show Slave Status','COM_SHOW_SLAVE_STATUS','No. of show slave status commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_status','Show Status','COM_SHOW_STATUS','No. of show status commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_storage_engines','Show Storage Engines','COM_SHOW_STORAGE_ENGINES','No. of show storage engines commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_table_status','Show Table Status','COM_SHOW_TABLE_STATUS','No. of show table status commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_tables','Show Tables','COM_SHOW_TABLES','No. of show tables commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_triggers','Show Triggers','COM_SHOW_TRIGGERS','No. of show triggers commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_variables','Show Variables','COM_SHOW_VARIABLES','No. of show variables commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_show_warnings','Show warnings','COM_SHOW_WARNINGS','No. of show warnings commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_slave_start','Slave Starts','COM_SLAVE_START','No. of slave starts','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_slave_stop','Slave Stops','COM_SLAVE_STOP','No. of slave stops','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_stmt_close','Statement Close','COM_STMT_CLOSE','No. of statement close statements','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_stmt_execute','Statement Execute','COM_STMT_EXECUTE','No. of statement execute commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_stmt_fetch','Statement Fetch','COM_STMT_FETCH','No. of statement fetch statements','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_stmt_prepare','Statement Prepare','COM_STMT_PREPARE','No. of statement prepares','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_stmt_reprepare','Statement Reprepare','COM_STMT_REPREPARE','No. of statement reprepare commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_stmt_reset','Statement Reset','COM_STMT_RESET','No. of statement reset statements','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_stmt_send_long_data','Statement Send Long Data','COM_STMT_SEND_LONG_DATA','No. of statement send long data comands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_truncate','Statement Truncate','COM_TRUNCATE','No. of table truncations','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_uninstall_plugin','Statement Uninstall Plugins','COM_UNINSTALL_PLUGIN','No. of uninstall plugin commands','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_unlock_tables','Unlock Table','COM_UNLOCK_TABLES','No. of explicit table unlocks','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_update','Updates','COM_UPDATE','No. of update statements','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_update_multi','Multiple Updates','COM_UPDATE_MULTI','No. of multiple update statements','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_xa_commit','Distributed Commits','COM_XA_COMMIT','No. of XA distribute commits','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_xa_end','Distributed XA End','COM_XA_END','No. of XA end statements executed','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_xa_prepare','Distributed XA Prepares','COM_XA_PREPARE','No. of XA prepare statements','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_xa_recover','Distributed XA Recover','COM_XA_RECOVER','No. of XA recover statements','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_xa_rollback','Distributed Rollbacks','COM_XA_ROLLBACK','No. of XA transaction rollbacks','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','com_xa_start','Distributer XA Starts','COM_XA_START','No. of XA transaction starts','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_buffer_pool_pages_data','InnoDB Buffer Pool Data','INNODB_BUFFER_POOL_PAGES_DATA','No. of dirty, clean and index pages in the buffer pool','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_buffer_pool_pages_dirty','InnoDB Buffer Pool Dirty','INNODB_BUFFER_POOL_PAGES_DIRTY','No. of dirty pages in the InnoDB buffer pool','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_buffer_pool_pages_flushed','InnoDB Buffer Pool Flushes','INNODB_BUFFER_POOL_PAGES_FLUSHED','No. of page flushes in the InnoDB buffer pool','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_buffer_pool_pages_free','InnoDB Buffer Pool Free','INNODB_BUFFER_POOL_PAGES_FREE','No. of free pages in the InnoDB buffer pool','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_buffer_pool_pages_misc','InnoDB Buffer Pool Misc','INNODB_BUFFER_POOL_PAGES_MISC','No. of pages in buffer pool allocated to admin tasks','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_buffer_pool_pages_total','InnoDB Buffer Pool Total','INNODB_BUFFER_POOL_PAGES_TOTAL','No. of buffers in the InnoDB buffer pool','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_buffer_pool_read_ahead','InnoDB Buffer Read Aheads','INNODB_BUFFER_POOL_READ_AHEAD','No. of buffers read into InnoDB during readahead','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_buffer_pool_read_ahead_evicted','InnoDB Read Ahead Evictions','INNODB_BUFFER_POOL_READ_AHEAD_EVICTED','No. of readahead InnoDB buffers evicted from the buffer pool','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_buffer_pool_read_requests','InnoDB Buffer Logical Reads','INNODB_BUFFER_POOL_READ_REQUESTS','No. of logical reads of InnoDB buffers','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_buffer_pool_reads','InnoDB Buffer Reads','INNODB_BUFFER_POOL_READS','No. of InnoDB buffer reads not satisfied from the pool','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_buffer_pool_wait_free','InnoDB Buffer Waits','INNODB_BUFFER_POOL_WAIT_FREE','No. of waits for free InnoDB buffer','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_buffer_pool_write_requests','InnDB Buffer Writes','INNODB_BUFFER_POOL_WRITE_REQUESTS','No. of write requests for innoDB buffers','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_buffer_pool_hit_ratio','InnoDB Buffer Pool Hit Ratio','Number(globals.getStatus("INNODB_BUFFER_POOL_READ_REQUESTS")) / (Number(globals.getStatus("INNODB_BUFFER_POOL_READ_REQUESTS")) + Number(globals.getStatus("INNODB_BUFFER_POOL_READS"))) * 100','Hit ratio for the InnoDB Buffer Pool','2','','LineChart','0','JS','1','30','%');
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_data_fsyncs','InnoDB Fsyncs','INNODB_DATA_FSYNCS','No. of fsync calls made by InnoDB','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_data_pending_fsyncs','InnoDB Pending Fsyncs','INNODB_DATA_PENDING_FSYNCS','No. of pending fsync operations for InnoDB','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_data_pending_reads','InnoDB Pending Reads','INNODB_DATA_PENDING_READS','No. of pending read requests in InnoDB','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_data_pending_writes','InnoDB Pending Writes','INNODB_DATA_PENDING_WRITES','No. of pending write requests for InnoDB','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_data_read','InnoDB Read','INNODB_DATA_READ','Amount of data read by InnoDB','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_data_reads','InnoDB Reads','INNODB_DATA_READS','No. of InnoDB read requests','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_data_writes','InnoDB Writes','INNODB_DATA_WRITES','No. of InnoDB write requests','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_data_written','InnoDB Writen','INNODB_DATA_WRITTEN','Amount of data written by InnoDB','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_dblwr_pages_written','InnoDB Pages Double Writen','INNODB_DBLWR_PAGES_WRITTEN','No. of pages written by double writes','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_dblwr_writes','InnoDB Double Writes','INNODB_DBLWR_WRITES','No. of double write operations performed by InnoDB','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_log_waits','InnoDB Log Waits','INNODB_LOG_WAITS','No. of times log buffer was too small','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_log_write_requests','InnoDB Log Write Requests','INNODB_LOG_WRITE_REQUESTS','No. of log write requests','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_log_writes','InnoDB Log Writes','INNODB_LOG_WRITES','No. of physical log writes','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_os_log_fsyncs','InnoDB Log Fsyncs','INNODB_OS_LOG_FSYNCS','No. of fsync calls made on the log','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_os_log_pending_fsyncs','InnoDB Pending Log Fsyncs','INNODB_OS_LOG_PENDING_FSYNCS','No. of pending fsync calls on the log','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_os_log_pending_writes','InnoDB Log Pending Writes','INNODB_OS_LOG_PENDING_WRITES','No. of pending writes on the InnoDB log','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_os_log_written','InnoDB Log Bytes Written','INNODB_OS_LOG_WRITTEN','No. of bytes written to the InnoDB log','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_pages_created','InnoDB Pages','INNODB_PAGES_CREATED','No. of InnoDB pages created','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_pages_read','InnoDB Pages Read','INNODB_PAGES_READ','No of InnoDB pages read','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_pages_written','InnoDB Pages Written','INNODB_PAGES_WRITTEN','No. of InnoDB pages written','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_row_lock_current_waits','InnoDB Waiting Row Locks','INNODB_ROW_LOCK_CURRENT_WAITS','No. of row locks currently waiting','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_row_lock_time','InnoDB Total Lock Wait','INNODB_ROW_LOCK_TIME','Total lock wait time','0','',NULL,'1','GLOBAL','1','30','ms');
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_row_lock_time_avg','InnoDB Avg. Row Lock','INNODB_ROW_LOCK_TIME_AVG','Average time to acquire a row lock','0','','LineChart','0','GLOBAL','1','30','ms');
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_row_lock_time_max','InnoDB Max Row Lock','INNODB_ROW_LOCK_TIME_MAX','Maximum time to acquire a row lock','0','',NULL,'1','GLOBAL','1','30','ms');
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_row_lock_waits','InnoDB Row Lock Waits','INNODB_ROW_LOCK_WAITS','No. of times a row lock has been waited for','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_rows_deleted','InnoDB Row Deletions','INNODB_ROWS_DELETED','No. of rows deleted in InnoDB','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_rows_inserted','InnoDB Row Insertions','INNODB_ROWS_INSERTED','No. of rows inserted in InnoDB','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_rows_read','InnoDB Row Read','INNODB_ROWS_READ','No. of rows read in InnoDB','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','innodb_rows_updated','InnoDB Row Updated','INNODB_ROWS_UPDATED','No. of rows updated in InnoDB','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','key_blocks_not_flushed','Key Blocks Not Flushed','KEY_BLOCKS_NOT_FLUSHED','No. of Key blocks requiring flushing','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','key_blocks_unused','Key Blocks Unused','KEY_BLOCKS_UNUSED','No. of unsused blocks in the key cache','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','key_blocks_used','Key Blocks Used','KEY_BLOCKS_USED','No. of used blocks in the key cache','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','key_read_requests','Key Block Read Requests','KEY_READ_REQUESTS','No. of logical key block read requests','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','key_reads','Key Reads','KEY_READS','No. of physical key reads ','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','key_write_requests','Key Write Requests','KEY_WRITE_REQUESTS','No. of logical key writes','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','key_writes','Key Writes','KEY_WRITES','Number of physical key writes','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','open_files','Open Files','OPEN_FILES','No. of open files','0','',NULL,'0','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','open_streams','Open Streams','OPEN_STREAMS','No. of open streams','0','',NULL,'0','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','open_table_definitions','Open Table Definitions','OPEN_TABLE_DEFINITIONS','No. of open table definitions','0','',NULL,'0','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','open_tables','Open Tables','OPEN_TABLES','No. of open tables','0','',NULL,'0','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','select_full_join','Select Full Joins','SELECT_FULL_JOIN','No. of full joins in a select','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','select_full_range_join','Range Joins','SELECT_FULL_RANGE_JOIN','No. of range joins in a select','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','select_range','Select Range','SELECT_RANGE','No. of joins that used ranges','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','select_range_check','Select Range Check','SELECT_RANGE_CHECK','No. of joins without keys','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','select_scan','Select Scan','SELECT_SCAN','No. of joins requiring a full scan','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','table_locks_immediate','Table Lock Immediate','TABLE_LOCKS_IMMEDIATE','No. of table locks granted imediately','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','table_locks_waited','Table Lock Waits','TABLE_LOCKS_WAITED','No. of table locks that had to wait','0','',NULL,'1','GLOBAL','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','DDL','DDL Statements','Number(globals.getStatus("COM_SELECT")) + Number(globals.getStatus("COM_INSERT")) + Number(globals.getStatus("COM_UPDATE"))','No. of DDL Statements','0','',NULL,'1','JS','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','DML','DML Statements','Number(globals.getStatus("COM_ALTER_TABLE")) + Number(globals.getStatus("COM_ALTER_DB")) + Number(globals.getStatus("COM_CREATE_TABLE")) + Number(globals.getStatus("COM_CREATE_DB"))','No. of DML Satements','0','',NULL,'1','JS','1','30',NULL);
INSERT INTO "Monitor" ("MonitorID","SystemType","Monitor","Name","SQL","Description","Decimals","Mapping","ChartType","delta","MonitorType","SystemAverage","Interval","Unit") VALUES (NULL,'galera','read_write_ratio','Percentage Of Read Statements','(Number(globals.getStatus("COM_SELECT")) * 100) / (Number(globals.getStatus("COM_SELECT")) + Number(globals.getStatus("COM_UPDATE")) + Number(globals.getStatus("COM_DELETE")))','Percentage of Read Satements','0','','LineChart','0','JS','1','30','%');

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

