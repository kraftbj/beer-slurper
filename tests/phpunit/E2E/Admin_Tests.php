<?php
/**
 * E2E Admin Tests for Beer Slurper
 *
 * Tests admin functionality including settings registration, page rendering,
 * and AJAX handler prerequisites.
 *
 * @package Kraft\Beer_Slurper\Tests\E2E
 */

namespace Kraft\Beer_Slurper\Tests\E2E;

use Kraft\Beer_Slurper as Base;

/**
 * Tests for admin functionality.
 */
class Admin_Tests extends Base\TestCase {

	protected $testFiles = [
		'functions/core.php',
		'functions/oauth.php',
		'functions/sync-status.php',
		'functions/queue.php',
	];

	/**
	 * Tests setting functions exist.
	 */
	public function test_setting_functions_exist() {
		$this->assertTrue( function_exists( 'Kraft\Beer_Slurper\Core\setting_init' ) );
		$this->assertTrue( function_exists( 'Kraft\Beer_Slurper\Core\setting_menu' ) );
		$this->assertTrue( function_exists( 'Kraft\Beer_Slurper\Core\setting_page' ) );
		$this->assertTrue( function_exists( 'Kraft\Beer_Slurper\Core\setting_key' ) );
		$this->assertTrue( function_exists( 'Kraft\Beer_Slurper\Core\setting_secret' ) );
		$this->assertTrue( function_exists( 'Kraft\Beer_Slurper\Core\setting_gallery' ) );
	}

	/**
	 * Tests default_settings function exists.
	 */
	public function test_default_settings_function_exists() {
		$this->assertTrue( function_exists( 'Kraft\Beer_Slurper\Core\default_settings' ) );
	}

	/**
	 * Tests ajax_sync_now function exists.
	 */
	public function test_ajax_sync_now_function_exists() {
		$this->assertTrue( function_exists( 'Kraft\Beer_Slurper\Core\ajax_sync_now' ) );
	}

	/**
	 * Tests core setup function exists.
	 */
	public function test_setup_function_exists() {
		$this->assertTrue( function_exists( 'Kraft\Beer_Slurper\Core\setup' ) );
	}

	/**
	 * Tests activate function exists.
	 */
	public function test_activate_function_exists() {
		$this->assertTrue( function_exists( 'Kraft\Beer_Slurper\Core\activate' ) );
	}

	/**
	 * Tests deactivate function exists.
	 */
	public function test_deactivate_function_exists() {
		$this->assertTrue( function_exists( 'Kraft\Beer_Slurper\Core\deactivate' ) );
	}

	/**
	 * Tests OAuth section callback exists.
	 */
	public function test_oauth_section_callback_exists() {
		$this->assertTrue( function_exists( 'Kraft\Beer_Slurper\Core\oauth_section_callback' ) );
	}

	/**
	 * Tests sync status section callback exists.
	 */
	public function test_sync_status_section_callback_exists() {
		$this->assertTrue( function_exists( 'Kraft\Beer_Slurper\Core\sync_status_section_callback' ) );
	}

	/**
	 * Tests rate limit section callback exists.
	 */
	public function test_rate_limit_section_callback_exists() {
		$this->assertTrue( function_exists( 'Kraft\Beer_Slurper\Core\rate_limit_section_callback' ) );
	}

	/**
	 * Tests setting_key renders input when no constant defined.
	 */
	public function test_setting_key_renders_input() {
		ob_start();
		\Kraft\Beer_Slurper\Core\setting_key();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<input', $output );
		$this->assertStringContainsString( 'beer-slurper-key', $output );
	}

	/**
	 * Tests setting_secret renders input when no constant defined.
	 */
	public function test_setting_secret_renders_input() {
		ob_start();
		\Kraft\Beer_Slurper\Core\setting_secret();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<input', $output );
		$this->assertStringContainsString( 'beer-slurper-secret', $output );
	}

	/**
	 * Tests setting_gallery renders checkbox.
	 */
	public function test_setting_gallery_renders_checkbox() {
		ob_start();
		\Kraft\Beer_Slurper\Core\setting_gallery();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<input', $output );
		$this->assertStringContainsString( 'checkbox', $output );
		$this->assertStringContainsString( 'beer-slurper-gallery', $output );
	}

	/**
	 * Tests settings options can be stored and retrieved.
	 */
	public function test_settings_can_be_stored() {
		update_option( 'beer-slurper-key', 'test_api_key' );
		update_option( 'beer-slurper-secret', 'test_api_secret' );
		update_option( 'beer-slurper-gallery', true );

		$this->assertEquals( 'test_api_key', get_option( 'beer-slurper-key' ) );
		$this->assertEquals( 'test_api_secret', get_option( 'beer-slurper-secret' ) );
		$this->assertTrue( get_option( 'beer-slurper-gallery' ) );
	}

	/**
	 * Tests OAuth connection state detection.
	 */
	public function test_oauth_connection_state() {
		// Not connected initially
		$this->assertFalse( \Kraft\Beer_Slurper\OAuth\is_connected() );

		// Connected after token is set
		update_option( 'beer-slurper-access-token', 'test_token' );
		$this->assertTrue( \Kraft\Beer_Slurper\OAuth\is_connected() );
	}

	/**
	 * Tests sync status functions work with no data.
	 */
	public function test_sync_status_with_no_data() {
		$user = \Kraft\Beer_Slurper\Sync_Status\get_configured_user();
		$this->assertNull( $user );

		$last_sync = \Kraft\Beer_Slurper\Sync_Status\get_last_sync_time();
		$this->assertNull( $last_sync );

		$last_error = \Kraft\Beer_Slurper\Sync_Status\get_last_sync_error();
		$this->assertNull( $last_error );
	}

