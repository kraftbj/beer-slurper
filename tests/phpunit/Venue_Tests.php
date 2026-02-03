<?php
/**
 * Venue Tests for Beer Slurper
 *
 * Tests for venue taxonomy term creation, lookup, and metadata storage.
 *
 * @package Kraft\Beer_Slurper\Venue
 */

namespace Kraft\Beer_Slurper\Venue;

use Kraft\Beer_Slurper as Base;
use Kraft\Beer_Slurper\Tests\ApiFixtures;

/**
 * Tests for the Venue functions.
 */
class Venue_Tests extends Base\TestCase {

	protected $testFiles = [
		'functions/venue.php',
	];

	/**
	 * Tests get_venue_term_id() returns false for empty ID.
	 */
	public function test_get_venue_term_id_returns_false_for_empty() {
		$result = get_venue_term_id( null );
		$this->assertFalse( $result );

		$result = get_venue_term_id( '' );
		$this->assertFalse( $result );
	}

	/**
	 * Tests get_venue_term_id() creates new term.
	 */
	public function test_get_venue_term_id_creates_new_term() {
		$venue_data = ApiFixtures::venue( array( 'venue_id' => 11111 ) );

		$term_id = get_venue_term_id( 11111, $venue_data );

		$this->assertIsInt( $term_id );
		$this->assertGreaterThan( 0, $term_id );

		$term = get_term( $term_id );
		$this->assertEquals( 'Test Taproom', $term->name );
	}

	/**
	 * Tests get_venue_term_id() returns existing term.
	 */
	public function test_get_venue_term_id_returns_existing_term() {
		$existing_id = $this->create_venue_term( array( 'name' => 'Existing Venue' ) );
		update_term_meta( $existing_id, 'untappd_id', 99999 );

		$result = get_venue_term_id( 99999 );

		$this->assertEquals( $existing_id, $result );
	}

	/**
	 * Tests get_venue_term_id() stores untappd_id.
	 */
	public function test_get_venue_term_id_stores_untappd_id() {
		$venue_data = ApiFixtures::venue( array( 'venue_id' => 22222 ) );

		$term_id = get_venue_term_id( 22222, $venue_data );

		$stored_id = get_term_meta( $term_id, 'untappd_id', true );
		$this->assertEquals( 22222, $stored_id );
	}

	/**
	 * Tests add_venue() returns error without venue data.
	 */
	public function test_add_venue_returns_error_without_data() {
		$result = add_venue( 12345, null );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'invalid_venue', $result->get_error_code() );
	}

	/**
	 * Tests add_venue() returns error without venue name.
	 */
	public function test_add_venue_returns_error_without_name() {
		$result = add_venue( 12345, array( 'venue_id' => 12345 ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Tests add_venue() creates term with slug.
	 */
	public function test_add_venue_creates_term_with_slug() {
		$venue_data = ApiFixtures::venue( array(
			'venue_name' => 'Sluggy Bar',
			'venue_slug' => 'sluggy-bar',
		) );

		$term_id = add_venue( 33333, $venue_data );

		$term = get_term( $term_id );
		$this->assertEquals( 'sluggy-bar', $term->slug );
	}

	/**
	 * Tests add_venue() handles duplicate term.
	 */
	public function test_add_venue_handles_duplicate() {
		$existing = $this->create_venue_term( array(
			'name' => 'Duplicate Venue',
			'slug' => 'duplicate-venue',
		) );

		$venue_data = ApiFixtures::venue( array(
			'venue_name' => 'Duplicate Venue',
			'venue_slug' => 'duplicate-venue',
		) );

		$term_id = add_venue( 44444, $venue_data );

		$this->assertEquals( $existing, $term_id );
	}

	/**
	 * Tests save_venue_meta() stores location data.
	 */
	public function test_save_venue_meta_stores_location() {
		$term_id = $this->create_venue_term();
		$venue_data = ApiFixtures::venue();

		save_venue_meta( $term_id, 55555, $venue_data );

		$this->assertTermHasMeta( $term_id, array(
			'venue_address' => '456 Tap Street',
			'venue_city'    => 'Portland',
			'venue_state'   => 'OR',
			'venue_country' => 'United States',
			'venue_lat'     => 45.5051,
			'venue_lng'     => -122.6750,
		) );
	}

	/**
	 * Tests save_venue_meta() stores URL from contact.
	 */
	public function test_save_venue_meta_stores_url_from_contact() {
		$term_id = $this->create_venue_term();
		$venue_data = ApiFixtures::venue( array(
			'contact' => array( 'venue_url' => 'https://venue-contact.com' ),
		) );

		save_venue_meta( $term_id, 66666, $venue_data );

		$this->assertEquals( 'https://venue-contact.com', get_term_meta( $term_id, 'venue_url', true ) );
	}

	/**
	 * Tests save_venue_meta() stores category.
	 */
	public function test_save_venue_meta_stores_category() {
		$term_id = $this->create_venue_term();
		$venue_data = ApiFixtures::venue( array(
			'primary_category' => 'Bar & Restaurant',
		) );

		save_venue_meta( $term_id, 77777, $venue_data );

		$this->assertEquals( 'Bar & Restaurant', get_term_meta( $term_id, 'venue_category', true ) );
	}

	/**
	 * Tests save_venue_meta() stores icon.
	 */
	public function test_save_venue_meta_stores_icon() {
		$term_id = $this->create_venue_term();
		$venue_data = ApiFixtures::venue( array(
			'venue_icon' => array( 'sm' => 'https://example.com/icon.png' ),
		) );

		save_venue_meta( $term_id, 88888, $venue_data );

		$this->assertEquals( 'https://example.com/icon.png', get_term_meta( $term_id, 'venue_icon', true ) );
	}

	/**
	 * Tests save_venue_meta() stores foursquare ID.
	 */
	public function test_save_venue_meta_stores_foursquare_id() {
		$term_id = $this->create_venue_term();
		$venue_data = ApiFixtures::venue( array(
			'foursquare' => array( 'foursquare_id' => '4abc123xyz' ),
		) );

		save_venue_meta( $term_id, 99999, $venue_data );

		$this->assertEquals( '4abc123xyz', get_term_meta( $term_id, 'foursquare_id', true ) );
	}

	/**
	 * Tests maybe_update_venue_meta() only updates changed values.
	 */
	public function test_maybe_update_venue_meta_only_updates_changed() {
		$term_id = $this->create_venue_term();
		update_term_meta( $term_id, 'venue_city', 'Portland' );
		update_term_meta( $term_id, 'venue_state', 'WA' );

		$venue_data = array(
			'location' => array(
				'venue_city'  => 'Portland',
				'venue_state' => 'OR',
			),
		);

		maybe_update_venue_meta( $term_id, 11111, $venue_data );

		$this->assertEquals( 'Portland', get_term_meta( $term_id, 'venue_city', true ) );
		$this->assertEquals( 'OR', get_term_meta( $term_id, 'venue_state', true ) );
	}

	/**
	 * Tests get_venue_term_id() refreshes meta from checkin data.
	 */
	public function test_get_venue_term_id_refreshes_meta() {
		$term_id = $this->create_venue_term();
		update_term_meta( $term_id, 'untappd_id', 10101 );
		update_term_meta( $term_id, 'venue_category', 'Old Category' );

		$venue_data = ApiFixtures::venue( array(
			'venue_name'       => 'Test Venue',
			'primary_category' => 'New Category',
		) );

		get_venue_term_id( 10101, $venue_data );

		$this->assertEquals( 'New Category', get_term_meta( $term_id, 'venue_category', true ) );
	}
}
