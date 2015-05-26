#!/usr/bin/perl

use lib '.';				# <VISITOR_LIB>
use strict;
use File::Path;
use Config::IniFiles;
use Date::Calc qw(Today Add_Delta_Days Add_Delta_YM);
use Carp;
use DBI;
use POSIX;
use CGI qw(-nosticky :standard start_table);
use CGI::Carp;
use Sys::Syslog;

my $config = $ENV{'BLABGEN_ETC'}.'/config.ini';
$config = '../conf/config.ini' if -r '../conf/config.ini'; # for debug
$config = 'conf/config.ini' if -r 'conf/config.ini'; # for debug
die "no config file" unless -r $config;

my $local_conf = $ENV{'BLABGEN_ETC'}.'/local.ini';
$local_conf = '../conf/local.ini'
	if -r '../conf/local.ini'; # for debug
$local_conf = 'conf/local.ini' if -r 'conf/local.ini'; # for debug

my $config_obj = new Config::IniFiles(-file => $config);
die "no config" unless $config_obj;
if (-r $local_conf) {
	$config_obj = new Config::IniFiles(-file => $local_conf,
		-import => $config_obj);
}
openlog('blabgen_list_cgi', undef, cnf('gen.syslog_facility'));

my $verbose;

######################################################################
my $cgi = new CGI;

######################################################################
my $myurl = $cgi->url;

######################################################################
my @show_date;
if (my $use_date = $cgi->param('date')) {
	@show_date = split(/-/, $use_date);
}
@show_date = Today() unless @show_date;

if (my $diff_day = $cgi->param('dday')) {
	@show_date = Add_Delta_Days(@show_date, $diff_day);
}
if (my $diff_week = $cgi->param('dweek')) {
	@show_date = Add_Delta_Days(@show_date, $diff_week*7);
}
if (my $diff_month = $cgi->param('dmonth')) {
	@show_date = Add_Delta_YM(@show_date, 0, $diff_month);
}
if ($cgi->param('year')) {
	@show_date = ($show_date[0], undef, undef);
}

my $date_str = sprintf("%4d-%02d-%02d", @show_date);
my @keep_args = qw(
	date
	sort
);

######################################################################
# Check the input parameters and act the right way:
my $dsn = 'DBI:mysql:database='.cnf('db.db').';host='.cnf('db.host').
	';port='.cnf('db.port');
my $dbh = DBI->connect($dsn, cnf('db.user'), cnf('db.pwd'));
err($DBI::errstr) unless $dbh;
$dbh->do('set character set utf8');

######################################################################
my $curr_id;
my @leave = grep(/^leave_/, $cgi->param);
my $leave_id;
if (my $id = $cgi->param('goto_id')) {
	$curr_id = $id;
	my $cmd = "select date_format(enter_time, '%Y-%m-%d') ".
		"from pers where id = ?";
	my $sth = $dbh->prepare($cmd);
	$sth->execute($curr_id);
	($date_str) = $sth->fetchrow_array;
	@show_date = split(/-/, $date_str);
}
if ($cgi->param('find')) {
	$curr_id = $cgi->param('curr_id');
	if (my ($id) = $curr_id =~ /^lv(\d+)/) {
		push(@leave, $id);
		$curr_id = $id;
		$cgi->param('curr_id', '');
		$cgi->param('goto_id', $id);
	}
	my $cmd = "select date_format(enter_time, '%Y-%m-%d') ".
		"from pers where id = ?";
	my $sth = $dbh->prepare($cmd);
	$sth->execute($curr_id);
	($date_str) = $sth->fetchrow_array;
	@show_date = split(/-/, $date_str);
}
# only use the first leave id, there should never be more than one
if (@leave) {
	($leave_id = $leave[0]) =~ s/^leave_//;
	$curr_id = $leave_id;
	my $cmd = "update pers set status = 'fin', leave_time = now() ".
		"where id = ? and status = 'act'";
	$dbh->do($cmd, undef, $leave_id);
	$cgi->delete('find', 'curr_id');
	print $cgi->redirect($cgi->self_url);
	exit;
}

