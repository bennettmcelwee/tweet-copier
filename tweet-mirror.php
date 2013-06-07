<?php
/*
 * Plugin Name: Tweet Mirror
 * Version: 0.1
 * Plugin URI: http://thunderguy.com/semicolon
 * Description: Basic template for creating a WordPress plugin
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

// Include plugin class files
require_once( 'classes/class-tweet-mirror.php' );
require_once( 'classes/class-tweet-mirror-settings.php' );

// Instantiate necessary classes
global $plugin_obj;
$plugin_obj = new TweetMirror( __FILE__ );
$plugin_settings_obj = new TweetMirrorSettings( __FILE__ );
