<?php
/*
 * Copyright (c) 2013 Bennett McElwee. Licensed under the GPL (v2 or later).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * @package Tweet Copier
 * @author Bennett McElwee
 */
class TweetCopier {

	// Options
	const TWITTER_CONSUMER_KEY_OPTION    = 'tweet_copier_consumer_key';
	const TWITTER_CONSUMER_SECRET_OPTION = 'tweet_copier_consumer_secret';
	// TODO Get the user stuff via oAuth
	const TWITTER_USER_TOKEN_OPTION      = 'tweet_copier_user_token';
	const TWITTER_USER_SECRET_OPTION     = 'tweet_copier_user_secret';
	const TWITTER_USER_SCREENNAME_OPTION = 'tweet_copier_user_screenname';

	const SCREENNAME_OPTION = 'tweet_copier_screenname';
	const TITLE_FORMAT_OPTION = 'tweet_copier_title_format';
	const AUTHOR_OPTION = 'tweet_copier_author';
	const CATEGORY_OPTION = 'tweet_copier_category';
	const HISTORY_OPTION = 'tweet_copier_history';
	const HISTORY_COMPLETE_OPTION = 'tweet_copier_history_conplete';

	// Schedule hook
	// Codex says "For some reason there seems to be a problem on some systems where the hook must not contain underscores or uppercase characters."
	const SCHEDULE_HOOK = 'tweetcopierschedule';

	private $dir;
	private $file;
	private $assets_dir;
	private $assets_url;

	/** Debug? */
	private $is_debug = false;

	public function __construct( $file ) {
		$this->dir = dirname( $file );
		$this->file = $file;
		$this->assets_dir = trailingslashit( $this->dir ) . 'assets';
		$this->assets_url = esc_url( trailingslashit( plugins_url( '/assets/', $file ) ) );

		// Lifecycle
		register_deactivation_hook( $this->file, array( &$this, 'deactivate' ) );

		// Handle localisation
		$this->load_plugin_textdomain();
		add_action( 'init', array( &$this, 'load_localisation' ), 0 );

		// Handle schedule
		add_action( self::SCHEDULE_HOOK, array( &$this, 'copy_tweets' ) );
	}
	
	public function set_debug( $is_debug ) {
		$this->is_debug = $is_debug;
	}

	public function load_localisation () {
		load_plugin_textdomain( 'tweet_copier' , false , dirname( plugin_basename( $this->file ) ) . '/lang/' );
	}
	
