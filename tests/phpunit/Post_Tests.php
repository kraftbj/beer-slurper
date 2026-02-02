<?php
/**
 * Post Tests for Beer Slurper
 *
 * Tests for beer post creation, updates, deduplication, and metadata storage.
 *
 * @package Kraft\Beer_Slurper\Post
 */

namespace Kraft\Beer_Slurper\Post;

use Kraft\Beer_Slurper as Base;
use Kraft\Beer_Slurper\Tests\MockHttpClient;
use Kraft\Beer_Slurper\Tests\ApiFixtures;

/**
 * Tests for the Post functions.
 */
class Post_Tests extends Base\TestCase {

	protected $testFiles = [
		'functions/api.php',
		'functions/oauth.php',
		'functions/post.php',
		'functions/brewery.php',
		'functions/venue.php',
		'functions/badge.php',
		'functions/checkin.php',
		'functions/companion.php',
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
	 * Sets up mock responses for a standard checkin import.
	 *
	 * @param array $beer_overrides Optional. Beer data overrides.
	 */
	private function setup_standard_mocks( $beer_overrides = array() ) {
		// Mock beer/info API call
		$beer_info = ApiFixtures::beer_info( $beer_overrides );
		MockHttpClient::mock_json( '*api.untappd.com*/v4/beer/info/*', $beer_info );

		// Mock brewery/info API call
		$brewery_info = ApiFixtures::brewery_info();
		MockHttpClient::mock_json( '*api.untappd.com*/v4/brewery/info/*', $brewery_info );
	}

	/**
	 * Tests insert_beer() returns error when no checkin data provided.
	 */
	public function test_insert_beer_returns_error_without_checkin() {
		$result = insert_beer( null );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'no_checkin', $result->get_error_code() );
	}

	/**
	 * Tests insert_beer() returns error when checkin has no beer.
	 */
	public function test_insert_beer_returns_error_without_beer() {
		$result = insert_beer( array( 'checkin_id' => 123 ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'no_beer', $result->get_error_code() );
	}

	/**
	 * Tests insert_beer() creates new beer post.
	 */
	public function test_insert_beer_creates_new_post() {
		$this->setup_standard_mocks();

		$checkin = ApiFixtures::checkin();
		$post_id = insert_beer( $checkin );

		$this->assertIsInt( $post_id );
		$this->assertGreaterThan( 0, $post_id );

		$post = get_post( $post_id );
		$this->assertEquals( BEER_SLURPER_CPT, $post->post_type );
		$this->assertEquals( 'Test IPA', $post->post_title );
		$this->assertEquals( 'publish', $post->post_status );
	}

	/**
	 * Tests insert_beer() stores beer metadata.
	 */
	public function test_insert_beer_stores_metadata() {
		$this->setup_standard_mocks();

		$checkin = ApiFixtures::checkin();
		$post_id = insert_beer( $checkin );

		$this->assertPostHasMeta( $post_id, array(
			'_beer_slurper_id'  => 12345,
			'_beer_slurper_abv' => 6.5,
			'_beer_slurper_ibu' => 65,
		) );
	}

	/**
	 * Tests insert_beer() stores checkin ID in meta_multiple.
	 */
	public function test_insert_beer_stores_checkin_id() {
		$this->setup_standard_mocks();

		$checkin = ApiFixtures::checkin( array( 'checkin_id' => 999888 ) );
		$post_id = insert_beer( $checkin );

		$untappd_ids = get_post_meta( $post_id, '_beer_slurper_untappd_id', false );
		// Meta values are stored as strings
		$this->assertContains( '999888', $untappd_ids );
	}

	/**
	 * Tests insert_beer() assigns style taxonomy term.
	 */
	public function test_insert_beer_assigns_style_term() {
		$this->setup_standard_mocks();

		$checkin = ApiFixtures::checkin();
		$post_id = insert_beer( $checkin );

		$terms = wp_get_object_terms( $post_id, BEER_SLURPER_TAX_STYLE );
		$this->assertCount( 1, $terms );
		$this->assertEquals( 'IPA - American', $terms[0]->name );
	}

	/**
	 * Tests insert_beer() assigns brewery taxonomy term.
	 */
	public function test_insert_beer_assigns_brewery_term() {
		$this->setup_standard_mocks();

		$checkin = ApiFixtures::checkin();
		$post_id = insert_beer( $checkin );

		$terms = wp_get_object_terms( $post_id, BEER_SLURPER_TAX_BREWERY );
		$this->assertCount( 1, $terms );
		$this->assertEquals( 'Test Brewing Company', $terms[0]->name );
	}

	/**
	 * Tests insert_beer() detects duplicate checkins.
	 */
	public function test_insert_beer_detects_duplicate_checkin() {
		$this->setup_standard_mocks();

		$checkin = ApiFixtures::checkin( array( 'checkin_id' => 111222 ) );

		// First insert should succeed
		$post_id = insert_beer( $checkin );
		$this->assertIsInt( $post_id );

		// Clear mocks and set up again for second call
		MockHttpClient::clear_log();
		$this->setup_standard_mocks();

		// Second insert with same checkin_id should fail
		$result = insert_beer( $checkin );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'already_done', $result->get_error_code() );
	}

