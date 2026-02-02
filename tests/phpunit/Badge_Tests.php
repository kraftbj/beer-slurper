<?php
/**
 * Badge Tests for Beer Slurper
 *
 * Tests for badge taxonomy term creation, level handling, and metadata.
 *
 * @package Kraft\Beer_Slurper\Badge
 */

namespace Kraft\Beer_Slurper\Badge;

use Kraft\Beer_Slurper as Base;
use Kraft\Beer_Slurper\Tests\ApiFixtures;

/**
 * Tests for the Badge functions.
 */
class Badge_Tests extends Base\TestCase {

	protected $testFiles = [
		'functions/badge.php',
	];

	/**
	 * Tests parse_badge_name() extracts level from name.
	 */
	public function test_parse_badge_name_extracts_level() {
		$result = parse_badge_name( 'Hopped Up (Level 5)' );

		$this->assertEquals( 'Hopped Up', $result['base_name'] );
		$this->assertEquals( 5, $result['level'] );
	}

	/**
	 * Tests parse_badge_name() handles no level.
	 */
	public function test_parse_badge_name_handles_no_level() {
		$result = parse_badge_name( 'First Check-in' );

		$this->assertEquals( 'First Check-in', $result['base_name'] );
		$this->assertEquals( 0, $result['level'] );
	}

	/**
	 * Tests parse_badge_name() handles various level formats.
	 */
	public function test_parse_badge_name_handles_level_formats() {
		$tests = array(
			'Badge (Level 10)'  => array( 'base_name' => 'Badge', 'level' => 10 ),
			'Badge (level 1)'   => array( 'base_name' => 'Badge', 'level' => 1 ),
			'Badge  (Level  3)' => array( 'base_name' => 'Badge', 'level' => 3 ),
		);

		foreach ( $tests as $input => $expected ) {
			$result = parse_badge_name( $input );
			$this->assertEquals( $expected['base_name'], $result['base_name'], "Failed for: {$input}" );
			$this->assertEquals( $expected['level'], $result['level'], "Failed for: {$input}" );
		}
	}

	/**
	 * Tests get_badge_term_id() returns false for empty ID.
	 */
	public function test_get_badge_term_id_returns_false_for_empty() {
		$result = get_badge_term_id( null );
		$this->assertFalse( $result );
	}

	/**
	 * Tests get_badge_term_id() returns false without badge name.
	 */
	public function test_get_badge_term_id_returns_false_without_name() {
		$result = get_badge_term_id( 12345, array( 'badge_id' => 12345 ) );
		$this->assertFalse( $result );
	}

	/**
	 * Tests get_badge_term_id() creates new term.
	 */
	public function test_get_badge_term_id_creates_new_term() {
		$badge = ApiFixtures::badge( array(
			'badge_id'   => 11111,
			'badge_name' => 'Test Badge (Level 3)',
		) );

		$term_id = get_badge_term_id( 11111, $badge );

		$this->assertIsInt( $term_id );
		$this->assertGreaterThan( 0, $term_id );

		$term = get_term( $term_id );
		$this->assertEquals( 'Test Badge', $term->name );
	}

	/**
	 * Tests get_badge_term_id() returns existing term.
	 */
	public function test_get_badge_term_id_returns_existing_term() {
		$existing_id = $this->create_badge_term( array(
			'name' => 'Existing Badge',
			'slug' => 'existing-badge',
		) );

		$badge = ApiFixtures::badge( array(
			'badge_id'   => 22222,
			'badge_name' => 'Existing Badge (Level 5)',
		) );

		$result = get_badge_term_id( 22222, $badge );

		$this->assertEquals( $existing_id, $result );
	}

	/**
	 * Tests get_badge_term_id() stores untappd_id.
	 */
	public function test_get_badge_term_id_stores_untappd_id() {
		$badge = ApiFixtures::badge( array( 'badge_id' => 33333 ) );

		$term_id = get_badge_term_id( 33333, $badge );

		$stored_id = get_term_meta( $term_id, 'untappd_id', true );
		$this->assertEquals( 33333, $stored_id );
	}

	/**
	 * Tests get_badge_term_id() stores badge level.
	 */
	public function test_get_badge_term_id_stores_level() {
		$badge = ApiFixtures::badge( array(
			'badge_id'   => 44444,
			'badge_name' => 'Leveled Badge (Level 7)',
		) );

		$term_id = get_badge_term_id( 44444, $badge );

		$level = get_term_meta( $term_id, 'badge_level', true );
		$this->assertEquals( 7, $level );
	}

