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

	/**
	 * Tests get_relative_time() returns correct relative time regardless of site timezone.
	 *
	 * This test would have failed before the fix when get_relative_time() used
	 * current_time('timestamp') instead of time(). The old code compared a UTC
	 * timestamp against a timezone-adjusted value, causing incorrect results
	 * like "5 hours ago" when the actual time was only minutes ago.
	 */
	public function test_get_relative_time_uses_utc_comparison() {
		// Set site to UTC-5 (Eastern Standard Time offset)
		update_option( 'gmt_offset', -5 );

		// Create a timestamp for 10 minutes ago (in UTC, which is what we store)
		$ten_minutes_ago = time() - ( 10 * MINUTE_IN_SECONDS );

		$result = get_relative_time( $ten_minutes_ago );

		// The result should say "10 mins ago", not "5 hours ago"
		// With the old buggy code using current_time('timestamp'), this would fail
		// because current_time('timestamp') returns time() + (gmt_offset * HOUR_IN_SECONDS)
		// which for UTC-5 would be 5 hours behind, making the diff ~5 hours instead of 10 mins
		$this->assertStringContainsString( 'min', $result, 'Relative time should be in minutes, not hours' );
		$this->assertStringContainsString( 'ago', $result );
		$this->assertStringNotContainsString( 'hour', $result, 'Should not show hours for a 10-minute-old timestamp' );
	}

	/**
	 * Tests get_relative_time() with positive timezone offset.
	 *
	 * Verifies correct behavior for sites east of UTC (e.g., UTC+5).
	 * This would also fail with the old current_time('timestamp') approach.
	 */
	public function test_get_relative_time_with_positive_offset() {
		// Set site to UTC+5
		update_option( 'gmt_offset', 5 );

		// Create a timestamp for 5 minutes ago (in UTC)
		$five_minutes_ago = time() - ( 5 * MINUTE_IN_SECONDS );

		$result = get_relative_time( $five_minutes_ago );

		// Should show minutes, not hours
		$this->assertStringContainsString( 'min', $result );
		$this->assertStringContainsString( 'ago', $result );
		$this->assertStringNotContainsString( 'hour', $result );
	}

	/**
	 * Tests get_relative_time() calculates correctly for longer durations.
	 *
	 * Verifies the function works correctly for timestamps hours ago,
	 * ensuring the timezone offset doesn't compound the error.
	 */
	public function test_get_relative_time_hours_ago_with_offset() {
		// Set site to UTC-8 (Pacific)
		update_option( 'gmt_offset', -8 );

		// Create a timestamp for exactly 2 hours ago
		$two_hours_ago = time() - ( 2 * HOUR_IN_SECONDS );

		$result = get_relative_time( $two_hours_ago );

		// Should show "2 hours ago", not "10 hours ago" (2 + 8 offset)
		$this->assertStringContainsString( 'hour', $result );
		$this->assertStringContainsString( 'ago', $result );
		// Extract the number - should be around 2, not 10
		preg_match( '/(\d+)\s*hour/', $result, $matches );
		$this->assertNotEmpty( $matches, 'Should contain a number of hours' );
		$hours = (int) $matches[1];
		$this->assertLessThanOrEqual( 2, $hours, 'Should show approximately 2 hours, not offset-adjusted time' );
	}

	/**
	 * Tests get_relative_time() works correctly with UTC timezone.
	 *
	 * Baseline test to ensure function works when site is set to UTC.
	 */
	public function test_get_relative_time_with_utc_timezone() {
		// Set site to UTC (no offset)
		update_option( 'gmt_offset', 0 );

		$thirty_minutes_ago = time() - ( 30 * MINUTE_IN_SECONDS );

		$result = get_relative_time( $thirty_minutes_ago );

		$this->assertStringContainsString( 'min', $result );
		$this->assertStringContainsString( 'ago', $result );
	}
}
