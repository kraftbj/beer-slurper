<?php
/**
 * CPT Integration Tests for Beer Slurper
 *
 * Tests Custom Post Type and Taxonomy registration, REST API exposure,
 * and WordPress integration.
 *
 * @package Kraft\Beer_Slurper\Tests\Integration
 */

namespace Kraft\Beer_Slurper\Tests\Integration;

use Kraft\Beer_Slurper as Base;

/**
 * Tests for CPT and Taxonomy registration.
 */
class CPT_Tests extends Base\TestCase {

	protected $testFiles = [
		'functions/cpt.php',
	];

	/**
	 * Sets up test environment with registered types.
	 */
	protected function set_up(): void {
		parent::set_up();

		// Manually trigger registration (normally runs on init hook)
		\Kraft\Beer_Slurper\CPT\init_cpt();
		\Kraft\Beer_Slurper\CPT\init_tax_brewery();
		\Kraft\Beer_Slurper\CPT\init_tax_style();
		\Kraft\Beer_Slurper\CPT\init_tax_venue();
		\Kraft\Beer_Slurper\CPT\init_tax_badge();
		\Kraft\Beer_Slurper\CPT\init_tax_companion();
	}

	/**
	 * Tests beer post type is registered.
	 */
	public function test_beer_post_type_registered() {
		$this->assertTrue( post_type_exists( BEER_SLURPER_CPT ) );
	}

	/**
	 * Tests beer post type has correct properties.
	 */
	public function test_beer_post_type_properties() {
		$post_type = get_post_type_object( BEER_SLURPER_CPT );

		$this->assertTrue( $post_type->public );
		$this->assertTrue( $post_type->show_ui );
		$this->assertTrue( $post_type->show_in_rest );
		$this->assertEquals( 'beers', $post_type->has_archive );
	}

	/**
	 * Tests beer post type supports expected features.
	 */
	public function test_beer_post_type_supports() {
		$this->assertTrue( post_type_supports( BEER_SLURPER_CPT, 'title' ) );
		$this->assertTrue( post_type_supports( BEER_SLURPER_CPT, 'editor' ) );
		$this->assertTrue( post_type_supports( BEER_SLURPER_CPT, 'thumbnail' ) );
		$this->assertTrue( post_type_supports( BEER_SLURPER_CPT, 'comments' ) );
		$this->assertTrue( post_type_supports( BEER_SLURPER_CPT, 'revisions' ) );
		$this->assertTrue( post_type_supports( BEER_SLURPER_CPT, 'custom-fields' ) );
	}

	/**
	 * Tests brewery taxonomy is registered.
	 */
	public function test_brewery_taxonomy_registered() {
		$this->assertTrue( taxonomy_exists( BEER_SLURPER_TAX_BREWERY ) );
	}

	/**
	 * Tests brewery taxonomy properties.
	 */
	public function test_brewery_taxonomy_properties() {
		$taxonomy = get_taxonomy( BEER_SLURPER_TAX_BREWERY );

		$this->assertTrue( $taxonomy->hierarchical );
		$this->assertTrue( $taxonomy->public );
		$this->assertTrue( $taxonomy->show_in_rest );
		$this->assertTrue( $taxonomy->show_admin_column );
	}

	/**
	 * Tests brewery taxonomy is associated with beer post type.
	 */
	public function test_brewery_taxonomy_object_type() {
		$taxonomies = get_object_taxonomies( BEER_SLURPER_CPT );

		$this->assertContains( BEER_SLURPER_TAX_BREWERY, $taxonomies );
	}

	/**
	 * Tests style taxonomy is registered.
	 */
	public function test_style_taxonomy_registered() {
		$this->assertTrue( taxonomy_exists( BEER_SLURPER_TAX_STYLE ) );
	}

	/**
	 * Tests style taxonomy properties.
	 */
	public function test_style_taxonomy_properties() {
		$taxonomy = get_taxonomy( BEER_SLURPER_TAX_STYLE );

		$this->assertFalse( $taxonomy->hierarchical );
		$this->assertTrue( $taxonomy->public );
		$this->assertTrue( $taxonomy->show_in_rest );
	}

	/**
	 * Tests style taxonomy is associated with beer post type.
	 */
	public function test_style_taxonomy_object_type() {
		$taxonomies = get_object_taxonomies( BEER_SLURPER_CPT );

		$this->assertContains( BEER_SLURPER_TAX_STYLE, $taxonomies );
	}

	/**
	 * Tests venue taxonomy is registered.
	 */
	public function test_venue_taxonomy_registered() {
		$this->assertTrue( taxonomy_exists( BEER_SLURPER_TAX_VENUE ) );
	}