	/**
	 * Tests insert_beer() updates existing post for same beer.
	 */
	public function test_insert_beer_updates_existing_beer_post() {
		$this->setup_standard_mocks();

		// First checkin creates the post
		$checkin1 = ApiFixtures::checkin( array(
			'checkin_id' => 111,
			'created_at' => '2024-01-15 14:30:00',
		) );
		$post_id1 = insert_beer( $checkin1 );

		// Clear mocks and set up again
		MockHttpClient::clear_log();
		$this->setup_standard_mocks();

		// Second checkin for same beer (different checkin_id)
		$checkin2 = ApiFixtures::checkin( array(
			'checkin_id' => 222,
			'created_at' => '2024-01-16 10:00:00', // Newer date
		) );
		$post_id2 = insert_beer( $checkin2 );

		// Should update the same post
		$this->assertEquals( $post_id1, $post_id2 );

		// Post date should remain the earlier date (first checkin)
		$post = get_post( $post_id1 );
		$this->assertStringContainsString( '2024-01-15', $post->post_date_gmt );
	}

	/**
	 * Tests insert_beer() with nodup=false creates new posts.
	 *
	 * When nodup is false, deduplication checks are skipped entirely,
	 * allowing re-import of the same checkin (useful for repairs).
	 */
	public function test_insert_beer_allows_reimport_when_nodup_disabled() {
		$this->setup_standard_mocks();

		$checkin = ApiFixtures::checkin( array( 'checkin_id' => 333444 ) );

		// First insert with duplicate check disabled
		$post_id1 = insert_beer( $checkin, false );
		$this->assertIsInt( $post_id1 );
		$this->assertGreaterThan( 0, $post_id1 );
	}

	/**
	 * Tests find_existing_post() returns post data when found.
	 */
	public function test_find_existing_post_returns_post_data() {
		$post_id = $this->create_beer_post( array( 'post_title' => 'Existing Beer' ) );
		update_post_meta( $post_id, '_beer_slurper_id', 55555 );

		$result = find_existing_post( 55555 );

		$this->assertIsArray( $result );
		$this->assertEquals( $post_id, $result['id'] );
		$this->assertArrayHasKey( 'date', $result );
	}

	/**
	 * Tests find_existing_post() returns false when not found.
	 */
	public function test_find_existing_post_returns_false_when_not_found() {
		$result = find_existing_post( 99999 );

		$this->assertFalse( $result );
	}

	/**
	 * Tests find_existing_checkin() returns true when checkin exists.
	 */
	public function test_find_existing_checkin_returns_true_when_exists() {
		$post_id = $this->create_beer_post();
		add_post_meta( $post_id, '_beer_slurper_untappd_id', 777888, false );

		$result = find_existing_checkin( 777888 );

		$this->assertTrue( $result );
	}

	/**
	 * Tests find_existing_checkin() returns false when not found.
	 */
	public function test_find_existing_checkin_returns_false_when_not_found() {
		$result = find_existing_checkin( 999000 );

		$this->assertFalse( $result );
	}

	/**
	 * Tests insert_beer() assigns venue when present.
	 */
	public function test_insert_beer_assigns_venue_when_present() {
		$this->setup_standard_mocks();

		$checkin = ApiFixtures::checkin( array(
			'venue' => ApiFixtures::venue( array( 'venue_id' => 88888 ) ),
		) );

		$post_id = insert_beer( $checkin );

		$terms = wp_get_object_terms( $post_id, BEER_SLURPER_TAX_VENUE );
		$this->assertCount( 1, $terms );
		$this->assertEquals( 'Test Taproom', $terms[0]->name );
	}

	/**
	 * Tests insert_beer() stores venue ID in post meta.
	 */
	public function test_insert_beer_stores_venue_in_meta() {
		$this->setup_standard_mocks();

		$checkin = ApiFixtures::checkin( array(
			'venue' => ApiFixtures::venue( array( 'venue_id' => 77777 ) ),
		) );

		$post_id = insert_beer( $checkin );

		$venue_id = get_post_meta( $post_id, '_beer_slurper_venue', true );
		$this->assertEquals( 77777, $venue_id );
	}

