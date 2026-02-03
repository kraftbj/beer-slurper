<?php
/**
 * Toast Tests for Beer Slurper
 *
 * Tests for toast-as-comment functionality including comment creation,
 * metadata storage, companion term linking, and deduplication.
 *
 * @package Kraft\Beer_Slurper\Toast
 */

namespace Kraft\Beer_Slurper\Toast;

use Kraft\Beer_Slurper as Base;
use Kraft\Beer_Slurper\Tests\ApiFixtures;

/**
 * Tests for the Toast functions.
 */
class Toast_Tests extends Base\TestCase {

	protected $testFiles = [
		'functions/toast.php',
		'functions/companion.php',
	];

	/**
	 * Tests insert_toast() returns false without like_id.
	 */
	public function test_insert_toast_returns_false_without_like_id() {
		$post_id = $this->create_beer_post();

		$result = insert_toast( array(), $post_id );

		$this->assertFalse( $result );
	}

	/**
	 * Tests insert_toast() returns false without post_id.
	 */
	public function test_insert_toast_returns_false_without_post_id() {
		$toast = ApiFixtures::toast();

		$result = insert_toast( $toast, 0 );

		$this->assertFalse( $result );
	}

	/**
	 * Tests insert_toast() creates comment.
	 */
	public function test_insert_toast_creates_comment() {
		$post_id = $this->create_beer_post();
		$toast = ApiFixtures::toast( array(
			'like_id'    => 123456,
			'created_at' => '2024-01-15 15:30:00',
		) );

		$comment_id = insert_toast( $toast, $post_id );

		$this->assertIsInt( $comment_id );
		$this->assertGreaterThan( 0, $comment_id );

		$comment = get_comment( $comment_id );
		$this->assertEquals( 'beer_toast', $comment->comment_type );
		$this->assertEquals( '', $comment->comment_content );
		$this->assertEquals( $post_id, $comment->comment_post_ID );
	}

	/**
	 * Tests insert_toast() stores toast ID in meta.
	 */
	public function test_insert_toast_stores_toast_id() {
		$post_id = $this->create_beer_post();
		$toast = ApiFixtures::toast( array( 'like_id' => 789012 ) );

		$comment_id = insert_toast( $toast, $post_id );

		$stored_id = get_comment_meta( $comment_id, '_beer_slurper_toast_id', true );
		$this->assertEquals( 789012, $stored_id );
	}

	/**
	 * Tests insert_toast() stores toaster term ID.
	 */
	public function test_insert_toast_stores_toaster_term_id() {
		$post_id = $this->create_beer_post();
		$toast = ApiFixtures::toast( array(
			'like_id' => 111222,
			'user'    => array(
				'uid'        => 44444,
				'user_name'  => 'toastfan',
				'first_name' => 'Toast',
				'last_name'  => 'Fan',
			),
		) );

		$comment_id = insert_toast( $toast, $post_id );

		$toaster_term_id = get_comment_meta( $comment_id, '_beer_slurper_toaster_term_id', true );
		$this->assertNotEmpty( $toaster_term_id );

		// Verify the term exists and has correct meta
		$term = get_term( (int) $toaster_term_id, BEER_SLURPER_TAX_COMPANION );
		$this->assertNotInstanceOf( \WP_Error::class, $term );
		$this->assertEquals( 44444, get_term_meta( $term->term_id, 'untappd_uid', true ) );
	}

	/**
	 * Tests insert_toast() sets author from user data.
	 */
	public function test_insert_toast_sets_author() {
		$post_id = $this->create_beer_post();
		$toast = ApiFixtures::toast( array(
			'like_id' => 333444,
			'user'    => array(
				'uid'       => 55555,
				'user_name' => 'cheersmate',
			),
		) );

		$comment_id = insert_toast( $toast, $post_id );

		$comment = get_comment( $comment_id );
		$this->assertEquals( 'cheersmate', $comment->comment_author );
	}

	/**
	 * Tests insert_toast() auto-approves comments.
	 */
	public function test_insert_toast_auto_approves() {
		$post_id = $this->create_beer_post();
		$toast = ApiFixtures::toast( array( 'like_id' => 555666 ) );

		$comment_id = insert_toast( $toast, $post_id );

		$comment = get_comment( $comment_id );
		$this->assertEquals( 1, $comment->comment_approved );
	}

	/**
	 * Tests insert_toast() prevents duplicates.
	 */
	public function test_insert_toast_prevents_duplicates() {
		$post_id = $this->create_beer_post();
		$toast = ApiFixtures::toast( array( 'like_id' => 777888 ) );

		// First insert should succeed
		$comment_id1 = insert_toast( $toast, $post_id );
		$this->assertIsInt( $comment_id1 );

		// Second insert with same like_id should fail
		$comment_id2 = insert_toast( $toast, $post_id );
		$this->assertFalse( $comment_id2 );
	}

	/**
	 * Tests get_toast_exists() returns true when exists.
	 */
	public function test_get_toast_exists_returns_true() {
		$post_id = $this->create_beer_post();
		$toast = ApiFixtures::toast( array( 'like_id' => 999111 ) );
		insert_toast( $toast, $post_id );

		$result = get_toast_exists( 999111 );

		$this->assertTrue( $result );
	}

