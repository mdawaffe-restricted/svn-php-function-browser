<?php

/* Begin Configuration */
define( 'FUNC_SVN_APP_URL', 'http://hacek.local/function-diff' );

define( 'FUNC_SVN_REPO_ROOT', '/Users/mdawaffe/Repositories/Sync/wordpress' );
define( 'FUNC_SVN_REPO_PATH', '/trunk' );

define( 'FUNC_SVN_TRAC_URL', 'http://core.trac.wordpress.org' );
/* End Configuration */


define( 'FUNC_SVN_APP_PATH', dirname( __FILE__ ) . '/' );
define( 'BACKPRESS_PATH', FUNC_SVN_APP_PATH . 'backpress/includes/' );

require_once BACKPRESS_PATH . 'functions.core.php';
require_once BACKPRESS_PATH . 'functions.compat.php';
require_once BACKPRESS_PATH . 'functions.formatting.php';
require_once BACKPRESS_PATH . 'class.wp-error.php';
require_once BACKPRESS_PATH . 'functions.plugin-api.php';
require_once BACKPRESS_PATH . 'functions.kses.php';

require_once FUNC_SVN_APP_PATH . 'functions.php';
require_once FUNC_SVN_APP_PATH . 'class.function-svn-repo.php';

function backpress_get_option( $option ) {
	switch ( $option ) {
	case 'charset' :
		return 'utf-8';
		break;
	}
	trigger_error( "Unknown BackPress option: $option", E_USER_ERROR );
}
