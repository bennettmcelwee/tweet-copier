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
