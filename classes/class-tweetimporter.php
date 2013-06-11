<?php

class Tweet_Importer {

/** URL for fetching tweets; <SCREENNAME> is replaced with the, erm, screen name */
const TWITTER_API_USER_TIMELINE_URL = 'https://api.twitter.com/1/statuses/user_timeline.json?screen_name=<SCREENNAME>&count=3';

/** Namespace prefix, used for hooks */
private $namespace;

public function __construct($namespace) {
	$this->namespace = $namespace;

	// Default actions and filters
	if ( ! has_action ($this->namespace . '_tweet_before_new_post', 'Tweet_Importer::stop_duplicates')) {
		add_action($this->namespace . '_tweet_before_new_post', 'Tweet_Importer::stop_duplicates');
	}
}

public function import_twitter_feed($params) {

    $feed_url = str_replace('<SCREENNAME>', $params['screen_name'], TWITTER_API_USER_TIMELINE_URL);

	// Get JSON data
	
	// Don't verify SSL certificate, as this fails from my work PC...
	// $response = wp_remote_get( $feed_url );
	$response = wp_remote_get( $feed_url, array( 'sslverify' => false ) );

	// wp_die( '<pre>'. htmlentities( print_r( $response, true ) ) .'</pre>' );
	$body = wp_remote_retrieve_body( $response );
	$tweet_list = json_decode( $body );

	if ( $tweet_list && !empty( $response['headers']['status'] ) && $response['headers']['status'] == '200 OK' ) {
		// all is well
	} else {
		return '<strong>ERROR: Feed Reading Error: ' . $response['headers']['status'] . '</strong>';
	}

	return $this->import_tweets($params, $tweet_list);
}


private function import_tweets($params, $tweet_list) {

	$count = 0;
	$lo_id = null;
	$hi_id = null;
	foreach ($tweet_list as $tweet) {
		$tweet = apply_filters ($this->namespace . '_tweet_before_new_post', $tweet); //return false to stop processing an item.
		if (!$tweet) {
			continue;
		}

		$plain_text = iconv( "UTF-8", "ISO-8859-1//IGNORE", $tweet->text );

		// Extract the author and the message
		$tweet_author = trim(preg_replace("~^(\w+):(.*?)$~", "\\1", $plain_text));
		$text_only    = trim(preg_replace("~^(\w+):~", "", $plain_text));

		//if ($twitter_account['strip_name'] == 1) {
			$plain_text = $text_only;
		//}

		$processed_text = $plain_text;

		//if ($twitter_account['names_clickable'] == 1) {
			$processed_text = preg_replace("~@(\w+)~", "<a href=\"http://www.twitter.com/\\1\" target=\"_blank\">@\\1</a>", $processed_text);
			$processed_text = preg_replace("~^(\w+):~", "<a href=\"http://www.twitter.com/\\1\" target=\"_blank\">@\\1</a>:", $processed_text);
		//}

		//if ($twitter_account['hashtags_clickable'] == 1) {
			//if ($twitter_account['hashtags_clickable_twitter'] == 1) {
				$processed_text = preg_replace("/#(\w+)/", "<a href=\"http://search.twitter.com/search?q=\\1\" target=\"_blank\">#\\1</a>", $processed_text);
			//} else {
			//	$processed_text = preg_replace("/#(\w+)/", "<a href=\"" . skinju_get_tag_link("\\1") . "\">#\\1</a>", $processed_text);
			//}
		//}

		$processed_text = preg_replace("#(^|[\n ])([\w]+?://[\w]+[^ \"\n\r\t< ]*)#", "\\1<a href=\"\\2\" target=\"_blank\">\\2</a>", $processed_text);
		$processed_text = preg_replace("#(^|[\n ])((www|ftp)\.[^ \"\t\n\r< ]*)#", "\\1<a href=\"http://\\2\" target=\"_blank\">\\2</a>", $processed_text);

		$new_post = array('post_title' => trim( substr( $text_only, 0, 25 ) . '...' ),
						  'post_content' => trim( $processed_text ),
						  'post_date' => date( 'Y-m-d H:i:s', strtotime( $tweet->created_at ) ),
						  'post_date_gmt' => date( 'Y-m-d H:i:s', strtotime( $tweet->created_at ) ),
						  'post_author' => $params['author'],
						  'post_category' => array($params['category']),
						  'post_status' => 'publish');

		$new_post = apply_filters($this->namespace . '_new_post_before_create', $new_post); // Offer the chance to manipulate new post data. return false to skip
		if (!$new_post) {
			continue;
		}
		$new_post_id = wp_insert_post($new_post);

		add_post_meta ($new_post_id, 'tweetimport_twitter_author', $tweet_author, true); 
		add_post_meta ($new_post_id, 'tweetimport_date_imported', date ('Y-m-d H:i:s'), true);
		add_post_meta ($new_post_id, 'tweetimport_twitter_id', $tweet->id_str, true);

		//if ($twitter_account['hash_tag'] == 1) {
		//	preg_match_all ('~#([A-Za-z0-9_]+)(?=\s|\Z)~', $tweet->text, $out);
		//}
		//if ($twitter_account['add_tag']) {
		//	$out[0][] = $twitter_account['add_tag'];
		//}
		//wp_set_post_tags($new_post_id, implode (',', $out[0]));

		++$count;
		$lo_id = min_twitter_id($tweet->id_str, $lo_id);
		$hi_id = max_twitter_id($tweet->id_str, $hi_id);
	}

	return compact('count', 'lo_id', 'hi_id');
}

function stop_duplicates($tweet)
{
	global $wpdb;

	// FIXME: don't count trashed posts
	$posts = $wpdb->get_var ($wpdb->prepare ("SELECT COUNT(*) FROM $wpdb->postmeta 
                                              WHERE meta_key = 'tweetimport_twitter_id'
                                              AND meta_value = '%s'", $tweet->id_str));


	if ($posts > 0)  return false;
	else return $tweet;
}


static function max_twitter_id($id1, $id2) {
	if (is_null($id1)) return $id2;
	if (is_null($id2)) return $id1;
	if (strlen($id1) == 0) return $id2;
	if (strlen($id2) == 0) return $id1;
	return self::compare_twitter_id($id1, $id2) < 0 ? $id2 : $id1;
}

static function min_twitter_id($id1, $id2) {
	if (is_null($id1)) return $id2;
	if (is_null($id2)) return $id1;
	if (strlen($id1) == 0) return $id2;
	if (strlen($id2) == 0) return $id1;
	return self::compare_twitter_id($id1, $id2) < 0 ? $id1 : $id2;
}

static function compare_twitter_id($id1, $id2) {
	// null compares less than non-null
	if (is_null($id1) && is_null($id2)) return 0;
	if (is_null($id1)) return 1;
	if (is_null($id2)) return -1;
	$len1 = strlen($id1);
	$len2 = strlen($id2);
	if ($len1 < $len2) return 1;
	if ($len2 < $len1) return -1;
	return strcmp($id1, $id2);
}

} // class Tweet_Importer

