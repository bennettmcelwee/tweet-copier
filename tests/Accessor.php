<?php

/**
 * Extend the class to be tested, providing access to protected elements
 *
 * @package tweet-mirror
 * @author
 * @copyright
 * @license http://www.gnu.org/licenses/gpl-2.0.html GPLv2
 *
 * This plugin used the Object-Oriented Plugin Template Solution as a skeleton
 * http://wordpress.org/extend/plugins/oop-plugin-template-solution/
 */

/**
 * Obtain the parent class
 *
 * Use dirname(dirname()) because safe mode can disable "../" and use
 * dirname(__FILE__) instead of __DIR__ so tests run on PHP 5.2.
 */
require_once dirname(dirname(__FILE__)) . '/tweet-mirror.php';

/**
 * Get the admin class
 */
require_once dirname(dirname(__FILE__)) .  '/admin.php';

// Remove automatically created object.
unset($GLOBALS['tweet_mirror']);

/**
 * Extend the class to be tested, providing access to protected elements
 *
 * @package tweet-mirror
 * @author
 * @copyright
 * @license http://www.gnu.org/licenses/gpl-2.0.html GPLv2
 *
 * This plugin used the Object-Oriented Plugin Template Solution as a skeleton
 * http://wordpress.org/extend/plugins/oop-plugin-template-solution/
 */
class Accessor extends tweet_mirror_admin {
	public function __call($method, $args) {
		return call_user_func_array(array($this, $method), $args);
	}
	public function __get($property) {
		return $this->$property;
	}
	public function __set($property, $value) {
		$this->$property = $value;
	}
	public function get_data_element($key) {
		return $this->data[$key];
	}
}
