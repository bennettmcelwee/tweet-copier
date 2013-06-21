<?php

class Tweet_Importer {

/** URL for fetching tweets; <SCREENNAME> is replaced with the, erm, screen name */
const TWITTER_API_USER_TIMELINE_URL = 'https://api.twitter.com/1/statuses/user_timeline.json?screen_name=<SCREENNAME>&count=3';

/** Namespace prefix, used for hooks */
private $namespace;

public function __construct( $namespace ) {
	$this->namespace = $namespace;

	// Default actions and filters
	if ( ! has_action ($this->namespace . '_tweet_before_new_post', 'Tweet_Importer::stop_duplicates')) {
		add_action($this->namespace . '_tweet_before_new_post', 'Tweet_Importer::stop_duplicates');
	}
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

	if ( TWEET_MIRROR_DEBUG ) twmi_debug( 'Fetch: About to fetch tweets via Twitter API' );

	$twitter_api = new tmhOAuth(array(
		'consumer_key'    => TWITTER_CONSUMER_KEY,
		'consumer_secret' => TWITTER_CONSUMER_SECRET,
		'user_token'      => TWITTER_USER_TOKEN,
		'user_secret'     => TWITTER_USER_SECRET,
	));

	$twitter_params = array(
			'count' => 3,
			'screen_name' => $params['screen_name'],
			'trim_user' => true,
			);
	if (isset( $params['since_id'] )) {
		$twitter_params['since_id'] = $params['since_id'];
	}
	if (isset( $params['max_id'] )) {
		$twitter_params['max_id'] = $params['max_id'];
	}
	if ( TWEET_MIRROR_DEBUG ) twmi_debug( 'Fetch: Twitter request: ' . print_r( $twitter_params, true ) );
	$twitter_api->request( 'GET', 'https://api.twitter.com/1.1/statuses/user_timeline.json', $twitter_params );

	if ( $twitter_api->response['code'] === 200 ) {
		$body = $twitter_api->response['response'];
		$tweet_list = json_decode( $body );
		twmi_log( 'Fetched ' . count( $tweet_list ) . ' tweets from Twitter for @' . $params['screen_name'] );
		return array(
			'tweets' => $tweet_list,
			'error' => null,
			);
	} else {
		twmi_log( "Error fetching tweets for @{$params['screen_name']}. Code [{$twitter_api->response['code']}] Error number [{$twitter_api->response['errno']}] Error [{$twitter_api->response['error']}]", 'WARNING' );
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
$params is an array:
	author
	posttype
	category
returns an array
	count
*/
public function import_tweets($tweet_list, $params) {

	if ( TWEET_MIRROR_DEBUG ) twmi_debug( 'Import: About to import tweets. count ' . count( $tweet_list ));
	$count = 0;
	foreach ($tweet_list as $tweet) {
		$tweet = apply_filters ($this->namespace . '_tweet_before_new_post', $tweet); //return false to stop processing an item.
		if (!$tweet) {
			continue;
		}

		$plain_text = iconv( "UTF-8", "ISO-8859-1//IGNORE", $tweet->text );

		$processed_text = $plain_text;

		// Hyperlink screen names
		$processed_text = preg_replace("~@(\w+)~", "<a href=\"https://twitter.com/\\1\" target=\"_blank\">@\\1</a>", $processed_text);
		$processed_text = preg_replace("~^(\w+):~", "<a href=\"https://twitter.com/\\1\" target=\"_blank\">@\\1</a>:", $processed_text);

		// Hyperlink hashtags
		$processed_text = preg_replace("/#(\w+)/", "<a href=\"https://twitter.com/search?q=%23\\1&amp;src=hash\" target=\"_blank\">#\\1</a>", $processed_text);

		// Hyperlink URLs
		$processed_text = preg_replace("#(^|[\n ])([\w]+?://[\w]+[^ \"\n\r\t< ]*)#", "\\1<a href=\"\\2\" target=\"_blank\">\\2</a>", $processed_text);
		$processed_text = preg_replace("#(^|[\n ])((www|ftp)\.[^ \"\t\n\r< ]*)#", "\\1<a href=\"http://\\2\" target=\"_blank\">\\2</a>", $processed_text);

		$new_post = array('post_title' => trim( substr( $plain_text, 0, 25 ) . '...' ),
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

		add_post_meta ($new_post_id, 'tweetimport_twitter_id', $tweet->id_str, true);
		add_post_meta ($new_post_id, 'tweetimport_twitter_author', $params['screen_name'], true); 
		add_post_meta ($new_post_id, 'tweetimport_date_imported', date ('Y-m-d H:i:s'), true);

		if ( TWEET_MIRROR_DEBUG ) twmi_debug( 'Import: Imported post id [' . $new_post_id . '] ' . trim( substr( $plain_text, 0, 25 ) . '...' ));
		++$count;
	}
	twmi_log( "Saved $count tweets to WordPress for @" . $params['screen_name'] );
	return compact( 'count' );
}

function stop_duplicates($tweet)
{
	global $wpdb;

	// FIXME: don't count trashed posts
	$posts = $wpdb->get_var ($wpdb->prepare ("SELECT COUNT(*) FROM $wpdb->postmeta 
                                              WHERE meta_key = 'tweetimport_twitter_id'
                                              AND meta_value = '%s'", $tweet->id_str));
	if ( 0 < $posts ) {
		twmi_log( 'Skipped duplicate tweet: ' . trim( substr( $tweet->text, 0, 25 ) . '...' ));
		return false;
	} else {
		return $tweet;
	}
}

} // class Tweet_Importer