	/**
	 * Tests venue taxonomy properties.
	 */
	public function test_venue_taxonomy_properties() {
		$taxonomy = get_taxonomy( BEER_SLURPER_TAX_VENUE );

		$this->assertFalse( $taxonomy->hierarchical );
		$this->assertTrue( $taxonomy->public );
		$this->assertTrue( $taxonomy->show_in_rest );
		$this->assertTrue( $taxonomy->show_admin_column );
	}

	/**
	 * Tests badge taxonomy is registered.
	 */
	public function test_badge_taxonomy_registered() {
		$this->assertTrue( taxonomy_exists( BEER_SLURPER_TAX_BADGE ) );
	}

	/**
	 * Tests badge taxonomy properties.
	 */
	public function test_badge_taxonomy_properties() {
		$taxonomy = get_taxonomy( BEER_SLURPER_TAX_BADGE );

		$this->assertFalse( $taxonomy->hierarchical );
		$this->assertTrue( $taxonomy->public );
		$this->assertTrue( $taxonomy->show_in_rest );
		$this->assertFalse( $taxonomy->show_admin_column ); // Badge doesn't show in admin column
	}

	/**
	 * Tests companion taxonomy is registered.
	 */
	public function test_companion_taxonomy_registered() {
		$this->assertTrue( taxonomy_exists( BEER_SLURPER_TAX_COMPANION ) );
	}

	/**
	 * Tests companion taxonomy properties.
	 */
	public function test_companion_taxonomy_properties() {
		$taxonomy = get_taxonomy( BEER_SLURPER_TAX_COMPANION );

		$this->assertFalse( $taxonomy->hierarchical );
		$this->assertTrue( $taxonomy->public );
		$this->assertTrue( $taxonomy->show_in_rest );
		$this->assertFalse( $taxonomy->show_admin_column );
	}

	/**
	 * Tests all taxonomies are associated with beer post type.
	 */
	public function test_all_taxonomies_registered_for_beer() {
		$taxonomies = get_object_taxonomies( BEER_SLURPER_CPT );

		$expected = array(
			BEER_SLURPER_TAX_BREWERY,
			BEER_SLURPER_TAX_STYLE,
			BEER_SLURPER_TAX_VENUE,
			BEER_SLURPER_TAX_BADGE,
			BEER_SLURPER_TAX_COMPANION,
		);

		foreach ( $expected as $tax ) {
			$this->assertContains( $tax, $taxonomies, "Taxonomy {$tax} should be registered for beer CPT" );
		}
	}

	/**
	 * Tests beer post type has archive enabled.
	 */
	public function test_beer_post_type_has_archive() {
		$post_type = get_post_type_object( BEER_SLURPER_CPT );

		$this->assertNotFalse( $post_type->has_archive );
		$this->assertEquals( 'beers', $post_type->has_archive );
	}

	/**
	 * Tests beer post type rewrite settings.
	 */
	public function test_beer_post_type_rewrite() {
		$post_type = get_post_type_object( BEER_SLURPER_CPT );

		$this->assertIsArray( $post_type->rewrite );
		$this->assertEquals( 'beers', $post_type->rewrite['slug'] );
		$this->assertFalse( $post_type->rewrite['with_front'] );
	}

	/**
	 * Tests beer post can be created and retrieved.
	 */
	public function test_can_create_and_retrieve_beer() {
		$post_id = wp_insert_post( array(
			'post_title'   => 'Test IPA',
			'post_type'    => BEER_SLURPER_CPT,
			'post_status'  => 'publish',
			'post_content' => 'A hoppy test beer.',
		) );

		$this->assertGreaterThan( 0, $post_id );

		$post = get_post( $post_id );
		$this->assertEquals( 'Test IPA', $post->post_title );
		$this->assertEquals( BEER_SLURPER_CPT, $post->post_type );
	}

	/**
	 * Tests can assign brewery term to beer post.
	 */
	public function test_can_assign_brewery_to_beer() {
		$post_id = $this->create_beer_post();
		$term = wp_insert_term( 'Test Brewery', BEER_SLURPER_TAX_BREWERY );

		wp_set_object_terms( $post_id, $term['term_id'], BEER_SLURPER_TAX_BREWERY );

		$terms = wp_get_object_terms( $post_id, BEER_SLURPER_TAX_BREWERY );
		$this->assertCount( 1, $terms );
		$this->assertEquals( 'Test Brewery', $terms[0]->name );
	}

