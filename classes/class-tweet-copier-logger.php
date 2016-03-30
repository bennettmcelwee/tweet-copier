<?php
namespace TweetCopier {
/*
 * Copyright (c) 2013 Bennett McElwee. Licensed under the GPL (v2 or later).
 */

/**
 * @package Thunderguy
 * @author Bennett McElwee
 */
class Logger {

	const OFF   = 0;
	const ERROR = 1;
	const WARN  = 2;
	const INFO  = 3;
	const DEBUG = 4;

	private $log_file_path;

	public function __construct( $log_file_path ) {
		$this->log_file_path = $log_file_path;
                $this->level = Logger::DEBUG;
	}

	function enable($level = Logger::DEBUG) {
		$this->level = $level;
	}
	function disable() {
		$this->level = Logger::OFF;
	}

	function is_error() { return $this->level >= Logger::ERROR; }
	function is_warn()  { return $this->level >= Logger::WARN; }
	function is_info()  { return $this->level >= Logger::INFO; }
	function is_debug() { return $this->level >= Logger::DEBUG; }

	function error() { if ($this->is_error()) { $this->log( func_get_args(), 'ERROR' ); } }
	function warn()  { if ($this->is_warn() ) { $this->log( func_get_args(), 'WARN'  ); } }
	function info()  { if ($this->is_info() ) { $this->log( func_get_args(), 'INFO'  ); } }
	function debug() { if ($this->is_debug()) { $this->log( func_get_args(), 'DEBUG' ); } }

	// Same as debug(), but append a stack trace
	function stack() {
		if ($this->is_debug()) {
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
	}

	private function log( $arguments, $label ) {
		$message = rtrim( implode( $arguments ) );
		$message = str_replace( "\n", "\n                    " . $label . ' ', $message );
		$message = current_time( 'mysql' ) . ' ' . $label . ' ' . $message . "\n";
		@error_log( $message, 3, $this->log_file_path );
	}
}


class NullLogger extends Logger {

	public function __construct() {
		parent::__construct('');
	}
	function set_level($level) {}
	function is_error() { return false; }
	function is_warn()  { return false; }
	function is_info()  { return false; }
	function is_debug() { return false; }
	function error() {}
	function warn() {}
	function info() {}
	function debug() {}
	function stack() {}
}

}
