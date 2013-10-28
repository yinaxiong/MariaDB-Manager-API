/* The Monitor Database is solely for monitoring data
** 
** Contains the single table MonitorData
**
**/

create table MonitorData (
	MonitorID	int,		/* ID number for monitor class */
	SystemID	int,		/* System ID for observation */
	NodeID		int,		/* Node ID for observation, zero if system observation */
	Value		int,		/* Value for the observation */
	Stamp		int,		/* Date/Time this value was observed, unix time */
	Repeats		int			/* Number of repeated observations same value */
);
CREATE INDEX MonitorDataStampIDX ON MonitorData (Stamp);

create table LatestMonitorData (
	MonitorID	int,		/* ID number for monitor class */
	SystemID	int,		/* System ID for observation */
	NodeID		int,		/* Node ID for observation, zero if system observation */
	Value		int,		/* Value for the observation */
	Stamp		int,		/* Date/Time this value was observed, unix time */
	Repeats		int			/* Number of repeated observations same value */
);
