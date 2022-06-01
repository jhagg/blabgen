<?php

/**
 * Moves camera.
 *
 * Sends a relative tilt command to the PTZ URL of the camera:
 *
 */

require __DIR__ . '/../bootstrap.php';

$url = conf('cam.ptz_url');

$value = '0,0';
$timestamp = time() * 1000;

if ( request_method() != 'post' ) {
	http_error( 405, 'Only POST allowed.' );
}

if ( empty( $_POST['value'] ) ) {
	http_error( 400, "Need 'value'." );
}

$value = $_POST['value'];

// use curl for http request
$cmd = '/usr/bin/curl ';
if (conf('cam.options')) {
	$cmd .= conf('cam.options').' ';
}
$cmd .= '-i -u %s:%s "%s"';

$qry = http_build_query( array(
	'camera' => 1,
	'rtilt' => $value,
));
$url = "$url?$qry";
$cmd1 = sprintf( $cmd, conf('cam.username'), conf('cam.password'), $url );

syslog(LOG_INFO, $cmd1);
system( $cmd1 );
