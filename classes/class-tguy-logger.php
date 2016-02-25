<?php
namespace Thunderguy {
/*
 * Copyright (c) 2013 Bennett McElwee. Licensed under the GPL (v2 or later).
 */

/**
 * @package Thunderguy
 * @author Bennett McElwee
 */
class Logger {

	private $log_file_path;

	public function __construct( $log_file_path ) {
		$this->log_file_path = $log_file_path;
	}

	function info() {
		$this->log( func_get_args(), 'INFO' );
	}

	function warn() {
		$this->log( func_get_args(), 'WARN' );
	}

	function debug() {
		$this->log( func_get_args(), 'DEBUG' );
	}

	// Same as debug(), but append a stack trace
	function stack() {
		$frames = debug_backtrace();
		array_shift( $frames ); // remove the stack() function call
		$stack = array_map(function($frame) {
					return "\n- ".$frame['function'].
						(array_key_exists('file', $frame)
							? '() ('.basename($frame['file']).':'.$frame['line'].')'
							: '');
				},
				$frames
			);
		$this->log( array_merge( func_get_args(), $stack), 'DEBUG' );
	}

	private function log( $arguments, $level ) {
		$message = rtrim( implode( $arguments ) );
		$message = str_replace( "\n", "\n                    " . $level . ' ', $message );
		$message = current_time( 'mysql' ) . ' ' . $level . ' ' . $message . "\n";
		@error_log( $message, 3, $this->log_file_path );
	}
}

}
