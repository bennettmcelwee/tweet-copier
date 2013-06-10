<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TweetMirrorSettings {
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
		add_options_page( 'Tweet Mirror Settings' , 'Tweet Mirror Settings' , 'manage_options' , 'tweet_mirror_settings' ,  array( &$this , 'settings_page' ) );
	}

	public function add_settings_link( $links ) {
		$settings_link = '<a href="options-general.php?page=tweet_mirror_settings">Settings</a>';
  		array_push( $links, $settings_link );
  		return $links;
	}

	public function register_settings() {
		
		// add_settings_section( $id, $title, $callback, $page );
		add_settings_section( 'tweet_mirror_main_settings' , __( 'Mirroring tweets' , 'tweet_mirror_textdomain' ) , array( &$this , 'main_settings' ) , 'tweet_mirror_settings' );
		
		// add_settings_field( $id, $title, $callback, $page, $section, $args );
		add_settings_field( 'tweet_mirror_field1' , __( 'Field 1:' , 'tweet_mirror_textdomain' ) , array( &$this , 'settings_field' )  , 'tweet_mirror_settings' , 'tweet_mirror_main_settings' );
		
		// register_setting( $option_group, $option_name, $sanitize_callback );
		register_setting( 'tweet_mirror_settings' , 'tweet_mirror_field1' , array( &$this , 'validate_field' ) );

	}

	public function main_settings() { echo '<p>' . __( 'Change these settings ot customise your plugin.' , 'tweet_mirror_textdomain' ) . '</p>'; }

	public function settings_field() {

		$option = get_option('tweet_mirror_field1');

		$data = '';
		if( $option && strlen( $option ) > 0 && $option != '' ) {
			$data = $option;
		}

		echo '<input id="slug" type="text" name="tweet_mirror_field1" value="' . $data . '"/>
				<label for="slug"><span class="description">' . __( 'Descipriton of settings field' , 'tweet_mirror_textdomain' ) . '</span></label>';
	}

	public function validate_field( $slug ) {
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

				settings_fields( 'plugin_settings' );
				do_settings_sections( 'plugin_settings' );

				submit_button( __( 'Save Settings' , 'tweet_mirror_textdomain' ) );
				
				submit_button( __( 'Import Now' , 'tweet_mirror_textdomain' ), 'secondary', 'import_now' );
				
				
		echo '</form>
			  </div>';
	}
	
//	pre_update_option_tweet_mirror_import_now
	public function import_now_filter( $newvalue, $oldvalue ) {
		add_settings_error('general', 'tweets_imported', __('Tweets imported (not really though!)'), 'updated');
		return $oldvalue;
	}

}