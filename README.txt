=== Function SVN Repository Browser ===
Contributors: mdawaffe
Tags: svn, php

CLI and Web interface for an SVN interface to PHP functions.
Implements blame, cat, diff, info, list.

== Description ==

This script provides an implementation of the read-only portion of the SVN CLI:
blame, cat, diff, info, list.

Instead of operating on files in a [Subversion][1] repository, this implementation
operates on the individual PHP functions within a repository, providing a
convenient method of browsing PHP functions and their histories.

== Limitiations ==

Only local repositories (not remote repositories or local checkouts) are
supported.  Works well, however, with [svnsync][2].

Only works within a single SVN branch/tag/trunk.  Cannot compare across branches,
tags, trunk or other repositories.

== Installation ==

= Using =

CLI: `php svn-function.php command arguments`.
     See `php svn-function.php help` for more information.

Web: Just browse to URL at which you installed the script.  To use List on many hosts,
     you'll need to set [AllowEncodedSlashes On][3].  Lame.

= Requirements =
1. PHP >= 5.2.0
2. PECL svn: http://pecl.php.net/package/svn (aka php5-svn in some linix distros)
3. PECL xdiff: http://pecl.php.net/package/xdiff (aka php5-xdiff)
4. libxdiff: http://www.xmailserver.org/xdiff-lib.html (for the above)

For use with remote repositories, you will also need to set up a local mirror
via [svnsync][2].

== Configuration ==

The following constants must be defined for the Web interface to work:

`FUNC_SVN_APP_URL`
:	Absolute URL to this script's web interface.
:	E.g. `http://hacek.local/function-svn-repo-browser/`

`FUNC_SVN_REPO_ROOT`
:	Absolute path to the local repository.  No trailing slash.
:	E.g. `/Users/mdawaffe/Repositories/Sync/wordpress`

`FUNC_SVN_REPO_PATH`
:	Relative path from the repository root to the branch or tag you want to view.
	Leading slash.  No trailing slash.
:	E.g. `/trunk`

`FUNC_SVN_TRAC_URL` (optional)
:	Absolute URL to the [Trac][4] instance set up for the repository.
	No trailing slash.
:	E.g. `http://core.trac.wordpress.org`


[1]: http://subversion.tigris.org/
[2]: http://svnbook.red-bean.com/en/1.5/svn.reposadmin.maint.html#svn.reposadmin.maint.tk.svnsync "svnsync documentation"
[3]: http://httpd.apache.org/docs/2.2/mod/core.html#allowencodedslashes
[4]: http://trac.edgewall.org/
