<?php
/**
 * Base Test Case for Beer Slurper
 *
 * Provides a base test case class with common utilities and setup methods
 * for all Beer Slurper PHPUnit tests using WorDBless with SQLite.
 *
 * @package Kraft\Beer_Slurper
 */

namespace Kraft\Beer_Slurper;

use WorDBless\BaseTestCase;
use Kraft\Beer_Slurper\Tests\MockHttpClient;
use Kraft\Beer_Slurper\Tests\ApiFixtures;

// Load test tools
require_once __DIR__ . '/MockHttpClient.php';
require_once __DIR__ . '/ApiFixtures.php';

/**
 * Base test case class for Beer Slurper tests.
 *
 * Extends WorDBless BaseTestCase to provide common functionality
 * for all plugin tests with SQLite database support.
 */
class TestCase extends BaseTestCase {
	/**
	 * Array of test files to load before running tests.
	 *
	 * @var array
	 */
	protected $testFiles = array();

	/**
	 * Whether to use the mock HTTP client.
	 *
	 * @var bool
	 */
	protected $use_mock_http = false;

	/**
	 * Sets up the test environment before each test.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();

		// Clean up data from previous tests BEFORE this test runs
		// This ensures each test starts with a clean slate
		$this->cleanup_test_data();
		$this->cleanup_test_options();

		// Ensure db_version is set for term meta support
		// This must be done after cleanup as cleanup might remove it
		if ( ! get_option( 'db_version' ) ) {
			update_option( 'db_version', 58975 );
		}

		// Initialize mock HTTP client if needed
		if ( $this->use_mock_http ) {
			MockHttpClient::init();
		}

		// Register CPT and taxonomies for tests that need them
		$this->maybe_register_post_types();

		if ( ! empty( $this->testFiles ) ) {
			foreach ( $this->testFiles as $file ) {
				if ( file_exists( PROJECT . $file ) ) {
					require_once( PROJECT . $file );
				}
			}
		}
	}

	/**
	 * Tears down the test environment after each test.
	 *
	 * @return void
	 */
	protected function tear_down(): void {
		// Clean up mock HTTP client
		if ( $this->use_mock_http ) {
			MockHttpClient::cleanup();
		}

		// Clean up options created during this test
		$this->cleanup_test_options();

		// Clean up any created posts, terms, comments
		$this->cleanup_test_data();

		parent::tear_down();
	}

	/**
	 * Clean up test-specific options from the database.
	 *
	 * @return void
	 */
	protected function cleanup_test_options() {
		global $wpdb;

		// Delete beer_slurper options
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'beer_slurper%'" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'beer-slurper%'" );

		// Clear cron/scheduled events for our hooks
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name = 'cron'" );

		// Clear transients
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_beer_slurper%'" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_beer_slurper%'" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bs_%'" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_bs_%'" );

