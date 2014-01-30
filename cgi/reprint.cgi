#!/usr/bin/perl

# (C) Copyright Axis Communications AB, LUND, SWEDEN

use lib '.';				# <VISITOR_LIB>
use vars qw($debug $verbose);
use File::Path;
use Config::IniFiles;
use Carp;
use POSIX;
use Socket;
use DBI;
use CGI qw(:standard);
use CGI::Carp;
use strict;

my $cgi = new CGI;

my $config = '/etc/blabgen/admin.ini';
$config = '../conf/admin.ini' if -r '../conf/admin.ini'; # for debug

my $iaddr = inet_aton($cgi->remote_addr);
(my $host  = gethostbyaddr($iaddr, AF_INET)) =~ s/\..*//;

my $hconfig = "/etc/blabgen/host-$host.ini";
$hconfig = '../conf/host-$host.ini' if -r '../conf/host-$host.ini'; # for debug

die "no config file" unless -r $config;
die "no host config file for $host" unless -r $hconfig;

my $host_obj = new Config::IniFiles(-file => $hconfig);
die "no host config for $host" unless $host_obj;

my $config_obj = new Config::IniFiles(-file => $config, -import => $host_obj);
die "no config" unless $config_obj;

my $dsn = 'DBI:mysql:database='.cnf('db.db').';host='.cnf('db.host').
	';port='.cnf('db.port');
my $dbh = DBI->connect($dsn, cnf('db.user'), cnf('db.pwd'));
err($DBI::errstr) unless $dbh;
$dbh->do('set character set utf8');


my $key = $cgi->param('key');
my $sth = $dbh->prepare("select id, name, company, webhost, enter_time ".
	"from pers where webkey = ?");

unless ($sth->execute($key)) {
    print $cgi->header();
    err($dbh->errstr);
}
my ($id, $name, $company, $webhost, $enter) = $sth->fetchrow_array();
unless ($id) {
    print $cgi->header();
    print pre("Picture not found, wrong key");
    exit 0;
}
my $subdir = substr($id, -1, 1);
my $path = sprintf(cnf('picture.dir')."/$subdir/$id.jpg", $webhost);
unless (-e $path) {
    print $cgi->header();
    print pre("Picture not found, probably too old");
    exit 0;
}

my $printer = cnf('printing.printer_name');
my $cmd = sprintf cnf('printer.cmd'), $path, $name, $company,
	$id, $enter, $printer;

system($cmd);

print redirect('list.cgi');
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
