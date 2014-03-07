<?php

/**
 * Moves camera.
 *
 * Sends two commands to the PTZ URL of the camera:
 *
 * 1. Move camera in desired direction.
 *
 * Followed immediately by a second command:
 *
 * 2. Stop movement.
 *  
 * Not a pretty solution. 
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
$cmd = '/usr/bin/curl -i -u %s:%s "%s"';

$qry = http_build_query( array(
	'camera' => 1,
	'continuouspantiltmove' => $value,
));
$url = "$url?$qry";
$cmd1 = sprintf( $cmd, conf('cam.username'), conf('cam.password'), $url );

$qry = http_build_query( array(
	'camera' => 1,
	'continuouspantiltmove' => '0,0',
));
$url = "$url?$qry";
$cmd2 = sprintf( $cmd, conf('cam.username'), conf('cam.password'), $url );

system( $cmd1 );
system( $cmd2 );