	/**
	 * Tests get_toast_exists() returns false when not exists.
	 */
	public function test_get_toast_exists_returns_false() {
		$result = get_toast_exists( 88888888 );

		$this->assertFalse( $result );
	}

	/**
	 * Tests insert_toast() converts date correctly.
	 */
	public function test_insert_toast_converts_date() {
		$post_id = $this->create_beer_post();
		$toast = ApiFixtures::toast( array(
			'like_id'    => 222333,
			'created_at' => '2024-06-15 18:30:00',
		) );

		$comment_id = insert_toast( $toast, $post_id );

		$comment = get_comment( $comment_id );
		$this->assertEquals( '2024-06-15 18:30:00', $comment->comment_date_gmt );
	}

	/**
	 * Tests insert_toast() stores toast date in meta.
	 */
	public function test_insert_toast_stores_date_meta() {
		$post_id = $this->create_beer_post();
		$toast = ApiFixtures::toast( array(
			'like_id'    => 444555,
			'created_at' => '2024-07-20 12:00:00',
		) );

		$comment_id = insert_toast( $toast, $post_id );

		$toast_date = get_comment_meta( $comment_id, '_beer_slurper_toast_date', true );
		$this->assertEquals( '2024-07-20 12:00:00', $toast_date );
	}

	/**
	 * Tests attach_toasts() attaches multiple toasts.
	 */
	public function test_attach_toasts_attaches_multiple() {
		$post_id = $this->create_beer_post();
		$checkin = array(
			'toasts' => ApiFixtures::toasts( 3 ),
		);

		$attached = attach_toasts( $checkin, $post_id );

		$this->assertEquals( 3, $attached );

		// Verify toasts were created
		$toasts = get_toasts( $post_id );
		$this->assertCount( 3, $toasts );
	}

	/**
	 * Tests attach_toasts() returns 0 with empty toasts.
	 */
	public function test_attach_toasts_returns_zero_with_empty() {
		$post_id = $this->create_beer_post();
		$checkin = array(
			'toasts' => array( 'count' => 0, 'items' => array() ),
		);

		$attached = attach_toasts( $checkin, $post_id );

		$this->assertEquals( 0, $attached );
	}

	/**
	 * Tests attach_toasts() returns 0 with no toasts key.
	 */
	public function test_attach_toasts_returns_zero_without_key() {
		$post_id = $this->create_beer_post();
		$checkin = array();

		$attached = attach_toasts( $checkin, $post_id );

		$this->assertEquals( 0, $attached );
	}

	/**
	 * Tests attach_toasts() skips toasts without like_id.
	 */
	public function test_attach_toasts_skips_invalid() {
		$post_id = $this->create_beer_post();
		$checkin = array(
			'toasts' => array(
				'count' => 2,
				'items' => array(
					ApiFixtures::toast( array( 'like_id' => 666777 ) ),
					array( 'user' => array( 'uid' => 12345 ) ), // Missing like_id
				),
			),
		);

		$attached = attach_toasts( $checkin, $post_id );

		$this->assertEquals( 1, $attached );
	}

	/**
	 * Tests attach_toasts() deduplicates on re-import.
	 */
	public function test_attach_toasts_deduplicates() {
		$post_id = $this->create_beer_post();

		// Use unique IDs for this test
		$base_id = 500000 + wp_rand( 1, 10000 );
		$checkin = array(
			'toasts' => array(
				'count' => 2,
				'items' => array(
					ApiFixtures::toast( array( 'like_id' => $base_id ) ),
					ApiFixtures::toast( array( 'like_id' => $base_id + 1 ) ),
				),
			),
		);

		// First import
		$attached1 = attach_toasts( $checkin, $post_id );
		$this->assertEquals( 2, $attached1 );

		// Second import of same toasts
		$attached2 = attach_toasts( $checkin, $post_id );
		$this->assertEquals( 0, $attached2 );

		// Still only 2 toasts total
		$toasts = get_toasts( $post_id );
		$this->assertCount( 2, $toasts );
	}

	/**
	 * Tests get_toasts() returns toasts for post.
	 */
	public function test_get_toasts_returns_post_toasts() {
		$post_id1 = $this->create_beer_post();
		$post_id2 = $this->create_beer_post();

		// Use unique IDs for this test
		$base_id = 600000 + wp_rand( 1, 10000 );

		// Add 2 toasts to post 1
		attach_toasts( array(
			'toasts' => array(
				'count' => 2,
				'items' => array(
					ApiFixtures::toast( array( 'like_id' => $base_id ) ),
					ApiFixtures::toast( array( 'like_id' => $base_id + 1 ) ),
				),
			),
		), $post_id1 );

		// Add 1 toast to post 2
		attach_toasts( array(
			'toasts' => array(
				'count' => 1,
				'items' => array( ApiFixtures::toast( array( 'like_id' => $base_id + 100 ) ) ),
			),
		), $post_id2 );

		$toasts1 = get_toasts( $post_id1 );
		$toasts2 = get_toasts( $post_id2 );

		$this->assertCount( 2, $toasts1 );
		$this->assertCount( 1, $toasts2 );
	}

