<?php
/**
 * Import Integration Tests for Beer Slurper
 *
 * Tests the full import flow including API calls, post creation,
 * and sync state management.
 *
 * @package Kraft\Beer_Slurper\Tests\Integration
 */

namespace Kraft\Beer_Slurper\Tests\Integration;

use Kraft\Beer_Slurper as Base;
use Kraft\Beer_Slurper\Tests\MockHttpClient;
use Kraft\Beer_Slurper\Tests\ApiFixtures;

/**
 * Tests for the full import pipeline.
 */
class Import_Tests extends Base\TestCase {

	protected $testFiles = [
		'functions/api.php',
		'functions/oauth.php',
		'functions/post.php',
		'functions/brewery.php',
		'functions/venue.php',
		'functions/badge.php',
		'functions/checkin.php',
		'functions/companion.php',
		'functions/toast.php',
		'functions/queue.php',
		'functions/scraper.php',
		'functions/walker.php',
	];

	protected $use_mock_http = true;

	/**
	 * Sets up test environment.
	 */
	protected function set_up(): void {
		parent::set_up();
		$this->set_api_credentials( 'key', 'secret', 'token' );
	}

	/**
	 * Sets up standard mocks for import testing.
	 *
	 * @param array $checkins Array of checkin data.
	 */
	private function setup_import_mocks( $checkins ) {
		// Mock user checkins endpoint
		$response = ApiFixtures::user_checkins_response( $checkins );
		MockHttpClient::mock_json( '*api.untappd.com*/v4/user/checkins/*', $response );

		// Mock beer info endpoint
		MockHttpClient::mock_json( '*api.untappd.com*/v4/beer/info/*', ApiFixtures::beer_info() );

		// Mock brewery info endpoint
		MockHttpClient::mock_json( '*api.untappd.com*/v4/brewery/info/*', ApiFixtures::brewery_info() );
	}

	/**
	 * Tests import_new() starts historical import on first run.
	 */
	public function test_import_new_starts_historical_import_first_run() {
		$this->setup_import_mocks( array( ApiFixtures::checkin() ) );

		$result = \Kraft\Beer_Slurper\Walker\import_new( 'testuser' );

		// First run should delegate to import_old, not return "No new beers"
		$this->assertNotEquals( 'No new beers here!', $result );
	}

	/**
	 * Tests import_new() returns error for empty username.
	 */
	public function test_import_new_returns_error_for_empty_username() {
		$result = \Kraft\Beer_Slurper\Walker\import_new( '' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'invalid_user', $result->get_error_code() );
	}

	/**
	 * Tests import_new() returns no beers message when no new checkins.
	 */
	public function test_import_new_returns_no_beers_message() {
		// Set a since_id to indicate prior sync
		update_option( 'beer_slurper_testuser_since', 12345 );

		// Mock empty response
		MockHttpClient::mock_json( '*api.untappd.com*/v4/user/checkins/*', array(
			'meta'     => array( 'code' => 200 ),
			'response' => array(
				'checkins' => array(
					'count' => 0,
					'items' => array(),
				),
			),
		) );

		$result = \Kraft\Beer_Slurper\Walker\import_new( 'testuser' );

		$this->assertEquals( 'No new beers here!', $result );
	}

	/**
	 * Tests import_new() updates since_id after successful import.
	 */
	public function test_import_new_updates_since_id() {
		// Set initial since_id
		update_option( 'beer_slurper_testuser_since', 10000 );

		$checkin = ApiFixtures::checkin( array( 'checkin_id' => 20000 ) );
		$this->setup_import_mocks( array( $checkin ) );

		\Kraft\Beer_Slurper\Walker\import_new( 'testuser' );

		$new_since = get_option( 'beer_slurper_testuser_since' );
		$this->assertEquals( 20000, $new_since );
	}

	/**
	 * Tests import_old() returns error for empty username.
	 */
	public function test_import_old_returns_error_for_empty_username() {
		$result = \Kraft\Beer_Slurper\Walker\import_old( '' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'invalid_user', $result->get_error_code() );
	}

	/**
	 * Tests import_old() updates pagination state.
	 */
	public function test_import_old_updates_pagination_state() {
		$checkin = ApiFixtures::checkin();

		// Create full response with pagination
		$response = array(
			'meta'     => array( 'code' => 200 ),
			'response' => array(
				'checkins' => array(
					'count' => 1,
					'items' => array( $checkin ),
				),
				'pagination' => array(
					'max_id'    => '99999',
					'since_url' => 'https://api.untappd.com/v4/user/checkins?min_id=12345',
				),
			),
		);

		MockHttpClient::mock_json( '*api.untappd.com*/v4/user/checkins/*', $response );
		MockHttpClient::mock_json( '*api.untappd.com*/v4/beer/info/*', ApiFixtures::beer_info() );
		MockHttpClient::mock_json( '*api.untappd.com*/v4/brewery/info/*', ApiFixtures::brewery_info() );

		\Kraft\Beer_Slurper\Walker\import_old( 'testuser' );

		$max_id = get_option( 'beer_slurper_testuser_max' );
		$this->assertEquals( '99999', $max_id );
	}