print header(-charset => 'utf-8');
print start_html(-title => 'Visitors of Axis', -encoding => 'utf-8',
	-script => [
			{ -type => 'javascript',
			-src => '/javascript/jquery/jquery.min.js' },
			{ -type => 'javascript', -src => 'list.js' },
		],
	-style => { -src => 'css/list.css' });

######################################################################
$cgi->param('date', $date_str);

######################################################################
if ($cgi->param('emerg')) {
	print start_table({-cellspacing => 0, -cellpadding => 0,
		'class' => 'header_table', -width => '100%'}), "\n";
	print Tr(
		td({-align => 'left'}),
		th({-class => 'header_title'},
			"Active visitors $date_str")."\n",
		td({-align => 'right'})), "\n";
	print end_table(), "\n";

	print emerg_table($date_str, $curr_id, @show_date);

	print end_form();
	print end_html(), "\n";
	exit 0;
}

######################################################################
if ($cgi->param('park')) {
	print start_table({-cellspacing => 0, -cellpadding => 0,
		'class' => 'header_table', -width => '100%'}), "\n";
	print Tr(
		td({-align => 'left'}),
		th({-class => 'header_title'},
			"Current visitors license plates $date_str")."\n",
		td({-align => 'right'})), "\n";
	print end_table(), "\n";

	print parking_table($date_str, $curr_id, @show_date);

	print end_html(), "\n";
	exit 0;
}

######################################################################
print start_table({-cellspacing => 0, -cellpadding => 0,
	'class' => 'header_table', -width => '100%'}), "\n";
print start_form();
print Tr(
	td({-align => 'left'}, update_box()),
	th({-class => 'header_title'}, "Visitors $date_str")."\n",
	td({-align => 'right'}, button_row())), "\n";
print end_table(), "\n";

print date_table($date_str, $curr_id, @show_date);

print end_form();
print end_html(), "\n";
exit 0;
######################################################################
sub button_row {

	my @buttons = (
		[
			['EMERGENCY', $cgi->url.'?emerg=1', 1],
			['Today', $cgi->url],
			['This year', $cgi->url],
		],
		[
			['Parking', $cgi->url.'?park=1', 1],
			['Prev day', gen_url('dday=-1')],
			['Next day', gen_url('dday=1')],
		],
		[
			[],
			['Prev week', gen_url('dweek=-1')],
			['Next week', gen_url('dweek=1')],
		],
		[
			[],
			['Prev month', gen_url('dmonth=-1')],
			['Next month', gen_url('dmonth=1')],
		],
	);

	my $s = start_table({-cellspacing => 0, -cellpadding => 0,
		'class' => 'button_row'});
	for my $i (@buttons) {
		my @brow;
		for my $j (@$i) {
			push(@brow, td({-class => 'button_row_item'},
				$j->[2] ?  a({-href => $j->[1],
					-target => '_blank'}, $j->[0]) :
				 a({-href => $j->[1]}, $j->[0]))."\n");
		}
		$s .= Tr(@brow);
	}

	$s .= end_table();
	$s;
}

sub update_box {
	my $s = start_table({-cellspacing => 0, -cellpadding => 0,
		'class' => 'update_box'});
	$s .= Tr(th{-class => 'update_item update_upper_item'},
			('Enter id'),
		td({-class => 'update_item update_upper_item'},
			textfield(-name => 'curr_id',
				-autofocus => undef,
				-size => 7, -maxlength => 10)));
	$s .= Tr(
		td{-class => 'update_item', -colspan => 2, -align => 'center'},
			submit(-name => 'find', -value => 'Find')
		);
	$s .= end_table();
	$s;
}
sub gen_url {

	my $url = $cgi->url;
	my @args = @_;
	for my $i ($cgi->param) {
		next unless grep($_ eq $i, @keep_args);
		push(@args, "$i=".$cgi->param($i));
	}
	$url .= '?'.join(';', @args) if @args;
	$url;
}

