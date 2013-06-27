<?php

/**
 * @package Tweet Copier
 */
class TweetCopierEngine {

/** How many tweets to fetch at once */
const FETCH_COUNT = 50;
const FETCH_COUNT_DEBUG = 5;

/** Namespace prefix, used for hooks */
private $namespace;

/** Debug? */
private $is_debug = false;

public function __construct( $namespace ) {
	$this->namespace = $namespace;

	// Default actions and filters
	add_action( $this->namespace . '_tweet_before_new_post', array( &$this, 'stop_duplicates' ) );
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
			'count' => $this->is_debug ? self::FETCH_COUNT_DEBUG : self::FETCH_COUNT,
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
	posttype
	category
returns an array
	count
*/
public function save_tweets($tweet_list, $params) {

	if ( $this->is_debug ) twcp_debug( 'Save: About to save tweets. count ' . count( $tweet_list ));
	$count = 0;
	foreach ($tweet_list as $tweet) {
		$tweet = apply_filters ($this->namespace . '_tweet_before_new_post', $tweet); //return false to stop processing an item.
		if (!$tweet) {
			continue;
		}
		$processed_text = $tweet->text;

		// Hyperlink screen names
		$processed_text = preg_replace("~@(\w+)~", "<a href=\"https://twitter.com/\\1\" target=\"_blank\">@\\1</a>", $processed_text);
		$processed_text = preg_replace("~^(\w+):~", "<a href=\"https://twitter.com/\\1\" target=\"_blank\">@\\1</a>:", $processed_text);

		// Hyperlink hashtags
		$processed_text = preg_replace("/#(\w+)/", "<a href=\"https://twitter.com/search?q=%23\\1&amp;src=hash\" target=\"_blank\">#\\1</a>", $processed_text);

		// Hyperlink URLs
		$processed_text = preg_replace("#(^|[\n ])([\w]+?://[\w]+[^ \"\n\r\t< ]*)#", "\\1<a href=\"\\2\" target=\"_blank\">\\2</a>", $processed_text);
		$processed_text = preg_replace("#(^|[\n ])((www|ftp)\.[^ \"\t\n\r< ]*)#", "\\1<a href=\"http://\\2\" target=\"_blank\">\\2</a>", $processed_text);

		$new_post = array('post_title' => trim( substr( $tweet->text, 0, 25 ) . '...' ),
						  'post_content' => trim( $processed_text ),
						  'post_date' => date( 'Y-m-d H:i:s', strtotime( $tweet->created_at ) ),
						  'post_date_gmt' => date( 'Y-m-d H:i:s', strtotime( $tweet->created_at ) ),
						  'post_author' => $params['author'],
						  'post_type' => $params['posttype'],
						  'post_category' => array($params['category']),
						  'post_status' => 'publish');
		$new_post = apply_filters($this->namespace . '_new_post_before_create', $new_post); // Offer the chance to manipulate new post data. return false to skip
		if ( ! $new_post ) {
			continue;
		}
		$new_post_id = wp_insert_post($new_post);

		add_post_meta ($new_post_id, 'tweetcopier_twitter_id', $tweet->id_str, true);
		add_post_meta ($new_post_id, 'tweetcopier_twitter_author', $params['screen_name'], true); 
		add_post_meta ($new_post_id, 'tweetcopier_date_saved', date ('Y-m-d H:i:s'), true);

		if ( $this->is_debug ) twcp_debug( 'Save: Saved post id [' . $new_post_id . '] ' . trim( substr( $tweet->text, 0, 25 ) . '...' ));
		++$count;
	}
	return compact( 'count' );
}

function stop_duplicates($tweet)
{
	global $wpdb;

	// FIXME: don't count trashed posts
	$posts = $wpdb->get_var ($wpdb->prepare ("SELECT COUNT(*) FROM $wpdb->postmeta 
                                              WHERE meta_key = 'tweetcopier_twitter_id'
                                              AND meta_value = '%s'", $tweet->id_str));
	if ( 0 < $posts ) {
		if ( $this->is_debug ) twcp_debug( 'Skipped duplicate tweet: ' . trim( substr( $tweet->text, 0, 25 ) . '...' ));
		return false;
	} else {
		return $tweet;
	}
}

} // class TweetCopierEngine