		// Clear any object cache
		wp_cache_flush();
	}

	/**
	 * Clean up test posts, terms, and comments.
	 *
	 * @return void
	 */
	protected function cleanup_test_data() {
		global $wpdb;

		// Delete all beer posts
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->posts} WHERE post_type = %s",
				BEER_SLURPER_CPT
			)
		);

		// Delete orphaned post meta
		$wpdb->query(
			"DELETE pm FROM {$wpdb->postmeta} pm
			LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE p.ID IS NULL"
		);

		// Delete beer_checkin comments
		$wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_type = 'beer_checkin'" );

		// Delete orphaned comment meta
		$wpdb->query(
			"DELETE cm FROM {$wpdb->commentmeta} cm
			LEFT JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id
			WHERE c.comment_ID IS NULL"
		);

		// Delete taxonomy terms
		$taxonomies = array(
			BEER_SLURPER_TAX_STYLE,
			BEER_SLURPER_TAX_BREWERY,
			BEER_SLURPER_TAX_VENUE,
			BEER_SLURPER_TAX_BADGE,
			BEER_SLURPER_TAX_COMPANION,
		);

		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_terms( array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'fields'     => 'ids',
			) );

			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term_id ) {
					wp_delete_term( $term_id, $taxonomy );
				}
			}
		}
	}

	/**
	 * Register post types and taxonomies if not already registered.
	 *
	 * @return void
	 */
	protected function maybe_register_post_types() {
		// Load CPT functions if not already loaded
		if ( ! function_exists( '\Kraft\Beer_Slurper\CPT\init_cpt' ) ) {
			if ( file_exists( PROJECT . 'functions/cpt.php' ) ) {
				require_once PROJECT . 'functions/cpt.php';
			} else {
				return; // CPT file not available
			}
		}

		if ( ! post_type_exists( BEER_SLURPER_CPT ) ) {
			\Kraft\Beer_Slurper\CPT\init_cpt();
		}

		if ( ! taxonomy_exists( BEER_SLURPER_TAX_BREWERY ) ) {
			\Kraft\Beer_Slurper\CPT\init_tax_brewery();
		}

		if ( ! taxonomy_exists( BEER_SLURPER_TAX_STYLE ) ) {
			\Kraft\Beer_Slurper\CPT\init_tax_style();
		}

		if ( ! taxonomy_exists( BEER_SLURPER_TAX_VENUE ) ) {
			\Kraft\Beer_Slurper\CPT\init_tax_venue();
		}

		if ( ! taxonomy_exists( BEER_SLURPER_TAX_BADGE ) ) {
			\Kraft\Beer_Slurper\CPT\init_tax_badge();
		}

		if ( ! taxonomy_exists( BEER_SLURPER_TAX_COMPANION ) ) {
			\Kraft\Beer_Slurper\CPT\init_tax_companion();
		}
	}

	/**
	 * Resolves a function name to its fully qualified namespace.
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

		$thisNamespace = implode( '\\', array_slice( explode( '\\', $thisClassName ), 0, - 1 ) );

		return "$thisNamespace\\$function";
	}

	/**
	 * Creates a beer post for testing.
	 *
	 * @param array $args Optional. Post arguments.
	 *
	 * @return int The post ID.
	 */
	protected function create_beer_post( $args = array() ) {
		$defaults = array(
			'post_type'   => BEER_SLURPER_CPT,
			'post_title'  => 'Test Beer ' . wp_generate_uuid4(),
			'post_status' => 'publish',
		);

		$args = array_merge( $defaults, $args );
		return wp_insert_post( $args );
	}

	/**
	 * Creates a brewery term for testing.
	 *
	 * @param array $args Optional. Term arguments.
	 *
	 * @return int The term ID.
	 */
	protected function create_brewery_term( $args = array() ) {
		$name = $args['name'] ?? 'Test Brewery ' . wp_generate_uuid4();
		unset( $args['name'] );

		$result = wp_insert_term( $name, BEER_SLURPER_TAX_BREWERY, $args );
		return is_wp_error( $result ) ? 0 : $result['term_id'];
	}

	/**
	 * Creates a venue term for testing.
	 *
	 * @param array $args Optional. Term arguments.
	 *
	 * @return int The term ID.
	 */
	protected function create_venue_term( $args = array() ) {
		$name = $args['name'] ?? 'Test Venue ' . wp_generate_uuid4();
		unset( $args['name'] );

		$result = wp_insert_term( $name, BEER_SLURPER_TAX_VENUE, $args );
		return is_wp_error( $result ) ? 0 : $result['term_id'];
	}

	/**
	 * Creates a badge term for testing.
	 *
	 * @param array $args Optional. Term arguments.
	 *
	 * @return int The term ID.
	 */
	protected function create_badge_term( $args = array() ) {
		$name = $args['name'] ?? 'Test Badge ' . wp_generate_uuid4();
		unset( $args['name'] );

		$result = wp_insert_term( $name, BEER_SLURPER_TAX_BADGE, $args );
		return is_wp_error( $result ) ? 0 : $result['term_id'];
	}

	/**
	 * Creates a companion term for testing.
	 *
	 * @param array $args Optional. Term arguments.
	 *
	 * @return int The term ID.
	 */
	protected function create_companion_term( $args = array() ) {
		$name = $args['name'] ?? 'Test Companion ' . wp_generate_uuid4();
		unset( $args['name'] );

		$result = wp_insert_term( $name, BEER_SLURPER_TAX_COMPANION, $args );
		return is_wp_error( $result ) ? 0 : $result['term_id'];
	}

	/**
	 * Mocks an Untappd API response.
	 *
	 * @param string $pattern URL pattern to match.
	 * @param array  $data    Response data.
	 * @param int    $code    HTTP status code.
	 * @param array  $headers Optional headers.
	 *
	 * @return void
	 */
	protected function mock_api_response( $pattern, $data, $code = 200, $headers = array() ) {
		MockHttpClient::mock_json( $pattern, $data, $code, $headers );
	}

	/**
	 * Mocks an Untappd API error.
	 *
	 * @param string $pattern URL pattern to match.
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 *
	 * @return void
	 */
	protected function mock_api_error( $pattern, $code, $message ) {
		MockHttpClient::mock_error( $pattern, $code, $message );
	}

	/**
	 * Sets up API credentials for testing.
	 *
	 * @param string $key    API key.
	 * @param string $secret API secret.
	 * @param string $token  Optional access token.
	 *
	 * @return void
	 */
	protected function set_api_credentials( $key = 'test_key', $secret = 'test_secret', $token = null ) {
		update_option( 'beer-slurper-key', $key );
		update_option( 'beer-slurper-secret', $secret );

		if ( $token ) {
			update_option( 'beer-slurper-access-token', $token );
		}
	}

	/**
	 * Asserts that a post has specific meta values.
	 *
	 * @param int   $post_id  The post ID.
	 * @param array $expected Array of meta_key => expected_value pairs.
	 *
	 * @return void
	 */
	protected function assertPostHasMeta( $post_id, $expected ) {
		foreach ( $expected as $key => $value ) {
			$actual = get_post_meta( $post_id, $key, true );
			$this->assertEquals( $value, $actual, "Post meta {$key} does not match expected value." );
		}
	}

	/**
	 * Asserts that a term has specific meta values.
	 *
	 * @param int   $term_id  The term ID.
	 * @param array $expected Array of meta_key => expected_value pairs.
	 *
	 * @return void
	 */
	protected function assertTermHasMeta( $term_id, $expected ) {
		foreach ( $expected as $key => $value ) {
			$actual = get_term_meta( $term_id, $key, true );
			$this->assertEquals( $value, $actual, "Term meta {$key} does not match expected value." );
		}
	}

	/**
	 * Asserts that a post has specific taxonomy terms.
	 *
	 * @param int    $post_id  The post ID.
	 * @param string $taxonomy The taxonomy name.
	 * @param array  $term_ids Expected term IDs.
	 *
	 * @return void
	 */
	protected function assertPostHasTerms( $post_id, $taxonomy, $term_ids ) {
		$actual = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
		$expected = $term_ids;
		sort( $expected );
		sort( $actual );
		$this->assertEquals( $expected, $actual, "Post does not have expected {$taxonomy} terms." );
	}
}
