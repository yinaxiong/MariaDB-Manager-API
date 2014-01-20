MariaDB-Manager-API
===================

The MariaDB Manager API is a component of the MariaDB Manager system created by SkySQL, it provides a REST API for controlling and monitoring a set of machines running the MariaDB/Galera Cluster. Management facilities include the ability to start and stop nodes in the clusters, remove nodes from the cluster for maintenance, create backups of the data in the clsuter and provision new nodes into the cluster.

The MariaDB-Manager-API works in conjunction with other packages in the MariaDB Manager family

MariaDB-Manager-Monitor     A monitoring solution that collects data from MariaDB servers and feeds it into the MariaDB-Manger-API
MariaDB-Manager-WebUI       A web based user interface based on the MariaDB-Manager-API
MariaDB-Manager-GREX        A set of utilities that are installed on each node in the cluster to provide management functionality
MariaDB-Manager             The overall packaging project that encapsualtes all of the MariaDB-Manager functionality
