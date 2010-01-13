<?php

/* CLI */

require 'load.php';
require 'getopts.php'; // http://hash-bang.net/2008/12/missing-php-functions-getopts/

if ( empty( $argc ) )
	die( "This tool must be called from the command line\n" );

$help = <<<EOH
$argv[0] command repository_path arguments

commands:
	blame | praise | annotate | ann {function}
	cat {function}
	diff | di {function}
	info {function}
	list | ls {file}

A {function} is a PHP function name optionally prefaced with a relative path.
If a path is given, that path will be recursively searched for that function.
If none is given, the search path defaults to "trunk/".

EXAMPLE: php $argv[0] cat {repo} {function}


EOH;

if ( isset( $argv[1] ) && in_array( $argv[1], array( 'help', '?', 'h' ) ) ) {
	$command = isset( $argv[2] ) ? $argv[2] : false;
	switch ( $command ) {
	case 'ann' :
	case 'annotate' :
	case 'blame' :
	case 'praise' :
		echo "blame (praise, annotate, ann): Output the content of specified function with revision and author information in-line.\n";
		echo "usage: blame {function}\n\n";
		echo "Valid options:\n";
		echo "	-r | --revision	ARG	: ARG can be one of:\n";
		echo "				  NUMBER	revision number\n";
		echo "				  'HEAD'	latest in repository\n";
		echo "	-v | --verbose		: Displays extra information.\n"; // What?
		echo "	--incremental		: give output suitable for concatenation\n";
		echo "	--xml			: output in XML\n";
		break;
	case 'cat' :
		echo "cat: Output the content of specified function.\n";
		echo "usage: cat {function}\n\n";
		echo "Valid options:\n";
		echo "	-r | --revision	ARG	: ARG can be one of:\n";
		echo "				  NUMBER	revision number\n";
		echo "				  'HEAD'	latest in repository\n";
		break;
	case 'di' :
	case 'diff' :
		echo "diff (di): Display the differences between two revisions of the given function.\n";
		echo "usage: diff {function}\n\n";
		echo "Valid options:\n";
		echo "	-r | --revision	ARG	: ARG is of the form REV1:REV2 where REV1 and REV2 are each one of:\n";
		echo "				  NUMBER	revision number\n";
		echo "				  'HEAD'	latest in repository\n";
		echo "	-c | --change ARG	: The change made by revision ARG.\n";
		echo "				  If positive, equivalent to -r ARG-1:ARG\n";
		echo "				  If negative, equivalent to -r ARG:ARG-1\n";
		echo "	--no-look-back		: Do not reset the given revisions to their earliest respective\n";
		echo "				  revisions having the same function_content\n";
		break;
	case 'info' :
		echo "info: Display information about a function.\n";
		echo "usage: info {function}\n\n";
		echo "Valid options:\n";
		echo "	-r | --revision	ARG	: ARG can be one of:\n";
		echo "				  NUMBER	revision number\n";
		echo "				  'HEAD'	latest in repository\n";
		echo "	--incremental		: give output suitable for concatenation\n";
		echo "	--xml			: output in XML\n";
		die( "not implemented\n" );
		break;
	case 'list' :
	case 'ls' :
		echo "list (ls): List functions in the specified file.\n";
		echo "usage: list {file}\n\n";
		echo "Valid options:\n";
		echo "	-r | --revision	ARG	: ARG can be one of:\n";
		echo "				  NUMBER	revision number\n";
		echo "				  'HEAD'	latest in repository\n";
		echo "	-v | --verbose		: Displays extra information in the following column.\n";
		echo "				  Revision number of the last commit\n";
		echo "				  Author of the last commit\n";
		echo "				  Size (in bytes)\n";
		echo "				  Date and time of the last commit\n";
		echo "	--incremental		: give output suitable for concatenation\n";
		echo "	--xml			: output in XML\n";
		break;
	default :
		die( $help );
	}
	exit;
}

if ( empty( $argc ) || $argc < 4 )
	die( $help );

if ( 'list' == $argv[1] ) {
	$file = $argv[3];
} else {
	$last_sl = strrpos( $argv[3], '/' );
	if ( false == $last_sl ) {
		$function = $argv[3];
		$path = '/trunk';
	} else {
		$function = substr( $argv[3], $last_sl + 1);
		$path = substr( $argv[3], 0, $last_sl );
	}
	unset( $last_sl );

	if ( !$function = validate_function_name( $function ) )
		trigger_error( 'Invalid function', E_USER_ERROR );
}

$repo = new Function_SVN_Repo( $argv[2], $path );

switch ( $argv[1] ) {
case 'ann' :
case 'annotate' :
case 'blame' :
case 'praise' :
	$options = getopts( array(
		'revision' => array( 'switch' => array( 'r', 'revision' ), 'type' => GETOPT_VAL, 'default' => 'HEAD' )
	) );

	echo $repo->blame( $function, $options['revision'] );
	break;
case 'cat' :
	$options = getopts( array(
		'revision' => array( 'switch' => array( 'r', 'revision' ), 'type' => GETOPT_VAL, 'default' => 'HEAD' )
	) );

	echo $repo->cat( $function, $options['revision'] );
	echo "\n";
	break;
case 'di' :
case 'diff' :
	$options = getopts( array(
		'change' => array( 'switch' => array( 'c', 'change' ), 'type' => GETOPT_VAL ),
		'revision' => array( 'switch' => array( 'r', 'revision' ), 'type' => GETOPT_VAL ),
		'no-look-back' => array( 'switch' => 'no-look-back', 'type' => GETOPT_SWITCH )
	) );

	if ( $options['change'] ) {
		if ( !$change = (int) $options['change'] )
			break;

		if ( $change < 0 ) {
			$from_revision = $change;
			$to_revision = $change - 1;
		} else {
			$from_revision = $change - 1;
			$to_revision = $change;
		}
	} elseif ( $options['revision'] ) {
		list( $from_revision, $to_revision ) = explode( ':', $options['revision'] );
		if ( !$from_revision || !$to_revision )
			break;
		if ( !$from_revision = (int) $from_revision )
			$from_revision = SVN_REVISION_HEAD;
		if ( !$to_revision = (int) $to_revision )
			$to_revision = SVN_REVISION_HEAD;
	} else {
		break;
	}

	echo $repo->diff( $function, $from_revision, $to_revision, !$options['no-look-back'] );
	echo "\n";
	break;
case 'info' :
	die( 'not implemented' );
	break;
case 'list' :
case 'ls' :
	$options = getopts( array(
		'revision' => array( 'switch' => array( 'r', 'revision' ), 'type' => GETOPT_VAL, 'default' => 'HEAD' )
	) );

	echo $repo->ls( $file, $options['revision'] );
	echo "\n";
	break;
default :
	die( $help );
}
