<?php
/*
 * Plugin Name: Tweet Copier
 * Version: 0.1
 * Plugin URI: http://thunderguy.com/semicolon
 * Description: Im in ur Wordprez. Copyng ur tweetz.
 * Author: Bennett McElwee
 * Author URI: http://thunderguy.com/
 * Requires at least: 3.0
 * Tested up to: 3.5.1
 * 
 * @package WordPress
 * @author Bennett McElwee
 * @since 1.0.0
 *
 * Based on WordPress Plugin Template 1.0 by Hugh Lashbrooke
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'TWEET_COPIER_DEBUG', true );
define( 'TWEET_COPIER_LOG', true || TWEET_COPIER_DEBUG );

// Include plugin libraries and class files
require_once 'lib/tmhOAuth.php';
require_once 'lib/tmhUtilities.php';

require_once 'classes/class-tweet-copier.php';
require_once 'classes/class-tweet-copier-engine.php';

require_once 'constants.php';

// Instantiate necessary classes
call_user_func( function() {
	// Don't touch global namespace
	$plugin = new TweetCopier( __FILE__ );
	if (is_admin()) {
		require_once 'classes/class-tweet-copier-settings.php';
		$plugin_settings = new TweetCopierSettings( __FILE__, $plugin );
	}
});

// Logging

if ( TWEET_COPIER_LOG || TWEET_COPIER_DEBUG ) {
	define( 'TWEET_COPIER_LOG_FILE', dirname( __FILE__ ) . '/tweet-copier.log' );
}

if ( TWEET_COPIER_LOG ) {
	function twcp_log( $message, $level = 'INFO' ) {
		$message = rtrim( $message );
		$message = str_replace( "\n", "\n                    " . $level . ' ', $message );
		$message = current_time( 'mysql' ) . ' ' . $level . ' ' . $message . "\n";
		error_log( $message, 3, TWEET_COPIER_LOG_FILE );
	}
} else {
	function twcp_log( $level, $message ) {
	}
}

/**
	Usage: if ( TWEET_COPIER_DEBUG ) twcp_debug( 'My message' );
*/
if ( TWEET_COPIER_DEBUG ) {
	function twcp_debug( $message ) {
		twcp_log( $message, 'DEBUG' );
	}
}
