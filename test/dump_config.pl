#!/usr/bin/perl

use strict;
use Config::IniFiles;

my $config = '/etc/blabgen/config.ini';
$config = '../conf/config.ini' if -r '../conf/config.ini'; # for debug
$config = 'conf/config.ini' if -r 'conf/config.ini'; # for debug

my $local_conf = '/etc/blabgen/local.ini';
$local_conf = '../conf/local.ini'
	if -r '../conf/local.ini'; # for debug
$local_conf = 'conf/local.ini' if -r 'conf/local.ini'; # for debug

my $config_obj = new Config::IniFiles(-file => $config);
die "no config" unless $config_obj;
if (-r $local_conf) {
	$config_obj = new Config::IniFiles(-file => $local_conf,
		-import => $config_obj);
}

for my $s (sort $config_obj->Sections) {
	for my $p (sort $config_obj->Parameters($s)) {
		print "$s\t$p\t", $config_obj->val($s, $p), "\n";
	}
}
