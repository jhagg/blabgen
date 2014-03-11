#!/usr/bin/php
<?php

$opts_tmp = array();
$config = '/etc/blabgen/config.ini';
$dev_config = __DIR__.'/../conf/config.ini';
if (is_readable($dev_config)) {
	$config = $dev_config;
}
print "Reading $config\n";
$opts = parse_ini_file($config, 1);

# global config that is local
$config = '/etc/blabgen/local.ini';
$dev_config = __DIR__.'/../conf/local.ini';
if (is_readable($dev_config)) {
	$config = $dev_config;
}
print "Reading local $config\n";
if (is_readable($config)) {
	$opts_tmp = parse_ini_file($config, 1);
	$opts = array_replace_recursive($opts, $opts_tmp );
}

// some configuration depends on remote hostname
$hn = $argv[1];
$config = '/etc/blabgen/host-'.$hn.'.ini';
$dev_config = __DIR__.'/../conf/host-'.$hn.'.ini';
if (is_readable($dev_config)) {
	$config = $dev_config;
}
if (is_readable($config)) {
	print "Reading host $config\n";
	$opts_tmp = parse_ini_file($config, 1);
	$opts = array_replace_recursive($opts, $opts_tmp);
}

foreach($opts as $k => $p) {
	foreach($opts[$k] as $kp => $v) {
		print "$k\t$kp\t$v\n";
	}
}
	
