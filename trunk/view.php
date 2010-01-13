<!DOCTYPE HTML>
<html lang="en-US">
<head>
<meta charset="utf-8" />
<title>Function Diff</title>

<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.3.2/jquery.js"></script>
<script type="text/javascript" src="<?php echo clean_url( FUNC_SVN_APP_URL . '/syntaxhighlighter/src/shCore.js' ); ?>"></script>
<script type="text/javascript" src="<?php echo clean_url( FUNC_SVN_APP_URL . '/syntaxhighlighter/scripts/shBrushDiff.js' ); ?>"></script>
<script type="text/javascript" src="<?php echo clean_url( FUNC_SVN_APP_URL . '/syntaxhighlighter/scripts/shBrushPhp.js' ); ?>"></script>

<link href="<?php echo clean_url( FUNC_SVN_APP_URL . '/syntaxhighlighter/styles/shCore.css' ); ?>" rel="stylesheet" type="text/css" />
<link href="<?php echo clean_url( FUNC_SVN_APP_URL . '/syntaxhighlighter/styles/shThemeRDark.css' ); ?>" rel="stylesheet" type="text/css" />

<script type="text/javascript">
jQuery( function($) {
	$( 'select[name=view]' ).change( function() {
		var v = $(this).val();
		if ( 'diff' == v ) {
			$( '#compare-old' ).show().find( 'input' ).attr( 'disabled', false );
			$( '#view-punctuation' ).text( '+' );
		} else {
			$( '#compare-old' ).hide().find( 'input' ).attr( 'disabled', true );
			$( '#view-punctuation' ).text( '@' );
		}
	} ).change();
	SyntaxHighlighter.config.strings.expandSource = 'Documentation';
	SyntaxHighlighter.all();
	$.post( document.location.hre, { next_url: 1 }, function( response, status ) {
		if ( 'success' != status ) {
			return;
		}
		$( '#change-next' ).attr( 'href', response );
	}, 'text' );
	$( '.doc-link a' ).click( function() {
		$( '.documentation' ).slideToggle();
		return false;
	} );
} );
</script>

<style type="text/css">
header, section, article, nav {
	display: block;
}
body {
	margin: 0;
	padding: 0;
	font-family: "Lucida Grande",Verdana,"Bitstream Vera Sans",Arial,sans-serif;
	color: #000;
	overflow-x: hidden;
}
a {
	color: #d54e21;
}
a:hover {
	color: #464646;
}
body > header, body > section {
	padding: 1em;
	display: block;
}
body > section {
	margin: 0 3em;
}
body > header {
	height: 4em;
	position: relative;
	background-color: #21759b;
	color: #fff;
}
body > header > nav {
	position: absolute;
	top: 50%;
	height: 3em;
	margin-top: -1.5em;
	font-size: 1.5em;
	line-height: 3em;
}
body > header > nav select, body > header > nav input {
	font-size: 1em;
	line-height: 2;
	padding: .2em;
}
body > header > nav select {
	font-size: 20px;
}
body > header > nav input {
	-moz-box-sizing: border-box;
	box-sizing: border-box;
	height: 2em;
	padding-top: 0;
	padding-bottom: 0;
	margin: 0;
	line-height: 1 !important;
}
input[type=text] {
	width: 12em;
}
input[type=submit] {
	margin-left: .5em;
}
input.revision {
	width: 5em;
}
.punctuation {
	display: inline-block;
	width: 1em;
	text-align: center;
}
h1 {
	display: none;
	margin: 0 0 .5em;
}
section h1 {
	display: block;
}
p, address, pre {
	margin: 1em 0 0;
}
address {
	float: left;
}
p.doc-link {
	float: right;
}
ul {
	margin: 0 0 0 1em;
	padding: 0;
	text-indent: 0;
	list-style: none;
}
.error {
	-moz-border-radius: 3px;
	border-radius: 3px;
	padding: .5em;
	border: 1px solid #c00;
	background-color: #ffebeb;
}
article nav a {
	position: absolute;
	display: block;
	top: 3em;
	font-size: 5em;
	font-weight: bold;
	text-decoration: none;
}
article nav a[href=""] {
	display: none;
}
#change-prev {
	left: -.38em;
}
#change-next {
	right: -.3em;
}
.documentation {
	display: none;
	margin: 1em 0 0;
	background-color: #d3e7f8;
	background-color: #eee;
	padding: .5em;
}
header:after {
	content: ".";
	display: block;
	height: 0;
	clear: both;
	visibility: hidden;
}
.syntaxhighlighter {
	width: 100% !important;
}
</style>

</head>

<body>

<header>
	<h1>Function Diff</h1>
	<nav>
		<form method="post" action="">
			<select name="view">
<?php foreach ( array( 'cat' => 'Display', 'diff' => 'Compare', 'blame' => 'Annotate', 'list' => 'List' ) as $view => $label ) : ?>
				<option value="<?php echo esc_attr( $view ); ?>"<?php if ( $view == get_view() ) echo ' selected="selected"'; ?>><?php echo esc_html( $label ); ?></option>
<?php endforeach; ?>
			</select>
			<input type="text" name="function" value="<?php echo esc_attr( get_function_name() ); ?>" />
			<span id="view-punctuation" class="punctuation"><?php echo 'display' == get_view() ? '@' : '+'; ?></span>
			<input type="text" name="revision" class="revision" value="<?php echo esc_attr( SVN_REVISION_HEAD == get_revision() ? 'HEAD' : get_revision() ); ?>" />
			<span id='compare-old'>
				<span class="punctuation">&#x2212;</span>
				<input type="text" name="old_revision" class="revision" value="<?php echo esc_attr( get_old_revision_form() ); ?>" />
			</span>
			<input type="submit" value="Submit" />
		</form>
	</nav>
</header>

<section>
	<article>
	<?php if ( 'list' == get_view() ) display_file(); else display_function(); ?>
	</article>
</section>

</body>

