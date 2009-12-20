<?php

class Function_SVN_Repo {
	var $repo_root = null;
	var $repo_path = null;

	var $repo = null;
	var $fs = null;
	var $rev_fs = array();

	var $last_file = null; // Holds path to file in which the function was most recently found
	var $last_line = null; // Holds line number of the start of the function most recently found

	function __construct( $repo_root, $repo_path ) {
		if ( !$this->repo = svn_repos_open( $repo_root ) )
			trigger_error( 'Unable to open repository', E_USER_ERROR );
		if ( !$this->fs = svn_repos_fs( $this->repo ) )
			trigger_error( 'Unable to open repository file system', E_USER_ERROR );
		$this->repo_root = rtrim( $repo_root, '/' ) . '/'; // Note slash convention inconsistency
		$this->repo_path = '/' . trim( $repo_path, '/' );
	}

	// @return (resource) Handle to Repo FS @ $revision
	function &get_revision( $revision ) {
		if ( !$revision = (int) $revision )
			$revision = SVN_REVISION_HEAD;
		if ( !isset( $this->rev_fs[$revision] ) )
			$this->rev_fs[$revision] = svn_fs_revision_root( $this->fs, SVN_REVISION_HEAD == $revision ? svn_fs_youngest_rev( $this->fs ) : $revision );
		return $this->rev_fs[$revision];
	}

	// @return (array) single svn log for specified revision
	// if $look_back is true, go back through the repo to find the most recent revision prior or equal to $revision where $function changed
	function log( $revision, $function = null, $look_back = false, $path = null ) {
		if ( is_null( $path ) )
			$path = $this->repo_path;

		if ( SVN_REVISION_HEAD == $revision )
			$revision = svn_fs_youngest_rev( $this->fs );

		if ( !$look_back || !$function = validate_function_name( $function ) ) {
			@list( $log ) = self::svn_log( "file://$this->repo_root", $revision );
			return $log;
		}

		if ( !$function_content = $this->cat( $function, $revision, $path ) )
			return false;

		@list( $current_log, $previous_log ) = self::svn_log( "file://{$this->repo_root}{$this->last_file}", $revision, SVN_REVISION_INITIAL, 2, SVN_DISCOVER_CHANGED_PATHS );
		if ( !$current_log )
			return false;

		$current_log['function_file'] = $this->last_file;
		$current_log['function_line'] = $this->last_line;
		$current_log['function_content'] = $function_content;

		if ( !$previous_log ) { // Could be that it's in a different file that svn_log() didn't catch, or could be outside of peg_revision
			if ( $previous_content = $this->cat( $function, $revision - 1 ) )
				@list( $previous_log ) = $a = self::svn_log( "file://{$this->repo_root}{$this->last_file}", $revision - 1, SVN_REVISION_INITIAL, 1, SVN_DISCOVER_CHANGED_PATHS );

			if ( !$previous_log )
				return $current_log;
		}

		// $current_log: Loop through previous revisions to find the earliest one with this $function_content
		while ( $function_content == $previous_content = $this->cat( $function, $previous_log['rev'] ) ) {
			$previous_log['function_file'] = $this->last_file;
			$previous_log['function_line'] = $this->last_line;
			$current_log = $previous_log;
			@list( $previous_log ) = self::svn_log( "file://{$this->repo_root}{$this->last_file}", $previous_log['rev'] - 1, SVN_REVISION_INITIAL, 1, SVN_DISCOVER_CHANGED_PATHS );
			if ( !$previous_log )
				break;
		}

		$current_log['function_content'] = $function_content;
		return $current_log;
	}

	function diff( $function, $from_revision = SVN_REVISION_HEAD, $to_revision = SVN_REVISION_HEAD, $look_back = true ) {
		if ( !$function = validate_function_name( $function ) )
			return false;

		if ( !$from_log = $this->log( $from_revision, $function, $look_back ) )
			return false;

		if ( !$look_back ) {
			if ( !$from_log['function_content'] = $this->cat( $function, $from_log['rev'] ) )
				return false;
			$from_log['function_file'] = $this->last_file;
			$from_log['function_line'] = $this->last_line;
		}

		$to_log = $this->log( $to_revision, $function, $look_back );
		if ( !$look_back ) {
			if ( !$to_log['function_content'] = $this->cat( $function, $to_log['rev'] ) )
				return false;
			$to_log['function_file'] = $this->last_file;
			$to_log['function_line'] = $this->last_line;
		}

		return $this->diff_logs( $function, $from_log, $to_log );
	}

