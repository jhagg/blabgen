<?php

/**
 * Takes a picture from the camera, stores it to disk and responds
 * with an URL of the taken picture.
 */

require __DIR__ . '/../bootstrap.php';

/**
 * Takes a picture and returns the resulting URL.
 */
function take_picture() {
	$picture_dir = conf('picture.tmp_dir');
	$curl_download_cmd = '/usr/bin/curl -s ';
	if (conf('cam.username')) {
		$curl_download_cmd .= '-u "'.
			conf('cam.username').':'.
			conf('cam.password').'" ';
	}
	$curl_download_cmd .= '"%s" > "%s"';
 
	$max_tries = 10;

	if ( !is_readable( $picture_dir ) ) {
		mkdir( $picture_dir );
	}

	if ( !is_readable( $picture_dir ) ) {
		throw new Http_error( 500,
			sprintf( 'Picture temp directory not readable: %s',
				$picture_dir ) );
	}

	$tries = 0;
	// attempt to generate a unique filename, trying max 10 times
	// if 10th attempt not ok, some other picture will be overwritten
	do {
		$fn = md5( microtime() . '.' .  mt_rand() ) . '.jpg';
		$fname = path_join( $picture_dir, $fn );
		$tries++;
	} while ( !is_readable( $fname ) && $tries < $max_tries );

	// download image from camera
	$cmd = sprintf( $curl_download_cmd, conf('cam.picture_url'), $fname );
	exec_cmd( $cmd );

	if ( !is_readable( $fname ) ) {
		throw new Http_error( 500, 'Failed to download picture file' );
	}

	$url = sprintf( conf('picture.tmp_url_template'), basename( $fname ) );

	return $url;
}

try {
	if ( request_method() != 'post' ) {
		throw new Http_error( 405, 'Only POST allowed.' );
	}

	// throws Http_error on fail
	$url = take_picture();
	// 201 Created
	header( get_reason_phrase( 201 ) );
	header( 'Location: ' . $url );
} catch ( Http_error $e ) {
	http_error( $e->status_code, $e->msg );
}
