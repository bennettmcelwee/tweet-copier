<?php
/*
 * Copyright (c) 2013-15 Bennett McElwee. Licensed under the GPL (v2 or later).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * @package Tweet Copier
 * @author Bennett McElwee
 */
class TweetCopierSettings {

	const SETTINGS_PAGE = 'tweet_copier_settings';
	const SETTINGS_OPTION_GROUP = 'tweet_copier_options1';
	const AUTH_SECTION = 'tweet_copier_auth_settings';
	const FETCH_SECTION = 'tweet_copier_fetch_settings';
	const IMPORT_SECTION = 'tweet_copier_import_settings';
	const SCHEDULE_SECTION = 'tweet_copier_schedule_settings';

	// These options are used only in the settings page, not by the plugin
	const SCHEDULE_OPTION = 'tweet_copier_schedule';
	const SCHEDULE_VALUE_MANUAL = 'manual';
	const COPYNOW_OPTION = 'tweet_copier_copy_now';
	const TWITTERAUTH_OPTION = 'tweet_copier_twitter_auth';

	private $plugin;
	private $dir;
	private $file;
	private $assets_dir;
	private $assets_url;

	public function __construct( $file, &$plugin ) {
		$this->plugin = $plugin;
		$this->dir = dirname( $file );
		$this->file = $file;
		$this->assets_dir = trailingslashit( $this->dir ) . 'assets';
		$this->assets_url = esc_url( trailingslashit( plugins_url( '/assets/', $file ) ) );

		// Check whether we need to redirect for Twitter auth (high priority)
		add_action( 'init', array( &$this , 'check_for_twitter_response' ), 0 );

		// Register plugin settings
		add_action( 'admin_init' , array( &$this , 'register_settings' ) );

		// Add settings page to menu
		add_action( 'admin_menu' , array( &$this , 'add_settings_page' ) );

		// Add warning if plugin is not configured
		add_action( 'admin_menu' , array( &$this , 'check_configuration' ) );

		// Add settings link to plugins page
		add_filter( 'plugin_action_links_' . plugin_basename( $this->file ) , array( &$this , 'add_settings_link' ) );

		// Set up filters to run actions as settings are saved
		add_filter( 'pre_update_option_' . self::TWITTERAUTH_OPTION , array( &$this , 'filter_twitter_auth' ), 10, 2 );
		add_filter( 'pre_update_option_' . self::SCHEDULE_OPTION , array( &$this , 'filter_schedule' ), 10, 2 );
		add_filter( 'pre_update_option_' . self::COPYNOW_OPTION , array( &$this , 'filter_copy_now' ), 10, 2 );

	}

	private function is_auth_configuration_complete() {

		return ( self::has_text( get_option( TweetCopier::TWITTER_CONSUMER_KEY_OPTION ))
		  && self::has_text( get_option( TweetCopier::TWITTER_CONSUMER_SECRET_OPTION ))
		  && self::has_text( get_option( TweetCopier::TWITTER_USER_TOKEN_OPTION ))
		  && self::has_text( get_option( TweetCopier::TWITTER_USER_SECRET_OPTION )));
	}

	public function check_configuration() {

		if ( $this->is_auth_configuration_complete()
		  && self::has_text( get_option( TweetCopier::SCREENNAME_OPTION ))) {
			// Everyting irie.
		} else {
			$message = __( 'Tweet Copier is not active.', 'tweet_copier_textdomain' ) . ' '
				. sprintf( __( 'Please complete your %sTweet Copier settings%s.', 'tweet_copier_textdomain' ),
					'<a href="' . $this->get_settings_url_relative() . '">', '</a>' );
			add_settings_error( 'general', 'tweet_copier', $message, 'updated' );
		}
	}

	public function add_settings_page() {
		// add_options_page( $page_title, $menu_title, $capability, $menu_slug, $function);
		$hook_suffix = add_options_page( 'Tweet Copier Settings' , 'Tweet Copier' , 'manage_options' , self::SETTINGS_PAGE ,  array( &$this , 'settings_page' ) );
		add_action( "admin_print_scripts-$hook_suffix", array( &$this , 'settings_page_head' ) );
	}

