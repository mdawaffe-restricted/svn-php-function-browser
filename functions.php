<?php

/* Current Parameters: Set in index.php from URL */
function get_function_name() {
	if ( isset( $GLOBALS['function'] ) )
		return $GLOBALS['function'];
	return '';
}

function get_revision() {
	return $GLOBALS['revision'];
}

function get_old_revision() {
	return $GLOBALS['old_revision'];
}

function get_old_revision_form() {
	if ( $rev = get_old_revision() )
		return $rev;
	return 'PREV';
}
function get_view() {
	return $GLOBALS['view'];
}

/* Validation */
function is_valid_function_name( $function ) {
	return preg_match( '/[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/', $function );
}

function validate_function_name( $function = null ) {
	if ( !$function )
		$function = get_function_name();
	elseif ( !is_valid_function_name( $function ) )
		return false;

	return $function;
}

/* Formating */
function trac_urls( $text ) {
	if ( !defined( 'FUNC_SVN_TRAC_URL' ) || !FUNC_SVN_TRAC_URL )
		return $text;

	$text = htmlspecialchars( $text, ENT_NOQUOTES );
	$text = preg_replace_callback( '/\[(\d+)\]/', '_trac_url_changeset', $text );
	$text = preg_replace_callback( '/#(\d+)/', '_trac_url_ticket', $text );
	return $text;
}

function _trac_url_changeset( $match ) {
	return '<a href="' . clean_url( FUNC_SVN_TRAC_URL . "/changeset/$match[1]" ) . '">' . "[$match[1]]</a>";
}

function _trac_url_ticket( $match ) {
	return '<a href="' . clean_url( FUNC_SVN_TRAC_URL . "/ticket/$match[1]" ) . '">' . "#$match[1]</a>";
}

/* Views */

function display_file( $file = null ) {
	$file = get_function_name();
	$revision = get_revision();

	$repo = new Function_SVN_Repo( FUNC_SVN_REPO_ROOT, FUNC_SVN_REPO_PATH );
	if ( !$list = $repo->ls( FUNC_SVN_REPO_PATH . "/$file", $revision ) )
		return false;
	$list_array = explode( "\n", $list );
?>

	<h1><?php printf( 'Functions in %s', esc_html( $file ) ); ?></h1>

	<ul>
<?php	foreach ( $list_array as $list_item ) : ?>

		<li><a href="<?php echo clean_url( FUNC_SVN_APP_URL . "/$list_item" . ( SVN_REVISION_HEAD == $revision ? '' : "/$revision" ) ); ?>"><?php echo esc_html( $list_item ); ?></a></li>
<?php	endforeach; ?>

	</ul>
<?php
}

