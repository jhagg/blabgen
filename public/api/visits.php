<?php

/**
 * Resource to POST a visit to the system.
 */

require __DIR__.'/../bootstrap.php';

/**
 * Saves visit. Throws Http_error on fail.
 */
function save_visit($visit_data ) {
	// company is not required, default to ''
	$visit_data += array(
		'company' => '',
	);

	$db = db_connect();

	// throws Http_error on validation fail
	$visit_data = validate_visit_data($visit_data);

	$insert_data = array(
		'name' => $visit_data['name'],
		'company' => $visit_data['company'],
		'parking' => $visit_data['parking'],
		// use $_SERVER instead of gethostname() to support virtual
		// hosts
		'srvhost' => $_SERVER['SERVER_NAME'],
		'webhost' => $_SERVER['REMOTE_ADDR'],
	);

	// generate unique webkey
	$webkey = '';
	do {
		$webkey = md5(microtime().mt_rand());
		$stmt = $db->prepare("SELECT id FROM pers WHERE webkey=?");
		$stmt->bind_param('s', $webkey);
		// query db
		if (!$stmt->execute() || !$stmt->bind_result($id )) {
			throw new Http_error(500, 'Could not generate webkey.');
		}
		$f = $stmt->fetch();
		// webkey is ok
		if ($f === null ) {
			break;
		// webkey already exists, try again
		} else if ($f === true ) {
			continue;
		// error (f === false)
		} else {
			throw new Http_error(500, 'Could not generate webkey.');
		}
	} while (true);

	$insert_data['webkey'] = $webkey;

	// set up dates to be inserted as DATETIME 
	$insert_data['enter_time'] =
		date('Y-m-d H:i:s', $visit_data['start_date']);
	$insert_data['leave_time'] =
		date('Y-m-d H:i:s', $visit_data['end_date']);

	log_msg(LOG_DEBUG, 'inserting visit data: '.
		implode('; ', $insert_data ).' ...');

	$sql = "INSERT INTO pers
		(name, company, enter_time, leave_time,
			webhost, webkey, parking)
		VALUES (?, ?, ?, ?, ?, ?, ?)";

	$stmt = $db->prepare($sql);
	if (!$stmt ) {
		throw new Http_error(500, 'DB error');
	}

	$stmt->bind_param('sssssss',
		$insert_data['name'], $insert_data['company'],
		$insert_data['enter_time'], $insert_data['leave_time'],
		$insert_data['webhost'], $insert_data['webkey'],
		$insert_data['parking']);
	
	if (!$stmt->execute()) {
		throw new Http_error(500, 'DB error');
	}

	$stmt->close();

	$last_id = get_last_insert_id($db);
	if (!$last_id ) {
		throw new Http_error(500, 'DB error');
	}
	$insert_data['id'] = $last_id;

	// insert information about visit, one row per receiver
	foreach ($visit_data['receivers'] as $r ) {
		$sql = 'INSERT INTO visit (id, uname) VALUES(?, ?)';
		$stmt = $db->prepare($sql);
		if (!$stmt ) {
			throw new Http_error(500, 'DB error');
		}
		$stmt->bind_param('is', $last_id, $r['uname']);
		if (!$stmt->execute()) {
			throw new Http_error(500, 'DB error');
		}
	}

	save_picture(basename(trim($visit_data['picture'] )), $last_id);
	send_visitor_email($insert_data, $visit_data['receivers']);
	print_visitor_badge($insert_data + $visit_data);
}

/**
 * Validates given visit data. Throws Http_error on fail.
 */
function validate_visit_data($visit_data ) {
	extract($visit_data);
	$err = array();

	// name: min 2 chars length
	if (empty($name )) {
		$err[] = 'Name is required.';
	}	else if (mb_strlen($name ) < 1 ) {
		$err[] = 'Name must be at least 1 character long.';
	}
	
	// start_date: valid date. either integer timestamp (unix time in 
	// milliseconds) or parseable date string.
	if (!isset($start_date )) {
		$err[] = 'Start date is required.';
	} else if (!is_numeric($start_date )) {
		$start_date = strtotime($start_date);
		if ($start_date === false ) {
			$err[] = "Start date must be parseable by strtotime().";
		}
	} else {
		$start_date /= 1000;
	}

	// end_date: valid date. either integer timestamp (unix time in
	// milliseconds) or parseable date string.
	if (!isset($end_date )) {
		$err[] = 'End date is required';
	} else if (!is_numeric($end_date )) {
		$end_date = strtotime($end_date);
		if ($end_date === false ) {
			$err[] = "End date must be parseable by strtotime().";
		}
	} else {
		$end_date /= 1000;
	}

	// sanity check: start date is BEFORE end date!
	if ($start_date && $end_date && ($end_date < $start_date)) {
		$err[] = 'End date must be >= start date';
	}

	// receivers: comma-separated list of axis usernames
	if (empty($receivers )) {
		$err[] = 'Receivers is required.';
	} else {
		$db = db_connect();

		$rs = $receivers;
		if (!is_array($receivers )) {
			$rs = explode(',', $receivers);
		}

		$receivers = array();
		foreach ($rs as $r ) {
			$r = trim($r);

			$sql = sprintf("SELECT * FROM info ".
				"WHERE uname='%s' LIMIT 1",
				$db->real_escape_string($r ));

			if ($result = $db->query($sql )) {
				$rec = $result->fetch_assoc();
				if (!$rec ) {
					$err[] = "Receiver username ".
						"not found: $r";
				} else {
					$receivers[] = $rec;
				}
				$result->free();
			} else {
				$err[] = "Internal error";
			}
		}
	}

	if (empty($picture )) {
		$err[] = 'Picture is required.';
	}
	
	if ($err ) {
		throw new Http_error(400, implode("\n", $err ));
	}

	return array(
		'name' => $name,
		// company is not required
		'company' => @$_POST['company'],
		'parking' => $parking,
		'start_date' => $start_date,
		'end_date' => $end_date,
		'receivers' => $receivers,
		'picture' => $picture,
	);
}

