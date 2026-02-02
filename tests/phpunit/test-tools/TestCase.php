<?php
/**
 * Base Test Case for Beer Slurper
 *
 * Provides a base test case class with common utilities and setup methods
 * for all Beer Slurper PHPUnit tests using WorDBless with SQLite.
 *
 * @package Kraft\Beer_Slurper
 */

namespace Kraft\Beer_Slurper;

use WorDBless\BaseTestCase;

/**
 * Base test case class for Beer Slurper tests.
 *
 * Extends WorDBless BaseTestCase to provide common functionality
 * for all plugin tests with SQLite database support.
 */
class TestCase extends BaseTestCase {
	/**
	 * Array of test files to load before running tests.
	 *
	 * @var array
	 */
	protected $testFiles = array();

	/**
	 * Options created during a test that should be cleaned up.
	 *
	 * @var array
	 */
	protected $test_options = array();

	/**
	 * Sets up the test environment before each test.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();

		// Clean up test-specific options from previous tests
		$this->cleanup_test_options();

		if ( ! empty( $this->testFiles ) ) {
			foreach ( $this->testFiles as $file ) {
				if ( file_exists( PROJECT . $file ) ) {
					require_once( PROJECT . $file );
				}
			}
		}
	}

	/**
	 * Tears down the test environment after each test.
	 *
	 * @return void
	 */
	protected function tear_down(): void {
		// Clean up options created during this test
		$this->cleanup_test_options();

		parent::tear_down();
	}

	/**
	 * Clean up test-specific options from the database.
	 *
	 * @return void
	 */
	protected function cleanup_test_options() {
		global $wpdb;

		// Delete beer_slurper options
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'beer_slurper%'" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'beer-slurper%'" );

		// Clear cron/scheduled events for our hooks
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name = 'cron'" );

		// Clear any object cache
		wp_cache_flush();
	}

	/**
	 * Resolves a function name to its fully qualified namespace.
	 *
	 * @param string $function The function name to namespace.
	 * @return string The fully qualified function name with namespace.
	 */
	public function ns( $function ) {
		if ( ! is_string( $function ) || false !== strpos( $function, '\\' ) ) {
			return $function;
		}

		$thisClassName = trim( get_class( $this ), '\\' );

		if ( ! strpos( $thisClassName, '\\' ) ) {
			return $function;
		}

		$thisNamespace = implode( '\\', array_slice( explode( '\\', $thisClassName ), 0, - 1 ) );

		return "$thisNamespace\\$function";
	}
}
