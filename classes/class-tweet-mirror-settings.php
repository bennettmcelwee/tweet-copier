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
	}
	
	public function add_menu_item() {
		add_options_page( 'Tweet Mirror Settings' , 'Tweet Mirror Settings' , 'manage_options' , 'tweet_mirror_settings' ,  array( &$this , 'settings_page' ) );
	}

	public function add_settings_link( $links ) {
		$settings_link = '<a href="options-general.php?page=tweet_mirror_settings">Settings</a>';
  		array_push( $links, $settings_link );
  		return $links;
	}

	public function register_settings() {
		
		// Add settings section
		add_settings_section( 'tweet_mirror_main_settings' , __( 'Mirroring tweets' , 'tweet_mirror_textdomain' ) , array( &$this , 'main_settings' ) , 'tweet_mirror_settings' );
		
		// Add settings fields
		add_settings_field( 'tweet_mirror_field1' , __( 'Field 1:' , 'tweet_mirror_textdomain' ) , array( &$this , 'settings_field' )  , 'tweet_mirror_settings' , 'tweet_mirror_main_settings' );
		
		// Register settings fields
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
				<div class="icon32" id="plugin_settings-icon"><br/></div>
				<h2>Tweet Mirror Settings</h2>
				<form method="post" action="options.php" enctype="multipart/form-data">';

				settings_fields( 'plugin_settings' );
				do_settings_sections( 'plugin_settings' );

			  echo '<p class="submit">
						<input name="Submit" type="submit" class="button-primary" value="' . esc_attr( __( 'Save Settings' , 'tweet_mirror_textdomain' ) ) . '" />
					</p>
				</form>
			  </div>';
	}
	
}