// Instead of just doing ->cat() and ->diff() here, we go through some hoops to get the previous url, logs etc.
// Could probably move to ->cat() and ->diff() if we stored or cached logs
function display_function( $function = null ) {
	if ( !$function = validate_function_name( $function ) )
		return;
	$revision = get_revision();

	$repo = new Function_SVN_Repo( FUNC_SVN_REPO_ROOT, FUNC_SVN_REPO_PATH );
	if ( !$current_function = $repo->cat( $function, $revision ) )
		return;

	$error = '';

	// $current_log: Find the earliest revision with that verssion of the function.  Get its svn log.
	if ( !$current_log = $repo->log( $revision, $function, true ) )
		return;

	// $previous_log: Find the earliest revision with the previous version of the function.  Get its svn log.
	$previous_log = $repo->log( $current_log['rev'] - 1, $function, true );

	$needs_old = true;

	switch ( $old_revision = get_old_revision() ) {
	case 'prev' : // 'prev' => we want the most recent previous version of the function
		break;
	case 'blame' :
		$blame = $repo->blame_log( $function, $current_log );
		break;
	case false :
		$needs_old = false;
		break;
	default : // (int) => we want a particular previous version of the function
		if ( $previous_log['rev'] != $old_revision ) {
			// This particular version of the function
			$previous_log = $repo->log( $old_revision, $function );
		}
	}

	if ( $needs_old && !$previous_log ) {
		$error = 'This revision is the first containing this function.';
		$old_revision = false;
	}

	// Build the URL for the "previous" button.
	$prev_url = FUNC_SVN_APP_URL . "/$function/" . (int) $previous_log['rev'];

	if ( $old_revision ) {
		if ( !$previous_log['function_content'] )
			return;

		if ( 'prev' == $old_revision )
			$prev_url .= '/prev';
		elseif ( $old_revision < (int) $previous_log['rev'] )
			$prev_url .= "/$old_revision";
		else
			$prev_url .= '/prev';
	}

	$header_args = array( 
		(int) $current_log['rev'],
		esc_attr( $current_log['date']->format( 'c' ) ),
		esc_html( $current_log['date']->format( 'Y-m-d H:i:s' ) )
	);

	if ( defined( 'FUNC_SVN_TRAC_URL' ) && FUNC_SVN_TRAC_URL ) {
		array_push( $header_args, 
			clean_url( FUNC_SVN_TRAC_URL . '/browser' . FUNC_SVN_REPO_PATH . '/' . $current_log['function_file'] . '?rev=' . (int) $current_log['rev'] ),
			clean_url( FUNC_SVN_TRAC_URL . '/changeset/' . (int) $current_log['rev'] )
		);
		$header_format = '<a href="%4$s">Revision %1$d</a> @ <time datetime="%2$s">%3$s</time> (<a href="%5$s">diff</a>)';
	} else {
		$header_format = 'Revision %1$s @ <time datetime="%2$s">%3$s</time>';
	}
?>

	<nav>
<?php	if ( $previous_log ) : ?>
		<a id="change-prev" href="<?php echo clean_url( $prev_url ); ?>">&#x3008;</a>
<?php	endif; // the "next" button is expensive to calculate.  SVN Log not that great at going looking forward.  Generated later via AJAX. ?>
		<a id="change-next" href="">&#x3009;</a>
	</nav>

	<header>
		<h1><?php vprintf( $header_format, $header_args ); ?></h1>
<?php		if ( $error ) : ?>
		<p class="error"><?php echo esc_html( $error ); ?></p>
<?php		endif; ?>
		<p><?php echo trac_urls( $current_log['msg'] ); ?></p>
		<address class="hcard"><span class="fn nickname"><?php echo esc_html( $current_log['author'] ); ?></span></address>
	</header>

<?php
	$view = get_view();
	if ( !$previous_log )
		$view = 'cat';


	switch ( $view ) {
	case 'cat' : ?>

	<pre class="brush: php; wrap-lines: false;"><?php echo htmlspecialchars( $current_log['function_content'], ENT_NOQUOTES ); ?></pre>
<?php
		break;
	case 'diff' : ?>

	<pre class="brush: diff; wrap-lines: false;"><?php echo htmlspecialchars( $repo->diff_logs( $function, $previous_log, $current_log ) ); ?></pre>
<?php
		break;
	case 'blame' : ?>

	<pre class="brush: php; wrap-lines: false;"><?php echo htmlspecialchars( $blame, ENT_NOQUOTES ); ?></pre>

<?php
		break;
	}
}

// Generates the URL for the "next" button.  Expensive.
function get_next_url( $function, $revision, $old_revision ) {
	$repo = new Function_SVN_Repo( FUNC_SVN_REPO_ROOT, FUNC_SVN_REPO_PATH );

	if ( !$current_log = $repo->log( $revision, $function, true ) )
		return;

	// This is the slow part
	if ( !$next_revision = $repo->find_next_change_revision( $function, $current_log, $revision ) )
		return;

	$next_url = FUNC_SVN_APP_URL . "/$function/" . (int) $next_revision;
	if ( $old_revision ) {
		if ( 'prev' == $old_revision )
			$next_url .= '/prev';
		elseif ( $old_revision < (int) $next_revision )
			$next_url .= "/$old_revision";
		else
			$next_url .= '/prev';
	}

	return $next_url;
}
