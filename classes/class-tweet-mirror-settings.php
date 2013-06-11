<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TweetMirrorSettings {

	const SETTINGS_PAGE = 'tweet_mirror_settings';
	const SETTINGS_OPTION_GROUP = 'tweet_mirror_options1';
	const MIRRORING_SECTION = 'tweet_mirror_main_settings';

	const SCREENNAME_FIELD = 'tweet_mirror_screenname';
	const POSTTYPE_FIELD = 'tweet_mirror_posttype';
	const CATEGORY_FIELD = 'tweet_mirror_category';
	const AUTHOR_FIELD = 'tweet_mirror_author';

	private $dir;
	private $file;
	private $assets_dir;
	private $assets_url;

	public function __construct( $file ) {
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
		
		// Handle the Import Now button
		add_filter( 'pre_update_option_tweet_mirror_import_now' , array( &$this , 'import_now_filter' ) );
		
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
		add_settings_section( self::MIRRORING_SECTION , __( 'Mirroring tweets' , 'tweet_mirror_textdomain' ) , array( &$this , 'main_settings' ) , self::SETTINGS_PAGE );
		
		// add_settings_field( $id, $title, $callback, $page, $section, $args );
		add_settings_field( self::SCREENNAME_FIELD, __( 'Screen name:' , 'tweet_mirror_textdomain' ) ,
			array( &$this , 'settings_field_string' )  , self::SETTINGS_PAGE , self::MIRRORING_SECTION,
			array( 'fieldname' => self::SCREENNAME_FIELD, 'description' => 'Screen name of Twitter account to mirror', 'label_for' => self::SCREENNAME_FIELD ) );
		add_settings_field( self::AUTHOR_FIELD, __( 'Author:' , 'tweet_mirror_textdomain' ) ,
			array( &$this , 'settings_field_author' )  , self::SETTINGS_PAGE , self::MIRRORING_SECTION,
			array( 'fieldname' => self::AUTHOR_FIELD, 'description' => 'Author to use for mirrored tweets', 'label_for' => self::AUTHOR_FIELD ) );
		
		// register_setting( $option_group, $option_name, $sanitize_callback );
		register_setting( self::SETTINGS_OPTION_GROUP , self::SCREENNAME_FIELD , array( &$this , 'sanitize_slug' ) );
		register_setting( self::SETTINGS_OPTION_GROUP , self::AUTHOR_FIELD , array( &$this , 'sanitize_slug' ) );
		register_setting( self::SETTINGS_OPTION_GROUP , 'tweet_mirror_import_now' );
	}

	public function main_settings() { echo '<p>' . __( 'Change these settings ot customise your plugin.' , 'tweet_mirror_textdomain' ) . '</p>'; }

	public function settings_field_string( $args ) {

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

	public function settings_field_author( $args ) {

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
				
				submit_button( __( 'Import Now' , 'tweet_mirror_textdomain' ), 'secondary', 'tweet_mirror_import_now' );
				
				
		echo '</form>
			  </div>';
	}
	
//	pre_update_option_tweet_mirror_import_now
	public function import_now_filter( $newvalue, $oldvalue ) {
		// If there's a new value then this button was clicked, so do the import
		if ( $newvalue != '' ) {
			// HACK: updated options are available here, but only because this button comes after the form fields.
			add_settings_error('general', 'tweets_imported', __('Will import from ') . ' [' . get_option( self::SCREENNAME_FIELD ) . ']', 'updated');
		}
		// Return the old value so it doesn't get saved
		return $oldvalue;
	}

}