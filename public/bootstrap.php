<?php

/**
 * Bootstraps the visitor system.
 */

require 'utils.php';

// don't display errors in production mode
if ( conf( 'mode' ) == 'development' ) {
	ini_set('display_errors', '1');
}

umask(conf('umask'));

ini_set('error_reporting', E_ALL | E_STRICT );

date_default_timezone_set( conf('timezone') );