	function diff_logs( $function, $from_log, $to_log ) {
		if ( $from_log['function_content'] == $to_log['function_content'] )
			return;

		$format = '%s:%s (revision %d @ %s)';
		$return  = sprintf( "--- $format\n", $function, $from_log['function_file'], $from_log['rev'], $from_log['date']->format( 'Y-m-d H:i:s' ) );
		$return .= sprintf( "+++ $format\n", $function, $to_log['function_file'], $to_log['rev'], $to_log['date']->format( 'Y-m-d H:i:s' ) );
		$return .= xdiff_string_diff( "{$from_log['function_content']}\n", "{$to_log['function_content']}\n" );
		return $return;
	}

	// @return (string) function body.  Recursive.  Sets $this->lastfile
	function cat( $function, $revision, $path = null ) {
		if ( is_null( $path ) )
			$path = $this->repo_path;

		$rev_fs = $this->get_revision( $revision );

		if ( $this->last_file && $path != $this->last_file ) {
			if ( $return = $this->find_function_in_file( $function, $revision, $this->last_file ) )
				return $return;
			$this->last_file = null;
		}

		foreach ( svn_fs_dir_entries( $rev_fs, $path ) as $file => $type ) {
			switch ( $type ) {
			case SVN_NODE_DIR : // Recurse
				if ( $return = $this->cat( $function, $revision, "$path/$file" ) )
					return $return;
				break;
			case SVN_NODE_FILE :
				if ( !$return = $this->find_function_in_file( $function, $revision, "$path/$file" ) )
					continue 2;

				$this->last_file = "$path/$file";
				return $return;
			}
		}
	}

	function blame( $function, $revision ) {
		if ( !$function = validate_function_name( $function ) )
			return false;

		if ( !$log = $this->log( $revision, $function, true ) )
			return false;

		return $this->blame_log( $function, $log );
	}

	function blame_log( $function, $log ) {
		$blame_lines = svn_blame( "file://$this->repo_root{$log['function_file']}", $log['rev'] );
		$blame_lines = array_slice( $blame_lines, $log['function_line'], preg_match_all( '/(?:\r\n|\n|\r)/', $log['function_content'], $match ) + 1 );

		$blame = '';
		$rev_len = $author_len = 0;
		foreach ( $blame_lines as $blame_line ) {
			if ( $rev_len < $len = strlen( $blame_line['rev'] ) )
				$rev_len = $len;
			if ( $author_len < $len = strlen( $blame_line['author'] ) )
				$author_len = $len;
		}

		foreach ( $blame_lines as $blame_line )
			$blame .= str_pad( $blame_line['rev'], $rev_len, ' ', STR_PAD_LEFT ) . ' ' . str_pad( $blame_line['author'], $author_len ) . "\t{$blame_line['line']}\n";

		return $blame;
	}

	// @return false not found, (string) function body.
	function find_function_in_file( $function, $revision, $file ) {
		$rev_fs = $this->get_revision( $revision );

		if ( SVN_NODE_FILE != svn_fs_check_path( $rev_fs, $file ) )
			return false;
		if ( !$f = svn_fs_file_contents( $rev_fs, $file ) )
			return false;

		$file_contents = stream_get_contents( $f ); // can be more efficient.  Could use stream filter?
		fclose( $f ); // Is there a better function to close a stream?

		$_function = preg_quote( $function, '/' );
		if ( !preg_match( "/function\s+&?$_function\s*\(/", $file_contents, $match, PREG_OFFSET_CAPTURE ) ) // Could b0rk.  Should technically use tokenizer
			return false;
		if ( !$file_contents_tail = substr( $file_contents, $match[0][1] ) )
			return false;

		$this->last_line = preg_match_all( '/(?:\r\n|\n|\r)/', substr( $file_contents, 0, $match[0][1] ), $match );

		return $this->find_function_in_file_contents( $function, $file_contents_tail );
	}