	/**
	 * Tests import_old() sets since_id on first import.
	 */
	public function test_import_old_sets_since_id_on_first_import() {
		$checkin = ApiFixtures::checkin();

		// Create full response with pagination
		$response = array(
			'meta'     => array( 'code' => 200 ),
			'response' => array(
				'checkins' => array(
					'count' => 1,
					'items' => array( $checkin ),
				),
				'pagination' => array(
					'max_id'    => '99999',
					'since_url' => 'https://api.untappd.com/v4/user/checkins?min_id=55555',
				),
			),
		);

		MockHttpClient::mock_json( '*api.untappd.com*/v4/user/checkins/*', $response );
		MockHttpClient::mock_json( '*api.untappd.com*/v4/beer/info/*', ApiFixtures::beer_info() );
		MockHttpClient::mock_json( '*api.untappd.com*/v4/brewery/info/*', ApiFixtures::brewery_info() );

		\Kraft\Beer_Slurper\Walker\import_old( 'testuser' );

		$since_id = get_option( 'beer_slurper_testuser_since' );
		$this->assertEquals( 55555, $since_id );
	}

	/**
	 * Tests import_old() handles API errors gracefully.
	 */
	public function test_import_old_handles_api_errors() {
		MockHttpClient::mock_error( '*api.untappd.com*', 'api_error', 'API unavailable' );

		$result = \Kraft\Beer_Slurper\Walker\import_old( 'testuser' );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Tests get_latest_local_checkin_id() returns highest ID.
	 */
	public function test_get_latest_local_checkin_id_returns_highest() {
		// Create posts with very high checkin IDs to ensure they're the highest
		$post1 = $this->create_beer_post();
		$post2 = $this->create_beer_post();

		// Use high values to ensure they're greater than any leftover data
		add_post_meta( $post1, '_beer_slurper_untappd_id', '9999900', false );
		add_post_meta( $post1, '_beer_slurper_untappd_id', '9999999', false );
		add_post_meta( $post2, '_beer_slurper_untappd_id', '9999950', false );

		$latest = \Kraft\Beer_Slurper\Walker\get_latest_local_checkin_id();

		$this->assertEquals( '9999999', $latest );
	}

	/**
	 * Tests get_latest_local_checkin_id() behavior.
	 *
	 * Note: This function queries the database directly, so we just verify
	 * the function is callable. Full isolation testing would require
	 * database transactions which WorDBless doesn't fully support.
	 */
	public function test_get_latest_local_checkin_id_is_callable() {
		$this->assertTrue( is_callable( 'Kraft\Beer_Slurper\Walker\get_latest_local_checkin_id' ) );
	}

	/**
	 * Tests import creates beer posts with all metadata.
	 */
	public function test_full_import_creates_complete_posts() {
		update_option( 'beer_slurper_testuser_since', 10000 );

		$checkin = ApiFixtures::checkin( array(
			'checkin_id'      => 20000,
			'checkin_comment' => 'Great beer!',
			'rating_score'    => 4.5,
			'venue'           => ApiFixtures::venue(),
		) );

		$this->setup_import_mocks( array( $checkin ) );

		// Import would normally queue, but let's test direct insert
		$post_id = \Kraft\Beer_Slurper\Post\insert_beer( $checkin );

		// Verify post was created
		$this->assertIsInt( $post_id );

		// Verify beer metadata
		$this->assertEquals( 12345, get_post_meta( $post_id, '_beer_slurper_id', true ) );

		// Verify taxonomies
		$brewery_terms = wp_get_object_terms( $post_id, BEER_SLURPER_TAX_BREWERY );
		$this->assertCount( 1, $brewery_terms );

		$style_terms = wp_get_object_terms( $post_id, BEER_SLURPER_TAX_STYLE );
		$this->assertCount( 1, $style_terms );

		$venue_terms = wp_get_object_terms( $post_id, BEER_SLURPER_TAX_VENUE );
		$this->assertCount( 1, $venue_terms );

		// Verify checkin comment
		$comments = get_comments( array(
			'post_id' => $post_id,
			'type'    => 'beer_checkin',
		) );
		$this->assertCount( 1, $comments );
		$this->assertEquals( 'Great beer!', $comments[0]->comment_content );
	}

	/**
	 * Tests import handles duplicate checkins correctly.
	 */
	public function test_import_handles_duplicates() {
		$checkin = ApiFixtures::checkin( array( 'checkin_id' => 99999 ) );
		$this->setup_import_mocks( array( $checkin ) );

		// First import
		$post_id1 = \Kraft\Beer_Slurper\Post\insert_beer( $checkin );
		$this->assertIsInt( $post_id1 );

		// Reset mocks
		MockHttpClient::clear_log();
		$this->setup_import_mocks( array( $checkin ) );

		// Second import with same checkin_id
		$result = \Kraft\Beer_Slurper\Post\insert_beer( $checkin );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'already_done', $result->get_error_code() );
	}
}
