<?php
/*
 * Plugin Name: Tweet Copier
 * Version: 1.3.2
 * Plugin URI: https://thunderguy.com/semicolon
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
Copyright (C) 2013-20 Bennett McElwee. This software may contain code licensed
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

// Include plugin libraries and class files
require_once 'lib/tmhOAuth.php';
require_once 'classes/class-tweet-copier.php';
require_once 'classes/class-tweet-copier-engine.php';
require_once 'classes/class-tweet-copier-logger.php';
use TweetCopier\Logger as Logger;
use TweetCopier\NullLogger as NullLogger;

// Instantiate necessary classes (use call_user_func to avoid global namespace)
call_user_func( function() {

	$logfile_suffix = '';
	$log_level = null;
	// Load optional local configuration
	if (file_exists(__DIR__ . '/tweet-copier-config.php')) {
		@include __DIR__ . '/tweet-copier-config.php';
	}

	if ($log_level && $logfile_suffix) {
		$logfile = __DIR__ . '/tweet-copier-' . $logfile_suffix . '.log';
		$log = new Logger($logfile);
		$log->enable($log_level);
	} else {
		$log = new NullLogger();
	}

	$plugin = new TweetCopier( __FILE__, $log );
	if ( is_admin() ) {
		require_once 'classes/class-tweet-copier-settings.php';
		$plugin_settings = new TweetCopierSettings( __FILE__, $plugin, $log );
	}
});