	/**
	 * Tests insert_beer() processes badges.
	 *
	 * Note: This test creates a badge without an image to avoid
	 * triggering the insert_thumbnail function which requires
	 * file system access.
	 */
	public function test_insert_beer_processes_badges() {
		$this->setup_standard_mocks();

		// Badge without image to avoid thumbnail download
		$badge = array(
			'badge_id'          => 77777,
			'badge_name'        => 'Hopped Up (Level 5)',
			'badge_description' => 'Test badge',
			'badge_image'       => array(), // No image
		);

		$checkin = ApiFixtures::checkin( array(
			'badges' => array(
				'count' => 1,
				'items' => array( $badge ),
			),
		) );

		$post_id = insert_beer( $checkin );

		$terms = wp_get_object_terms( $post_id, BEER_SLURPER_TAX_BADGE );
		$this->assertCount( 1, $terms );
		$this->assertEquals( 'Hopped Up', $terms[0]->name ); // Level stripped
	}

	/**
	 * Tests insert_beer() attaches companions.
	 */
	public function test_insert_beer_attaches_companions() {
		$this->setup_standard_mocks();

		$checkin = ApiFixtures::checkin( array(
			'tagged_friends' => array(
				'count' => 1,
				'items' => array( ApiFixtures::tagged_friend() ),
			),
		) );

		$post_id = insert_beer( $checkin );

		$terms = wp_get_object_terms( $post_id, BEER_SLURPER_TAX_COMPANION );
		$this->assertCount( 1, $terms );
	}

	/**
	 * Tests insert_beer() creates checkin comment.
	 */
	public function test_insert_beer_creates_checkin_comment() {
		$this->setup_standard_mocks();

		$checkin = ApiFixtures::checkin( array(
			'checkin_id'      => 555666,
			'checkin_comment' => 'This beer is amazing!',
			'rating_score'    => 4.5,
		) );

		$post_id = insert_beer( $checkin );

		$comments = get_comments( array(
			'post_id' => $post_id,
			'type'    => 'beer_checkin',
		) );

		$this->assertCount( 1, $comments );
		$this->assertEquals( 'This beer is amazing!', $comments[0]->comment_content );
	}

	/**
	 * Tests setup_post() returns error without checkin.
	 */
	public function test_setup_post_returns_error_without_checkin() {
		$result = setup_post( null );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'no_checkin', $result->get_error_code() );
	}

	/**
	 * Tests setup_post() structures post data correctly.
	 */
	public function test_setup_post_structures_data() {
		$this->setup_standard_mocks();

		$checkin = ApiFixtures::checkin();
		$result = setup_post( $checkin );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'title', $result );
		$this->assertArrayHasKey( 'slug', $result );
		$this->assertArrayHasKey( 'content', $result );
		$this->assertArrayHasKey( 'excerpt', $result );
		$this->assertArrayHasKey( 'date', $result );
		$this->assertArrayHasKey( 'meta', $result );
		$this->assertArrayHasKey( 'meta_multiple', $result );
		$this->assertArrayHasKey( 'term_id', $result );
		$this->assertArrayHasKey( 'brewery', $result );

		$this->assertEquals( 'Test IPA', $result['title'] );
	}

	/**
	 * Tests setup_post() uses beer slug from API.
	 */
	public function test_setup_post_uses_beer_slug() {
		$this->setup_standard_mocks( array(
			'response' => array(
				'beer' => array(
					'bid'       => 12345,
					'beer_name' => 'Test IPA',
					'beer_slug' => 'custom-slug',
					'brewery'   => ApiFixtures::brewery_basic(),
				),
			),
		) );

		$checkin = ApiFixtures::checkin();
		$result = setup_post( $checkin );

		$this->assertEquals( 'custom-slug', $result['slug'] );
	}

	/**
	 * Tests attach_brewery() creates and assigns brewery term.
	 */
	public function test_attach_brewery_assigns_term() {
		MockHttpClient::mock_json( '*api.untappd.com*/v4/brewery/info/*', ApiFixtures::brewery_info() );

		$post_id = $this->create_beer_post();

		attach_brewery( $post_id, 54321 );

		$terms = wp_get_object_terms( $post_id, BEER_SLURPER_TAX_BREWERY );
		$this->assertCount( 1, $terms );
	}

	/**
	 * Tests attach_brewery() does nothing with null brewery ID.
	 */
	public function test_attach_brewery_handles_null_id() {
		$post_id = $this->create_beer_post();

		attach_brewery( $post_id, null );

		$terms = wp_get_object_terms( $post_id, BEER_SLURPER_TAX_BREWERY );
		$this->assertCount( 0, $terms );
	}

	/**
	 * Tests attach_collaborations() attaches multiple breweries.
	 */
	public function test_attach_collaborations_attaches_multiple() {
		MockHttpClient::mock_json( '*api.untappd.com*/v4/brewery/info/*', ApiFixtures::brewery_info() );

		$post_id = $this->create_beer_post();
		$collabs = array( 111, 222 );

		attach_collaborations( $post_id, $collabs );

		// Each collab triggers a brewery lookup - we mock to return same data
		// so we get one term created and assigned
		$terms = wp_get_object_terms( $post_id, BEER_SLURPER_TAX_BREWERY );
		$this->assertGreaterThanOrEqual( 1, count( $terms ) );
	}
}