	/**
	 * Tests can assign style term to beer post.
	 */
	public function test_can_assign_style_to_beer() {
		$post_id = $this->create_beer_post();
		$term = wp_insert_term( 'IPA - American', BEER_SLURPER_TAX_STYLE );

		wp_set_object_terms( $post_id, $term['term_id'], BEER_SLURPER_TAX_STYLE );

		$terms = wp_get_object_terms( $post_id, BEER_SLURPER_TAX_STYLE );
		$this->assertCount( 1, $terms );
		$this->assertEquals( 'IPA - American', $terms[0]->name );
	}

	/**
	 * Tests can assign multiple terms to beer post.
	 */
	public function test_can_assign_multiple_taxonomies() {
		$post_id = $this->create_beer_post();

		$brewery = wp_insert_term( 'Multi Brewery', BEER_SLURPER_TAX_BREWERY );
		$style = wp_insert_term( 'Stout', BEER_SLURPER_TAX_STYLE );
		$venue = wp_insert_term( 'Test Bar', BEER_SLURPER_TAX_VENUE );

		wp_set_object_terms( $post_id, $brewery['term_id'], BEER_SLURPER_TAX_BREWERY );
		wp_set_object_terms( $post_id, $style['term_id'], BEER_SLURPER_TAX_STYLE );
		wp_set_object_terms( $post_id, $venue['term_id'], BEER_SLURPER_TAX_VENUE );

		$brewery_terms = wp_get_object_terms( $post_id, BEER_SLURPER_TAX_BREWERY );
		$style_terms = wp_get_object_terms( $post_id, BEER_SLURPER_TAX_STYLE );
		$venue_terms = wp_get_object_terms( $post_id, BEER_SLURPER_TAX_VENUE );

		$this->assertCount( 1, $brewery_terms );
		$this->assertCount( 1, $style_terms );
		$this->assertCount( 1, $venue_terms );
	}

	/**
	 * Tests beer post type labels are correct.
	 */
	public function test_beer_post_type_labels() {
		$post_type = get_post_type_object( BEER_SLURPER_CPT );

		$this->assertEquals( 'Beers', $post_type->labels->name );
		$this->assertEquals( 'Beer', $post_type->labels->singular_name );
		$this->assertEquals( 'Add New Beer', $post_type->labels->add_new_item );
	}

	/**
	 * Tests brewery taxonomy labels are correct.
	 */
	public function test_brewery_taxonomy_labels() {
		$taxonomy = get_taxonomy( BEER_SLURPER_TAX_BREWERY );

		$this->assertEquals( 'Breweries', $taxonomy->labels->name );
		$this->assertEquals( 'Brewery', $taxonomy->labels->singular_name );
	}

	/**
	 * Tests style taxonomy labels are correct.
	 */
	public function test_style_taxonomy_labels() {
		$taxonomy = get_taxonomy( BEER_SLURPER_TAX_STYLE );

		$this->assertEquals( 'Styles', $taxonomy->labels->name );
		$this->assertEquals( 'Style', $taxonomy->labels->singular_name );
	}

	/**
	 * Tests post type queries work correctly.
	 */
	public function test_post_type_queries() {
		// Create test posts
		$this->create_beer_post( array( 'post_title' => 'Query Beer 1' ) );
		$this->create_beer_post( array( 'post_title' => 'Query Beer 2' ) );
		wp_insert_post( array(
			'post_title'  => 'Regular Post',
			'post_type'   => 'post',
			'post_status' => 'publish',
		) );

		$query = new \WP_Query( array(
			'post_type' => BEER_SLURPER_CPT,
		) );

		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Tests taxonomy queries work correctly.
	 */
	public function test_taxonomy_queries() {
		$post1 = $this->create_beer_post( array( 'post_title' => 'Tax Query Beer 1' ) );
		$post2 = $this->create_beer_post( array( 'post_title' => 'Tax Query Beer 2' ) );
		$post3 = $this->create_beer_post( array( 'post_title' => 'Tax Query Beer 3' ) );

		$term = wp_insert_term( 'Query Brewery', BEER_SLURPER_TAX_BREWERY );
		wp_set_object_terms( $post1, $term['term_id'], BEER_SLURPER_TAX_BREWERY );
		wp_set_object_terms( $post2, $term['term_id'], BEER_SLURPER_TAX_BREWERY );
		// post3 intentionally not assigned to brewery

		$query = new \WP_Query( array(
			'post_type' => BEER_SLURPER_CPT,
			'tax_query' => array(
				array(
					'taxonomy' => BEER_SLURPER_TAX_BREWERY,
					'field'    => 'term_id',
					'terms'    => $term['term_id'],
				),
			),
		) );

		$this->assertEquals( 2, $query->found_posts );
	}
}