# Show the table.
sub date_table {
	my $date_str = shift;
	my $curr_id = shift;
	my @date = @_;

	my$cnt = 0;
	my $s;
	# Choose the order of the table:
	my @plh;
	my $cmd = "select id, name, company, enter_time, leave_time, ".
		"webkey, status, parking from pers ";
#	$cmd .= "join visit using(id) " if $search_user;
	unless (defined $date[1]) {
		$cmd .= "where enter_time between ? and ?";
		@plh = ($date[0].'-01-01', $date[0].'-12-31');
	}
	else {
		$cmd .= "where date(enter_time) = ? ";
		@plh = ($date_str);
	}
#	$cmd .= "and visit.uname = '$search_user'" if $search_user;
	my $sort_order = $cgi->param('sort');
	$cmd .= " order by $sort_order" if $sort_order;
	my $sth = $dbh->prepare($cmd);
	$sth->execute(@plh);

	$s .= start_table({-cellspacing => 0, -cellpadding => 0,
		'class' => 'list_table'})."\n";

	$s .= Tr({-class => 'list_hdr_row'}, th({-class => 'list_header'}, [
		a({-href => $myurl.'?sort=id'}, 'Id')."\n",
		a({-href => $myurl.'?sort=name'}, 'Name')."\n",
		a({-href => $myurl.'?sort=enter_time'}, 'Enter')."\n",
		'To visit'."\n",
		'Picture'."\n"
		]))."\n";
	$s .= Tr({-class => 'list_hdr_row2'}, th({-class => 'list_header'}, [
		'',
		a({-href => $myurl.'?sort=company'}, 'Company')."\n",
		a({-href => $myurl.'?sort=leave_time'}, 'Leave')."\n",
		'Parking', '&nbsp;']))."\n";
	while (my ($id, $name, $comp, $enter, $leave, $wkey, $status, $parking)
			= $sth->fetchrow_array) {

		my $date = substr($enter, 0, 10);
		# Seek for corresponding picture:
		my $picture = cnf('picture.dir').'/'.
			substr($id, -1, 1)."/$id.jpg";
		my $pic_url = sprintf(cnf('picture.cgi'), $wkey);
		my $pict_code;
		$pict_code = a({-href => $pic_url}, $id) if $wkey;

		$cmd = "select uname from visit where id=?";
		my $sth2 = $dbh->prepare($cmd);
		$sth2->execute($id);
		my ($uname) = $sth2->fetchrow_array;

		$cmd = "select name,surname from info where uname=?";
		my $sth3 = $dbh->prepare($cmd);
		$sth3->execute($uname);
		my $visit = join(' ', $sth3->fetchrow_array);

		my ($reprint);
		$leave = a({-href => gen_url("leave_$id=1"),
			-title => 'Click here when person '.
			'has left the building'}, $leave) if $status eq 'act';
		my $rep_url = sprintf(cnf('picture.reprint'), $wkey);
		$reprint = a({-href => $rep_url,
			-class => 'reprint',
			-title => 'Print label again'}, 'R') if $wkey &&
				$status eq 'act';

		my $class = 'list_item list_item1';
		$class .= ' list_current_item' if $id == $curr_id;
		$s .= Tr({-class => 'list_row'},
			td({-class => $class.' list_item_name'}, $id)."\n",
			td({-class => $class.' list_item_name'}, $name)."\n",
			td({-class => $class}, $enter)."\n",
			td({-class => $class}, $visit)."\n",
			td({-rowspan => 2, -class =>
				$class}, $pict_code)."\n"
			)."\n";
		my $class = 'list_item list_item2';
		$class .= ' list_current_item' if $id == $curr_id;
		$s .= Tr({-class => 'list_row2'},
			td({-class => $class.' reprint'}, $reprint)."\n",
			td({-class => $class}, $comp)."\n",
			td({-class => $class}, $leave)."\n",
			td({-class => $class}, $parking)."\n"
			)."\n";
	}
	$s .= end_table();
	$s;
}

