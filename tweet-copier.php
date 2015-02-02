<?php
/*
 * Plugin Name: Tweet Copier
 * Version: 0.1
 * Plugin URI: http://thunderguy.com/semicolon
 * Description: Im in ur Wordprez. Copyng ur tweetz.
 * Author: Bennett McElwee
 * Author URI: http://thunderguy.com/
 * Requires at least: 3.1
 * Tested up to: 3.6
 * Licence: GPLv2 or later
 * 
 * @package Tweet Copier
 * @author Bennett McElwee
 * @since 1.0.0
 */
/*
Copyright (C) 2013 Bennett McElwee. This software may contain code licensed
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

/** If true, write activity summaries to a log file. */
define( 'TWEET_COPIER_LOG', false );

/** If true, write activity details to a log file. */
define( 'TWEET_COPIER_DEBUG', false );

// Include plugin libraries and class files
require_once 'lib/tmhOAuth.php';
require_once 'classes/class-tweet-copier.php';
require_once 'classes/class-tweet-copier-engine.php';

// Instantiate necessary classes (use call_user_func to avoid global namespace)
call_user_func( function() {
	$plugin = new TweetCopier( __FILE__ );
	$plugin->set_debug( TWEET_COPIER_DEBUG );
	if ( is_admin() ) {
		require_once 'classes/class-tweet-copier-settings.php';
		$plugin_settings = new TweetCopierSettings( __FILE__, $plugin );
	}
});

// Logging
if ( TWEET_COPIER_LOG || TWEET_COPIER_DEBUG ) {
	define( 'TWEET_COPIER_LOG_FILE', dirname( __FILE__ ) . '/tweet-copier.log' );
	function twcp_log( $message, $level = 'INFO' ) {
		$message = rtrim( $message );
		$message = str_replace( "\n", "\n                    " . $level . ' ', $message );
		$message = current_time( 'mysql' ) . ' ' . $level . ' ' . $message . "\n";
		@error_log( $message, 3, TWEET_COPIER_LOG_FILE );
	}
} else {
	function twcp_log() {
	}
}

/** Usage: if ( TWEET_COPIER_DEBUG ) twcp_debug( 'My message' ); */
if ( TWEET_COPIER_DEBUG ) {
	function twcp_debug( $message ) {
		twcp_log( $message, 'DEBUG' );
	}
}
