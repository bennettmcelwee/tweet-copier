<?php
/*
 * Plugin Name: Tweet Mirror
 * Version: 0.1
 * Plugin URI: http://thunderguy.com/semicolon
 * Description: Im in ur Wordprez. Mrrring ur tweetz.
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

define( 'TWEET_MIRROR_DEBUG', true );

// Include plugin libraries and class files
require_once 'lib/tmhOAuth.php';
require_once 'lib/tmhUtilities.php';

require_once 'classes/class-tweet-mirror.php';
require_once 'classes/class-tweetimporter.php';

require_once 'constants.php';

// Instantiate necessary classes
$plugin = new TweetMirror( __FILE__ );
if (is_admin()) {
	require_once 'classes/class-tweet-mirror-settings.php';
	$plugin_settings = new TweetMirrorSettings( __FILE__, $plugin );
}

if (TWEET_MIRROR_DEBUG) {
	define( 'TWEET_MIRROR_DEBUG_FILE', dirname( __FILE__ ) . '/debug.log' );
	function twmi_debug( $message ) {
		$message = rtrim( $message );
		$message = str_replace( "\n", "\n                    ", $message );
		$message = current_time( 'mysql' ) . ' ' . $message . "\n";
		error_log( $message, 3, TWEET_MIRROR_DEBUG_FILE );
	}
} else {
	function twmi_debug( $message ) {
	}
}
