<?php
/*
 * Plugin Name: Tweet Copier
 * Version: 1.0
 * Plugin URI: http://thunderguy.com/semicolon
 * Description: Tweet Copier keeps your blog updated with copies of all your tweets, old and new.
 * Author: Bennett McElwee
 * Author URI: http://thunderguy.com/
 * Licence: GPLv2 or later
 * 
 * @package Tweet Copier
 * @author Bennett McElwee
 * @since 1.0.0
 */
/*
Copyright (C) 2013-15 Bennett McElwee. This software may contain code licensed
from WordPress Plugin Template by Hugh Lashbrooke, Tweet Import by Khaled
Afiouni, Twitter Importer by DsgnWrks, tmhOAuth by Matt Harris, and others.
It takes a village.

This program is free software; you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation; either version 2 of the License, or (at your option) any
later version.

This program is distributed in the hope that it will be useful, but WITHOUT
ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

The GNU General Public License is available from
http://www.gnu.org/licenses/gpl-2.0.html
or by writing to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

if ( ! defined( 'ABSPATH' ) ) exit;

/** This must be set to some random alphabetic string in order for logging to work. It's a poor man's security feature. */
define( 'TWEET_COPIER_LOGFILE_SUFFIX', 'oijskdjf' );

/** If true, write activity summaries to a log file. */
define( 'TWEET_COPIER_LOG', true && TWEET_COPIER_LOGFILE_SUFFIX);

/** If true, write activity details to a log file. */
define( 'TWEET_COPIER_DEBUG', true && TWEET_COPIER_LOGFILE_SUFFIX);

// Include plugin libraries and class files
require_once 'lib/tmhOAuth.php';
require_once 'classes/class-tweet-copier.php';
require_once 'classes/class-tweet-copier-engine.php';
require_once 'classes/class-tweet-copier-logger.php';

// Instantiate necessary classes (use call_user_func to avoid global namespace)
call_user_func( function() {
	$logfile = dirname( __FILE__ ) . '/tweet-copier-test-' . TWEET_COPIER_LOGFILE_SUFFIX . '.log';
	$log = new TweetCopier\Logger($logfile);
	if (TWEET_COPIER_DEBUG) {
		$log->enable(Logger::DEBUG);
	} else if (TWEET_COPIER_LOG) {
		$log->enable(Logger::INFO);
	} else {
		$log = new TweetCopier\NullLogger();
	}
	$log->info('For your information');
	$log->debug('Debugg\'rit');

	$plugin = new TweetCopier( __FILE__, $log );
	if ( is_admin() ) {
		require_once 'classes/class-tweet-copier-settings.php';
		$plugin_settings = new TweetCopierSettings( __FILE__, $plugin, $log );
	}
});