	/**
	 * Tests sync status functions with configured user.
	 */
	public function test_sync_status_with_configured_user() {
		update_option( 'beer-slurper-user', 'testuser' );
		update_option( 'beer_slurper_last_sync', time() );

		$user = \Kraft\Beer_Slurper\Sync_Status\get_configured_user();
		$this->assertEquals( 'testuser', $user );

		$last_sync = \Kraft\Beer_Slurper\Sync_Status\get_last_sync_time();
		$this->assertNotFalse( $last_sync );
	}

	/**
	 * Tests get_total_beers returns correct count.
	 */
	public function test_get_total_beers_count() {
		// No beers initially
		$this->assertEquals( 0, \Kraft\Beer_Slurper\Sync_Status\get_total_beers() );

		// Create some beers
		$this->create_beer_post();
		$this->create_beer_post();

		$this->assertEquals( 2, \Kraft\Beer_Slurper\Sync_Status\get_total_beers() );
	}

	/**
	 * Tests get_total_breweries returns correct count.
	 */
	public function test_get_total_breweries_count() {
		// No breweries initially
		$this->assertEquals( 0, \Kraft\Beer_Slurper\Sync_Status\get_total_breweries() );

		// Create brewery terms
		wp_insert_term( 'Admin Test Brewery', BEER_SLURPER_TAX_BREWERY );

		$this->assertEquals( 1, \Kraft\Beer_Slurper\Sync_Status\get_total_breweries() );
	}

	/**
	 * Tests is_backfilling returns correct state.
	 */
	public function test_is_backfilling_state() {
		$user = 'testuser';

		// Not backfilling initially
		$this->assertFalse( \Kraft\Beer_Slurper\Sync_Status\is_backfilling( $user ) );

		// Set backfilling flag
		update_option( 'beer_slurper_' . $user . '_import', true );
		$this->assertTrue( \Kraft\Beer_Slurper\Sync_Status\is_backfilling( $user ) );

		// Clear backfilling flag
		delete_option( 'beer_slurper_' . $user . '_import' );
		$this->assertFalse( \Kraft\Beer_Slurper\Sync_Status\is_backfilling( $user ) );
	}

	/**
	 * Tests queue functions are available.
	 */
	public function test_queue_functions_available() {
		$this->assertTrue( function_exists( 'Kraft\Beer_Slurper\Queue\get_remaining_budget' ) );
		$this->assertTrue( function_exists( 'Kraft\Beer_Slurper\Queue\has_budget' ) );
		$this->assertTrue( function_exists( 'Kraft\Beer_Slurper\Queue\init_scheduled_actions' ) );
	}

	/**
	 * Tests remaining budget calculation.
	 */
	public function test_remaining_budget_calculation() {
		// Full budget when no calls made
		delete_transient( 'beer_slurper_api_calls' );
		delete_transient( 'beer_slurper_api_window_end' );

		$budget = \Kraft\Beer_Slurper\Queue\get_remaining_budget();
		$this->assertEquals( \Kraft\Beer_Slurper\Queue\API_BUDGET_PER_HOUR, $budget );

		// Reduced budget after calls
		set_transient( 'beer_slurper_api_calls', 30, HOUR_IN_SECONDS );
		set_transient( 'beer_slurper_api_window_end', time() + HOUR_IN_SECONDS, HOUR_IN_SECONDS );

		$budget = \Kraft\Beer_Slurper\Queue\get_remaining_budget();
		$this->assertEquals( 60, $budget ); // 90 - 30
	}

	/**
	 * Tests enqueue_admin_assets function exists.
	 */
	public function test_enqueue_admin_assets_function_exists() {
		$this->assertTrue( function_exists( 'Kraft\Beer_Slurper\Core\enqueue_admin_assets' ) );
	}

	/**
	 * Tests i18n function exists.
	 */
	public function test_i18n_function_exists() {
		$this->assertTrue( function_exists( 'Kraft\Beer_Slurper\Core\i18n' ) );
	}

	/**
	 * Tests init function exists.
	 */
	public function test_init_function_exists() {
		$this->assertTrue( function_exists( 'Kraft\Beer_Slurper\Core\init' ) );
	}

	/**
	 * Tests sync error tracking.
	 */
	public function test_sync_error_tracking() {
		$error = array(
			'code'    => 'api_error',
			'message' => 'Test error message',
		);

		update_option( 'beer_slurper_last_sync_error', $error );

		$retrieved = \Kraft\Beer_Slurper\Sync_Status\get_last_sync_error();

		$this->assertIsArray( $retrieved );
		$this->assertEquals( 'api_error', $retrieved['code'] );
		$this->assertEquals( 'Test error message', $retrieved['message'] );
	}

	/**
	 * Tests clear_last_sync_error function if it exists.
	 */
	public function test_clear_last_sync_error() {
		update_option( 'beer_slurper_last_sync_error', array( 'code' => 'test' ) );

		if ( function_exists( 'Kraft\Beer_Slurper\Sync_Status\clear_last_sync_error' ) ) {
			\Kraft\Beer_Slurper\Sync_Status\clear_last_sync_error();
			$this->assertNull( \Kraft\Beer_Slurper\Sync_Status\get_last_sync_error() );
		} else {
			delete_option( 'beer_slurper_last_sync_error' );
			$this->assertNull( \Kraft\Beer_Slurper\Sync_Status\get_last_sync_error() );
		}
	}
}
