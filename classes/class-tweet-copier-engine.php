<?php
/*
 * Copyright (c) 2013-15 Bennett McElwee. Licensed under the GPL (v2 or later).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * @package Tweet Copier
 * @author Bennett McElwee
 */
class TweetCopierEngine {

/** How many tweets to fetch at once */
const FETCH_COUNT = 50;

/** Namespace prefix, used for hooks */
private $namespace;

/** Debug? */
private $is_debug = false;

public function __construct( $namespace ) {
	$this->namespace = $namespace;

	// Default actions and filters
	add_action( $this->namespace . '_tweet_before_new_post', array( &$this, 'stop_duplicates' ) );
	add_action( $this->namespace . '_text_before_new_post', array( &$this, 'screen_names_to_html' ) );
	add_action( $this->namespace . '_text_before_new_post', array( &$this, 'hashtags_to_html' ) );
	add_action( $this->namespace . '_text_before_new_post', array( &$this, 'urls_to_html' ) );
}

public function set_debug( $is_debug ) {
	$this->is_debug = $is_debug;
}

/**
$params is an array:
	screen_name
	since_id (optional)
	max_id (optional)
returns an array
	tweets
	error
*/
public function get_twitter_feed( $params ) {

	if ( $this->is_debug ) twcp_debug( 'Fetch: About to fetch tweets via Twitter API' );

	$twitter_api = new tmhOAuth(array(
		'consumer_key'    => get_option( TweetCopier::TWITTER_CONSUMER_KEY_OPTION ),
		'consumer_secret' => get_option( TweetCopier::TWITTER_CONSUMER_SECRET_OPTION ),
		'user_token'      => get_option( TweetCopier::TWITTER_USER_TOKEN_OPTION ),
		'user_secret'     => get_option( TweetCopier::TWITTER_USER_SECRET_OPTION ),
	));

	$twitter_params = array(
			'count' => self::FETCH_COUNT,
			'screen_name' => $params['screen_name'],
			'trim_user' => true,
			);
	if (isset( $params['since_id'] )) {
		$twitter_params['since_id'] = $params['since_id'];
	}
	if (isset( $params['max_id'] )) {
		$twitter_params['max_id'] = $params['max_id'];
		// We'll probably return the tweet with the given max ID, which will most likely be discarded,
		// so to make up for that we fetch one extra.
		++$twitter_params['count'];
	}
	if ( $this->is_debug ) twcp_debug( 'Fetch: Twitter request: ' . print_r( $twitter_params, true ) );
	$twitter_api->request( 'GET', 'https://api.twitter.com/1.1/statuses/user_timeline.json', $twitter_params );

	if ( $twitter_api->response['code'] === 200 ) {
		$body = $twitter_api->response['response'];
		$tweet_list = json_decode( $body );
		if ( $this->is_debug ) twcp_debug( 'Fetched ' . count( $tweet_list ) . ' tweets from Twitter for @' . $params['screen_name'] );
		return array(
			'tweets' => $tweet_list,
			'error' => null,
			);
	} else {
		return array(
			'tweets' => null,
			'error' => 'Twitter API: '
				. "code [{$twitter_api->response['code']}] "
				. "errno [{$twitter_api->response['errno']}] "
				. "error [{$twitter_api->response['error']}]",
			);
	}
}


/**
Save a list of tweets as WordPress posts.
$params is an array:
	author
	category
returns an array
	count
*/
public function save_tweets($tweet_list, $params) {

	if ( $this->is_debug ) twcp_debug( 'Save: About to save tweets. count ' . count( $tweet_list ));
	$count = 0;
	foreach ($tweet_list as $tweet) {
		$tweet = apply_filters ($this->namespace . '_tweet_before_new_post', $tweet); //return false to stop processing an item.
		if ( ! $tweet) {
			continue;
		}
		$processed_text = apply_filters ($this->namespace . '_text_before_new_post', $tweet->text);

		if (isset($tweet->entities->media[0]->media_url)) {
			$processed_text .= ' <br /><img alt="" src="' . $tweet->entities->media[0]->media_url . '" />';
		}

		$new_post = array('post_title' => $this->format_title( $tweet, $params['title_format'] ),
						  'post_content' => trim( $processed_text ),
						  'post_date' => date( 'Y-m-d H:i:s', strtotime( $tweet->created_at ) ),
						  'post_date_gmt' => date( 'Y-m-d H:i:s', strtotime( $tweet->created_at ) ),
						  'post_author' => $params['author'],
						  'post_category' => array($params['category']),
						  'post_status' => 'publish',
						  'comment_status' => 'closed');
		$new_post = apply_filters($this->namespace . '_new_post_before_create', $new_post); // Offer the chance to manipulate new post data. return false to skip
		if ( ! $new_post ) {
			continue;
		}
		$new_post_id = wp_insert_post($new_post);
		set_post_format( $new_post_id, 'status' );
		add_post_meta( $new_post_id, 'tweetcopier_twitter_id', $tweet->id_str, true);
		add_post_meta( $new_post_id, 'tweetcopier_twitter_author', $params['screen_name'], true); 
		add_post_meta( $new_post_id, 'tweetcopier_original_text', $tweet->text, true); 
		add_post_meta( $new_post_id, 'tweetcopier_date_saved', date ('Y-m-d H:i:s'), true);

		if ( $this->is_debug ) twcp_debug( 'Save: Saved post id [' . $new_post_id . '] ' . trim( mb_substr( $tweet->text, 0, 40 ) . '...' ));
		++$count;
	}
	return compact( 'count' );
}

function screen_names_to_html( $text )
{
	$text = preg_replace("~@(\w+)~", "<a href=\"https://twitter.com/\\1\" target=\"_blank\">@\\1</a>", $text);
	$text = preg_replace("~^(\w+):~", "<a href=\"https://twitter.com/\\1\" target=\"_blank\">@\\1</a>:", $text);
	return $text;
}

function hashtags_to_html( $text )
{
	$text = preg_replace("/#(\w+)/", "<a href=\"https://twitter.com/search?q=%23\\1&amp;src=hash\" target=\"_blank\">#\\1</a>", $text);
	return $text;
}

function urls_to_html( $text )
{
	// Replace all URLs with HTML
	$self = $this;
	$text = preg_replace_callback("#(^|[\n ])([\w]+?://[\w]+[^ \"\n\r\t< ]*)#", function($matches) use (&$self) {
		return ' ' . $self->url_to_html($matches[2]);
	}, $text);
	// Try to replace things that *look* like URLs with HTML
	$text = preg_replace("#(^|[\n ])((www|ftp)\.[^ \"\t\n\r< ]*)#", "\\1<a href=\"http://\\2\" target=\"_blank\">\\2</a>", $text);
	return $text;
}

/**
 * Resolve a (possibly shortened) URL and render it appropriately into HTML.
 * This is public so it can be called from a closure. There must be a better way.
 */
public function url_to_html( $url )
{
	$host = parse_url($url, PHP_URL_HOST);
	$guard = 5;
	while (--$guard > 0) {
		if ( $this->is_debug ) twcp_debug( "url_to_html: Checking $url" );
		$result = wp_remote_head($url, array('redirection' => 0));
		//print_r($result);
		if (isset($result['response']['code'])) {
			$code = $result['response']['code'];
			if ( $this->is_debug ) twcp_debug( "url_to_html: Code = $code" );
			if (300 <= $code && $code < 400 && isset($result['headers']['location'])) {
				$redirected_url = $result['headers']['location'];
				$redirected_host = parse_url($redirected_url, PHP_URL_HOST);
				if ($redirected_host !== $host) {
					$url = $redirected_url;
					$host = $redirected_host;
					if ( $this->is_debug ) twcp_debug( "url_to_html: Redirecting to $url" );
					continue;
				}
			}
		}
		break;
	}
	if ( $this->is_debug ) twcp_debug( "url_to_html: Resolved to $url" );

	if (isset($result['headers']['content-type'])
			&& 0 === strncmp($result['headers']['content-type'], 'image/', strlen('image/'))) {
		return '<img alt="" src="' . $url . '" />';
	} else {
		$parsed = parse_url($url);
		$display = $parsed['host'] . $parsed['path'];
		if (50 < mb_strlen($display)) {
			$display = mb_substr($display, 0, 50) . '&hellip;';
		}
		return '<a href="' . $url . '" target="_blank">' . $display . '</a>';
	}
}

function stop_duplicates( $tweet )
{
	$query = new WP_Query( array(
		'meta_key' => 'tweetcopier_twitter_id',
		'meta_value' => $tweet->id_str,
	));
	if ( $query->have_posts() ) {
		if ( $this->is_debug ) twcp_debug( 'Skipped duplicate tweet: ' . trim( mb_substr( $tweet->text, 0, 40 ) . '...' ));
		return false;
	} else {
		return $tweet;
	}
}

function format_title( $tweet, $format ) {
	$title = $format;
	if ( mb_strstr( $format, '%t' ) ) {
		$text = $tweet->text;
		if ( 40 < mb_strlen( $text ) ) {
			$text = mb_substr( $text, 0, 40 );
			$initial = mb_strrchr( $text, ' ', true );
			if ( 20 < mb_strlen( $initial ) ) {
				$text = $initial;
			}
			$text = rtrim( $text ) . '...';
		}
		$title = str_replace ( '%t', $text, $title );
	}
	if ( mb_strstr( $format, '%d' ) ) {
		$date = date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $tweet->created_at ) );
		$title = str_replace ( '%d', $date, $title );
	}
	return $title;
}

} // class TweetCopierEngine