	public function settings_page_head() {

		echo '<style>
			.twcp-edit-button { margin: 0 0.5em; text-decoration: underline; }
			.settings-section-tweet_copier_auth_settings .settings-section-content {
				display: none;
			}
			.auth-config-incomplete .settings-section-tweet_copier_auth_settings .settings-section-content {
				display: block;
			}
			.auth-config-incomplete .tweet_copier_auth_expander {
				display: none;
			}
			.settings-field-tweet_copier_user_token, .settings-field-tweet_copier_user_secret {
				display: none;
			}
		</style>';
		echo '<script>
			function tcadvancedmode() {
				jQuery(".settings-field-tweet_copier_user_token,.settings-field-tweet_copier_user_secret").css({display: "table-row"});
			}
			addLoadEvent(function() {
					var $ = jQuery;
					$("a.twcp-edit-button").click(function(event) {
						event.preventDefault();
						$("#" + $(this).attr("for")).prop("readonly", false).focus();
						$(this).hide();
					});
					if ( ! $("#tweet_copier_title_format_custom").is(":checked")) {
						$("#tweet_copier_title_format").prop("readonly", true);
					};
					$("input.tweet_copier_title_format_fixed").click(function(event) {
						$("#tweet_copier_title_format").val($(this).data("format"))
							.prop("readonly", true);
					});
					$("#tweet_copier_title_format").click(function(event) {
						$(this).prop("readonly", false).focus();
						$("#tweet_copier_title_format_custom").click();
					});
					$("#tweet_copier_title_format_custom").click(function(event) {
						$("#tweet_copier_title_format").prop("readonly", false).focus();
					});
					$(".tweet_copier_auth_expander").click(function(event) {
						$(this).hide().closest(".settings-section-tweet_copier_auth_settings").find(".settings-section-content").slideDown();
						event.preventDefault();
					});
				});
			</script>';
	}

	public function get_settings_url_absolute() {
		return admin_url( $this->get_settings_url_relative() );
	}

	public function get_settings_url_relative() {
		return 'options-general.php?page=' . self::SETTINGS_PAGE;
	}

	public function add_settings_link( $links ) {
		$settings_link = '<a href="' . $this->get_settings_url_relative() . '">Settings</a>';
  		array_unshift( $links, $settings_link );
  		return $links;
	}

