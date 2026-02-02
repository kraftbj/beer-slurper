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

use Kraft\Beer_Slurper as Base;

/**
 * Tests for the Sync Status helper functions.
 *
 * Validates the sync status tracking functionality including last sync time
 * retrieval, error recording and clearing, success recording, user
 * configuration, and backfill status detection.
 *
 * Uses WorDBless to provide real WordPress option functions.
 */
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
		update_option( 'beer_slurper_last_sync', $timestamp );

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
		update_option( 'beer_slurper_last_sync_error', $error_data );

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
		update_option( 'beer_slurper_last_sync_error', array( 'code' => 'test' ) );

		$result = clear_sync_error();

		$this->assertTrue( $result );
		$this->assertFalse( get_option( 'beer_slurper_last_sync_error' ) );
	}

	/**
	 * Tests record_sync_success() saves timestamp and clears previous errors.
	 *
	 * Verifies that the function updates the last sync timestamp option
	 * and clears any existing error from previous failed syncs.
	 */
	public function test_record_sync_success_updates_timestamp_and_clears_error() {
		$timestamp = 1705766400;
		update_option( 'beer_slurper_last_sync_error', array( 'code' => 'old_error' ) );

		$result = record_sync_success( $timestamp );

		$this->assertTrue( $result );
		$this->assertEquals( $timestamp, get_option( 'beer_slurper_last_sync' ) );
		$this->assertFalse( get_option( 'beer_slurper_last_sync_error' ) );
	}

	/**
	 * Tests get_configured_user() returns option value when no constant is defined.
	 *
	 * Verifies that the function falls back to the beer-slurper-user option
	 * when the UNTAPPD_USER constant is not defined.
	 */
	public function test_get_configured_user_returns_option_when_no_constant() {
		update_option( 'beer-slurper-user', 'testuser' );

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
		update_option( 'beer_slurper_testuser_import', true );

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
		$result = is_backfilling( 'testuser' );

		$this->assertFalse( $result );
	}
}
