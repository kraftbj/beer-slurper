<?php
/**
 * Core Tests for Beer Slurper
 *
 * Tests for the core plugin functionality including setup, initialization,
 * internationalization, activation, and deactivation routines.
 *
 * @package Kraft\Beer_Slurper\Core
 */

namespace Kraft\Beer_Slurper\Core;

use Kraft\Beer_Slurper as Base;

/**
 * Tests for the core plugin functions.
 *
 * Validates the plugin's core lifecycle methods including setup hooks,
 * internationalization loading, initialization actions, and activation/
 * deactivation routines.
 *
 * Uses WorDBless to provide real WordPress hook functions.
 */
class Core_Tests extends Base\TestCase {

	protected $testFiles = [
		'functions/core.php',
		'functions/queue.php',
	];

	/**
	 * Tests setup() registers all required WordPress hooks.
	 *
	 * Verifies that the setup function correctly adds init hooks for
	 * internationalization and initialization, and fires the beer_slurper_loaded action.
	 */
	public function test_setup() {
		$loaded_fired = false;
		add_action( 'beer_slurper_loaded', function() use ( &$loaded_fired ) {
			$loaded_fired = true;
		} );

		setup();

		// Verify hooks were registered
		$this->assertNotFalse( has_action( 'init', 'Kraft\Beer_Slurper\Core\i18n' ) );
		$this->assertNotFalse( has_action( 'init', 'Kraft\Beer_Slurper\Core\init' ) );
		$this->assertTrue( $loaded_fired, 'beer_slurper_loaded action should have fired' );
	}

	/**
	 * Tests i18n() runs without errors.
	 *
	 * Verifies that the internationalization function can be called
	 * and completes without throwing exceptions.
	 */
	public function test_i18n() {
		// i18n() loads text domain - with WorDBless this should complete without error
		i18n();

		// If we get here without exceptions, the test passes
		$this->assertTrue( true );
	}

	/**
	 * Tests init() fires the beer_slurper_init action.
	 *
	 * Verifies that the initialization function correctly triggers the
	 * beer_slurper_init action hook for other components to hook into.
	 */
	public function test_init() {
		$init_fired = false;
		add_action( 'beer_slurper_init', function() use ( &$init_fired ) {
			$init_fired = true;
		} );

		init();

		$this->assertTrue( $init_fired, 'beer_slurper_init action should have fired' );
	}

	/**
	 * Tests activate() fires beer_slurper_init action.
	 *
	 * Verifies that the activation function calls init() which fires
	 * the beer_slurper_init action hook.
	 */
	public function test_activate() {
		$init_fired = false;
		add_action( 'beer_slurper_init', function() use ( &$init_fired ) {
			$init_fired = true;
		} );

		activate();

		$this->assertTrue( $init_fired, 'beer_slurper_init action should have fired during activation' );
	}

	/**
	 * Tests deactivate() performs cleanup on plugin deactivation.
	 *
	 * Verifies that the deactivation function clears legacy WP-Cron hooks.
	 * Note: Current hooks (bs_hourly_import) are cleared via Queue\cleanup()
	 * which uses Action Scheduler, not WP-Cron.
	 */
	public function test_deactivate() {
		// Schedule legacy hooks that deactivate() clears via wp_unschedule_hook
		wp_schedule_single_event( time() + 3600, 'bs_hourly_importer' );
		wp_schedule_single_event( time() + 3600, 'bs_daily_maintenance' );

		deactivate();

		// Verify legacy scheduled hooks were cleared
		$this->assertFalse( wp_next_scheduled( 'bs_hourly_importer' ) );
		$this->assertFalse( wp_next_scheduled( 'bs_daily_maintenance' ) );
	}
}
