#!/usr/bin/perl

use lib '.';				# <VISITOR_LIB>
use vars qw($debug $verbose);
use strict;
use File::Path;
use Config::IniFiles;
use Log::Dlog qw(dconfess set_log dlog);
use Carp;
use POSIX;
use DBI;
use CGI qw(:standard);
use CGI::Carp;
use Sys::Syslog;

my $config = '/etc/blabgen/config.ini';
$config = '../conf/config.ini' if -r '../conf/config.ini'; # for debug
$config = 'conf/config.ini' if -r 'conf/config.ini'; # for debug
die "no config file" unless -r $config;

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
openlog('blabgen_show_picture_cgi', undef, cnf('gen.syslog_facility'));

my $dsn = 'DBI:mysql:database='.cnf('db.db').';host='.cnf('db.host').
	';port='.cnf('db.port');
my $dbh = DBI->connect($dsn, cnf('db.user'), cnf('db.pwd'));
err($DBI::errstr) unless $dbh;
$dbh->do('set character set utf8');

my $cgi = new CGI;

my $key = $cgi->param('key');

my $sth = $dbh->prepare("select id, webhost from pers where webkey = ?");

unless ($sth->execute($key)) {
    print $cgi->header();
    err($dbh->errstr);
}
my ($id, $hostname) = $sth->fetchrow_array();
unless ($id) {
    print $cgi->header();
    print pre("Picture not found, wrong key");
    exit 0;
}
my $subdir = substr($id, -1, 1);
my $path = sprintf(cnf('picture.dir')."/$subdir/$id.jpg", $hostname);
unless (open(IN, $path)) {
    print $cgi->header();
    print pre("Picture not found, probably too old");
    exit 0;
}
my $buf;
read(IN, $buf, -s $path);
close(IN);
print $cgi->header(-type => 'image/jpeg'), $buf;
exit 0;


sub err {
	print h1("Error: @_")."\n";
	syslog('err', join(', ', @_));
	exit 1;
}

sub cnf {
	my $id = shift;

	my ($sect, $key) = split(/\./, $id);
	my $v = $config_obj->val($sect, $key);
	$v =~ s/^'(.*)'$/\1/;
	print "$id = '$v'\n" if $verbose;
	$v;
}
