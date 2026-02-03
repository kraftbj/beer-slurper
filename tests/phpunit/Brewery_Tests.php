<?php
/**
 * Brewery Tests for Beer Slurper
 *
 * Tests for brewery taxonomy term creation, lookup, and metadata storage.
 *
 * @package Kraft\Beer_Slurper\Brewery
 */

namespace Kraft\Beer_Slurper\Brewery;

use Kraft\Beer_Slurper as Base;
use Kraft\Beer_Slurper\Tests\MockHttpClient;
use Kraft\Beer_Slurper\Tests\ApiFixtures;

/**
 * Tests for the Brewery functions.
 */
class Brewery_Tests extends Base\TestCase {

	protected $testFiles = [
		'functions/api.php',
		'functions/oauth.php',
		'functions/brewery.php',
		'functions/queue.php',
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
	 * Tests get_brewery_term_id() returns false for empty ID.
	 */
	public function test_get_brewery_term_id_returns_false_for_empty() {
		$result = get_brewery_term_id( null );
		$this->assertFalse( $result );

		$result = get_brewery_term_id( '' );
		$this->assertFalse( $result );
	}

	/**
	 * Tests get_brewery_term_id() creates new term.
	 */
	public function test_get_brewery_term_id_creates_new_term() {
		MockHttpClient::mock_json( '*api.untappd.com*/v4/brewery/info/*', ApiFixtures::brewery_info() );

		$term_id = get_brewery_term_id( 54321 );

		$this->assertIsInt( $term_id );
		$this->assertGreaterThan( 0, $term_id );

		$term = get_term( $term_id );
		$this->assertEquals( 'Test Brewing Company', $term->name );
	}

	/**
	 * Tests get_brewery_term_id() returns existing term.
	 */
	public function test_get_brewery_term_id_returns_existing_term() {
		// Create existing term
		$existing_id = $this->create_brewery_term( array( 'name' => 'Existing Brewery' ) );
		update_term_meta( $existing_id, 'untappd_id', 99999 );

		$result = get_brewery_term_id( 99999 );

		$this->assertEquals( $existing_id, $result );
	}

	/**
	 * Tests get_brewery_term_id() stores untappd_id in meta.
	 */
	public function test_get_brewery_term_id_stores_untappd_id() {
		MockHttpClient::mock_json( '*api.untappd.com*/v4/brewery/info/*', ApiFixtures::brewery_info( array(
			'brewery' => array( 'brewery_id' => 12345 ),
		) ) );

		$term_id = get_brewery_term_id( 12345 );

		$stored_id = get_term_meta( $term_id, 'untappd_id', true );
		$this->assertEquals( '12345', $stored_id ); // Meta values are stored as strings
	}

	/**
	 * Tests get_brewery_term_id() uses fallback data when API fails.
	 */
	public function test_get_brewery_term_id_uses_fallback_data() {
		MockHttpClient::mock_error( '*api.untappd.com*', 'api_error', 'API failed' );

		$fallback_data = ApiFixtures::brewery_basic( array(
			'brewery_id'   => 77777,
			'brewery_name' => 'Fallback Brewery',
		) );

		$term_id = get_brewery_term_id( 77777, $fallback_data );

		$this->assertIsInt( $term_id );
		$term = get_term( $term_id );
		$this->assertEquals( 'Fallback Brewery', $term->name );
	}

	/**
	 * Tests add_brewery() creates term with slug.
	 */
	public function test_add_brewery_creates_term_with_slug() {
		MockHttpClient::mock_json( '*api.untappd.com*/v4/brewery/info/*', ApiFixtures::brewery_info( array(
			'brewery' => array(
				'brewery_id'   => 11111,
				'brewery_name' => 'Sluggy Brewing',
				'brewery_slug' => 'sluggy-brewing',
			),
		) ) );

		$term_id = add_brewery( 11111 );

		$term = get_term( $term_id );
		$this->assertEquals( 'sluggy-brewing', $term->slug );
	}

	/**
	 * Tests add_brewery() handles duplicate term.
	 */
	public function test_add_brewery_handles_duplicate() {
		// Create existing term
		$existing = $this->create_brewery_term( array(
			'name' => 'Duplicate Brewery',
			'slug' => 'duplicate-brewery',
		) );

		MockHttpClient::mock_json( '*api.untappd.com*/v4/brewery/info/*', ApiFixtures::brewery_info( array(
			'brewery' => array(
				'brewery_id'   => 22222,
				'brewery_name' => 'Duplicate Brewery',
				'brewery_slug' => 'duplicate-brewery',
			),
		) ) );

		$term_id = add_brewery( 22222 );

		$this->assertEquals( $existing, $term_id );
	}

	/**
	 * Tests save_brewery_meta() stores location data.
	 */
	public function test_save_brewery_meta_stores_location() {
		$term_id = $this->create_brewery_term();
		$brewery = ApiFixtures::brewery_basic();

		save_brewery_meta( $term_id, $brewery );

		$this->assertTermHasMeta( $term_id, array(
			'brewery_city'    => 'Portland',
			'brewery_state'   => 'OR',
			'brewery_country' => 'United States',
			'brewery_lat'     => 45.5051,
			'brewery_lng'     => -122.6750,
		) );
	}

	/**
	 * Tests save_brewery_meta() stores contact data.
	 */
	public function test_save_brewery_meta_stores_contact() {
		$term_id = $this->create_brewery_term();
		$brewery = ApiFixtures::brewery_basic();

		save_brewery_meta( $term_id, $brewery );

		$this->assertTermHasMeta( $term_id, array(
			'brewery_url'       => 'https://testbrewing.com',
			'brewery_twitter'   => 'testbrewing',
			'brewery_facebook'  => 'testbrewing',
			'brewery_instagram' => 'testbrewing',
		) );
	}

	/**
	 * Tests save_brewery_meta() stores type and label.
	 */
	public function test_save_brewery_meta_stores_type_and_label() {
		$term_id = $this->create_brewery_term();
		$brewery = ApiFixtures::brewery_basic( array(
			'brewery_type'  => 'Regional Brewery',
			'brewery_label' => 'https://example.com/label.png',
		) );

		save_brewery_meta( $term_id, $brewery );

		$this->assertTermHasMeta( $term_id, array(
			'brewery_type'  => 'Regional Brewery',
			'brewery_label' => 'https://example.com/label.png',
		) );
	}

	/**
	 * Tests maybe_update_brewery_meta() only updates changed values.
	 */
	public function test_maybe_update_brewery_meta_only_updates_changed() {
		$term_id = $this->create_brewery_term();
		update_term_meta( $term_id, 'brewery_city', 'Portland' );
		update_term_meta( $term_id, 'brewery_state', 'WA' ); // Different from fixture

		$brewery = array(
			'location' => array(
				'brewery_city'  => 'Portland', // Same
				'brewery_state' => 'OR',       // Changed
			),
		);

		maybe_update_brewery_meta( $term_id, $brewery );

		$this->assertEquals( 'Portland', get_term_meta( $term_id, 'brewery_city', true ) );
		$this->assertEquals( 'OR', get_term_meta( $term_id, 'brewery_state', true ) );
	}

	/**
	 * Tests get_brewery_term_id() refreshes meta from checkin data.
	 */
	public function test_get_brewery_term_id_refreshes_meta() {
		// Create existing term with old URL
		$term_id = $this->create_brewery_term();
		update_term_meta( $term_id, 'untappd_id', 88888 );
		update_term_meta( $term_id, 'brewery_url', 'https://old-url.com' );

		// New checkin data with updated URL
		$brewery_data = ApiFixtures::brewery_basic( array(
			'brewery_name' => 'Test Brewery',
			'contact'      => array( 'url' => 'https://new-url.com' ),
		) );

		get_brewery_term_id( 88888, $brewery_data );

		$this->assertEquals( 'https://new-url.com', get_term_meta( $term_id, 'brewery_url', true ) );
	}
}
