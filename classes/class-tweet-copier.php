<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class TweetMirror {

	// Options
	const SCREENNAME_OPTION = 'tweet_mirror_screenname';
	const POSTTYPE_OPTION = 'tweet_mirror_posttype';
	const AUTHOR_OPTION = 'tweet_mirror_author';
	const CATEGORY_OPTION = 'tweet_mirror_category';
	const HISTORY_OPTION = 'tweet_mirror_history';
	const HISTORY_COMPLETE_OPTION = 'tweet_mirror_history_conplete';

	// Schedule hook
	// Codex says "For some reason there seems to be a problem on some systems where the hook must not contain underscores or uppercase characters."
	const SCHEDULE_HOOK = 'tweetmirrorschedule';

	private $dir;
	private $file;
	private $assets_dir;
	private $assets_url;

	public function __construct( $file ) {
		$this->dir = dirname( $file );
		$this->file = $file;
		$this->assets_dir = trailingslashit( $this->dir ) . 'assets';
		$this->assets_url = esc_url( trailingslashit( plugins_url( '/assets/', $file ) ) );

		// Handle localisation
		$this->load_plugin_textdomain();
		add_action( 'init', array( &$this, 'load_localisation' ), 0 );

		// Handle schedule
		add_action( self::SCHEDULE_HOOK, array( &$this, 'import_tweets' ) );
	}
	
	public function load_localisation () {
		load_plugin_textdomain( 'tweet_mirror' , false , dirname( plugin_basename( $this->file ) ) . '/lang/' );
	}
	
	public function load_plugin_textdomain () {
	    $domain = 'tweet_mirror_textdomain';
	    
	    $locale = apply_filters( 'plugin_locale' , get_locale() , $domain );
	 
	    load_textdomain( $domain , WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
	    load_plugin_textdomain( $domain , FALSE , dirname( plugin_basename( $this->file ) ) . '/lang/' );
	}
	
	public function import_tweets() {

		$screen_name = get_option( self::SCREENNAME_OPTION );
		if ( $screen_name == '' ) {
			$message = __('Error: Tweet Mirror settings have not yet been saved');
			add_settings_error( 'general', 'tweets_imported', $message, 'error' );
			$this->checkpoint( 'last_error', $message );
			twmi_log( 'Import failed: settings have not yet been saved' );
			return;
		}

		$importer = new Tweet_Importer( 'tweet_mirror' );

		$twitter_params = array(
			'screen_name' => $screen_name,
		);
		$newest_tweet_id = $this->get_tweet_id_limit( $screen_name, 'newest' );
		if ( isset( $newest_tweet_id )) {
			$twitter_params['since_id'] = $newest_tweet_id;
		}
		
		$twitter_result = $importer->get_twitter_feed( $twitter_params );
		if ( isset( $twitter_result['error'] )) {
			add_settings_error( 'general', 'tweets_imported', __('Error: ') . $twitter_result['error'], 'error' );
			$this->checkpoint( 'last_error', $twitter_result['error'] );
		} else {
			$import_result = $importer->import_tweets( $twitter_result['tweets'], array(
				'screen_name' => $screen_name,
				'author' => get_option( self::AUTHOR_OPTION ),
				'posttype' => get_option( self::POSTTYPE_OPTION ),
				'category' => get_option( self::CATEGORY_OPTION ),
			));
			$message = 'Imported ' . $import_result['count'] . ' tweets from @' . $screen_name;
			add_settings_error( 'general', 'tweets_imported', $message, 'updated' );
			$this->checkpoint( $import_result['count'] === 0 ? 'last_empty' : 'last_import', $message );
		}
		
		if ( get_option( self::HISTORY_OPTION )) {
			$oldest_tweet_id = $this->get_tweet_id_limit( $screen_name, 'oldest' );
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
					$this->checkpoint( 'last_error', $twitter_result['error'] );
				} else {
					if ( count( $twitter_result['tweets'] ) === 1 ) {
						// We only got one tweet, so no history is left
						update_option( self::HISTORY_OPTION, false );
						update_option( self::HISTORY_COMPLETE_OPTION, true );
						twmi_log( 'No more tweet history to fetch for @' . $screen_name );
						$message = 'No more tweet history to mirror from @' . $screen_name;
						add_settings_error( 'general', 'tweets_imported', $message, 'updated' );
						$this->checkpoint( 'last_empty', $message );
					} else {
						$import_result = $importer->import_tweets( $twitter_result['tweets'], array(
							'screen_name' => $screen_name,
							'author' => get_option( self::AUTHOR_OPTION ),
							'posttype' => get_option( self::POSTTYPE_OPTION ),
							'category' => get_option( self::CATEGORY_OPTION ),
						));
						$message = 'Imported ' . $import_result['count'] . ' historical tweets from @' . $screen_name;
						add_settings_error( 'general', 'tweets_imported', $message, 'updated' );
						$this->checkpoint( $import_result['count'] === 0 ? 'last_empty' : 'last_import', $message );
					}
				}
			}
		}
	}

	private function get_tweet_id_limit( $screen_name, $newest_or_oldest ) {

		$query = new WP_Query( array(
			// tweets by this user
			'post_type' => get_option( self::POSTTYPE_OPTION ),
			'meta_key' => 'tweetimport_twitter_author',
			'meta_value' => $screen_name,
			// Get the tweet at the limit
			'orderby' => 'date',
			'order' => ( $newest_or_oldest === 'newest' ? 'DESC' : 'ASC' ),
			'posts_per_page' => 1,
		));
		$id = null;
		if ( $query->have_posts()) {
			$post = $query->next_post();
			$id = get_metadata( 'post', $post->ID, 'tweetimport_twitter_id', true );
		}
		if ( TWEET_MIRROR_DEBUG ) twmi_debug( 'Tweet limit: Retrieved limit: ' . $newest_or_oldest . ' = ' . $id );
		return $id;
	}

	/**
		Update a checkpoint for a given category. These are displayed on the settings page.
	*/
	private function checkpoint( $category, $message ) {
		$category = 'tweet_mirror_' . $category;
		$message = current_time( 'mysql' ) . ' ' . $message;
		if ( ! add_option( $category, $message, '', 'no' )) {
			// option already exists. Update it
			update_option( $category, $message );
		}
	}

}
