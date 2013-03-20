<?php

	define('ADMIN_DB', 'sqlite:/usr/local/skysql/SQLite/SQLite/AdminConsole/admin');

	$PDO_admin = new PDO(ADMIN_DB);

	$query = "UPDATE Node SET state=" . $_GET['status'] . " WHERE NodeName='" . $_GET['node'] . "' AND SystemID=" . $_GET['system_id'];

	echo $query . "\n";

	$nodes = $PDO_admin->exec($query);

	echo "AA[";
	echo $PDO_admin->errorCode();
	print_r($PDO_admin->errorInfo());
	echo "]AA\n";


	echo json_encode(array('status' => $_GET['status'], 'hostname' => $_GET['node'], 'system_id' => $_GET['system_id']));


	$PDO_admin = NULL;

?>
