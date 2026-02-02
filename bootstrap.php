<?php
/**
 * PHPUnit Bootstrap File
 *
 * This file initializes the testing environment for the Beer Slurper plugin.
 * It defines necessary constants, loads Composer dependencies, and bootstraps
 * WP_Mock for mocking WordPress functions during unit tests.
 *
 * @package Kraft\Beer_Slurper\Tests
 */

/*
 * Project directory constant.
 *
 * Defines the path to the includes directory for loading project files.
 */
if ( ! defined( 'PROJECT' ) ) {
	define( 'PROJECT', __DIR__ . '/includes/' );
}

/*
 * Beer Slurper directory constant.
 *
 * Defines the root path of the Beer Slurper plugin.
 */
if ( ! defined( 'BEER_SLURPER_DIR' ) ) {
	define( 'BEER_SLURPER_DIR', __DIR__ . '/' );
}

/*
 * WordPress and plugin constants for testing.
 *
 * These constants are normally defined by WordPress or the main plugin file.
 * They are defined here with placeholder values to allow tests to run
 * without a full WordPress installation.
 */
if ( ! defined( 'WP_LANG_DIR' ) ) {
	define( 'WP_LANG_DIR', 'lang_dir' );
}
if ( ! defined( 'BEER_SLURPER_PATH' ) ) {
	define( 'BEER_SLURPER_PATH', 'path' );
}

/*
 * Composer autoloader validation and loading.
 *
 * Verifies that Composer dependencies have been installed before proceeding.
 * Throws an exception if the autoloader is missing.
 */
if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	throw new PHPUnit_Framework_Exception(
		'ERROR' . PHP_EOL . PHP_EOL .
		'You must use Composer to install the test suite\'s dependencies!' . PHP_EOL
	);
}

require_once __DIR__ . '/vendor/autoload.php';

require_once __DIR__ . '/tests/phpunit/test-tools/TestCase.php';

/*
 * WP_Error stub class for testing.
 *
 * Provides a minimal implementation of WordPress's WP_Error class
 * for use in unit tests without a full WordPress installation.
 */
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		protected $errors = array();
		protected $error_data = array();

		public function __construct( $code = '', $message = '', $data = '' ) {
			if ( empty( $code ) ) {
				return;
			}
			$this->add( $code, $message, $data );
		}

		public function add( $code, $message, $data = '' ) {
			$this->errors[ $code ][] = $message;
			if ( ! empty( $data ) ) {
				$this->error_data[ $code ] = $data;
			}
		}

		public function get_error_codes() {
			if ( empty( $this->errors ) ) {
				return array();
			}
			return array_keys( $this->errors );
		}

		public function get_error_code() {
			$codes = $this->get_error_codes();
			if ( empty( $codes ) ) {
				return '';
			}
			return $codes[0];
		}

		public function get_error_messages( $code = '' ) {
			if ( empty( $code ) ) {
				$all_messages = array();
				foreach ( (array) $this->errors as $code => $messages ) {
					$all_messages = array_merge( $all_messages, $messages );
				}
				return $all_messages;
			}
			if ( isset( $this->errors[ $code ] ) ) {
				return $this->errors[ $code ];
			}
			return array();
		}

		public function get_error_message( $code = '' ) {
			if ( empty( $code ) ) {
				$code = $this->get_error_code();
			}
			$messages = $this->get_error_messages( $code );
			if ( empty( $messages ) ) {
				return '';
			}
			return $messages[0];
		}

		public function get_error_data( $code = '' ) {
			if ( empty( $code ) ) {
				$code = $this->get_error_code();
			}
			if ( isset( $this->error_data[ $code ] ) ) {
				return $this->error_data[ $code ];
			}
			return null;
		}

		public function has_errors() {
			return ! empty( $this->errors );
		}
	}
}

/*
 * is_wp_error function stub for testing.
 *
 * Provides a minimal implementation of WordPress's is_wp_error function.
 */
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

/*
 * WP_Mock initialization.
 *
 * Configures WP_Mock with Patchwork support for function mocking and
 * bootstraps the mocking environment. The tearDown call ensures a clean
 * state before tests begin.
 */
WP_Mock::setUsePatchwork( true );
WP_Mock::bootstrap();
WP_Mock::tearDown();