	// @return (string) function body.  Uses Tokenizer for accuracy: counts braces.
	// Assumes $function definition begins with first character of $file_contents.
	function find_function_in_file_contents( $function, $file_contents ) {
		$tokens = token_get_all( "<?php $file_contents" );
		array_shift( $tokens );

		$function_content = '';
		$brace = 0;
		$braced = false;
		foreach ( $tokens as $token ) {
			if ( is_string( $token ) ) {
				if ( '{' == $token ) {
					$brace++;
					$braced = true;
				} elseif ( '}' == $token ) {
					$brace--;
					$braced = true;
				}
				$function_content .= $token;
			} else {
				switch ( $token[0] ) {
				case T_CURLY_OPEN :
				case T_DOLLAR_OPEN_CURLY_BRACES :
				case T_STRING_VARNAME :
					$brace++;
				}
				$function_content .= $token[1];
			}
			if ( $braced && !$brace )
				break;
		}
		return $function_content;
	}

	// Expensive
	// @return false not found, (int) first revision greater than $revision whose function_content is different than $function_content
	function find_next_change_revision( $function, $current_log, $revision ) {
		if ( !$function_content = $current_log['function_content'] )
			return false;

		if ( SVN_REVISION_HEAD == $revision )
			return false;
		else
			$revision = max( $revision, $current_log['rev'] );

		if ( $revision == $head = svn_fs_youngest_rev( $this->fs ) )
			return false;

		@list( $next_log ) = self::svn_log( "file://{$this->repo_root}{$current_log['function_file']}", $revision + 1, $head, 1, SVN_DISCOVER_CHANGED_PATHS );

		if ( !$next_log ) { // Forward looking svn_logs don't work well.  No log probably means $head is outside of peg_revision.  
			// Brute force
			while ( $function_content == $this->cat( $function, ++$revision ) );

			return (int) $revision;
		}

		while ( $function_content == $this->cat( $function, $next_log['rev'] ) ) {
			@list( $next_log ) = self::svn_log( "file://{$this->repo_root}{$this->last_file}", $next_log['rev'] + 1, $head, 1, SVN_DISCOVER_CHANGED_PATHS );
			if ( !$next_log )
				return false;
		}

		return (int) $next_log['rev'];
	}

	// Because PECL svn only supports svn 1.2: no peg_revision
	static function svn_log( $repo_url, $start_revision = SVN_REVISION_HEAD, $end_revision = null, $limit = 0, $flags = null ) {
		static $which_svn = false;

		if ( is_null( $end_revision ) )
			$end_revision = $start_revision;
		if ( is_null( $flags ) )
			$flags = SVN_DISCOVER_CHANGED_PATHS | SVN_STOP_ON_COPY;

		if ( $logs = svn_log( $repo_url, $start_revision, $end_revision, $limit, $flags ) ) {
			self::parse_log_dates( $logs );
			return $logs;
		} // Else: could have returned nothing only due to lack of peg_revision support.  Have to shell out to SVN CLI.

		if ( SVN_REVISION_HEAD == $start_revision ) // don't need peg_revision
			return $logs;

		if ( false === $which_svn )
			$which_svn = trim( exec( 'which svn' ) );
		if ( !$which_svn )
			return $logs;

		// url@peg_revision - use start_revision as peg_revision
		$exec = "$which_svn log " . escapeshellarg( "$repo_url@$start_revision" ) . ' --verbose --xml -r ' . escapeshellarg( "$start_revision:$end_revision" ) . ' --limit ' . escapeshellarg( $limit );

		if ( $flags & SVN_STOP_ON_COPY )
			$exec .= ' --stop-on-copy';

		exec( $exec, $out, $ret );
		if ( $ret )
			return $logs;

		$return = array();
		if ( !$xml = simplexml_load_string( join( "\n", $out ) ) )
			return $logs;

		foreach ( $xml->logentry as $logentry ) {
			$log = array(
				'rev' => (string) $logentry['revision'],
				'author' => (string) $logentry->author,
				'msg' => (string) $logentry->msg,
				'date' => (string) $logentry->date,
				'paths' => array()
			);

			foreach ( $logentry->paths->path as $path ) {
				$log['paths'][] = array(
					'action' => (string) $path['action'],
					'path' => (string) $path
				);
			}
			
			$return[] = $log;
		}

		self::parse_log_dates( $return );
		return $return;
	}

	static function parse_log_dates( &$logs ) {
		static $utc = false;

		if ( false === $utc )
			$utc = new DateTimeZone( 'UTC' );

		foreach ( $logs as &$log )
			$log['date'] = DateTime::createFromFormat( 'Y-m-d\TH:i:s.u\Z', $log['date'], $utc );
	}
}
