<?php
namespace Kraft\Beer_Slurper\Sync_Status;

/**
 * Tests for the Sync Status helper functions.
 *
 * References:
 *   - http://phpunit.de/manual/current/en/index.html
 *   - https://github.com/padraic/mockery
 *   - https://github.com/10up/wp_mock
 */

use Kraft\Beer_Slurper as Base;

class Sync_Status_Tests extends Base\TestCase {

	protected $testFiles = [
		'functions/sync-status.php'
	];

	/**
	 * Test get_last_sync_time returns timestamp when set.
	 */
	public function test_get_last_sync_time_returns_timestamp() {
		$timestamp = 1705766400;

		\WP_Mock::wpFunction( 'get_option', array(
			'times'  => 1,
			'args'   => array( 'beer_slurper_last_sync' ),
			'return' => $timestamp,
		) );

		$result = get_last_sync_time();

		$this->assertEquals( $timestamp, $result );
	}

	/**
	 * Test get_last_sync_time returns null when not set.
	 */
	public function test_get_last_sync_time_returns_null_when_not_set() {
		\WP_Mock::wpFunction( 'get_option', array(
			'times'  => 1,
			'args'   => array( 'beer_slurper_last_sync' ),
			'return' => false,
		) );

		$result = get_last_sync_time();

		$this->assertNull( $result );
	}

	/**
	 * Test get_last_sync_error returns error array when set.
	 */
	public function test_get_last_sync_error_returns_error_array() {
		$error_data = array(
			'code'    => 'api_error',
			'message' => 'API request failed',
		);

		\WP_Mock::wpFunction( 'get_option', array(
			'times'  => 1,
			'args'   => array( 'beer_slurper_last_sync_error' ),
			'return' => $error_data,
		) );

		$result = get_last_sync_error();

		$this->assertEquals( $error_data, $result );
	}

	/**
	 * Test get_last_sync_error returns null when no error.
	 */
	public function test_get_last_sync_error_returns_null_when_not_set() {
		\WP_Mock::wpFunction( 'get_option', array(
			'times'  => 1,
			'args'   => array( 'beer_slurper_last_sync_error' ),
			'return' => false,
		) );

		$result = get_last_sync_error();

		$this->assertNull( $result );
	}

	/**
	 * Test clear_sync_error removes the error option.
	 */
	public function test_clear_sync_error_deletes_option() {
		\WP_Mock::wpFunction( 'delete_option', array(
			'times'  => 1,
			'args'   => array( 'beer_slurper_last_sync_error' ),
			'return' => true,
		) );

		$result = clear_sync_error();

		$this->assertTrue( $result );
	}

	/**
	 * Test record_sync_success updates timestamp and clears error.
	 */
	public function test_record_sync_success_updates_timestamp_and_clears_error() {
		$timestamp = 1705766400;

		\WP_Mock::wpFunction( 'update_option', array(
			'times'  => 1,
			'args'   => array( 'beer_slurper_last_sync', $timestamp, false ),
			'return' => true,
		) );

		\WP_Mock::wpFunction( 'delete_option', array(
			'times'  => 1,
			'args'   => array( 'beer_slurper_last_sync_error' ),
			'return' => true,
		) );

		$result = record_sync_success( $timestamp );

		$this->assertTrue( $result );
	}

	/**
	 * Test get_configured_user returns constant value when defined.
	 */
	public function test_get_configured_user_returns_option_when_no_constant() {
		\WP_Mock::wpFunction( 'get_option', array(
			'times'  => 1,
			'args'   => array( 'beer-slurper-user' ),
			'return' => 'testuser',
		) );

		$result = get_configured_user();

		$this->assertEquals( 'testuser', $result );
	}

	/**
	 * Test get_configured_user returns null when not configured.
	 */
	public function test_get_configured_user_returns_null_when_not_configured() {
		\WP_Mock::wpFunction( 'get_option', array(
			'times'  => 1,
			'args'   => array( 'beer-slurper-user' ),
			'return' => false,
		) );

		$result = get_configured_user();

		$this->assertNull( $result );
	}

	/**
	 * Test is_backfilling returns true when import option is set.
	 */
	public function test_is_backfilling_returns_true_when_importing() {
		\WP_Mock::wpFunction( 'get_option', array(
			'times'  => 1,
			'args'   => array( 'beer_slurper_testuser_import' ),
			'return' => true,
		) );

		$result = is_backfilling( 'testuser' );

		$this->assertTrue( $result );
	}

	/**
	 * Test is_backfilling returns false when import option is not set.
	 */
	public function test_is_backfilling_returns_false_when_caught_up() {
		\WP_Mock::wpFunction( 'get_option', array(
			'times'  => 1,
			'args'   => array( 'beer_slurper_testuser_import' ),
			'return' => false,
		) );

		$result = is_backfilling( 'testuser' );

		$this->assertFalse( $result );
	}
}
