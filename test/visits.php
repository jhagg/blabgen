#!/bin/php
<?php

/**
 * Tests the visit resource (/api/visit).
 */
$tests_ran;
$tests_passed = 0;

$blabgen_server = 'localhost';
if ( $argc ) {
	$blabgen_server = $argv[1];
}

echo 'Testing Blabgen @ http://' . $blabgen_server . "\n\n";

/**
 * Tests HTTP POST.
 */
function test_post( $test_name, $url, $data = array(),
	$expected_status_code = 200, $expected_body = null ) {
	global $tests_ran, $tests_passed;

	echo $url;
	if ( @$data['add_picture'] ) {
		$picture_url = take_picture();
		$data['picture'] = $picture_url;
		unset( $data['add_picture'] );
	}

	$c = curl_init();

	curl_setopt( $c, CURLOPT_URL, $url );
	curl_setopt( $c, CURLOPT_POST, 1 );
	curl_setopt( $c, CURLOPT_POSTFIELDS, $data );
	curl_setopt( $c, CURLOPT_RETURNTRANSFER, 1 );

	$s = curl_exec( $c );
	$code = curl_getinfo( $c, CURLINFO_HTTP_CODE );

	curl_close( $c );

	$tests_ran++;
	$stat = 'OK';
	if ( $code != $expected_status_code ) {
		$stat = sprintf( "ERROR: Expected status code '%s' but got '%s': %s\n",
			$expected_status_code, $code, $s );
	} else {
		$tests_passed++;
	}
	printf( "\n%s\n  %s\n", $test_name, $stat );
}

function take_picture() {
	global $blabgen_server;
	$url = 'http://' . $blabgen_server . '/api/pictures.php';

	$c = curl_init();

	curl_setopt( $c, CURLOPT_URL, $url );
	curl_setopt( $c, CURLOPT_POST, 1 );
	curl_setopt( $c, CURLOPT_POSTFIELDS, array());
	curl_setopt( $c, CURLOPT_RETURNTRANSFER, 1 );
	curl_setopt( $c, CURLOPT_HEADER, 1 );

	$s = curl_exec( $c );
	$code = curl_getinfo( $c, CURLINFO_HTTP_CODE );

	curl_close( $c );

	if ( $code != 201 ) {
		printf( "ERROR: Could not take picture: %s", $s );
		return false;
	}

	$ms;
	if ( preg_match( '/Location: (.+)/', $s, $ms ) ) {
		return $ms[1];
	}
}

/**
 * Prints test summary.
 */
function print_summary() {
	global $tests_ran, $tests_passed;

	printf( "\n---------------------------------\n\nSummary:\n Total tests: %s\n Passed: %s\n Failed: %s\n",
		$tests_ran, $tests_passed, $tests_ran - $tests_passed );
}

####

$url = 'http://' . $blabgen_server . '/api/visits.php';

# trivial negatives first
$d = array(
);
test_post( 'Missing all', $url, $d, 400 );

$d['name'] = 'David';
test_post( 'Missing start_date, end_date, receivers & picture', $url, $d, 400 );

$d['start_date'] = 0;
test_post( 'Missing end_date, receivers & picture', $url, $d, 400 );

$d['end_date'] = 0;
test_post( 'Missing receivers & picture', $url, $d, 400 );

$d['receivers'] = 'davidho';
test_post( 'Missing picture', $url, $d, 400 );

# then simplest possible valid query
$d['add_picture'] = true;
test_post( 'Minimum required', $url, $d, 200 );

$d['company'] = 'company';
test_post( 'Including company', $url, $d, 200 );

# different date formats for start and end date
$d['start_date'] = '2011-08-30';
$d['end_date'] = '2011-08-31';
test_post( 'Y-M-D start & end dates', $url, $d, 200 );

$d['start_date'] = '2011-08-30 10:00';
$d['end_date'] = '2011-08-30 10:00';
test_post( 'Y-M-D H:M start & end dates', $url, $d, 200 );

$d['start_date'] = 'today';
$d['end_date'] = 'tomorrow';
test_post( 'Relative start & end dates', $url, $d, 200 );

# invalid start & end dates
$d['start_date'] = '2011-08-30';
$d['end_date'] = '2011-08-29';
test_post( 'End date < start date', $url, $d, 400 );

$d['start_date'] = '2011-08-30 10:00';
$d['end_date'] = '2011-08-30 09:59';
test_post( 'End date < start date', $url, $d, 400 );

# multiple receivers + invalid receiver
$d['start_date'] = 'today';
$d['end_date'] = 'tomorrow';
$d['receivers'] = 'davidho,danielb';
test_post( 'Multiple receivers (2)', $url, $d, 200 );

$d['receivers'] = 'davidho,danielb,marinam';
test_post( 'Multiple receivers (3)', $url, $d, 200 );

$d['receivers'] = 'jdoe';
test_post( 'Invalid receiver', $url, $d, 400 );

$d['receivers'] = 'davidho,jdoe';
test_post( 'Valid + invalid receiver', $url, $d, 400 );

$d['receivers'] = 'jdoe,kdoe';
test_post( 'Multiple invalid receivers (2)', $url, $d, 400 );

print_summary();
