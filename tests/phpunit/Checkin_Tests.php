<?php
/**
 * Checkin Tests for Beer Slurper
 *
 * Tests for checkin-as-comment functionality including comment creation,
 * metadata storage, and deduplication.
 *
 * @package Kraft\Beer_Slurper\Checkin
 */

namespace Kraft\Beer_Slurper\Checkin;

use Kraft\Beer_Slurper as Base;
use Kraft\Beer_Slurper\Tests\ApiFixtures;

/**
 * Tests for the Checkin functions.
 */
class Checkin_Tests extends Base\TestCase {

	protected $testFiles = [
		'functions/checkin.php',
		'functions/venue.php',
	];

	/**
	 * Tests insert_checkin() returns false without checkin_id.
	 */
	public function test_insert_checkin_returns_false_without_checkin_id() {
		$post_id = $this->create_beer_post();

		$result = insert_checkin( array(), $post_id );

		$this->assertFalse( $result );
	}

	/**
	 * Tests insert_checkin() returns false without post_id.
	 */
	public function test_insert_checkin_returns_false_without_post_id() {
		$checkin = ApiFixtures::checkin();

		$result = insert_checkin( $checkin, 0 );

		$this->assertFalse( $result );
	}

	/**
	 * Tests insert_checkin() creates comment.
	 */
	public function test_insert_checkin_creates_comment() {
		$post_id = $this->create_beer_post();
		$checkin = ApiFixtures::checkin( array(
			'checkin_id'      => 123456,
			'checkin_comment' => 'Delicious IPA!',
			'created_at'      => '2024-01-15 14:30:00',
		) );

		$comment_id = insert_checkin( $checkin, $post_id );

		$this->assertIsInt( $comment_id );
		$this->assertGreaterThan( 0, $comment_id );

		$comment = get_comment( $comment_id );
		$this->assertEquals( 'beer_checkin', $comment->comment_type );
		$this->assertEquals( 'Delicious IPA!', $comment->comment_content );
		$this->assertEquals( $post_id, $comment->comment_post_ID );
	}

	/**
	 * Tests insert_checkin() stores checkin ID in meta.
	 */
	public function test_insert_checkin_stores_checkin_id() {
		$post_id = $this->create_beer_post();
		$checkin = ApiFixtures::checkin( array( 'checkin_id' => 789012 ) );

		$comment_id = insert_checkin( $checkin, $post_id );

		$stored_id = get_comment_meta( $comment_id, '_beer_slurper_checkin_id', true );
		$this->assertEquals( 789012, $stored_id );
	}

	/**
	 * Tests insert_checkin() stores rating in meta.
	 */
	public function test_insert_checkin_stores_rating() {
		$post_id = $this->create_beer_post();
		$checkin = ApiFixtures::checkin( array(
			'checkin_id'   => 1110001,
			'rating_score' => 4.25,
		) );

		$comment_id = insert_checkin( $checkin, $post_id );

		$rating = get_comment_meta( $comment_id, '_beer_slurper_rating', true );
		$this->assertEquals( 4.25, $rating );
	}

	/**
	 * Tests insert_checkin() stores serving type.
	 */
	public function test_insert_checkin_stores_serving_type() {
		$post_id = $this->create_beer_post();
		$checkin = ApiFixtures::checkin( array(
			'checkin_id'   => 2220002,
			'serving_type' => 'Can',
		) );

		$comment_id = insert_checkin( $checkin, $post_id );

		$serving = get_comment_meta( $comment_id, '_beer_slurper_serving_type', true );
		$this->assertEquals( 'Can', $serving );
	}

	/**
	 * Tests insert_checkin() stores venue term ID.
	 */
	public function test_insert_checkin_stores_venue_term() {
		$post_id = $this->create_beer_post();
		$venue = ApiFixtures::venue( array( 'venue_id' => 55555 ) );
		$checkin = ApiFixtures::checkin( array(
			'checkin_id' => 333,
			'venue'      => $venue,
		) );

		$comment_id = insert_checkin( $checkin, $post_id );

		$venue_term_id = get_comment_meta( $comment_id, '_beer_slurper_venue_id', true );
		$this->assertNotEmpty( $venue_term_id );
	}

	/**
	 * Tests insert_checkin() sets author from user data.
	 */
	public function test_insert_checkin_sets_author() {
		$post_id = $this->create_beer_post();
		$checkin = ApiFixtures::checkin( array(
			'checkin_id' => 444,
			'user'       => array(
				'uid'       => 12345,
				'user_name' => 'beergeek',
			),
		) );

		$comment_id = insert_checkin( $checkin, $post_id );

		$comment = get_comment( $comment_id );
		$this->assertEquals( 'beergeek', $comment->comment_author );
	}

