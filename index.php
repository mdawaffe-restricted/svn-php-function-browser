<?php

/* WEB */

require 'load.php';

// Parse $_POST and redirect
if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'post' == strtolower( $_SERVER['REQUEST_METHOD'] ) && !empty( $_POST['function'] ) ) {
	$function = $_POST['function'];
	$url = FUNC_SVN_APP_URL;
	if ( is_valid_function_name( $function ) ) {
		$url .= "/$function";
		$head = '';
		if ( ctype_digit( $_POST['revision'] ) )
			$url .= "/$_POST[revision]";
		elseif ( 'head' == strtolower( $_POST['revision'] ) )
			$head = '/head';

		if ( ctype_digit( $_POST['old_revision'] ) )
			$url .= "$head/$_POST[old_revision]";
		elseif ( 'prev' == strtolower( $_POST['old_revision'] ) )
			$url .= "$head/prev";
		header( 'Location: ' . clean_url( $url ) );
		exit;
	}
} else { // Parse pretty URL
	$app_path = parse_url( FUNC_SVN_APP_URL, PHP_URL_PATH );
	$rel = trim( substr( $_SERVER['REQUEST_URI'], strlen( $app_path ) ), '/' );
	list( $rel ) = explode( '?', $rel );
	@list( $function, $revision, $old_revision ) = explode( '/', $rel );

	unset( $app_path, $rel );
	if ( !is_valid_function_name( $function ) )
		unset( $function );
	if ( !ctype_digit( $revision ) )
		$revision = SVN_REVISION_HEAD;
	else
		$revision = (int) $revision;
	if ( ctype_digit( $old_revision ) )
		$old_revision = (int) $old_revision;
	elseif ( 'prev' == strtolower( $old_revision ) )
		$old_revision = 'prev';
	else
		$old_revision = false;

	$view = $old_revision ? 'compare' : 'display';
}

// Handle AJAX
if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'post' == strtolower( $_SERVER['REQUEST_METHOD'] ) && !empty( $_POST['next_url'] ) ) {
	echo clean_url( get_next_url( $function, $revision, $old_revision ) );
	exit;
}

function do_view() { // wrapped in a function so we don't stomp any globals
	include FUNC_SVN_APP_PATH . 'view.php';
}

do_view();
