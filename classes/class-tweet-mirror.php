<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class TweetMirror {
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

		// Handle post types
		add_action( 'init', array( &$this, 'create_tweet_post_type' ), 0 );
		add_action( 'pre_get_posts', array( &$this, 'add_tweet_post_types_to_query' ) );

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
	
	public function create_tweet_post_type() {
		register_post_type( 'tweetecho_tweet',
			array(
				'labels' => array(
					'singular_name' => __( 'Tweet' ),
					'name' => __( 'Tweets' ),
				),
				'public' => true,
				'has_archive' => true,
				'rewrite' => array('slug' => 'tweet'),
			)
		);
	}

	function add_tweet_post_types_to_query( $query ) {
		if ( is_home() && $query->is_main_query() ) {
			$post_types = $query->get( 'post_type' );
			$post_types[] = 'tweetecho_tweet';
			$query->set( 'post_type', $post_types );
		}
		return $query;
	}

}
