<?php

/**
 * Utils.
 */

/**
 * Returns configuration option. Options are stored in 'config.ini'.
 */
function conf( $conf ) {
	static $opts = null;

	if ( !$opts ) {
		// local config overrides /etc/blabgen/
		if ( is_readable( __DIR__ . '/../conf/config.ini' ) ) {
			$opts = parse_ini_file( __DIR__ . '/../conf/config.ini' );
			log_msg( LOG_DEBUG, sprintf( "Read config from '%s' ...",
				__DIR__ . '/../conf/config.ini' ) );
		} elseif ( is_readable( '/etc/blabgen/config.ini' ) ) {
			$opts = parse_ini_file( '/etc/blabgen/config.ini' );
			log_msg( LOG_DEBUG, sprintf( "Read config from '%s' ...",
				'/etc/blabgen/config.ini' ) );
		}
		// some configuration depends on remote hostname
		$hn = get_remote_hostname();
		// local config overrides /etc/blabgen/
		if ( is_readable( __DIR__ . '/../conf/host-' . $hn . '.ini' ) ) {
			$host_opts = parse_ini_file( __DIR__ . '/../conf/host-' . $hn . '.ini' );
			$opts = array_merge( $opts, (array) $host_opts );
			log_msg( LOG_DEBUG, sprintf( "Read host-specific config from '%s' ...",
				__DIR__ . '/../conf/host-' . $hn . '.ini' ) );
		} elseif ( is_readable( '/etc/blabgen/host-' . $hn . '.ini' ) ) {
			$host_opts = parse_ini_file( '/etc/blabgen/host-' . $hn . '.ini' );
			$opts = array_merge( $opts, (array) $host_opts );
			log_msg( LOG_DEBUG, sprintf( "Read host-specific config from '%s' ...",
				'/etc/blabgen/host-' . $hn . '.ini' ) );
		}
	}

	return @$opts[$conf];
}

/**
 * Returns remote hostname.
 */
function get_remote_hostname() {
	$remote_hostname = gethostbyaddr( $_SERVER['REMOTE_ADDR'] );
	$parts = explode( '.', $remote_hostname );
	return array_shift( $parts );
}

/**
 * Joins two or more paths.
 * 
 * See http://stackoverflow.com/questions/1091107/how-to-join-filesystem-path-strings-in-php
 */
function path_join() {
	// strip out empty args
	$paths = array_filter( func_get_args(), function ( $v ) {
		return mb_strlen( $v ) != 0; } );
	// replace any multiple '//' with one '/'
	return preg_replace( '#/{2,}#', '/', implode( '/', $paths ) );
}

/**
 * Logs message to syslog.
 */
function log_msg( $prio, $msg ) {
	if ( conf('use_syslog') ) {
		$prio = conf('syslog_facility') | $prio;
		syslog( $prio, $msg );
	}
}

/**
 * Executes shell command.
 */
function exec_cmd( $cmd ) {
	log_msg( LOG_DEBUG, sprintf( 'Execing: %s', $cmd ) );
	return exec( $cmd );
}

// -- HTTP functions -- //

/**
 * Sets HTTP content type header for response.
 */
function content_type( $content_type, $charset = 'utf-8',
	$allow_override = true ) {
	# let explicit content_type override (nice for testing)
	if ( !empty( $_GET['content_type'] ) ) {
		$content_type = $_GET['content_type'];
	}

	header( sprintf( 'Content-Type: %s; charset=%s', $content_type, $charset ) );
}

/**
 * Sets HTTP charset for response.
 */
function charset( $charset ) {
	header( sprintf( 'Charset: %s', $charset ) );
}

/**
 * Returns request method, lowercased.
 */
function request_method() {
	return strtolower( $_SERVER['REQUEST_METHOD'] );
}

/**
 * Sends HTTP response with given code and dies.
 */
function http_error( $errcode, $errmsg ) {
	header( get_reason_phrase( $errcode ) );
	echo $errmsg;
	exit();
}

/**
 * Returns full HTTP reason phrase given status code.
 */
function get_reason_phrase( $status_code ) {
	$codes = array(
		100 => "Continue",
		101 => "Switching Protocols",
		102 => "Processing",

		200 => "OK",
		201 => "Created",
		202 => "Accepted",
		203 => "Non-Authoritative Information",
		204 => "No Content",
		205 => "Reset Content",
		206 => "Partial Content",
		207 => "Multi-Status",
		226 => "IM Used",

		300 => "Multiple Choices",
		301 => "Moved Permanently",
		302 => "Found",
		303 => "See Other",
		304 => "Not Modified",
		305 => "Use Proxy",
		307 => "Temporary Redirect",

		400 => "Bad Request",
		401 => "Unauthorized",
		402 => "Payment Required",
		403 => "Forbidden",
		404 => "Not Found",
		405 => "Method Not Allowed",
		406 => "Not Acceptable",
		407 => "Proxy Authentication Required",
		408 => "Request Timeout",
		409 => "Conflict",
		410 => "Gone",
		411 => "Length Required",
		412 => "Precondition Failed",
		413 => "Request Entity Too Large",
		414 => "Request-URI Too Long",
		415 => "Unsupported Media Type",
		416 => "Requested Range Not Satisfiable",
		417 => "Expectation Failed",
		418 => "I'm a teapot",
		422 => "Unprocessable Entity",
		423 => "Locked",
		424 => "Failed Dependency",
		426 => "Upgrade Required",

		500 => "Internal Server Error",
		501 => "Not Implemented",
		502 => "Bad Gateway",
		503 => "Service Unavailable",
		504 => "Gateway Timeout",
		505 => "HTTP Version Not Supported",
		507 => "Insufficient Storage",
		510 => "Not Extended",
	);

	if( !isset( $codes[$status_code] ) ) {
		return null;
	} else {
		return "HTTP/1.1 $status_code " . $codes[$status_code];
	}
}

// -- Database functionality -- //

/**
 * Returns a connection to the database.
 */
function db_connect() {
	static $db = null;

	if ( $db ) {
		return $db;
	}

	$host = conf('db_host');
	$db_name = conf('db_db');
	$username = conf('db_user');
	$password = conf('db_pwd');

	$db = new mysqli( $host, $username, $password, $db_name );

	if ( $db->connect_error ) {
		http_error( 500, $db->connect_error );
	}

	if ( ! $db->set_charset('utf8') ) {
		http_error( 500, 'Unable to set MySQL charset.' );
	}

	return $db;
}

/**
 * Returns last insert id (for auto_increment fields).
 */
function get_last_insert_id( $db ) {
	if ( $last_id = $db->query('SELECT last_insert_id() id') ) {
		$last_id = $last_id->fetch_assoc();
		return $last_id['id'];
	} else {
		return false;
	}
}

/**
 * Represents an HTTP error.
 */
class Http_error extends Exception {
	/** HTTP status code. */
	public $status_code = 500;

	/** Error message. */
	public $msg = '';

	/** Creates new Http_error. */
	public function __construct( $status_code, $msg )
	{
		log_msg( LOG_ERR, "Http_error: $msg" );

		$this->status_code = $status_code;
		$this->msg = $msg;
		parent::__construct( $msg );
	}
}
