<?php
/*
 * Copyright (c) 2013-20 Bennett McElwee. Licensed under the GPL (v2 or later).
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

private $log;

public function __construct( $namespace, $log ) {
	$this->namespace = $namespace;
	$this->log = $log;

	// Default actions and filters
	//add_action( $this->namespace . '_tweet_before_new_post', array( &$this, 'log_tweet' ) );
	add_action( $this->namespace . '_tweet_before_new_post', array( &$this, 'skip_duplicate' ) );
	add_action( $this->namespace . '_tweet_before_new_post', array( &$this, 'skip_if_filtered' ) );
	add_action( $this->namespace . '_text_before_new_post', array( &$this, 'screen_names_to_html' ) );
	add_action( $this->namespace . '_text_before_new_post', array( &$this, 'hashtags_to_html' ) );
	add_action( $this->namespace . '_text_before_new_post', array( &$this, 'urls_to_html' ) );
}

function log_tweet( $tweet )
{
	$this->log->info(print_r($tweet, true));
	return $tweet;
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

	if ( $this->log->is_debug() ) $this->log->debug( 'Fetch: About to fetch tweets via Twitter API' );

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
			'exclude_replies' => true,
			'tweet_mode' => 'extended',
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
	if ( $this->log->is_debug() ) $this->log->debug( 'Fetch: Twitter request: ' . print_r( $twitter_params, true ) );
	$twitter_api->request( 'GET', 'https://api.twitter.com/1.1/statuses/user_timeline.json', $twitter_params );

	if ( $twitter_api->response['code'] === 200 ) {
		$body = $twitter_api->response['response'];
		$tweet_list = json_decode( $body );
		if ( $this->log->is_debug() ) $this->log->debug( 'Fetched ' . count( $tweet_list ) . ' tweets from Twitter for @' . $params['screen_name'] );
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

	if ($this->log->is_debug()) $this->log->debug('Save: About to save tweets. count ' . count($tweet_list));
	$count = 0;
	foreach ($tweet_list as $tweet) {
		if ($this->log->is_debug()) $this->log->debug('Save: Checking tweet: ' .  $this->get_original_text($tweet));
		$tweet = apply_filters ($this->namespace . '_tweet_before_new_post', $tweet); // return false to stop processing an item.
		if ( ! $tweet) {
			continue;
		}
		$tweet_html = '<div class="tweet-body">' . $this->get_original_text($tweet) . '</div>';
		$originator = $this->get_originator($tweet);
		if ($originator) {
			$tweet_html = '<div class="retweet-attribution"><em>Retweeted from @' . $originator . '</em></div><br/>'
				. $tweet_html;
		}
		$tweet_html = apply_filters ($this->namespace . '_text_before_new_post', $tweet_html);

		$media_url = $this->get_media_url($tweet);
		if ($media_url) {
			$tweet_html .= '<img class="tweet-image" alt="" src="' . $media_url . '" />';
		}

		$new_post = array('post_title' => $this->format_title( $tweet, $params['title_format'] ),
						  'post_content' => trim( $tweet_html ),
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
		add_post_meta( $new_post_id, 'tweetcopier_original_text', $this->get_plain_text($tweet), true);
		add_post_meta( $new_post_id, 'tweetcopier_date_saved', date ('Y-m-d H:i:s'), true);

		if ( $this->log->is_debug() ) $this->log->debug( 'Save: Saved post id [' . $new_post_id . '] ' . $this->get_abbrev_text($tweet));
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
		if ( $this->log->is_debug() ) $this->log->debug( "url_to_html: Checking $url" );
		$result = wp_remote_head($url, array('redirection' => 0));
		if (is_wp_error($result)) {
			if ( $this->log->is_debug() ) $this->log->debug( "url_to_html: Error: {$result->get_error_message()}" );
		}
		else if (isset($result['response']['code'])) {
			$code = $result['response']['code'];
			if ( $this->log->is_debug() ) $this->log->debug( "url_to_html: Code = $code" );
			if (300 <= $code && $code < 400 && isset($result['headers']['location'])) {
				$redirected_url = $result['headers']['location'];
				$redirected_host = parse_url($redirected_url, PHP_URL_HOST);
				if ($redirected_host !== $host) {
					$url = $redirected_url;
					$host = $redirected_host;
					if ( $this->log->is_debug() ) $this->log->debug( "url_to_html: Redirecting to $url" );
					continue;
				}
			}
		}
		break;
	}
	if ( $this->log->is_debug() ) $this->log->debug( "url_to_html: Resolved to $url" );

	if (!is_wp_error($result) && isset($result['headers']['content-type'])
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

function skip_duplicate( $tweet )
{
	if ($tweet) {
		$query = new WP_Query( array(
			'meta_key' => 'tweetcopier_twitter_id',
			'meta_value' => $tweet->id_str,
		));
		if ( $query->have_posts() ) {
			if ( $this->log->is_debug() ) $this->log->debug( 'Skipped duplicate tweet: ' . $this->get_abbrev_text($tweet));
			return false;
		}
	}
	return $tweet;
}

function skip_if_filtered( $tweet )
{
	if ($tweet) {
		$filter_option = get_option( TweetCopier::FILTER_WORDS_OPTION );
		if ($filter_option !== false) {
			$filter = trim($filter_option);
			if (strlen($filter)) {
				// Change e.g. " Tr*mp/ think-piece  NARF" to "/(?:^|\s)(?:Tr\*mp\/|think-piece|NARF)(?:$|\s)/i"
				$filter_re = '/(?:^|\\s)(?:' . preg_replace('/\\s+/', '|', preg_quote(trim($filter), '/')) . ')(?:$|\\s)/i';
				if (preg_match($filter_re, $this->get_original_text($tweet))) {
					if ( $this->log->is_debug() ) $this->log->debug( 'Skipped filtered tweet: ' . $this->get_abbrev_text($tweet));
					return false;
				}
			}
		}
	}
	return $tweet;
}

function format_title( $tweet, $format ) {
	$title = $format;
	if ( mb_strstr( $format, '%t' ) ) {
		$text = $this->get_original_text($tweet);
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

function get_plain_text( $tweet ) {
	// Returns the full text including RT if it's a retweet
	$original = $this->get_original_text($tweet);
	if (isset($tweet->retweeted_status)) {
		return 'RT @' . ($tweet->entities->user_mentions[0]->screen_name ?? '?') . ': ' . $original;
	}
	else {
		return $original;
	}
}

function get_abbrev_text( $tweet ) {
	$text = $tweet->truncated ? $tweet->extended_tweet->full_text : $tweet->full_text;
	return mb_substr($text, 0, 40) . '...';
}

function get_original_text( $tweet ) {
	if (isset($tweet->retweeted_status)) {
		return $this->get_original_text($tweet->retweeted_status);
	}
	else {
		return $tweet->truncated ? $tweet->extended_tweet->full_text : $tweet->full_text;
	}
}

function get_originator( $tweet ) {
	if (isset($tweet->retweeted_status)) {
		$originator = $tweet->entities->user_mentions[0]->screen_name ?? false;
		if ( ! $originator) {
			// Sometimes the originator isn't separately listed, so we have to parse the text
			if (preg_match('/^RT @([^:]+):/', $tweet->full_text, $matches)) {
				$originator = $matches[1];
			}
		}
		return $originator;
	}
	else {
		return false;
	}
}

function get_media_url( $tweet ) {
	return $tweet->extended_entities->media[0]->media_url
		?? $tweet->entities->media[0]->media_url
		?? false;
}

} // class TweetCopierEngine

