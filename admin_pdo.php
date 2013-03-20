<?php

	define('ADMIN_DB', 'sqlite:/usr/local/skysql/SQLite/SQLite/AdminConsole/admin');

	$PDO_admin = new PDO(ADMIN_DB);

	$query = "SELECT * FROM Node ORDER BY SystemId";


	$nodes = $PDO_admin->query($query);
	$nodes_rows = $nodes->fetchAll(PDO::FETCH_OBJ);

	echo json_encode($nodes_rows);


	$PDO_admin = NULL;

?>