	public function register_settings() {
		
		// add_settings_section( $id, $title, $callback, $page );
		add_settings_section( self::AUTH_SECTION , __( 'Authentication' , 'tweet_copier_textdomain' ) , array( &$this , 'auth_settings' ) , self::SETTINGS_PAGE );
		add_settings_section( self::FETCH_SECTION , __( 'Fetching tweets from Twitter' , 'tweet_copier_textdomain' ) , array( &$this , 'fetch_settings' ) , self::SETTINGS_PAGE );
		add_settings_section( self::IMPORT_SECTION , __( 'Saving tweets into WordPress' , 'tweet_copier_textdomain' ) , array( &$this , 'import_settings' ) , self::SETTINGS_PAGE );
		add_settings_section( self::SCHEDULE_SECTION , __( 'Scheduling' , 'tweet_copier_textdomain' ) , array( &$this , 'schedule_settings' ) , self::SETTINGS_PAGE );
		
		// add_settings_field( $id, $title, $callback, $page, $section, $args );
		// This really just renders a single title on the left and some HTML on the right. In some cases it actually
		// renders more than one field.
		add_settings_field( TweetCopier::TWITTER_CONSUMER_KEY_OPTION, __( 'Consumer key:' , 'tweet_copier_textdomain' ) ,
			array( &$this , 'render_field_auth' )  , self::SETTINGS_PAGE , self::AUTH_SECTION,
			array( 'fieldname' => TweetCopier::TWITTER_CONSUMER_KEY_OPTION, 'description' => 'Twitter application consumer key', 'label_for' => TweetCopier::TWITTER_CONSUMER_KEY_OPTION ) );
		add_settings_field( TweetCopier::TWITTER_CONSUMER_SECRET_OPTION, __( 'Consumer secret:' , 'tweet_copier_textdomain' ) ,
			array( &$this , 'render_field_auth' )  , self::SETTINGS_PAGE , self::AUTH_SECTION,
			array( 'fieldname' => TweetCopier::TWITTER_CONSUMER_SECRET_OPTION, 'description' => 'Twitter application consumer secret', 'label_for' => TweetCopier::TWITTER_CONSUMER_SECRET_OPTION ) );
		add_settings_field( TweetCopier::TWITTER_USER_TOKEN_OPTION, __( 'User token:' , 'tweet_copier_textdomain' ) ,
			array( &$this , 'render_field_auth' )  , self::SETTINGS_PAGE , self::AUTH_SECTION,
			array( 'fieldname' => TweetCopier::TWITTER_USER_TOKEN_OPTION, 'description' => 'Twitter user token', 'label_for' => TweetCopier::TWITTER_USER_TOKEN_OPTION ) );
		add_settings_field( TweetCopier::TWITTER_USER_SECRET_OPTION, __( 'User secret:' , 'tweet_copier_textdomain' ) ,
			array( &$this , 'render_field_auth' )  , self::SETTINGS_PAGE , self::AUTH_SECTION,
			array( 'fieldname' => TweetCopier::TWITTER_USER_SECRET_OPTION, 'description' => 'Twitter user secret', 'label_for' => TweetCopier::TWITTER_USER_SECRET_OPTION ) );
		add_settings_field( TweetCopier::TWITTER_USER_SCREENNAME_OPTION, __( 'Authenticated Twitter user' , 'tweet_copier_textdomain' ) ,
			array( &$this , 'render_field_auth_screenname' )  , self::SETTINGS_PAGE , self::AUTH_SECTION,
			array( 'fieldname' => TweetCopier::TWITTER_USER_SCREENNAME_OPTION, 'description' => 'The Twitter user that will be fetching tweets. Use the Authenticate button to change this.' ) );
		add_settings_field( self::TWITTERAUTH_OPTION, '',
			array( &$this , 'render_field_authenticate' )  , self::SETTINGS_PAGE , self::AUTH_SECTION,
			array( 'fieldname' => self::TWITTERAUTH_OPTION, 'description' => 'Set the details of the Twitter user that will be fetching tweets' ) );

		add_settings_field( TweetCopier::SCREENNAME_OPTION, __( 'Screen name:' , 'tweet_copier_textdomain' ) ,
			array( &$this , 'render_field_screenname' )  , self::SETTINGS_PAGE , self::FETCH_SECTION,
			array( 'fieldname' => TweetCopier::SCREENNAME_OPTION, 'description' => 'Screen name of Twitter account to copy', 'label_for' => TweetCopier::SCREENNAME_OPTION ) );
		add_settings_field( TweetCopier::HISTORY_OPTION, __( 'Copy past tweets?' , 'tweet_copier_textdomain' ) ,
			array( &$this , 'render_field_history' )  , self::SETTINGS_PAGE , self::FETCH_SECTION,
			array( 'fieldname' => TweetCopier::HISTORY_OPTION, 'description' => 'Copy all older tweets as well as new ones?' ) );

		add_settings_field( TweetCopier::TITLE_FORMAT_OPTION, __( 'Title:' , 'tweet_copier_textdomain' ) ,
			array( &$this , 'render_field_title' )  , self::SETTINGS_PAGE , self::IMPORT_SECTION,
			array( 'fieldname' => TweetCopier::TITLE_FORMAT_OPTION, 'description' => 'WordPress title to use for copied tweets' ) );
		add_settings_field( TweetCopier::AUTHOR_OPTION, __( 'Author:' , 'tweet_copier_textdomain' ) ,
			array( &$this , 'render_field_author' )  , self::SETTINGS_PAGE , self::IMPORT_SECTION,
			array( 'fieldname' => TweetCopier::AUTHOR_OPTION, 'description' => 'WordPress author to use for copied tweets', 'label_for' => TweetCopier::AUTHOR_OPTION ) );
		add_settings_field( TweetCopier::CATEGORY_OPTION, __( 'Category:' , 'tweet_copier_textdomain' ) ,
			array( &$this , 'render_field_category' )  , self::SETTINGS_PAGE , self::IMPORT_SECTION,
			array( 'fieldname' => TweetCopier::CATEGORY_OPTION, 'description' => 'Category to use for copied tweets', 'label_for' => TweetCopier::CATEGORY_OPTION ) );
		
		add_settings_field( self::SCHEDULE_OPTION, __( 'Automatic copying' , 'tweet_copier_textdomain' ) ,
			array( &$this , 'render_field_schedule' )  , self::SETTINGS_PAGE , self::SCHEDULE_SECTION,
			array( 'fieldname' => self::SCHEDULE_OPTION, 'description' => 'How often to automatically copy tweets, or <em>Manual Only<em> to use the <em>Copy Now</em> button', 'label_for' => self::SCHEDULE_OPTION ) );
		add_settings_field( self::COPYNOW_OPTION, __( 'Manual copying' , 'tweet_copier_textdomain' ) ,
			array( &$this , 'render_field_copynow' )  , self::SETTINGS_PAGE , self::SCHEDULE_SECTION,
			array( 'fieldname' => self::COPYNOW_OPTION, 'description' => 'Save your settings and copy tweets right now' ) );

		// register_setting( $option_group, $option_name, $sanitize_callback );
		register_setting( self::SETTINGS_OPTION_GROUP , TweetCopier::TWITTER_CONSUMER_KEY_OPTION , 'trim' );
		register_setting( self::SETTINGS_OPTION_GROUP , TweetCopier::TWITTER_CONSUMER_SECRET_OPTION , 'trim' );
		register_setting( self::SETTINGS_OPTION_GROUP , TweetCopier::TWITTER_USER_TOKEN_OPTION , 'trim' );
		register_setting( self::SETTINGS_OPTION_GROUP , TweetCopier::TWITTER_USER_SECRET_OPTION , 'trim' );
		// no updating register_setting( self::SETTINGS_OPTION_GROUP , TweetCopier::TWITTER_USER_SCREENNAME_OPTION , 'trim' );
		register_setting( self::SETTINGS_OPTION_GROUP , self::TWITTERAUTH_OPTION );
		register_setting( self::SETTINGS_OPTION_GROUP , TweetCopier::SCREENNAME_OPTION , array( &$this , 'sanitize_slug' ) );
		register_setting( self::SETTINGS_OPTION_GROUP , TweetCopier::HISTORY_OPTION , array( &$this , 'sanitize_slug' ) );
		register_setting( self::SETTINGS_OPTION_GROUP , TweetCopier::TITLE_FORMAT_OPTION , 'trim' );
		register_setting( self::SETTINGS_OPTION_GROUP , TweetCopier::AUTHOR_OPTION , array( &$this , 'sanitize_slug' ) );
		register_setting( self::SETTINGS_OPTION_GROUP , TweetCopier::CATEGORY_OPTION , array( &$this , 'sanitize_slug' ) );
		register_setting( self::SETTINGS_OPTION_GROUP , self::SCHEDULE_OPTION , array( &$this , 'sanitize_slug' ) );
		register_setting( self::SETTINGS_OPTION_GROUP , self::COPYNOW_OPTION );
	}

