<?php
/**
 * PHPUnit Bootstrap File
 *
 * This file initializes the testing environment for the Beer Slurper plugin.
 * It defines necessary constants, loads Composer dependencies, and bootstraps
 * WorDBless for running tests with real WordPress functions.
 *
 * @package Kraft\Beer_Slurper\Tests
 */

/*
 * Composer autoloader validation and loading.
 *
 * Verifies that Composer dependencies have been installed before proceeding.
 * Throws an exception if the autoloader is missing.
 */
if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	throw new PHPUnit\Framework\Exception(
		'ERROR' . PHP_EOL . PHP_EOL .
		'You must use Composer to install the test suite\'s dependencies!' . PHP_EOL
	);
}

require_once __DIR__ . '/vendor/autoload.php';

/*
 * Define ABSPATH before loading WorDBless.
 * WorDBless expects WordPress to be in vendor/wordpress directory.
 */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/wordpress/' );
}

/*
 * WorDBless initialization.
 *
 * Loads WordPress core functions for testing with SQLite database.
 * This enables full database functionality including Action Scheduler.
 */
\WorDBless\Load::load( 'sqlite' );

/*
 * Enable term meta support.
 *
 * WordPress checks db_version to determine if term meta is supported (added in WP 4.4).
 * WorDBless doesn't set this option, so WordPress disables term meta operations.
 * We set it to a modern WP version to enable full term meta functionality.
 */
if ( ! get_option( 'db_version' ) ) {
	update_option( 'db_version', 58975 );
}

/*
 * Project directory constant.
 *
 * Defines the path to the includes directory for loading project files.
 */
if ( ! defined( 'PROJECT' ) ) {
	define( 'PROJECT', __DIR__ . '/includes/' );
}

/*
 * Beer Slurper directory constant.
 *
 * Defines the root path of the Beer Slurper plugin.
 */
if ( ! defined( 'BEER_SLURPER_DIR' ) ) {
	define( 'BEER_SLURPER_DIR', __DIR__ . '/' );
}

/*
 * Plugin path constants.
 */
if ( ! defined( 'BEER_SLURPER_PATH' ) ) {
	define( 'BEER_SLURPER_PATH', __DIR__ . '/beer-slurper.php' );
}

if ( ! defined( 'BEER_SLURPER_INC' ) ) {
	define( 'BEER_SLURPER_INC', __DIR__ . '/includes/' );
}

// Load plugin constants needed for tests
// Note: BEER_SLURPER_PATH and BEER_SLURPER_INC are already defined above
if ( ! defined( 'BEER_SLURPER_CPT' ) ) {
	define( 'BEER_SLURPER_CPT', 'beerlog_beer' );
}
if ( ! defined( 'BEER_SLURPER_TAX_STYLE' ) ) {
	define( 'BEER_SLURPER_TAX_STYLE', 'beerlog_style' );
}
if ( ! defined( 'BEER_SLURPER_TAX_BREWERY' ) ) {
	define( 'BEER_SLURPER_TAX_BREWERY', 'beerlog_brewery' );
}
if ( ! defined( 'BEER_SLURPER_TAX_VENUE' ) ) {
	define( 'BEER_SLURPER_TAX_VENUE', 'beerlog_venue' );
}
if ( ! defined( 'BEER_SLURPER_TAX_BADGE' ) ) {
	define( 'BEER_SLURPER_TAX_BADGE', 'beerlog_badge' );
}
if ( ! defined( 'BEER_SLURPER_TAX_COMPANION' ) ) {
	define( 'BEER_SLURPER_TAX_COMPANION', 'beerlog_companion' );
}

require_once __DIR__ . '/tests/phpunit/test-tools/TestCase.php';