/** Moves temporary picture to permanent picture dir. */
function save_picture($tmp_picture_fname, $new_picture_id ) {
	$temp_filename = path_join(conf('picture_tmp_dir'), $tmp_picture_fname);

	$picture_dir = conf('picture_dir');
	if (!is_readable($picture_dir )) {
		mkdir($picture_dir);
	}

	$target_filename = path_join($picture_dir,
		substr($new_picture_id, -1 ), $new_picture_id.'.jpg');

	log_msg(LOG_DEBUG, sprintf('Attempting to move picture: %s -> %s',
		$tmp_picture_fname, $target_filename ));

	if (!is_dir(conf('picture_dir')) || !is_readable(conf('picture_dir'))) {
		throw new Http_error(500,
			sprintf('Directory is not readable: %s.',
				conf('picture_dir')));
	}
	if (!is_readable($temp_filename )) {
		throw new Http_error(500,
			sprintf('Temp picture not readable: %s',
				$temp_filename ));
	}
	if (!is_readable(dirname($target_filename ))) {
		mkdir(dirname($target_filename ));
	}
	if (!rename($temp_filename, $target_filename )) {
		throw new Http_error(500,
			sprintf('Could not move picture: %s -> %s',
			$temp_filename, $target_filename ));
	}

	log_msg(LOG_DEBUG, sprintf("Moved temp picture '%s' -> '%s'",
		$temp_filename, $target_filename ));
}

/** Sends e-mail with visit info. */
function send_visitor_email($visit_data, $receivers ) {
	$from = conf('email_from_address');
	$subject = conf('email_subject');
	$tos = array();
	$picture_url = sprintf(conf('email_picture_url_template'),
		$visit_data['srvhost'], $visit_data['webkey']);

	foreach ($receivers as $r ) {
		$tos[] = $r['uname'].'@'.conf('receiver_mail_domain');
	}

	if (conf('mode') == 'development' ) {
		$tos = array(conf('admin_email_address'));
	}

	$msg = 'You have a visitor: '.$visit_data['name'];
	if ($insert_data['company'])
		$msg .= ' from '.$insert_data['company'];

	$msg .= sprintf("\n\n%s", $picture_url);

	if (conf('send_email')) {
		$to = array_shift($tos);
		$cc = implode(', ', $tos);
		$bcc = conf('email_bcc_address');
		$hdrs = implode("\r\n", array(
			'MIME-Version: 1.0',
			'Content-Transfer-Encoding: 8bit',
			'Content-Type: text/plain; charset="utf-8"',
			'Content-Disposition: inline'));

		$res = imap_mail($to, $subject, $msg, $hdrs, $cc, $bcc);
		log_msg(LOG_DEBUG,
			sprintf("imap_mail: %s (to: '%s', cc: '%s', bcc: '%s')",
			$res, $to, $cc, $bcc ));
	}

	if (conf('email_output_file')) {
		log_msg(LOG_DEBUG, sprintf("Writing e-mail to file '%s' ...",
			conf('email_output_file')));
		$email = sprintf("From: %s\nTo: %s\nSubject: %s\n\n%s",
			$from, implode(', ', $tos ), $subject, $msg);
		file_put_contents(conf('email_output_file'), $email);
	}
}

/** Prints visitor badge, using external program. */
function print_visitor_badge($visit_data ) {
	$picture_filename = path_join(conf('picture_dir'),
		substr($visit_data['id'], -1 ), $visit_data['id'].'.jpg');

	$date = date('Y-m-d', $visit_data['end_date']);
	$nr = $visit_data['id'];
	$outfile = path_join(conf('card_picture_dir'), $nr.'.jpg');

	extract($visit_data);
	$d = getcwd();
	chdir('../../');
	log_msg(LOG_DEBUG, getcwd());
	$cmd = sprintf(conf('print_badge_cmd'), $picture_filename, $name,
		$company, $nr, $date, conf('printer_name'));
	exec_cmd($cmd);
	chdir($d);
}

try {
	if (request_method() != 'post' ) {
		throw new Http_error(405, 'Only POST allowed.');
	}

	// throws Http_error on failure
	save_visit($_POST);
} catch (Http_error $e ) {
	http_error($e->status_code, $e->msg);
}
