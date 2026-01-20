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

/**
 * Tests for the core plugin functions.
 *
 * Validates the plugin's core lifecycle methods including setup hooks,
 * internationalization loading, initialization actions, and activation/
 * deactivation routines.
 *
 * References:
 *   - http://phpunit.de/manual/current/en/index.html
 *   - https://github.com/padraic/mockery
 *   - https://github.com/10up/wp_mock
 */

use Kraft\Beer_Slurper as Base;

class Core_Tests extends Base\TestCase {

	protected $testFiles = [
		'functions/core.php'
	];

	/**
	 * Tests setup() registers all required WordPress hooks.
	 *
	 * Verifies that the setup function correctly adds init hooks for
	 * internationalization and initialization, and fires the beer_slurper_loaded action.
	 */
	public function test_setup() {
		// Setup
		\WP_Mock::expectActionAdded( 'init', 'Kraft\Beer_Slurper\Core\i18n' );
		\WP_Mock::expectActionAdded( 'init', 'Kraft\Beer_Slurper\Core\init' );
		\WP_Mock::expectAction( 'beer_slurper_loaded' );

		// Act
		setup();

		// Verify
		$this->assertConditionsMet();
	}

	/**
	 * Tests i18n() loads text domain for translations.
	 *
	 * Verifies that the internationalization function correctly loads the
	 * plugin's text domain from both the global languages directory and
	 * the plugin's languages folder.
	 */
	public function test_i18n() {
		// Setup
		\WP_Mock::wpFunction( 'get_locale', array(
			'times' => 1,
			'args' => array(),
			'return' => 'en_US',
		) );
		\WP_Mock::onFilter( 'plugin_locale' )->with( 'en_US', 'beer_slurper' )->reply( 'en_US' );
		\WP_Mock::wpFunction( 'load_textdomain', array(
			'times' => 1,
			'args' => array( 'beer_slurper', 'lang_dir/beer_slurper/beer_slurper-en_US.mo' ),
		) );
		\WP_Mock::wpFunction( 'plugin_basename', array(
			'times' => 1,
			'args' => array( 'path' ),
			'return' => 'path',
		) );
		\WP_Mock::wpFunction( 'load_plugin_textdomain', array(
			'times' => 1,
			'args' => array( 'beer_slurper', false, 'path/languages/' ),
		) );

		// Act
		i18n();

		// Verify
		$this->assertConditionsMet();
	}

	/**
	 * Tests init() fires the beer_slurper_init action.
	 *
	 * Verifies that the initialization function correctly triggers the
	 * beer_slurper_init action hook for other components to hook into.
	 */
	public function test_init() {
		// Setup
		\WP_Mock::expectAction( 'beer_slurper_init' );

		// Act
		init();

		// Verify
		$this->assertConditionsMet();
	}

	/**
	 * Tests activate() flushes rewrite rules on plugin activation.
	 *
	 * Verifies that the activation function correctly flushes WordPress
	 * rewrite rules to register the custom post type permalinks.
	 */
	public function test_activate() {
		// Setup
		\WP_Mock::wpFunction( 'flush_rewrite_rules', array(
			'times' => 1
		) );

		// Act
		activate();

		// Verify
		$this->assertConditionsMet();
	}

	/**
	 * Tests deactivate() performs cleanup on plugin deactivation.
	 *
	 * Verifies that the deactivation function executes without errors.
	 * Currently a placeholder for future cleanup operations.
	 */
	public function test_deactivate() {
		// Setup

		// Act
		deactivate();

		// Verify
	}
}