	public function auth_settings() { 
		echo '<p>'
				. __( 'Authentication details for fetching information from Twitter.' , 'tweet_copier_textdomain' )
				. ' <a class="tweet_copier_auth_expander" href="#">' . __( 'details' , 'tweet_copier_textdomain' ) . '</a>'
				. '</p>';
		echo '<div class="settings-section-content">';
		echo '<p>To fetch tweets, you need to tell Twitter who you are and what application you will be using.<p>';
		echo '<p>First, tell Twitter that you\'re using Tweet Copier as your application:<p>
			<ol>
			<li>Go to <a href="https://apps.twitter.com/">Twitter Apps</a> and sign in using your Twitter login.
			<li>Click <em>Create New App</em>
			<li>For <em>Name</em> and <em>Description</em>, type "Tweet Copier for" and your blog name, for example <tt>Tweet Copier for Spiderblog</tt>.
			<li>For <em>Website</em> and <em>Callback URL</em>, type your blog address.
			<li>Check the checkbox under the Developer Agreement and create the application.
			<li>When the next screen appears, look for the Consumer Key and click <em>manage keys and access tokens</em>.
			<li>Copy the codes next to <em>Consumer Key</em> and <em>Consumer Secret</em> and paste them below.
			</ol>';
		echo '<p>Then, tell Twitter who will be fetching the tweets. Use the <em>Authenticate</em> button below.<p>';
		echo '</div>';
	}

	public function fetch_settings() { echo '<p>' . __( 'How to fetch tweets from Twitter.' , 'tweet_copier_textdomain' ) . '</p>'; }

	public function import_settings() { echo '<p>' . __( 'How to save tweets into your blog.' , 'tweet_copier_textdomain' ) . '</p>'; }

	public function schedule_settings() { echo '<p>' . __( 'How often to copy tweets.' , 'tweet_copier_textdomain' ) . '</p>'; }

	public function render_field_auth( $args ) {

		$fieldname = $args['fieldname'];
		$description = __( $args['description'] , 'tweet_copier_textdomain' );
		$option = get_option( $fieldname );
		$value = ( self::has_text($option) ? $option : '' );
		$is_readonly = ( $value !== '' );
		$readonly_attr = $is_readonly ? 'readonly="readonly"' : '';
		echo "<input id='$fieldname' type='text' name='$fieldname' value='$value' class='description regular-text code' $readonly_attr/>";
		if ( $is_readonly ) {
			echo "<a class='twcp-edit-button' for='$fieldname' href='#' >edit</a>";
		}
		echo "<span class='description'>$description</span>";
	}

