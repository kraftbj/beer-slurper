<?php
/**
 * Base Test Case for Beer Slurper
 *
 * Provides a base test case class with common utilities and setup methods
 * for all Beer Slurper PHPUnit tests.
 *
 * @package Kraft\Beer_Slurper
 */

namespace Kraft\Beer_Slurper;

use PHPUnit_Framework_TestResult;
use Text_Template;
use WP_Mock;
use WP_Mock\Tools\TestCase as BaseTestCase;

/**
 * Base test case class for Beer Slurper tests.
 *
 * Extends WP_Mock\Tools\TestCase to provide common functionality for all
 * plugin tests, including automatic file loading, namespace resolution,
 * and custom assertion helpers.
 */
class TestCase extends BaseTestCase {
	/**
	 * Runs the test case with global state preservation disabled.
	 *
	 * Disables global state preservation before running the test to prevent
	 * issues with constants and global variables between test runs.
	 *
	 * @param PHPUnit_Framework_TestResult|null $result The test result collector.
	 * @return PHPUnit_Framework_TestResult The test result.
	 */
	public function run( PHPUnit_Framework_TestResult $result = null ) {
		$this->setPreserveGlobalState( false );
		return parent::run( $result );
	}

	/**
	 * Array of test files to load before running tests.
	 *
	 * @var array
	 */
	protected $testFiles = array();

	/**
	 * Sets up the test environment before each test.
	 *
	 * Loads any files specified in the $testFiles property and calls the
	 * parent setUp method to initialize WP_Mock.
	 *
	 * @return void
	 */
	public function setUp() {
		if ( ! empty( $this->testFiles ) ) {
			foreach ( $this->testFiles as $file ) {
				if ( file_exists( PROJECT . $file ) ) {
					require_once( PROJECT . $file );
				}
			}
		}

		parent::setUp();
	}

	/**
	 * Asserts that all expected WordPress actions were called.
	 *
	 * Wraps WP_Mock::assertActionsCalled() to provide a cleaner assertion
	 * interface and better error messages when expected actions are not called.
	 *
	 * @return void
	 */
	public function assertActionsCalled() {
		$actions_not_added = $expected_actions = 0;
		try {
			WP_Mock::assertActionsCalled();
		} catch ( \Exception $e ) {
			$actions_not_added = 1;
			$expected_actions  = $e->getMessage();
		}
		$this->assertEmpty( $actions_not_added, $expected_actions );
	}

	/**
	 * Resolves a function name to its fully qualified namespace.
	 *
	 * Takes a function name and prepends the current test class's namespace
	 * to create a fully qualified function name for mocking purposes.
	 *
	 * @param string $function The function name to namespace.
	 * @return string The fully qualified function name with namespace.
	 */
	public function ns( $function ) {
		if ( ! is_string( $function ) || false !== strpos( $function, '\\' ) ) {
			return $function;
		}

		$thisClassName = trim( get_class( $this ), '\\' );

		if ( ! strpos( $thisClassName, '\\' ) ) {
			return $function;
		}

		// $thisNamespace is constructed by exploding the current class name on
		// namespace separators, running array_slice on that array starting at 0
		// and ending one element from the end (chops the class name off) and
		// imploding that using namespace separators as the glue.
		$thisNamespace = implode( '\\', array_slice( explode( '\\', $thisClassName ), 0, - 1 ) );

		return "$thisNamespace\\$function";
	}

	/**
	 * Prepares the PHPUnit template for process isolation.
	 *
	 * Sets up the template with global variables needed for process isolation
	 * to work correctly with constants. This ensures the bootstrap file is
	 * properly referenced in isolated test processes.
	 *
	 * @see http://kpayne.me/2012/07/02/phpunit-process-isolation-and-constant-already-defined/
	 *
	 * @param \Text_Template $template The PHPUnit template to prepare.
	 * @return void
	 */
	public function prepareTemplate( \Text_Template $template ) {
		$template->setVar( [
			'globals' => '$GLOBALS[\'__PHPUNIT_BOOTSTRAP\'] = \'' . $GLOBALS['__PHPUNIT_BOOTSTRAP'] . '\';',
		] );
		parent::prepareTemplate( $template );
	}
}
