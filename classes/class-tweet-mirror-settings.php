<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TweetMirrorSettings {

	const SETTINGS_PAGE = 'tweet_mirror_settings';
	const SETTINGS_OPTION_GROUP = 'tweet_mirror_options1';
	const MIRRORING_SECTION = 'tweet_mirror_main_settings';

	const SCREENNAME_OPTION = 'tweet_mirror_screenname';
	const SCHEDULE_OPTION = 'tweet_mirror_schedule';
	const POSTTYPE_OPTION = 'tweet_mirror_posttype';
	const AUTHOR_OPTION = 'tweet_mirror_author';
	const CATEGORY_OPTION = 'tweet_mirror_category';
	const HISTORY_OPTION = 'tweet_mirror_history';
	const HISTORY_COMPLETE_OPTION = 'tweet_mirror_history_conplete';
	const IMPORTNOW_OPTION = 'tweet_mirror_import_now';

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
		add_settings_section( self::MIRRORING_SECTION , __( 'Mirroring tweets' , 'tweet_mirror_textdomain' ) , array( &$this , 'main_settings' ) , self::SETTINGS_PAGE );
		
		// add_settings_field( $id, $title, $callback, $page, $section, $args );
		add_settings_field( self::SCREENNAME_OPTION, __( 'Screen name:' , 'tweet_mirror_textdomain' ) ,
			array( &$this , 'render_field_string' )  , self::SETTINGS_PAGE , self::MIRRORING_SECTION,
			array( 'fieldname' => self::SCREENNAME_OPTION, 'description' => 'Screen name of Twitter account to mirror', 'label_for' => self::SCREENNAME_OPTION ) );
		add_settings_field( self::SCHEDULE_OPTION, __( 'Schedule:' , 'tweet_mirror_textdomain' ) ,
			array( &$this , 'render_field_schedule' )  , self::SETTINGS_PAGE , self::MIRRORING_SECTION,
			array( 'fieldname' => self::SCHEDULE_OPTION, 'description' => 'Schedule for fetching tweets to mirror', 'label_for' => self::SCHEDULE_OPTION ) );
		add_settings_field( self::HISTORY_OPTION, __( 'Import entire history?' , 'tweet_mirror_textdomain' ) ,
			array( &$this , 'render_field_history' )  , self::SETTINGS_PAGE , self::MIRRORING_SECTION,
			array( 'fieldname' => self::HISTORY_OPTION, 'description' => 'Mirror historical tweets as well as new ones?', 'label_for' => self::HISTORY_OPTION ) );

		add_settings_field( self::AUTHOR_OPTION, __( 'Author:' , 'tweet_mirror_textdomain' ) ,
			array( &$this , 'render_field_author' )  , self::SETTINGS_PAGE , self::MIRRORING_SECTION,
			array( 'fieldname' => self::AUTHOR_OPTION, 'description' => 'WordPress author to use for mirrored tweets', 'label_for' => self::AUTHOR_OPTION ) );
		add_settings_field( self::POSTTYPE_OPTION, __( 'Post type:' , 'tweet_mirror_textdomain' ) ,
			array( &$this , 'render_field_posttype' )  , self::SETTINGS_PAGE , self::MIRRORING_SECTION,
			array( 'fieldname' => self::POSTTYPE_OPTION, 'description' => 'WordPress post type to use for mirrored tweets', 'label_for' => self::POSTTYPE_OPTION ) );
		add_settings_field( self::CATEGORY_OPTION, __( 'Category:' , 'tweet_mirror_textdomain' ) ,
			array( &$this , 'render_field_category' )  , self::SETTINGS_PAGE , self::MIRRORING_SECTION,
			array( 'fieldname' => self::CATEGORY_OPTION, 'description' => 'Category to use for mirrored tweets', 'label_for' => self::CATEGORY_OPTION ) );
		
		// register_setting( $option_group, $option_name, $sanitize_callback );
		register_setting( self::SETTINGS_OPTION_GROUP , self::SCREENNAME_OPTION , array( &$this , 'sanitize_slug' ) );
		register_setting( self::SETTINGS_OPTION_GROUP , self::HISTORY_OPTION , array( &$this , 'sanitize_slug' ) );
		register_setting( self::SETTINGS_OPTION_GROUP , self::SCHEDULE_OPTION , array( &$this , 'sanitize_slug' ) );
		register_setting( self::SETTINGS_OPTION_GROUP , self::AUTHOR_OPTION , array( &$this , 'sanitize_slug' ) );
		register_setting( self::SETTINGS_OPTION_GROUP , self::CATEGORY_OPTION , array( &$this , 'sanitize_slug' ) );
		register_setting( self::SETTINGS_OPTION_GROUP , self::IMPORTNOW_OPTION );
	}

	public function main_settings() { echo '<p>' . __( 'Change these settings to do cool stuff.' , 'tweet_mirror_textdomain' ) . '</p>'; }

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
		echo '<input id="' . $fieldname . '" type="checkbox" name="' . $fieldname . '" ' . $checked . '/>
				<span class="description">' . __( $description , 'tweet_mirror_textdomain' ) . '</span>';
		if ( get_option( self::HISTORY_COMPLETE_OPTION )) {
			echo '<span class="description">' . __( 'There are currently no historical tweets left to mirror.' , 'tweet_mirror_textdomain' ) . '</span>';
		}
	}

	public function render_field_schedule( $args ) {

		$fieldname = $args['fieldname'];
		$description = $args['description'];
		$option = get_option( $fieldname );
		$value = '';
		if ( $option && strlen( $option ) > 0 && $option != '' ) {
			$value = $option;
		}
		$schedules = wp_get_schedules();

		echo '<select name="' . $fieldname . '" id="' . $fieldname . '" >';
		foreach ( $schedules as $schedule => $schedule_desc ) {
			$selected = ( $value == $schedule ) ? 'selected="selected"' : '';
			echo '<option value="' . $schedule . '" ' . $selected . '>' . $schedule_desc['display'] . '</option>';
		}
		echo '</select>';
		echo '<span class="description">' . __( $description , 'tweet_mirror_textdomain' ) . '</span>';
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

	public function filter_schedule( $newvalue, $oldvalue ) {
		if ( $newvalue !== $oldvalue ) {
			add_settings_error('general', 'tweets_imported', 'Scheduling skipped', 'updated');
			//TODO
			//wp_clear_scheduled_hook( 'tweet_mirror_schedule' );
			//wp_schedule_event( time(), $newvalue, 'tweet_mirror_schedule' );
		}
		return $newvalue;
	}

	public function filter_import_now( $newvalue, $oldvalue ) {

		// HACK: updated options are available here, but only because this button comes after the form fields.
		// If there's a new value then this button was clicked, so do the import
		if ( $newvalue != '' ) {
			$this->import_tweets();
		}
		// Return the old value so it doesn't get saved
		return $oldvalue;
	}

	public function import_tweets() {

		$screen_name = get_option( self::SCREENNAME_OPTION );
		$importer = new Tweet_Importer( 'tweet_mirror' );

		$twitter_params = array(
			'screen_name' => $screen_name,
		);
		$newest_tweet_id = $this->get_tweet_id_limit( $screen_name, 'newest' );
		error_log( "newest_tweet_id: $newest_tweet_id" );
		if ( isset( $newest_tweet_id )) {
			$twitter_params['since_id'] = $newest_tweet_id;
		}
		
		$twitter_result = $importer->get_twitter_feed( $twitter_params );
		if ( isset( $twitter_result['error'] )) {
			add_settings_error( 'general', 'tweets_imported', __('Error: ') . $twitter_result['error'], 'error' );
			$this->log( 'last_error', $twitter_result['error'] );
		} else {
			error_log( 'Tweets: ' . print_r( $twitter_result['tweets'], true ) );
			$import_result = $importer->import_tweets( $twitter_result['tweets'], array(
				'screen_name' => $screen_name,
				'author' => get_option( self::AUTHOR_OPTION ),
				'category' => get_option( self::CATEGORY_OPTION ),
			));
			$log_message = 'Imported ' . $import_result['count'] . ' tweets from @' . $screen_name;
			add_settings_error( 'general', 'tweets_imported', $log_message, 'updated' );
			$this->log( $import_result['count'] === 0 ? 'last_empty' : 'last_import', $log_message );
		}
		
		if ( get_option( self::HISTORY_OPTION )) {
			$oldest_tweet_id = $this->get_tweet_id_limit( $screen_name, 'oldest' );
			error_log( "oldest_tweet_id: $oldest_tweet_id" );
			if ( isset( $oldest_tweet_id )) {
				// Some tweets are already imported, so we fetch any older tweets if we can
				// Note we'll always get at least one tweet, which is the oldest one we already have.
				$twitter_params = array(
					'screen_name' => $screen_name,
					'max_id' => $oldest_tweet_id,
				);
				$twitter_result = $importer->get_twitter_feed( $twitter_params );
				if ( isset( $twitter_result['error'] )) {
					add_settings_error( 'general', 'tweets_imported', __('Error: ') . $twitter_result['error'], 'error' );
					$this->log( 'last_error', $twitter_result['error'] );
				} else {
					if ( count( $twitter_result['tweets'] ) === 1 ) {
						// We only got one tweet, so no history is left
						update_option( self::HISTORY_OPTION, false );
						update_option( self::HISTORY_COMPLETE_OPTION, true );
						$log_message = 'No more tweet history to mirror from @' . $screen_name;
						add_settings_error( 'general', 'tweets_imported', $log_message, 'updated' );
						$this->log( 'last_empty', $log_message );
					} else {
						error_log( 'Tweet history: ' . print_r( $twitter_result['tweets'], true ) );
						$import_result = $importer->import_tweets( $twitter_result['tweets'], array(
							'screen_name' => $screen_name,
							'author' => get_option( self::AUTHOR_OPTION ),
							'category' => get_option( self::CATEGORY_OPTION ),
						));
						$log_message = 'Imported ' . $import_result['count'] . ' historical tweets from @' . $screen_name;
						add_settings_error( 'general', 'tweets_imported', $log_message, 'updated' );
						$this->log( $import_result['count'] === 0 ? 'last_empty' : 'last_import', $log_message );
					}
				}
			}
		}
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
				
				submit_button( __( 'Import Now' , 'tweet_mirror_textdomain' ), 'secondary', self::IMPORTNOW_OPTION );
				
				
		echo '</form>';
		echo '<h3 class="title">Recent Results</h2>
			<table>
				<tr><th style="text-align: left;">Last empty</th><td>'  . get_option( 'tweet_mirror_last_empty' ) . '</td</tr>
				<tr><th style="text-align: left;">Last import</th><td>' . get_option( 'tweet_mirror_last_import' ) . '</td</tr>
				<tr><th style="text-align: left;">Last error</th><td>'  . get_option( 'tweet_mirror_last_error' ) . '</td</tr>
			</table>';

		echo '</div>';
	}
	
	private function get_tweet_id_limit( $screen_name, $newest_or_oldest ) {

		$query = new WP_Query( array(
			// tweets by this user
			'meta_key' => 'tweetimport_twitter_author',
			'meta_value' => $screen_name,
			// Get the tweet at the limit
			'orderby' => 'date',
			'order' => ( $newest_or_oldest === 'newest' ? 'DESC' : 'ASC' ),
			'posts_per_page' => 1,
		));
		// error_log( 'Query: ' . print_r( $query, true ) );
		if ( $query->have_posts()) {
			$post = $query->next_post();
			return get_metadata( 'post', $post->ID, 'tweetimport_twitter_id', true );
		}
		return null;
	}

	private function log( $category, $message ) {
		$category = 'tweet_mirror_' . $category;
		$message = current_time( 'mysql' ) . ' ' . $message;
		if ( ! add_option( $category, $message, '', 'no' )) {
			// option already exists. Update it
			update_option( $category, $message );
		}
	}

}