	public function render_field_auth_screenname( $args ) {

		$fieldname = $args['fieldname'];
		$description = $args['description'];
		$option = get_option( $fieldname );
		$value = ( self::has_text($option) ? $option : '' );
		echo "<input id='$fieldname' type='text' name='$fieldname' value='$value' class='description' readonly='readonly'/>";
		echo '<span class="description">' . $description . '</span>';
	}

	public function render_field_authenticate( $args ) {

		$fieldname = $args['fieldname'];
		$description = $args['description'];
		// submit_button( $text, $type, $name, $wrap, $other_attributes )
		submit_button( __( 'Authenticate' , 'tweet_copier_textdomain' ), 'secondary', $fieldname, false );
		echo '<span class="description">' . $description . '</span>';
	}

	public function render_field_screenname( $args ) {

		$fieldname = $args['fieldname'];
		$description = __( $args['description'] , 'tweet_copier_textdomain' );
		$option = get_option( $fieldname );
		$value = ( self::has_text($option) ? $option : '' );
		echo "<span class='description'>@</span>
			<input id='$fieldname' type='text' name='$fieldname' value='$value' class='description'/>
			<span class='description'>$description</span>";
	}

	public function render_field_history( $args ) {

		$fieldname = $args['fieldname'];
		$description = $args['description'];
		$option = get_option( $fieldname );
		$checked = ( $option ? ' checked="checked" ' : ' ');
		echo '<label for="' . $fieldname . '"><input id="' . $fieldname . '" type="checkbox" name="' . $fieldname . '" ' . $checked . '/>
				<span class="description">' . __( $description , 'tweet_copier_textdomain' ) . '</span></label>';
		if ( get_option( TweetCopier::HISTORY_COMPLETE_OPTION )) {
			echo '<span class="description">' . __( ' (We\'ve already copied all old tweets.)' , 'tweet_copier_textdomain' ) . '</span>';
		}
	}

	public function render_field_title( $args ) {

		$title_formats = array(
			array( 'id' => 'text',   'format' => '%t', 'description' => __( 'The first few words of the tweet text' , 'tweet_copier_textdomain' ) ),
			array( 'id' => 'time',   'format' => '%d', 'description' => ( __( 'The date and time of the tweet, for example ' , 'tweet_copier_textdomain' ) ) . date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ),
			array( 'id' => 'empty',  'format' => '',   'description' => __( 'No title' , 'tweet_copier_textdomain' ) ),
			);
		$fieldname = $args['fieldname'];
		$description = $args['description'];
		$option = get_option( $fieldname );
		$value = ( self::has_text($option) ? $option : '%t' );
		$is_rendered = false; // so far
		foreach ( $title_formats as $format ) {
			$checked_state = '';
			if ( $value == $format['format'] ) {
				$is_rendered = true;
				$checked_state = 'checked="checked"';
			}
			echo "<label for='{$fieldname}_{$format['id']}'>
				<input id='{$fieldname}_{$format['id']}' data-format='{$format['format']}' type='radio' name='{$fieldname}_radio' class='{$fieldname}_fixed' $checked_state />
				<span class='description'>{$format['description']}</span>
				</label>
				<br>";
		}
		$checked_state = ( ! $is_rendered ? 'checked="checked"' : '' );
		echo "<label for='{$fieldname}_custom'><input id='{$fieldname}_custom' type='radio' name='{$fieldname}_radio' $checked_state />
			<span class='description'>" . __( 'This title' , 'tweet_copier_textdomain' ) . "</span></label>
			";
		echo "<input id='$fieldname' type='text' name='$fieldname' value='$value' class='description'/>";
	}

	public function render_field_author( $args ) {

		$fieldname = $args['fieldname'];
		$description = $args['description'];
		$option = get_option( $fieldname );
		$value = ( self::has_text($option) ? $option : '' );
		wp_dropdown_users( array( 'id' => $fieldname, 'name' => $fieldname, 'selected' => $value ) );
		echo '<span class="description">' . __( $description , 'tweet_copier_textdomain' ) . '</span>';
	}

