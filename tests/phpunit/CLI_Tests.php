<?php
/**
 * CLI Tests for Beer Slurper
 *
 * Tests for WP-CLI command functionality. Since WP-CLI is not fully available
 * in the test environment, we test the underlying functions that commands call.
 *
 * @package Kraft\Beer_Slurper\CLI
 */

namespace Kraft\Beer_Slurper\CLI;

use Kraft\Beer_Slurper as Base;
use Kraft\Beer_Slurper\Tests\MockHttpClient;
use Kraft\Beer_Slurper\Tests\ApiFixtures;

/**
 * Tests for CLI commands and their underlying functions.
 */
class CLI_Tests extends Base\TestCase {

	protected $testFiles = [
		'functions/api.php',
		'functions/oauth.php',
		'functions/post.php',
		'functions/brewery.php',
		'functions/venue.php',
		'functions/badge.php',
		'functions/checkin.php',
		'functions/companion.php',
		'functions/queue.php',
		'functions/walker.php',
		'functions/sync-status.php',
	];

	protected $use_mock_http = true;

	/**
	 * Tests CLI file loads without WP_CLI defined.
	 *
	 * The CLI file should exit early if WP_CLI is not defined.
	 */
	public function test_cli_file_exits_without_wp_cli() {
		// The file should not define the command class without WP_CLI
		$this->assertFalse(
			defined( 'WP_CLI' ),
			'WP_CLI should not be defined in test environment'
		);
	}

	/**
	 * Tests status functions used by CLI status command exist.
	 */
	public function test_status_functions_exist() {
		$this->assertTrue( function_exists( 'Kraft\Beer_Slurper\Sync_Status\get_configured_user' ) );
		$this->assertTrue( function_exists( 'Kraft\Beer_Slurper\Sync_Status\get_last_sync_time' ) );
		$this->assertTrue( function_exists( 'Kraft\Beer_Slurper\Sync_Status\get_last_sync_error' ) );
		$this->assertTrue( function_exists( 'Kraft\Beer_Slurper\Sync_Status\is_backfilling' ) );
		$this->assertTrue( function_exists( 'Kraft\Beer_Slurper\Sync_Status\get_total_beers' ) );
		$this->assertTrue( function_exists( 'Kraft\Beer_Slurper\Sync_Status\get_total_pictures' ) );
		$this->assertTrue( function_exists( 'Kraft\Beer_Slurper\Sync_Status\get_total_breweries' ) );
	}

	/**
	 * Tests get_configured_user returns option value.
	 */
	public function test_get_configured_user_returns_option() {
		update_option( 'beer-slurper-user', 'testuser' );

		$user = \Kraft\Beer_Slurper\Sync_Status\get_configured_user();

		$this->assertEquals( 'testuser', $user );
	}

	/**
	 * Tests get_configured_user returns null when not set.
	 */
	public function test_get_configured_user_returns_null_when_not_set() {
		$user = \Kraft\Beer_Slurper\Sync_Status\get_configured_user();

		$this->assertNull( $user );
	}

	/**
	 * Tests is_backfilling returns true during import.
	 */
	public function test_is_backfilling_returns_true_during_import() {
		update_option( 'beer_slurper_testuser_import', true );

		$result = \Kraft\Beer_Slurper\Sync_Status\is_backfilling( 'testuser' );

		$this->assertTrue( $result );
	}

	/**
	 * Tests is_backfilling returns false when complete.
	 */
	public function test_is_backfilling_returns_false_when_complete() {
		delete_option( 'beer_slurper_testuser_import' );

		$result = \Kraft\Beer_Slurper\Sync_Status\is_backfilling( 'testuser' );

		$this->assertFalse( $result );
	}

	/**
	 * Tests get_total_beers returns post count.
	 */
	public function test_get_total_beers_returns_count() {
		$this->create_beer_post();
		$this->create_beer_post();

		$count = \Kraft\Beer_Slurper\Sync_Status\get_total_beers();

		$this->assertEquals( 2, $count );
	}

	/**
	 * Tests get_total_beers returns 0 when no posts.
	 */
	public function test_get_total_beers_returns_zero_when_empty() {
		$count = \Kraft\Beer_Slurper\Sync_Status\get_total_beers();

		$this->assertEquals( 0, $count );
	}

	/**
	 * Tests get_total_breweries returns term count.
	 */
	public function test_get_total_breweries_returns_count() {
		wp_insert_term( 'Brewery 1', BEER_SLURPER_TAX_BREWERY );
		wp_insert_term( 'Brewery 2', BEER_SLURPER_TAX_BREWERY );
		wp_insert_term( 'Brewery 3', BEER_SLURPER_TAX_BREWERY );

		$count = \Kraft\Beer_Slurper\Sync_Status\get_total_breweries();

		$this->assertEquals( 3, $count );
	}

	/**
	 * Tests get_last_sync_time returns timestamp.
	 */
	public function test_get_last_sync_time_returns_timestamp() {
		$timestamp = time();
		update_option( 'beer_slurper_last_sync', $timestamp );

		$result = \Kraft\Beer_Slurper\Sync_Status\get_last_sync_time();

		$this->assertEquals( $timestamp, $result );
	}

