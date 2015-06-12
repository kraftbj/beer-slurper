<?php
namespace Kraft\Beer_Slurper\Core;

/**
 * This is a very basic test case to get things started. You should probably rename this and make
 * it work for your project. You can use all the tools provided by WP Mock and Mockery to create
 * your tests. Coverage is calculated against your includes/ folder, so try to keep all of your
 * functional code self contained in there.
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
	 * Test load method.
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
	 * Test internationalization integration.
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
	 * Test initialization method.
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
	 * Test activation routine.
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
	 * Test deactivation routine.
	 */
	public function test_deactivate() {
		// Setup

		// Act
		deactivate();

		// Verify
	}
}