	public function load_plugin_textdomain () {
	    $domain = 'tweet_copier_textdomain';
	    
	    $locale = apply_filters( 'plugin_locale' , get_locale() , $domain );
	 
	    load_textdomain( $domain , WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
	    load_plugin_textdomain( $domain , FALSE , dirname( plugin_basename( $this->file ) ) . '/lang/' );
	}
	
	public function deactivate() {
		wp_clear_scheduled_hook( self::SCHEDULE_HOOK );
	}

	public function copy_tweets() {

		$screen_name = get_option( self::SCREENNAME_OPTION );
		if ( $screen_name == '' ) {
			$this->checkpoint( 'error', __('Tweet Copier settings have not yet been saved') );
			twcp_log( 'Copy failed: settings have not yet been saved' );
			return;
		}

		$engine = new TweetCopierEngine( 'tweet_copier' );
		$engine->set_debug( $this->is_debug );

		$twitter_params = array(
			'screen_name' => $screen_name,
		);
		$newest_tweet_id = $this->get_tweet_id_limit( $screen_name, 'newest' );
		if ( isset( $newest_tweet_id )) {
			$twitter_params['since_id'] = $newest_tweet_id;
		}
		
		$twitter_result = $engine->get_twitter_feed( $twitter_params );
		if ( isset( $twitter_result['error'] )) {
			$this->checkpoint( 'error', 'Fetching new tweets from @' . $screen_name . ': ' . $twitter_result['error'] );
			twcp_log( 'Copy failed: error fetching new tweets from @' . $screen_name . ': ' . $twitter_result['error'] );
		} else if ( 0 < count( $twitter_result['tweets'] ) ) {
			$save_result = $engine->save_tweets( $twitter_result['tweets'], array(
				'screen_name' => $screen_name,
				'title_format' => get_option( self::TITLE_FORMAT_OPTION ),
				'author' => get_option( self::AUTHOR_OPTION ),
				'category' => get_option( self::CATEGORY_OPTION ),
			));
			$message = 'Copied ' . $save_result['count'] . ' new tweets from @' . $screen_name;
			$this->checkpoint( $save_result['count'] === 0 ? 'empty' : 'copy', $message );
			twcp_log( $message );
		} else {
			$message = 'No new tweets from @' . $screen_name;
			$this->checkpoint( 'empty', $message );
			twcp_log( $message );
		}
		
		if ( get_option( self::HISTORY_OPTION )) {
			$oldest_tweet_id = $this->get_tweet_id_limit( $screen_name, 'oldest' );
			if ( isset( $oldest_tweet_id )) {
				// Some tweets are already copied, so we fetch any older tweets if we can.
				// Note we'll always get at least one tweet, which is the oldest one we already have.
				$twitter_params = array(
					'screen_name' => $screen_name,
					'max_id' => $oldest_tweet_id,
				);
				$twitter_result = $engine->get_twitter_feed( $twitter_params );
				if ( isset( $twitter_result['error'] )) {
					$this->checkpoint( 'error', 'Fetching old tweets from @' . $screen_name . ': ' . $twitter_result['error'] );
					twcp_log( 'Copy failed: error fetching old tweets from @' . $screen_name . ': ' . $twitter_result['error'] );
				} else if ( 0 < count( $twitter_result['tweets'] ) ) {
					if ( count( $twitter_result['tweets'] ) === 1 ) {
						// We only got one tweet, so no history is left
						update_option( self::HISTORY_OPTION, false );
						update_option( self::HISTORY_COMPLETE_OPTION, true );
						$message = 'No more old tweets to copy from @' . $screen_name;
						$this->checkpoint( 'empty', $message );
						twcp_log( $message );
					} else {
						$save_result = $engine->save_tweets( $twitter_result['tweets'], array(
							'screen_name' => $screen_name,
							'title_format' => get_option( self::TITLE_FORMAT_OPTION ),
							'author' => get_option( self::AUTHOR_OPTION ),
							'category' => get_option( self::CATEGORY_OPTION ),
						));
						$message = 'Copied ' . $save_result['count'] . ' old tweets from @' . $screen_name;
						$this->checkpoint( $save_result['count'] === 0 ? 'empty' : 'copy', $message );
						twcp_log( $message );
					}
				}
			}
		}
	}

	private function get_tweet_id_limit( $screen_name, $newest_or_oldest ) {

		$query = new WP_Query( array(
			// tweets by this user
			'meta_key' => 'tweetcopier_twitter_author',
			'meta_value' => $screen_name,
			// Get the tweet at the limit
			'orderby' => 'date',
			'order' => ( $newest_or_oldest === 'newest' ? 'DESC' : 'ASC' ),
			'posts_per_page' => 1,
		));
		$id = null;
		if ( $query->have_posts()) {
			$post = $query->next_post();
			$id = get_metadata( 'post', $post->ID, 'tweetcopier_twitter_id', true );
		}
		if ( $this->is_debug ) twcp_debug( 'Tweet limit: Retrieved limit: ' . $newest_or_oldest . ' = ' . $id );
		return $id;
	}

	/**
		Update a checkpoint for a given category. These are displayed on the settings page.
	*/
	public function checkpoint( $category, $message ) {
		$option = 'tweet_copier_' . $category;
		$timestamped_message = current_time( 'mysql' ) . ' ' . $message;
		if ( ! add_option( $option, $timestamped_message, '', 'no' )) {
			// option already exists. Update it
			update_option( $option, $timestamped_message );
		}
		// Show an immediate message if we're in the WP Admin UI
		if ( function_exists( 'add_settings_error' ) ) {
			if ( $category == 'error' ) {
				add_settings_error( 'general', 'tweet_copier', __('Error: ') . $message, 'error' );
			} else {
				add_settings_error( 'general', 'tweet_copier', $message, 'updated' );
			}
		}

	}

	public function get_checkpoint( $category ) {
		$option = 'tweet_copier_' . $category;
		return get_option( $option );
	}

}