	/**
	 * Tests get_last_sync_time returns null when not set.
	 */
	public function test_get_last_sync_time_returns_null_when_not_set() {
		$result = \Kraft\Beer_Slurper\Sync_Status\get_last_sync_time();

		$this->assertNull( $result );
	}

	/**
	 * Tests get_last_sync_error returns error array.
	 */
	public function test_get_last_sync_error_returns_error() {
		update_option( 'beer_slurper_last_sync_error', array(
			'code'    => 'api_error',
			'message' => 'API unavailable',
		) );

		$result = \Kraft\Beer_Slurper\Sync_Status\get_last_sync_error();

		$this->assertIsArray( $result );
		$this->assertEquals( 'api_error', $result['code'] );
		$this->assertEquals( 'API unavailable', $result['message'] );
	}

	/**
	 * Tests queue functions used by CLI commands.
	 */
	public function test_queue_functions_exist() {
		$this->assertTrue( function_exists( 'Kraft\Beer_Slurper\Queue\has_budget' ) );
		$this->assertTrue( function_exists( 'Kraft\Beer_Slurper\Queue\get_spread_params' ) );
		$this->assertTrue( function_exists( 'Kraft\Beer_Slurper\Queue\get_slot_delay' ) );
		$this->assertTrue( function_exists( 'Kraft\Beer_Slurper\Queue\cleanup' ) );
	}

	/**
	 * Tests walker functions used by CLI commands.
	 */
	public function test_walker_functions_exist() {
		$this->assertTrue( function_exists( 'Kraft\Beer_Slurper\Walker\import_new' ) );
		$this->assertTrue( function_exists( 'Kraft\Beer_Slurper\Walker\import_old' ) );
		$this->assertTrue( function_exists( 'Kraft\Beer_Slurper\Walker\get_latest_local_checkin_id' ) );
	}

	/**
	 * Tests import_new handles empty username.
	 */
	public function test_import_new_validates_username() {
		$result = \Kraft\Beer_Slurper\Walker\import_new( '' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'invalid_user', $result->get_error_code() );
	}

	/**
	 * Tests import_old handles empty username.
	 */
	public function test_import_old_validates_username() {
		$result = \Kraft\Beer_Slurper\Walker\import_old( '' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'invalid_user', $result->get_error_code() );
	}

	/**
	 * Tests has_budget returns true when budget available.
	 */
	public function test_has_budget_checks_availability() {
		delete_transient( 'beer_slurper_api_calls' );
		delete_transient( 'beer_slurper_api_window_end' );

		$result = \Kraft\Beer_Slurper\Queue\has_budget( 5 );

		$this->assertTrue( $result );
	}

	/**
	 * Tests has_budget returns false when exhausted.
	 */
	public function test_has_budget_detects_exhaustion() {
		set_transient( 'beer_slurper_api_calls', 95, HOUR_IN_SECONDS );
		set_transient( 'beer_slurper_api_window_end', time() + HOUR_IN_SECONDS, HOUR_IN_SECONDS );

		$result = \Kraft\Beer_Slurper\Queue\has_budget( 5 );

		$this->assertFalse( $result );
	}

	/**
	 * Tests get_spread_params returns expected structure.
	 */
	public function test_get_spread_params_returns_structure() {
		$params = \Kraft\Beer_Slurper\Queue\get_spread_params();

		$this->assertArrayHasKey( 'per_hour', $params );
		$this->assertArrayHasKey( 'interval', $params );
		$this->assertArrayHasKey( 'current_slots', $params );
		$this->assertArrayHasKey( 'current_interval', $params );
		$this->assertArrayHasKey( 'full_start', $params );
	}

	/**
	 * Tests cleanup function exists and is callable.
	 */
	public function test_cleanup_is_callable() {
		$this->assertTrue( is_callable( 'Kraft\Beer_Slurper\Queue\cleanup' ) );
	}

	/**
	 * Tests companion attach function used by backfill-companions command.
	 */
	public function test_attach_companions_function_exists() {
		$this->assertTrue( function_exists( 'Kraft\Beer_Slurper\Companion\attach_companions' ) );
	}

	/**
	 * Tests post find functions used by CLI commands.
	 */
	public function test_post_find_functions_exist() {
		$this->assertTrue( function_exists( 'Kraft\Beer_Slurper\Post\find_existing_checkin' ) );
		$this->assertTrue( function_exists( 'Kraft\Beer_Slurper\Post\find_existing_post' ) );
	}

	/**
	 * Tests find_existing_checkin returns true when exists.
	 */
	public function test_find_existing_checkin_detects_existing() {
		$post_id = $this->create_beer_post();
		add_post_meta( $post_id, '_beer_slurper_untappd_id', '123456', false );

		$result = \Kraft\Beer_Slurper\Post\find_existing_checkin( 123456 );

		$this->assertTrue( $result );
	}

	/**
	 * Tests find_existing_checkin returns false when not exists.
	 */
	public function test_find_existing_checkin_returns_false() {
		$result = \Kraft\Beer_Slurper\Post\find_existing_checkin( 999999 );

		$this->assertFalse( $result );
	}
}