	public function render_field_category( $args ) {

		$fieldname = $args['fieldname'];
		$description = $args['description'];
		$option = get_option( $fieldname );
		$value = ( self::has_text($option) ? $option : '' );

		$categories = get_categories( 'hide_empty=0' );

		// Figure out which category should be initially selected
		if ( $value != '' ) {
			$selected_id = $value;
		} else {
			$selected_id = 1; // default
			foreach ( $categories as $category ) {
				$cat_name = ! empty($category->name) ? $category->name : $category->cat_name;
				if ($cat_name == 'Twitter' || $cat_name == 'Tweets' || $cat_name == 'Tweet') {
					$cat_id = ! empty($category->term_id) ? $category->term_id : $category->cat_ID;
					$selected_id = $cat_id;
					break;
				}
			}
		}

		echo '<select name="' . $fieldname . '" id="' . $fieldname . '" >';
		foreach ( $categories as $category ) {
			$cat_id = ! empty($category->term_id) ? $category->term_id : $category->cat_ID;
			$cat_name = ! empty($category->name) ? $category->name : $category->cat_name;
			$selected = ($cat_id == $selected_id) ? 'selected="selected"' : '';
			echo '<option value="' . $cat_id . '" ' . $selected . '>' . $cat_name . '</option>';
		}
		echo '</select>';
		echo '<span class="description">' . __( $description , 'tweet_copier_textdomain' ) . '</span>';
	}

	public function render_field_schedule( $args ) {

		$fieldname = $args['fieldname'];
		$description = $args['description'];
		$option = get_option( $fieldname );
		$value = ( self::has_text($option) ? $option : 'daily' );
		$schedules = array( self::SCHEDULE_VALUE_MANUAL => array( 'display' => 'Manual Only' ))
		           + wp_get_schedules();

		echo '<select name="' . $fieldname . '" id="' . $fieldname . '" >';
		foreach ( $schedules as $schedule => $schedule_desc ) {
			$selected = ( $value == $schedule ) ? 'selected="selected"' : '';
			echo '<option value="' . $schedule . '" ' . $selected . '>' . $schedule_desc['display'] . '</option>';
		}
		echo '</select>';
		echo '<span class="description">' . __( $description , 'tweet_copier_textdomain' ) . '</span>';
	}

	public function render_field_copynow( $args ) {

		$fieldname = $args['fieldname'];
		$description = $args['description'];
		// submit_button( $text, $type, $name, $wrap, $other_attributes )
		submit_button( __( 'Copy Now' , 'tweet_copier_textdomain' ), 'secondary', $fieldname, false );
		echo '<span class="description">' . $description . '</span>';
	}

	// Process a click on the Twitter Auth button
	public function filter_twitter_auth( $newvalue, $oldvalue ) {

		// HACK: updated options are available here, but only because this button comes after the form fields.
		// If there's a new value then this button was clicked, so do the copy now
		if ( $newvalue != '' ) {
			$this->twitter_request_token();
		}
		// Return the old value so it doesn't get saved
		return $oldvalue;
	}

	// Process a change in the filter setting, by updating the schedule
	public function filter_schedule( $newvalue, $oldvalue ) {
		if ( $newvalue !== $oldvalue ) {
			wp_clear_scheduled_hook( TweetCopier::SCHEDULE_HOOK );
			if ( $newvalue !== self::SCHEDULE_VALUE_MANUAL ) {
				wp_schedule_event( time(), $newvalue, TweetCopier::SCHEDULE_HOOK );
			}
		}
		return $newvalue;
	}

	// Process a click on the Copy Now button
	public function filter_copy_now( $newvalue, $oldvalue ) {

		// HACK: updated options are available here, but only because this button comes after the form fields.
		// If there's a new value then this button was clicked, so do the copy now
		if ( $newvalue != '' ) {
			$this->plugin->copy_tweets();
		}
		// Return the old value so it doesn't get saved
		return $oldvalue;
	}

	public function sanitize_slug( $slug ) {
		if( $slug && 0 < strlen( $slug ) && $slug != '' ) {
			$slug = urlencode( strtolower( str_replace( ' ' , '-' , $slug ) ) );
		}
		return $slug;
	}

