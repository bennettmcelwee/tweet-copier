<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TweetMirrorSettings {

	const SETTINGS_PAGE = 'tweet_mirror_settings';
	const SETTINGS_OPTION_GROUP = 'tweet_mirror_options1';
	const FETCH_SECTION = 'tweet_mirror_fetch_settings';
	const IMPORT_SECTION = 'tweet_mirror_import_settings';
	const SCHEDULE_SECTION = 'tweet_mirror_schedule_settings';

	// These options are used only in the settings page, not by the plugin
	const SCHEDULE_OPTION = 'tweet_mirror_schedule';
	const SCHEDULE_VALUE_MANUAL = 'manual';
	const IMPORTNOW_OPTION = 'tweet_mirror_import_now';

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

		// Register plugin settings
		add_action( 'admin_init' , array( &$this , 'register_settings' ) );

		// Add settings page to menu
		add_action( 'admin_menu' , array( &$this , 'add_menu_item' ) );

		// Add settings link to plugins page
		add_filter( 'plugin_action_links_' . plugin_basename( $this->file ) , array( &$this , 'add_settings_link' ) );
		
		// Set up filters to run actions as settings are saved
		add_filter( 'pre_update_option_' . self::SCHEDULE_OPTION , array( &$this , 'filter_schedule' ), 10, 2 );
		add_filter( 'pre_update_option_' . self::IMPORTNOW_OPTION , array( &$this , 'filter_import_now' ), 10, 2 );
		
	}
	
	public function add_menu_item() {
		// add_options_page( $page_title, $menu_title, $capability, $menu_slug, $function);
		add_options_page( 'Tweet Mirror Settings' , 'Tweet Mirror Settings' , 'manage_options' , self::SETTINGS_PAGE ,  array( &$this , 'settings_page' ) );
	}

	public function add_settings_link( $links ) {
		$settings_link = '<a href="options-general.php?page=' . self::SETTINGS_PAGE . '">Settings</a>';
  		array_push( $links, $settings_link );
  		return $links;
	}

	public function register_settings() {
		
		// add_settings_section( $id, $title, $callback, $page );
		add_settings_section( self::FETCH_SECTION , __( 'Fetching tweets from Twitter' , 'tweet_mirror_textdomain' ) , array( &$this , 'fetch_settings' ) , self::SETTINGS_PAGE );
		add_settings_section( self::IMPORT_SECTION , __( 'Saving tweets into WordPress' , 'tweet_mirror_textdomain' ) , array( &$this , 'import_settings' ) , self::SETTINGS_PAGE );
		add_settings_section( self::SCHEDULE_SECTION , __( 'Scheduling' , 'tweet_mirror_textdomain' ) , array( &$this , 'schedule_settings' ) , self::SETTINGS_PAGE );
		
		// add_settings_field( $id, $title, $callback, $page, $section, $args );
		add_settings_field( TweetMirror::SCREENNAME_OPTION, __( 'Screen name:' , 'tweet_mirror_textdomain' ) ,
			array( &$this , 'render_field_string' )  , self::SETTINGS_PAGE , self::FETCH_SECTION,
			array( 'fieldname' => TweetMirror::SCREENNAME_OPTION, 'description' => 'Screen name of Twitter account to mirror', 'label_for' => TweetMirror::SCREENNAME_OPTION ) );
		add_settings_field( TweetMirror::HISTORY_OPTION, __( 'Import entire history?' , 'tweet_mirror_textdomain' ) ,
			array( &$this , 'render_field_history' )  , self::SETTINGS_PAGE , self::FETCH_SECTION,
			array( 'fieldname' => TweetMirror::HISTORY_OPTION, 'description' => 'Mirror historical tweets as well as new ones?' ) );

		add_settings_field( TweetMirror::AUTHOR_OPTION, __( 'Author:' , 'tweet_mirror_textdomain' ) ,
			array( &$this , 'render_field_author' )  , self::SETTINGS_PAGE , self::IMPORT_SECTION,
			array( 'fieldname' => TweetMirror::AUTHOR_OPTION, 'description' => 'WordPress author to use for mirrored tweets', 'label_for' => TweetMirror::AUTHOR_OPTION ) );
		add_settings_field( TweetMirror::POSTTYPE_OPTION, __( 'Post type:' , 'tweet_mirror_textdomain' ) ,
			array( &$this , 'render_field_posttype' )  , self::SETTINGS_PAGE , self::IMPORT_SECTION,
			array( 'fieldname' => TweetMirror::POSTTYPE_OPTION, 'description' => 'WordPress post type to use for mirrored tweets', 'label_for' => TweetMirror::POSTTYPE_OPTION ) );
		add_settings_field( TweetMirror::CATEGORY_OPTION, __( 'Category:' , 'tweet_mirror_textdomain' ) ,
			array( &$this , 'render_field_category' )  , self::SETTINGS_PAGE , self::IMPORT_SECTION,
			array( 'fieldname' => TweetMirror::CATEGORY_OPTION, 'description' => 'Category to use for mirrored tweets', 'label_for' => TweetMirror::CATEGORY_OPTION ) );
		
		add_settings_field( self::SCHEDULE_OPTION, __( 'Schedule:' , 'tweet_mirror_textdomain' ) ,
			array( &$this , 'render_field_schedule' )  , self::SETTINGS_PAGE , self::SCHEDULE_SECTION,
			array( 'fieldname' => self::SCHEDULE_OPTION, 'description' => 'Schedule for fetching tweets to mirror', 'label_for' => self::SCHEDULE_OPTION ) );
		add_settings_field( self::IMPORTNOW_OPTION, __( 'Manual mirror' , 'tweet_mirror_textdomain' ) ,
			array( &$this , 'render_field_importnow' )  , self::SETTINGS_PAGE , self::SCHEDULE_SECTION,
			array( 'fieldname' => self::IMPORTNOW_OPTION, 'description' => 'Save settings and mirror tweets right now' ) );

		// register_setting( $option_group, $option_name, $sanitize_callback );
		register_setting( self::SETTINGS_OPTION_GROUP , TweetMirror::SCREENNAME_OPTION , array( &$this , 'sanitize_slug' ) );
		register_setting( self::SETTINGS_OPTION_GROUP , TweetMirror::HISTORY_OPTION , array( &$this , 'sanitize_slug' ) );
		register_setting( self::SETTINGS_OPTION_GROUP , TweetMirror::AUTHOR_OPTION , array( &$this , 'sanitize_slug' ) );
		register_setting( self::SETTINGS_OPTION_GROUP , TweetMirror::POSTTYPE_OPTION , array( &$this , 'sanitize_slug' ) );
		register_setting( self::SETTINGS_OPTION_GROUP , TweetMirror::CATEGORY_OPTION , array( &$this , 'sanitize_slug' ) );
		register_setting( self::SETTINGS_OPTION_GROUP , self::SCHEDULE_OPTION , array( &$this , 'sanitize_slug' ) );
		register_setting( self::SETTINGS_OPTION_GROUP , self::IMPORTNOW_OPTION );
	}

	public function fetch_settings() { echo '<p>' . __( 'How to fetch tweets from Twitter.' , 'tweet_mirror_textdomain' ) . '</p>'; }

	public function import_settings() { echo '<p>' . __( 'How to save tweets into your blog.' , 'tweet_mirror_textdomain' ) . '</p>'; }

	public function schedule_settings() { echo '<p>' . __( 'How often to mirror tweets.' , 'tweet_mirror_textdomain' ) . '</p>'; }

	public function render_field_string( $args ) {

		$fieldname = $args['fieldname'];
		$description = $args['description'];
		$option = get_option( $fieldname );
		$value = '';
		if ( $option && strlen( $option ) > 0 && $option != '' ) {
			$value = $option;
		}
		echo '<input id="' . $fieldname . '" type="text" name="' . $fieldname . '" value="' . $value . '"/>
				<span class="description">' . __( $description , 'tweet_mirror_textdomain' ) . '</span>';
	}

	public function render_field_history( $args ) {

		$fieldname = $args['fieldname'];
		$description = $args['description'];
		$option = get_option( $fieldname );
		$checked = ( $option ? ' checked="checked" ' : ' ');
		echo '<label for="' . $fieldname . '"><input id="' . $fieldname . '" type="checkbox" name="' . $fieldname . '" ' . $checked . '/>
				<span class="description">' . __( $description , 'tweet_mirror_textdomain' ) . '</span></label>';
		if ( get_option( TweetMirror::HISTORY_COMPLETE_OPTION )) {
			echo '<span class="description">' . __( ' (There are currently no historical tweets left to mirror.)' , 'tweet_mirror_textdomain' ) . '</span>';
		}
	}

	public function render_field_author( $args ) {

		$fieldname = $args['fieldname'];
		$description = $args['description'];
		$option = get_option( $fieldname );
		$value = '';
		if ( $option && strlen( $option ) > 0 && $option != '' ) {
			$value = $option;
		}
		wp_dropdown_users( array( 'id' => $fieldname, 'name' => $fieldname, 'selected' => $value ) );
		echo '<span class="description">' . __( $description , 'tweet_mirror_textdomain' ) . '</span>';
	}

	public function render_field_posttype( $args ) {

		$fieldname = $args['fieldname'];
		$description = $args['description'];
		$option = get_option( $fieldname );
		$value = '';
		if ( $option && strlen( $option ) > 0 && $option != '' ) {
			$value = $option;
		}

		$post_types = get_post_types( array( 'public' => true ), 'objects' );

		// Figure out which type should be initially selected
		$selected_post_type = $value;
		if ( $value === '' ) {
			// Pre-select an appropriate type if there is one
			foreach ( $post_types as $post_type_id => $post_type ) {
				if ($post_type->label == 'Twitter' || $post_type->label == 'Tweets' || $post_type->label == 'Tweet') {
					$selected_post_type = $post_type_id;
					break;
				}
			}
		}

		// Render the list
		echo '<select name="' . $fieldname . '" id="' . $fieldname . '" >';
		foreach ( $post_types  as $post_type_id => $post_type ) {
			$selected = ( $selected_post_type == $post_type_id ) ? 'selected="selected"' : '';
			echo '<option value="' . $post_type_id . '" ' . $selected . '>' . $post_type->label . '</option>';
		}
		echo '</select>';
		echo '<span class="description">' . __( $description , 'tweet_mirror_textdomain' ) . '</span>';
	}

	public function render_field_category( $args ) {

		$fieldname = $args['fieldname'];
		$description = $args['description'];
		$option = get_option( $fieldname );
		$value = '';
		if ( $option && strlen( $option ) > 0 && $option != '' ) {
			$value = $option;
		}

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
		echo '<span class="description">' . __( $description , 'tweet_mirror_textdomain' ) . '</span>';
	}

	public function render_field_schedule( $args ) {

		$fieldname = $args['fieldname'];
		$description = $args['description'];
		$option = get_option( $fieldname );
		$value = '';
		if ( $option && strlen( $option ) > 0 && $option != '' ) {
			$value = $option;
		}
		$schedules = array( self::SCHEDULE_VALUE_MANUAL => array( 'display' => 'Manual only' ))
		           + wp_get_schedules();

		echo '<select name="' . $fieldname . '" id="' . $fieldname . '" >';
		foreach ( $schedules as $schedule => $schedule_desc ) {
			$selected = ( $value == $schedule ) ? 'selected="selected"' : '';
			echo '<option value="' . $schedule . '" ' . $selected . '>' . $schedule_desc['display'] . '</option>';
		}
		echo '</select>';
		echo '<span class="description">' . __( $description , 'tweet_mirror_textdomain' ) . '</span>';
	}

	public function render_field_importnow( $args ) {

		$fieldname = $args['fieldname'];
		$description = $args['description'];
		// submit_button( $text, $type, $name, $wrap, $other_attributes )
		submit_button( __( 'Import Now' , 'tweet_mirror_textdomain' ), 'secondary', $fieldname, false );
		echo '<span class="description">' . $description . '</span>';
	}

	// Process a change in the filter setting, by updating the scheuld
	public function filter_schedule( $newvalue, $oldvalue ) {
		if ( $newvalue !== $oldvalue ) {
			wp_clear_scheduled_hook( TweetMirror::SCHEDULE_HOOK );
			if ( $newvalue !== self::SCHEDULE_VALUE_MANUAL ) {
				wp_schedule_event( time(), $newvalue, TweetMirror::SCHEDULE_HOOK );
			}
		}
		return $newvalue;
	}

	// Process a click on the Import Now button
	public function filter_import_now( $newvalue, $oldvalue ) {

		// HACK: updated options are available here, but only because this button comes after the form fields.
		// If there's a new value then this button was clicked, so do the import
		if ( $newvalue != '' ) {
			$this->plugin->import_tweets();
		}
		// Return the old value so it doesn't get saved
		return $oldvalue;
	}

	public function sanitize_slug( $slug ) {
		if( $slug && strlen( $slug ) > 0 && $slug != '' ) {
			$slug = urlencode( strtolower( str_replace( ' ' , '-' , $slug ) ) );
		}
		return $slug;
	}

	public function settings_page() {

		echo '<div class="wrap">
				<div class="icon32" id="icon-options-general"><br/></div>
				<h2>Tweet Mirror Settings</h2>
				<form method="post" action="options.php" enctype="multipart/form-data">';

				// settings_fields( $option_group )
				settings_fields( self::SETTINGS_OPTION_GROUP );
				// do_settings_sections( $page )
				do_settings_sections( self::SETTINGS_PAGE );
				
				submit_button( __( 'Save Settings' , 'tweet_mirror_textdomain' ) );
				
		echo '</form>';
		echo '<h3 class="title">Recent Results</h2>
			<table>
				<tr><th style="text-align: left;">Last empty</th><td>'  . get_option( 'tweet_mirror_last_empty' ) . '</td</tr>
				<tr><th style="text-align: left;">Last import</th><td>' . get_option( 'tweet_mirror_last_import' ) . '</td</tr>
				<tr><th style="text-align: left;">Last error</th><td>'  . get_option( 'tweet_mirror_last_error' ) . '</td</tr>
			</table>';

		echo '</div>';
	}
	
}