	/**
	 * Tests get_toast_count() returns correct count.
	 */
	public function test_get_toast_count_returns_count() {
		$post_id = $this->create_beer_post();

		// Use unique IDs for this test
		$base_id = 700000 + wp_rand( 1, 10000 );
		$items = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$items[] = ApiFixtures::toast( array( 'like_id' => $base_id + $i ) );
		}

		attach_toasts( array( 'toasts' => array( 'count' => 5, 'items' => $items ) ), $post_id );

		$count = get_toast_count( $post_id );

		$this->assertEquals( 5, $count );
	}

	/**
	 * Tests get_toast_count() returns 0 for post without toasts.
	 */
	public function test_get_toast_count_returns_zero() {
		$post_id = $this->create_beer_post();

		$count = get_toast_count( $post_id );

		$this->assertEquals( 0, $count );
	}

	/**
	 * Tests get_toaster_term() returns term for toast.
	 */
	public function test_get_toaster_term_returns_term() {
		$post_id = $this->create_beer_post();
		$toast = ApiFixtures::toast( array(
			'like_id' => 888999,
			'user'    => array(
				'uid'        => 77777,
				'user_name'  => 'toastking',
				'first_name' => 'Toast',
				'last_name'  => 'King',
			),
		) );

		$comment_id = insert_toast( $toast, $post_id );
		$term = get_toaster_term( $comment_id );

		$this->assertInstanceOf( \WP_Term::class, $term );
		$this->assertEquals( BEER_SLURPER_TAX_COMPANION, $term->taxonomy );
	}

	/**
	 * Tests get_toaster_term() returns false for toast without toaster.
	 */
	public function test_get_toaster_term_returns_false_without_toaster() {
		$post_id = $this->create_beer_post();

		// Create a toast comment manually without toaster meta
		$comment_id = wp_insert_comment( array(
			'comment_type'     => 'beer_toast',
			'comment_post_ID'  => $post_id,
			'comment_approved' => 1,
		) );
		update_comment_meta( $comment_id, '_beer_slurper_toast_id', 111222 );

		$term = get_toaster_term( $comment_id );

		$this->assertFalse( $term );
	}

	/**
	 * Tests that toast and checkin comments can coexist.
	 */
	public function test_toast_and_checkin_comments_coexist() {
		$post_id = $this->create_beer_post();

		// Add a checkin comment
		$checkin_comment_id = wp_insert_comment( array(
			'comment_type'     => 'beer_checkin',
			'comment_post_ID'  => $post_id,
			'comment_content'  => 'Great beer!',
			'comment_approved' => 1,
		) );

		// Add a toast
		$toast = ApiFixtures::toast( array( 'like_id' => 123321 ) );
		$toast_comment_id = insert_toast( $toast, $post_id );

		// Add a regular comment
		$regular_comment_id = wp_insert_comment( array(
			'comment_post_ID'  => $post_id,
			'comment_content'  => 'Regular comment',
			'comment_type'     => 'comment',
			'comment_approved' => 1,
		) );

		// Query all types
		$all_comments = get_comments( array(
			'post_id' => $post_id,
			'type'    => '',
			'status'  => 'any',
		) );

		$this->assertCount( 3, $all_comments );

		// Query toasts only
		$toasts = get_comments( array(
			'post_id' => $post_id,
			'type'    => 'beer_toast',
		) );

		$this->assertCount( 1, $toasts );
	}

	/**
	 * Tests that same user toasting multiple checkins creates one companion term.
	 */
	public function test_same_toaster_reuses_companion_term() {
		$post_id1 = $this->create_beer_post();
		$post_id2 = $this->create_beer_post();

		// Same user toasts two different beers
		$user_data = array(
			'uid'        => 88888,
			'user_name'  => 'superfan',
			'first_name' => 'Super',
			'last_name'  => 'Fan',
		);

		$toast1 = ApiFixtures::toast( array( 'like_id' => 111, 'user' => $user_data ) );
		$toast2 = ApiFixtures::toast( array( 'like_id' => 222, 'user' => $user_data ) );

		$comment_id1 = insert_toast( $toast1, $post_id1 );
		$comment_id2 = insert_toast( $toast2, $post_id2 );

		$term_id1 = get_comment_meta( $comment_id1, '_beer_slurper_toaster_term_id', true );
		$term_id2 = get_comment_meta( $comment_id2, '_beer_slurper_toaster_term_id', true );

		// Same companion term should be used
		$this->assertEquals( $term_id1, $term_id2 );

		// Only one companion term should exist for this UID
		$terms = get_terms( array(
			'taxonomy'   => BEER_SLURPER_TAX_COMPANION,
			'hide_empty' => false,
			'meta_key'   => 'untappd_uid',
			'meta_value' => 88888,
		) );

		$this->assertCount( 1, $terms );
	}
}
