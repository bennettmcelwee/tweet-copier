<?php
namespace Thunderguy {
/*
 * Copyright (c) 2013 Bennett McElwee. Licensed under the GPL (v2 or later).
 */

/**
 * @package Thunderguy
 * @author Bennett McElwee
 *
 * NOT YET IN USE
 */
class Logger {

	private $log_file_path;

	public function __construct( $log_file_path ) {
		$this->log_file_path = $log_file_path;
	}

	function info() {
		log( func_get_args(), 'INFO' );
	}

	function warn() {
		log( func_get_args(), 'WARN' );
	}

	function debug() {
		log( func_get_args(), 'DEBUG' );
	}

	private function log( $arguments, $level ) {
		$message = rtrim( implode( $arguments ) );
		$message = str_replace( "\n", "\n                    " . $level . ' ', $message );
		$message = current_time( 'mysql' ) . ' ' . $level . ' ' . $message . "\n";
		@error_log( $message, 3, LOG_FILE_PATH );
	}
}

}