sub parking_table {

	my$cnt = 0;
	my $s;
	my @date = Today();
	# Choose the order of the table:
	my @plh;
	my $cmd = "select id, parking, enter_time, leave_time from pers where ".
		"date(enter_time) <= ? and date(leave_time) >= ? and ".
		"status='act' and parking != '' order by parking";
		@plh = ($date_str, $date_str);
	my $sth = $dbh->prepare($cmd);
	$sth->execute(@plh);

	$s .= start_table({-cellspacing => 0, -cellpadding => 0,
		'class' => 'list_table'})."\n";

	$s .= Tr({-class => 'list_hdr_row'}, th({-class => 'list_header'}, [
		a({-href => $myurl.'?sort=id'}, 'Id')."\n",
		a({-href => $myurl.'?sort=parking'}, 'License number')."\n"
		]))."\n";
	while (my ($id, $parking, $enter, $leave) = $sth->fetchrow_array) {

		my $date = substr($enter, 0, 10);

		my $class = 'list_item list_item1';
		$class .= ' list_current_item' if $id == $curr_id;
		$s .= Tr({-class => 'list_row'},
			td({-class => $class.' list_item_name'}, $id)."\n",
			td({-class => $class.' list_item_name'}, $parking)."\n",
			)."\n";
	}
	$s .= end_table();
	$s;
}

sub emerg_table {

	my$cnt = 0;
	my $s;
	# Choose the order of the table:
	my @plh;
	my @date = Today();
	my $cmd = "select id, name, company from pers where ".
		"date(enter_time) <= ? and date(leave_time) >= ? and ".
		"status='act' order by name";
	@plh = ($date_str, $date_str);

	my $sth = $dbh->prepare($cmd);
	$sth->execute(@plh);

	$s .= start_table({-cellspacing => 0, -cellpadding => 0,
		'class' => 'list_table'})."\n";

	$s .= Tr({-class => 'list_hdr_row'}, th({-class => 'list_header'}, [
		'Id', 'Name', 'To visit',
		]))."\n";
	while (my ($id, $name, $comp) = $sth->fetchrow_array) {

		$cmd = "select uname from visit where id=?";
		my $sth2 = $dbh->prepare($cmd);
		$sth2->execute($id);
		my @uname;
		while (my ($u) = $sth2->fetchrow_array) {
			push(@uname, $u);
		}

		$cmd = "select concat(name, ' ', surname) ".
			"from info where uname in (".
			join(',', map { '?' } @uname).')';
		my $sth3 = $dbh->prepare($cmd);
		$sth3->execute(@uname);
		my @rows;
		while (my ($fullname) = $sth3->fetchrow_array) {
			push(@rows, $fullname);
		}

		my $visit = join(', ', sort(@rows));

		my $class = 'list_item list_item1';
		$class .= ' list_current_item' if $id == $curr_id;
		$s .= Tr({-class => 'list_row'},
			td({-class => $class.' list_item_name'}, $id)."\n",
			td({-class => $class.' list_item_name'}, $name)."\n",
			td({-class => $class}, $visit)."\n"
			)."\n";
	}
	$s .= end_table();
	$s;
}

sub err {
	print h1("Error: @_")."\n";
	syslog('err', join(', ', @_));
	exit 1;
}
exit 0;

sub cnf {
	my $id = shift;

	my ($sect, $key) = split(/\./, $id);
	my $v = $config_obj->val($sect, $key);
	$v =~ s/^'(.*)'$/\1/;
	print "$id = '$v'\n" if $verbose;
	$v;
}
