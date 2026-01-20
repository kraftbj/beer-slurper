<?php
/**
 * PHPUnit Bootstrap File
 *
 * This file initializes the testing environment for the Beer Slurper plugin.
 * It defines necessary constants, loads Composer dependencies, and bootstraps
 * WP_Mock for mocking WordPress functions during unit tests.
 *
 * @package Kraft\Beer_Slurper\Tests
 */

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
 * WordPress and plugin constants for testing.
 *
 * These constants are normally defined by WordPress or the main plugin file.
 * They are defined here with placeholder values to allow tests to run
 * without a full WordPress installation.
 */
if ( ! defined( 'WP_LANG_DIR' ) ) {
	define( 'WP_LANG_DIR', 'lang_dir' );
}
if ( ! defined( 'BEER_SLURPER_PATH' ) ) {
	define( 'BEER_SLURPER_PATH', 'path' );
}

/*
 * Composer autoloader validation and loading.
 *
 * Verifies that Composer dependencies have been installed before proceeding.
 * Throws an exception if the autoloader is missing.
 */
if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	throw new PHPUnit_Framework_Exception(
		'ERROR' . PHP_EOL . PHP_EOL .
		'You must use Composer to install the test suite\'s dependencies!' . PHP_EOL
	);
}

require_once __DIR__ . '/vendor/autoload.php';

require_once __DIR__ . '/tests/phpunit/test-tools/TestCase.php';

/*
 * WP_Mock initialization.
 *
 * Configures WP_Mock with Patchwork support for function mocking and
 * bootstraps the mocking environment. The tearDown call ensures a clean
 * state before tests begin.
 */
WP_Mock::setUsePatchwork( true );
WP_Mock::bootstrap();
WP_Mock::tearDown();