	/**
	 * Prints out all settings sections added to a particular settings page.
	 *
	 * This is copied and modified from wp-admin/includes/template.php
	 * Here we add classes for each section and title and content to allow styling.
	 */
	private function do_settings_sections( $page ) {
		global $wp_settings_sections, $wp_settings_fields;

		if ( ! isset( $wp_settings_sections ) || !isset( $wp_settings_sections[$page] ) )
			return;

		foreach ( (array) $wp_settings_sections[$page] as $section ) {
			echo "<div class=\"settings-section settings-section-{$section['id']}\">\n";
			if ( $section['title'] )
				echo "<h3 class=\"settings-section-title\">{$section['title']}</h3>\n";

			if ( $section['callback'] )
				call_user_func( $section['callback'], $section );

			echo "<div class=\"settings-section-content\">\n";
			if ( ! isset( $wp_settings_fields ) || !isset( $wp_settings_fields[$page] ) || !isset( $wp_settings_fields[$page][$section['id']] ) ) {
				// do nothing
			} else {
				echo '<table class="form-table">';
				$this->do_settings_fields( $page, $section['id'] );
				echo '</table>';
			}
			echo "</div>\n";
			echo "</div>\n";
		}
	}

	/**
	 * Print out the settings fields for a particular settings section
	 *
	 * This is copied and modified from wp-admin/includes/template.php
	 * Here we add classes for each row to allow styling.
	 */
	function do_settings_fields($page, $section) {
		global $wp_settings_fields;

		if ( !isset($wp_settings_fields) || !isset($wp_settings_fields[$page]) || !isset($wp_settings_fields[$page][$section]) )
			return;

		foreach ( (array) $wp_settings_fields[$page][$section] as $field ) {
			echo "<tr valign=\"top\" class=\"settings-field settings-field-{$field['id']}\">";
			if ( !empty($field['args']['label_for']) )
				echo '<th scope="row"><label for="' . esc_attr( $field['args']['label_for'] ) . '">' . $field['title'] . '</label></th>';
			else
				echo '<th scope="row">' . $field['title'] . '</th>';
			echo '<td>';
			call_user_func($field['callback'], $field['args']);
			echo '</td>';
			echo '</tr>';
		}
	}
	public function settings_page() {

		$class = $this->is_auth_configuration_complete() ? '' : 'auth-config-incomplete';
		echo '<div class="wrap">
				<div class="icon32" id="icon-options-general"><br/></div>
				<h2>Tweet Copier Settings</h2>
				<form method="post" action="options.php" enctype="multipart/form-data" class="' . $class . '">';

				// settings_fields( $option_group )
				settings_fields( self::SETTINGS_OPTION_GROUP );
				// do_settings_sections( $page )
				$this->do_settings_sections( self::SETTINGS_PAGE );
				
				submit_button( __( 'Save Settings' , 'tweet_copier_textdomain' ) );
				
		echo '</form>';
		echo '<h3 class="title">Recent Results</h2>
			<table>
				<tr><th style="text-align: left;">Last empty</th><td>'  . $this->plugin->get_checkpoint( 'empty' ) . '</td</tr>
				<tr><th style="text-align: left;">Last copy</th><td>'   . $this->plugin->get_checkpoint( 'copy' ) . '</td</tr>
				<tr><th style="text-align: left;">Last error</th><td>'  . $this->plugin->get_checkpoint( 'error' ) . '</td</tr>
			</table>';

		$this->render_donation_message();
		echo '</div>';
	}

	private function render_donation_message() {
	?>
	<h3 class="title">Do you find this plugin useful?</h2>
	<p><div style="margin: 0; padding: 0 2ex 0.25ex 0; float: left;">
	<?php $this->render_donation_button() ?>
	</div>
	I write WordPress plugins because I enjoy doing it, but it does take up a lot
	of my time. If you think this plugin is useful, please consider donating some appropriate
	amount by clicking the <strong>Donate</strong> button. You can also send <strong>Bitcoins</strong>
	to address <tt>1542gqyprvQd7gwvtZZ4x25cPeGWVKg45x</tt>. Thanks!</p>
	<?php
	}

	private function render_donation_button() {
		// This donation code is specific to this plugin
		?><form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top"
		><input type="hidden" name="cmd" value="_s-xclick"
		><input type="hidden" name="encrypted" value="-----BEGIN PKCS7-----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-----END PKCS7-----"
		><input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!"
		><img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1"
		></form><?php
	}

	private static function has_text( $string ) {
		return ( is_string( $string ) && 0 < strlen( $string ) );
	}

