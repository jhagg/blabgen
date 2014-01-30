#!/usr/bin/perl

use File::Path;
use Config::IniFiles;
use Date::Calc qw(Today Add_Delta_Days Add_Delta_YM);
use Carp;
use DBI;
use POSIX;
use CGI qw(-nosticky :standard start_table);
use CGI::Carp;
use strict;

my $config = '/etc/blabgen/admin.ini';
$config = '../conf/admin.ini' if -r '../conf/admin.ini'; # for debug
$config = 'conf/admin.ini' if -r 'conf/admin.ini'; # for debug
die "no config file" unless -r $config;

my $config_obj = new Config::IniFiles(-file => $config);
die "no config" unless $config_obj;

my $verbose;

######################################################################
my $cgi = new CGI;

######################################################################
my $myurl = $cgi->url;

print header(-charset => 'utf-8');
print start_html(-title => 'Visitors of Axis', -encoding => 'utf-8',
	-script => [
			{ -type => 'javascript',
			-src => '/javascript/jquery/jquery.min.js' },
			{ -type => 'javascript', -src => 'list.js' },
		],
	-style => { -src => 'css/list.css' });

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
# only use the first leave id, there should never be more than one
if (@leave) {
	($leave_id = $leave[0]) =~ s/^leave_//;
	$curr_id = $leave_id;
	my $cmd = "update pers set status = 'fin', leave_time = now() ".
		"where id = ? and status = 'act'";
	$dbh->do($cmd, undef, $leave_id);
}
if ($cgi->param('find')) {
	$curr_id = $cgi->param('curr_id');
	my $cmd = "select date_format(enter_time, '%Y-%m-%d') ".
		"from pers where id = ?";
	my $sth = $dbh->prepare($cmd);
	$sth->execute($curr_id);
	($date_str) = $sth->fetchrow_array;
	@show_date = split(/-/, $date_str);

}

######################################################################
$cgi->param('date', $date_str);

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
	
	my $s = start_table({-cellspacing => 0, -cellpadding => 0,
		'class' => 'button_row'});
	$s .= Tr(td({-class => 'button_row_item'},
		a({-href => $cgi->url}, 'Today'))."\n".
		td({-class => 'button_row_item'},
		a({-href => gen_url('year=1')}, 'This year')))."\n";
	$s .= Tr(td({-class => 'button_row_item'},
		a({-href => gen_url('dday=-1')}, 'Prev day'))."\n".
		td({-class => 'button_row_item'},
		a({-href => gen_url('dday=1')}, 'Next day')))."\n";

	$s .= Tr(td({-class => 'button_row_item'},
		a({-href => gen_url('dweek=-1')}, 'Prev week'))."\n".
		td({-class => 'button_row_item'},
		a({-href => gen_url('dweek=1')}, 'Next week')))."\n";

	$s .= Tr(td({-class => 'button_row_item'},
		a({-href => gen_url('dmonth=-1')}, 'Prev month'))."\n".
		td({-class => 'button_row_item'},
		a({-href => gen_url('dmonth=1')}, 'Next month')))."\n";
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
				-size => 7, -maxlength => 7)));
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
		"webkey, status from pers ";
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
		'&nbsp;', '&nbsp;']))."\n";
	while (my ($id, $name, $comp, $enter, $leave, $wkey, $status)
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
		my $rep_url = sprintf(cnf('reprint.cgi'), $wkey);
		$reprint = a({-href => $rep_url,
			-class => 'reprint',
			-title => 'Print label again'}, 'R') if $wkey &&
				$status eq 'act';

		my $class = 'list_item list_item1';
		$class .= ' list_current_item' if $id == $curr_id;
		$s .= Tr({-class => 'list_row'},
			td({-class => $class}, $id)."\n",
			td({-class => $class}, $name)."\n",
			td({-class => $class}, $enter)."\n",
			td({-class => $class}, $visit)."\n",
			td({-rowspan => 2, -class =>
				$class}, $pict_code)."\n"
			)."\n";
		my $class = 'list_item list_item2';
		$class .= ' list_current_item' if $id == $curr_id;
		$s .= Tr({-class => 'list_row2'},
			td({-class => $class}, '&nbsp;')."\n",
			td({-class => $class}, $comp)."\n",
			td({-class => $class}, $leave)."\n",
			td({-class => $class.' reprint'}, $reprint)."\n"
			)."\n";
	}
	$s .= end_table();
	$s;
}

sub err {
	print h1("Error@_")."\n";
	exit 1;
}
exit 0;

sub cnf {
	my $id = shift;

	my ($sect, $key) = split(/\./, $id);
	my $v = $config_obj->val($sect, $key);
	print "$id = '$v'\n" if $verbose;
	$v;
}
