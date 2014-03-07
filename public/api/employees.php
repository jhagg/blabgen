<?php

/**
 * Resource to GET list of employees.
 */

require __DIR__ . '/../bootstrap.php';

/** Returns employees as array. */
function get_employees( $opts ) {
	$db = db_connect();

	$opts += array(
		'limit' => -1,
		'offset' => 0,
		'order' => 'asc',
		'order_by' => 'first_name',
	);

	$sql = 'SELECT uname username, name first_name, surname last_name '.
		'FROM '.conf('db.employees_table');

	$employees = array();

	if ( $result = $db->query( $sql ) ) {
		while ( $row = $result->fetch_assoc() ) {
			$employees[] = $row;
		}
		$result->free();
	}

	return $employees;
}

if ( request_method() != 'get' ) {
	http_error( 405, 'Only GET allowed.' );
}

$employees = get_employees( $_GET );

content_type( 'application/json' );
echo json_encode( $employees );
