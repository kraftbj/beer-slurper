<?php
/**
 * Companion Tests for Beer Slurper
 *
 * Tests for companion (tagged friends) taxonomy term management.
 *
 * @package Kraft\Beer_Slurper\Companion
 */

namespace Kraft\Beer_Slurper\Companion;

use Kraft\Beer_Slurper as Base;
use Kraft\Beer_Slurper\Tests\ApiFixtures;

/**
 * Tests for the Companion functions.
 */
class Companion_Tests extends Base\TestCase {

	protected $testFiles = [
		'functions/companion.php',
	];

	/**
	 * Tests get_companion_term_id() returns false for empty UID.
	 */
	public function test_get_companion_term_id_returns_false_for_empty() {
		$result = get_companion_term_id( null );
		$this->assertFalse( $result );

		$result = get_companion_term_id( '' );
		$this->assertFalse( $result );
	}

	/**
	 * Tests get_companion_term_id() creates new term.
	 */
	public function test_get_companion_term_id_creates_new_term() {
		$user_data = ApiFixtures::user( array(
			'uid'        => 11111,
			'user_name'  => 'beerbuddy',
			'first_name' => 'Beer',
			'last_name'  => 'Buddy',
		) );

		$term_id = get_companion_term_id( 11111, $user_data );

		$this->assertIsInt( $term_id );
		$this->assertGreaterThan( 0, $term_id );

		$term = get_term( $term_id );
		$this->assertEquals( 'Beer Buddy', $term->name );
		$this->assertEquals( 'beerbuddy', $term->slug );
	}

	/**
	 * Tests get_companion_term_id() returns existing term.
	 */
	public function test_get_companion_term_id_returns_existing_term() {
		$existing_id = $this->create_companion_term( array( 'name' => 'Existing Companion' ) );
		update_term_meta( $existing_id, 'untappd_uid', 99999 );

		$result = get_companion_term_id( 99999 );

		$this->assertEquals( $existing_id, $result );
	}

	/**
	 * Tests get_companion_term_id() stores untappd_uid.
	 */
	public function test_get_companion_term_id_stores_uid() {
		$user_data = ApiFixtures::user( array( 'uid' => 22222 ) );

		$term_id = get_companion_term_id( 22222, $user_data );

		$stored_uid = get_term_meta( $term_id, 'untappd_uid', true );
		$this->assertEquals( 22222, $stored_uid );
	}

	/**
	 * Tests add_companion() creates placeholder term without user data.
	 */
	public function test_add_companion_creates_placeholder_without_data() {
		$term_id = add_companion( 12345, null );
		$this->assertIsInt( $term_id );

		$term = get_term( $term_id, BEER_SLURPER_TAX_COMPANION );
		$this->assertEquals( 'Untappd User 12345', $term->name );
		$this->assertEquals( 'untappd-user-12345', $term->slug );
	}

	/**
	 * Tests add_companion() creates placeholder term without username.
	 */
	public function test_add_companion_creates_placeholder_without_username() {
		$term_id = add_companion( 67890, array( 'uid' => 67890 ) );
		$this->assertIsInt( $term_id );

		$term = get_term( $term_id, BEER_SLURPER_TAX_COMPANION );
		$this->assertEquals( 'Untappd User 67890', $term->name );
		$this->assertEquals( 'untappd-user-67890', $term->slug );
	}

	/**
	 * Tests add_companion() uses username as display name fallback.
	 */
	public function test_add_companion_uses_username_as_fallback() {
		$user_data = array(
			'uid'       => 33333,
			'user_name' => 'noname_user',
		);

		$term_id = add_companion( 33333, $user_data );

		$term = get_term( $term_id );
		$this->assertEquals( 'noname_user', $term->name );
	}

	/**
	 * Tests add_companion() handles duplicate term.
	 */
	public function test_add_companion_handles_duplicate() {
		$existing = $this->create_companion_term( array(
			'name' => 'Duplicate User',
			'slug' => 'duplicate-user',
		) );

		$user_data = array(
			'uid'        => 44444,
			'user_name'  => 'duplicate-user',
			'first_name' => 'Duplicate',
			'last_name'  => 'User',
		);

		$term_id = add_companion( 44444, $user_data );

		$this->assertEquals( $existing, $term_id );
	}

	/**
	 * Tests add_companion() stores username.
	 */
	public function test_add_companion_stores_username() {
		$user_data = ApiFixtures::user( array(
			'uid'       => 55555,
			'user_name' => 'stored_user',
		) );

		$term_id = add_companion( 55555, $user_data );

		$this->assertEquals( 'stored_user', get_term_meta( $term_id, 'untappd_username', true ) );
	}

	/**
	 * Tests add_companion() stores personal URL when provided.
	 */
	public function test_add_companion_stores_personal_url() {
		$user_data = ApiFixtures::user( array(
			'uid'       => 66666,
			'user_name' => 'personal_url_user',
			'url'       => 'https://personal-site.com',
		) );

		$term_id = add_companion( 66666, $user_data );

		$this->assertEquals( 'https://personal-site.com', get_term_meta( $term_id, 'url', true ) );
	}