	/**
	 * Tests insert_checkin() auto-approves comments.
	 */
	public function test_insert_checkin_auto_approves() {
		$post_id = $this->create_beer_post();
		$checkin = ApiFixtures::checkin( array( 'checkin_id' => 555 ) );

		$comment_id = insert_checkin( $checkin, $post_id );

		$comment = get_comment( $comment_id );
		$this->assertEquals( 1, $comment->comment_approved );
	}

	/**
	 * Tests insert_checkin() prevents duplicates.
	 */
	public function test_insert_checkin_prevents_duplicates() {
		$post_id = $this->create_beer_post();
		$checkin = ApiFixtures::checkin( array( 'checkin_id' => 666777 ) );

		// First insert should succeed
		$comment_id1 = insert_checkin( $checkin, $post_id );
		$this->assertIsInt( $comment_id1 );

		// Second insert with same checkin_id should fail
		$comment_id2 = insert_checkin( $checkin, $post_id );
		$this->assertFalse( $comment_id2 );
	}

	/**
	 * Tests get_checkin_exists() returns true when exists.
	 */
	public function test_get_checkin_exists_returns_true() {
		$post_id = $this->create_beer_post();
		$checkin = ApiFixtures::checkin( array( 'checkin_id' => 888999 ) );
		insert_checkin( $checkin, $post_id );

		$result = get_checkin_exists( 888999 );

		$this->assertTrue( $result );
	}

	/**
	 * Tests get_checkin_exists() returns false when not exists.
	 */
	public function test_get_checkin_exists_returns_false() {
		// Use a unique ID that no other test uses
		$result = get_checkin_exists( 77777777 );

		$this->assertFalse( $result );
	}

	/**
	 * Tests insert_checkin() handles empty comment gracefully.
	 */
	public function test_insert_checkin_handles_empty_comment() {
		$post_id = $this->create_beer_post();
		$checkin = ApiFixtures::checkin( array(
			'checkin_id'      => 777,
			'checkin_comment' => '',
		) );

		$comment_id = insert_checkin( $checkin, $post_id );

		$this->assertIsInt( $comment_id );
		$comment = get_comment( $comment_id );
		$this->assertEquals( '', $comment->comment_content );
	}

	/**
	 * Tests insert_checkin() handles missing rating gracefully.
	 */
	public function test_insert_checkin_handles_missing_rating() {
		$post_id = $this->create_beer_post();
		$checkin = ApiFixtures::checkin( array( 'checkin_id' => 888 ) );
		unset( $checkin['rating_score'] );

		$comment_id = insert_checkin( $checkin, $post_id );

		$this->assertIsInt( $comment_id );
		$rating = get_comment_meta( $comment_id, '_beer_slurper_rating', true );
		$this->assertEmpty( $rating );
	}

	/**
	 * Tests insert_checkin() converts date correctly.
	 */
	public function test_insert_checkin_converts_date() {
		$post_id = $this->create_beer_post();
		$checkin = ApiFixtures::checkin( array(
			'checkin_id' => 999,
			'created_at' => '2024-06-15 18:30:00',
		) );

		$comment_id = insert_checkin( $checkin, $post_id );

		$comment = get_comment( $comment_id );
		$this->assertEquals( '2024-06-15 18:30:00', $comment->comment_date_gmt );
	}

	/**
	 * Tests exclude_checkin_from_default_comments filter.
	 */
	public function test_checkin_excluded_from_default_comments() {
		// Create a beer post and a checkin comment
		$post_id = $this->create_beer_post();
		$checkin = ApiFixtures::checkin( array( 'checkin_id' => 88880001 ) );
		$comment_id = insert_checkin( $checkin, $post_id );
		$this->assertIsInt( $comment_id, 'Checkin comment should be created' );

		// Also add a regular comment
		$regular_comment_id = wp_insert_comment( array(
			'comment_post_ID' => $post_id,
			'comment_content' => 'Regular comment',
			'comment_type'    => 'comment',
			'comment_approved' => 1,
		) );
		$this->assertIsInt( $regular_comment_id, 'Regular comment should be created' );

		// Verify the comments exist in the database
		$checkin_comment = get_comment( $comment_id );
		$regular_comment = get_comment( $regular_comment_id );
		$this->assertNotNull( $checkin_comment, 'Checkin comment should exist' );
		$this->assertNotNull( $regular_comment, 'Regular comment should exist' );

		// Query all comment types explicitly to bypass any filters
		$all_comments = get_comments( array(
			'post_id' => $post_id,
			'type'    => '', // Empty string means all types
			'status'  => 'any',
		) );

		// Should return both comments (1 regular + 1 checkin)
		$this->assertCount( 2, $all_comments, 'Expected 2 comments (1 regular + 1 checkin). Got: ' . count( $all_comments ) );

		// Specifically request beer_checkin type
		$checkin_comments = get_comments( array(
			'post_id' => $post_id,
			'type'    => 'beer_checkin',
		) );

		$this->assertCount( 1, $checkin_comments );
	}
}
