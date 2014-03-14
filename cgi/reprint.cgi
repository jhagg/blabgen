#!/usr/bin/perl

use lib '.';				# <VISITOR_LIB>
use vars qw($debug $verbose);
use strict;
use File::Path;
use Config::IniFiles;
use Carp;
use POSIX;
use Socket;
use DBI;
use CGI qw(:standard);
use CGI::Carp;
use Sys::Syslog;

my $cgi = new CGI;

my $config = '/etc/blabgen/config.ini';
$config = '../conf/config.ini' if -r '../conf/config.ini'; # for debug

my $lconfig = '/etc/blabgen/local.ini';
$lconfig = '../conf/local.ini'
	if -r '../conf/local.ini'; # for debug

my $iaddr = inet_aton($cgi->remote_addr);
(my $host  = gethostbyaddr($iaddr, AF_INET)) =~ s/\..*//;

my $hconfig = "/etc/blabgen/host-$host.ini";
$hconfig = "../conf/host-$host.ini" if -r "../conf/host-$host.ini"; # for debug

die "no config file" unless -r $config;
die "no host config file for $host" unless -r $hconfig;


my $config_obj = new Config::IniFiles(-file => $config);
die "no config" unless $config_obj;

if (-r $lconfig) {
	$config_obj = new Config::IniFiles(-file => $lconfig,
		-import => $config_obj);
	die "no local config" unless $config_obj;
}

$config_obj = new Config::IniFiles(-file => $hconfig, -import => $config_obj);
die "no host config for $host" unless $config_obj;

openlog('blabgen_reprint_cgi', undef, cnf('gen.syslog_facility'));

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
	syslog('debug', "wrong key %s", $key);
	exit 0;
}
syslog('debug', "id %s, name %s, company %s, host %s, date %s",
	$id, $name, $company, $webhost, $enter);
my $subdir = substr($id, -1, 1);
my $path = sprintf(cnf('picture.dir')."/$subdir/$id.jpg", $webhost);
unless (-e $path) {
	print $cgi->header();
	print pre("Picture not found, probably too old");
	exit 0;
}
chdir('../..');

my $printer = cnf('print.name');
my $cmd = sprintf cnf('print.badge_cmd'), $path, $name, $company,
	$id, $enter, $printer;

do_system($cmd);

print redirect('list.cgi');
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
sub do_system {
	my $cmd = shift;

	open(PIPE, "$cmd 2>&1 |");
	chomp(my @err =  <PIPE>);
	close(PIPE);
	my $err = join(', ', @err);
	syslog('debug', "command %s: %s", $cmd, $err);
	$err;
}