	/**
	 * Tests add_badge() returns error without badge data.
	 */
	public function test_add_badge_returns_error_without_data() {
		$result = add_badge( null );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'invalid_badge', $result->get_error_code() );
	}

	/**
	 * Tests add_badge() handles duplicate term.
	 */
	public function test_add_badge_handles_duplicate() {
		$existing = $this->create_badge_term( array(
			'name' => 'Duplicate Badge',
			'slug' => 'duplicate-badge',
		) );

		$badge = ApiFixtures::badge( array(
			'badge_id'   => 55555,
			'badge_name' => 'Duplicate Badge',
		) );

		$term_id = add_badge( $badge );

		$this->assertEquals( $existing, $term_id );
	}

	/**
	 * Tests save_badge_meta() stores image URLs.
	 */
	public function test_save_badge_meta_stores_images() {
		$term_id = $this->create_badge_term();
		$badge = ApiFixtures::badge( array(
			'badge_id'    => 66666,
			'badge_image' => array(
				'sm' => 'https://example.com/sm.png',
				'md' => 'https://example.com/md.png',
				'lg' => 'https://example.com/lg.png',
			),
		) );

		save_badge_meta( $term_id, $badge, 1 );

		$this->assertTermHasMeta( $term_id, array(
			'badge_image_sm' => 'https://example.com/sm.png',
			'badge_image_md' => 'https://example.com/md.png',
			'badge_image_lg' => 'https://example.com/lg.png',
		) );
	}

	/**
	 * Tests save_badge_meta() stores description.
	 */
	public function test_save_badge_meta_stores_description() {
		$term_id = $this->create_badge_term();
		$badge = ApiFixtures::badge( array(
			'badge_id'          => 77777,
			'badge_description' => 'Earned for drinking 10 IPAs.',
		) );

		save_badge_meta( $term_id, $badge, 1 );

		$this->assertEquals( 'Earned for drinking 10 IPAs.', get_term_meta( $term_id, 'badge_description', true ) );
	}

	/**
	 * Tests maybe_update_level() updates when higher level.
	 */
	public function test_maybe_update_level_updates_higher_level() {
		$term_id = $this->create_badge_term();
		update_term_meta( $term_id, 'badge_level', 3 );

		$badge = ApiFixtures::badge( array(
			'badge_id'   => 88888,
			'badge_name' => 'Test Badge (Level 5)',
		) );

		maybe_update_level( $term_id, $badge, 5 );

		$this->assertEquals( 5, get_term_meta( $term_id, 'badge_level', true ) );
	}

	/**
	 * Tests maybe_update_level() does not downgrade level.
	 */
	public function test_maybe_update_level_does_not_downgrade() {
		$term_id = $this->create_badge_term();
		update_term_meta( $term_id, 'badge_level', 10 );

		$badge = ApiFixtures::badge( array(
			'badge_id'   => 99999,
			'badge_name' => 'Test Badge (Level 5)',
		) );

		maybe_update_level( $term_id, $badge, 5 );

		$this->assertEquals( 10, get_term_meta( $term_id, 'badge_level', true ) );
	}

	/**
	 * Tests maybe_update_level() backfills missing description.
	 */
	public function test_maybe_update_level_backfills_description() {
		$term_id = $this->create_badge_term();
		update_term_meta( $term_id, 'badge_level', 5 );
		// No description set

		$badge = ApiFixtures::badge( array(
			'badge_id'          => 10101,
			'badge_name'        => 'Test Badge (Level 3)', // Lower level
			'badge_description' => 'Backfilled description.',
		) );

		maybe_update_level( $term_id, $badge, 3 );

		// Level should stay at 5
		$this->assertEquals( 5, get_term_meta( $term_id, 'badge_level', true ) );
		// Description should be backfilled
		$this->assertEquals( 'Backfilled description.', get_term_meta( $term_id, 'badge_description', true ) );
	}

	/**
	 * Tests leveled badges map to same term.
	 */
	public function test_leveled_badges_map_to_same_term() {
		$badge_l1 = ApiFixtures::badge( array(
			'badge_id'   => 11111,
			'badge_name' => 'Brewery Pioneer (Level 1)',
		) );

		$badge_l5 = ApiFixtures::badge( array(
			'badge_id'   => 11112,
			'badge_name' => 'Brewery Pioneer (Level 5)',
		) );

		$term_id1 = get_badge_term_id( 11111, $badge_l1 );
		$term_id2 = get_badge_term_id( 11112, $badge_l5 );

		$this->assertEquals( $term_id1, $term_id2 );

		// Should have the higher level stored
		$this->assertEquals( 5, get_term_meta( $term_id1, 'badge_level', true ) );
	}
}