	/**
	 * Tests add_companion() uses Untappd profile URL as fallback.
	 */
	public function test_add_companion_uses_untappd_profile_fallback() {
		$user_data = array(
			'uid'        => 77777,
			'user_name'  => 'profile_fallback',
			'first_name' => 'Test',
		);

		$term_id = add_companion( 77777, $user_data );

		$this->assertEquals( 'https://untappd.com/user/profile_fallback', get_term_meta( $term_id, 'url', true ) );
	}

	/**
	 * Tests add_companion() stores avatar URL.
	 */
	public function test_add_companion_stores_avatar() {
		$user_data = ApiFixtures::user( array(
			'uid'         => 88888,
			'user_avatar' => 'https://example.com/avatar.png',
		) );

		$term_id = add_companion( 88888, $user_data );

		$this->assertEquals( 'https://example.com/avatar.png', get_term_meta( $term_id, 'avatar_url', true ) );
	}

	/**
	 * Tests maybe_update_companion_meta() updates changed values.
	 */
	public function test_maybe_update_companion_meta_updates_changed() {
		$term_id = $this->create_companion_term();
		update_term_meta( $term_id, 'avatar_url', 'https://old-avatar.com' );

		$user_data = array(
			'user_name'   => 'updated_user',
			'user_avatar' => 'https://new-avatar.com',
		);

		maybe_update_companion_meta( $term_id, $user_data );

		$this->assertEquals( 'https://new-avatar.com', get_term_meta( $term_id, 'avatar_url', true ) );
	}

	/**
	 * Tests maybe_update_companion_meta() does not update unchanged values.
	 */
	public function test_maybe_update_companion_meta_skips_unchanged() {
		$term_id = $this->create_companion_term();
		update_term_meta( $term_id, 'avatar_url', 'https://same-avatar.com' );

		$user_data = array(
			'user_name'   => 'same_user',
			'user_avatar' => 'https://same-avatar.com',
		);

		// Should not trigger an update (no assertion, just verifying no error)
		maybe_update_companion_meta( $term_id, $user_data );

		$this->assertEquals( 'https://same-avatar.com', get_term_meta( $term_id, 'avatar_url', true ) );
	}

	/**
	 * Tests attach_companions() attaches terms to post.
	 */
	public function test_attach_companions_attaches_terms() {
		$post_id = $this->create_beer_post();
		$checkin = array(
			'tagged_friends' => array(
				'items' => array(
					array( 'user' => ApiFixtures::user( array( 'uid' => 11111 ) ) ),
					array( 'user' => ApiFixtures::user( array( 'uid' => 22222, 'user_name' => 'friend2' ) ) ),
				),
			),
		);

		attach_companions( $checkin, $post_id );

		$terms = wp_get_object_terms( $post_id, BEER_SLURPER_TAX_COMPANION );
		$this->assertCount( 2, $terms );
	}

	/**
	 * Tests attach_companions() handles empty tagged_friends.
	 */
	public function test_attach_companions_handles_empty() {
		$post_id = $this->create_beer_post();

		// No tagged_friends key
		attach_companions( array(), $post_id );
		$terms = wp_get_object_terms( $post_id, BEER_SLURPER_TAX_COMPANION );
		$this->assertCount( 0, $terms );

		// Empty items
		attach_companions( array( 'tagged_friends' => array( 'items' => array() ) ), $post_id );
		$terms = wp_get_object_terms( $post_id, BEER_SLURPER_TAX_COMPANION );
		$this->assertCount( 0, $terms );
	}

	/**
	 * Tests attach_companions() skips users without UID.
	 */
	public function test_attach_companions_skips_invalid_users() {
		$post_id = $this->create_beer_post();
		$checkin = array(
			'tagged_friends' => array(
				'items' => array(
					array( 'user' => array( 'user_name' => 'no_uid' ) ), // No uid
					array( 'user' => ApiFixtures::user( array( 'uid' => 33333 ) ) ),
				),
			),
		);

		attach_companions( $checkin, $post_id );

		$terms = wp_get_object_terms( $post_id, BEER_SLURPER_TAX_COMPANION );
		$this->assertCount( 1, $terms );
	}

	/**
	 * Tests get_companion_term_id() refreshes meta from checkin data.
	 */
	public function test_get_companion_term_id_refreshes_meta() {
		$term_id = $this->create_companion_term();
		update_term_meta( $term_id, 'untappd_uid', 44444 );
		update_term_meta( $term_id, 'avatar_url', 'https://old.com/avatar.png' );

		$user_data = ApiFixtures::user( array(
			'uid'         => 44444,
			'user_name'   => 'refreshed',
			'user_avatar' => 'https://new.com/avatar.png',
		) );

		get_companion_term_id( 44444, $user_data );

		$this->assertEquals( 'https://new.com/avatar.png', get_term_meta( $term_id, 'avatar_url', true ) );
	}
}