	private function twitter_request_token() {
		// Step 1: Request a temporary token and
		// Step 2: Direct the user to the authorize web page
		$twitter_api = new tmhOAuth(array(
			'consumer_key'    => get_option( TweetCopier::TWITTER_CONSUMER_KEY_OPTION ),
			'consumer_secret' => get_option( TweetCopier::TWITTER_CONSUMER_SECRET_OPTION ),
		));
		$code = $twitter_api->apponly_request(array(
			'without_bearer' => true,
			'method' => 'POST',
			'url' => $twitter_api->url('oauth/request_token', ''),
			'params' => array(
				'oauth_callback' => $this->get_settings_url_absolute(),
			),
		));

		if ( $code != 200 ) {
			$this->plugin->checkpoint( 'error', __('There was an error communicating with Twitter: ' . $twitter_api->response['response']));
			return;
		}

		// store the params so they are there when we come back after the redirect
		$oauth = $twitter_api->extract_params($twitter_api->response['response']);
		set_transient( 'tweet-copy-oauth-' . get_current_user_id(), $oauth, MINUTE_IN_SECONDS );

		// check the callback has been confirmed
		if ( $oauth['oauth_callback_confirmed'] !== 'true' ) {
			$this->plugin->checkpoint( 'error', __('Twitter authentication callback was not confirmed by Twitter.'));
		} else {
			$url = $twitter_api->url('oauth/authorize', '') . "?oauth_token={$oauth['oauth_token']}";
			header( 'Location: ' . $url );
			// stop now and let the redirect happen
			exit;
		}
	}

	public function check_for_twitter_response() {
		$params = $this->uri_params();
		if ( isset( $params['oauth_token'] )) {
			$this->twitter_access_token($params);
		}
	}

	private function twitter_access_token($params) {
		if ( TWEET_COPIER_DEBUG ) twcp_debug( 'twitter_access_token');
		$oauth = get_transient( 'tweet-copy-oauth-' . get_current_user_id() );

		if ($params['oauth_token'] !== $oauth['oauth_token']) {
			$this->plugin->checkpoint( 'error', __('Twitter authentication token mismatch. Do you have multiple tabs open?'));
			return;
		}

		if (!isset($params['oauth_verifier'])) {
			$this->plugin->checkpoint( 'error', __('Twitter authentication oauth verifier missing. Did you deny the appliction access?'));
			return;
		}

		// update with the temporary token and secret
		$twitter_api = new tmhOAuth(array(
			'consumer_key'    => get_option( TweetCopier::TWITTER_CONSUMER_KEY_OPTION ),
			'consumer_secret' => get_option( TweetCopier::TWITTER_CONSUMER_SECRET_OPTION ),
		));
		$twitter_api->reconfigure(array_merge($twitter_api->config, array(
			'token'  => $oauth['oauth_token'],
			'secret' => $oauth['oauth_token_secret'],
		)));

		if ( TWEET_COPIER_DEBUG ) twcp_debug( 'twitter_access_token requesting permanent token');
		// Request the permanent token
		$code = $twitter_api->user_request(array(
			'method' => 'POST',
			'url' => $twitter_api->url('oauth/access_token', ''),
			'params' => array(
				'oauth_verifier' => trim($params['oauth_verifier']),
			)
		));

		if ( TWEET_COPIER_DEBUG ) twcp_debug( 'twitter_access_token result ' . $code);
		if ( $code == 200 ) {
			$oauth_creds = $twitter_api->extract_params($twitter_api->response['response']);
			update_option( TweetCopier::TWITTER_USER_TOKEN_OPTION, $oauth_creds['oauth_token'] );
			update_option( TweetCopier::TWITTER_USER_SECRET_OPTION, $oauth_creds['oauth_token_secret'] );
			update_option( TweetCopier::TWITTER_USER_SCREENNAME_OPTION, $oauth_creds['screen_name'] );
			$this->plugin->checkpoint( 'info', __('Twitter authentication details have been saved') );
			if ( TWEET_COPIER_DEBUG ) twcp_debug( 'user token: ' . $oauth_creds['oauth_token']);
			if ( TWEET_COPIER_DEBUG ) twcp_debug( 'user secret: ' . $oauth_creds['oauth_token_secret']);
			if ( TWEET_COPIER_DEBUG ) twcp_debug( 'user screen name: ' . $oauth_creds['screen_name']);
		}
	}
	
	function uri_params() {
		$url = parse_url($_SERVER['REQUEST_URI']);
		$params = array();
		if ( isset( $url['query'] )) {
			foreach (explode('&', $url['query']) as $p) {
				list($k, $v) = explode('=', $p);
				$params[$k] =$v;
			}
		}
		return $params;
	}
}
