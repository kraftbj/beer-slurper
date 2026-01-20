<?php
/**
 * Sync Status Tests for Beer Slurper
 *
 * Tests for the sync status helper functions that track synchronization
 * state, timestamps, errors, and configuration.
 *
 * @package Kraft\Beer_Slurper\Sync_Status
 */

namespace Kraft\Beer_Slurper\Sync_Status;

/**
 * Tests for the Sync Status helper functions.
 *
 * Validates the sync status tracking functionality including last sync time
 * retrieval, error recording and clearing, success recording, user
 * configuration, and backfill status detection.
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
	 * Tests get_last_sync_time() returns stored timestamp when sync has occurred.
	 *
	 * Verifies that the function retrieves and returns the Unix timestamp
	 * stored in the beer_slurper_last_sync option.
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
	 * Tests get_last_sync_time() returns null when no sync has occurred.
	 *
	 * Verifies that the function returns null when the last sync option
	 * does not exist or is empty.
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
	 * Tests get_last_sync_error() returns error data when an error is stored.
	 *
	 * Verifies that the function retrieves and returns the error array
	 * containing code and message keys from the last sync error option.
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
	 * Tests get_last_sync_error() returns null when no error is stored.
	 *
	 * Verifies that the function returns null when the last sync error
	 * option does not exist or is empty.
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
	 * Tests clear_sync_error() removes the stored error option.
	 *
	 * Verifies that the function deletes the beer_slurper_last_sync_error
	 * option from the database.
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
	 * Tests record_sync_success() saves timestamp and clears previous errors.
	 *
	 * Verifies that the function updates the last sync timestamp option
	 * and clears any existing error from previous failed syncs.
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
	 * Tests get_configured_user() returns option value when no constant is defined.
	 *
	 * Verifies that the function falls back to the beer-slurper-user option
	 * when the UNTAPPD_USER constant is not defined.
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
	 * Tests get_configured_user() returns null when no user is configured.
	 *
	 * Verifies that the function returns null when neither the constant
	 * nor the option has a configured Untappd username.
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
	 * Tests is_backfilling() returns true when import is in progress.
	 *
	 * Verifies that the function detects an active backfill operation
	 * by checking the user-specific import option.
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
	 * Tests is_backfilling() returns false when sync is caught up.
	 *
	 * Verifies that the function returns false when no backfill operation
	 * is active for the specified user.
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
