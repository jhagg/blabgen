#!/usr/bin/perl

use lib '.';				# <VISITOR_LIB>
use vars qw($debug $verbose);
use File::Path;
use Config::IniFiles;
use Log::Dlog qw(dconfess set_log dlog);
use Carp;
use POSIX;
use DBI;
use CGI qw(:standard);
use CGI::Carp;
use strict;

my $config = '/etc/blabgen/admin.ini';
$config = '../conf/admin.ini' if -r '../conf/admin.ini'; # for debug
$config = 'conf/admin.ini' if -r 'conf/admin.ini'; # for debug
die "no config file" unless -r $config;

my $config_obj = new Config::IniFiles(-file => $config);
die "no config" unless $config_obj;

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
	print h1("Error@_")."\n";
	exit 1;
}

sub cnf {
	my $id = shift;

	my ($sect, $key) = split(/\./, $id);
	my $v = $config_obj->val($sect, $key);
	print "$id = '$v'\n" if $verbose;
	$v;
}
