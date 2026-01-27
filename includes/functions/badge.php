<?php
namespace Kraft\Beer_Slurper\Badge;

/**
 * Badge Taxonomy Management
 *
 * Handles creating and managing badge taxonomy terms from
 * Untappd checkin badge data.
 *
 * @package Kraft\Beer_Slurper
 */

/**
 * Retrieves the term ID for a badge, creating it if it does not exist.
 *
 * @param int   $badge_id The Untappd badge ID.
 * @param array $badge    Optional. Badge data from the checkin.
 *
 * @return int|false The badge term ID, or false on failure.
 */
function get_badge_term_id( $badge_id, $badge = null ) {
	if ( empty( $badge_id ) ) {
		return false;
	}

	$args = array(
		'taxonomy'   => BEER_SLURPER_TAX_BADGE,
		'hide_empty' => false,
		'number'     => 1,
		'meta_key'   => 'untappd_id',
		'meta_value' => $badge_id,
	);
	$term = get_terms( $args );

	if ( empty( $term ) || is_wp_error( $term ) ) {
		if ( ! is_array( $badge ) ) {
			return false;
		}
		$term_id = add_badge( $badge );
		if ( is_wp_error( $term_id ) ) {
			return false;
		}
		return $term_id;
	}

	return $term[0]->term_id;
}

/**
 * Adds a new badge term to the taxonomy.
 *
 * @param array $badge Badge data from the Untappd API.
 *
 * @return int|\WP_Error The term ID on success, or WP_Error on failure.
 */
function add_badge( $badge ) {
	if ( ! is_array( $badge ) || empty( $badge['badge_name'] ) ) {
		return new \WP_Error( 'invalid_badge', __( 'Invalid badge data.', 'beer_slurper' ) );
	}

	$badge_slug = sanitize_title( $badge['badge_name'] );

	$term = wp_insert_term(
		$badge['badge_name'],
		BEER_SLURPER_TAX_BADGE,
		array( 'slug' => $badge_slug )
	);

	if ( is_wp_error( $term ) ) {
		if ( $term->get_error_code() === 'term_exists' ) {
			$existing_term = get_term_by( 'slug', $badge_slug, BEER_SLURPER_TAX_BADGE );
			if ( $existing_term ) {
				return $existing_term->term_id;
			}
		}
		return $term;
	}

	$term_id = $term['term_id'];

	update_term_meta( $term_id, 'untappd_id', $badge['badge_id'] );

	if ( isset( $badge['badge_image']['sm'] ) ) {
		update_term_meta( $term_id, 'badge_image_sm', $badge['badge_image']['sm'] );
	}
	if ( isset( $badge['badge_image']['md'] ) ) {
		update_term_meta( $term_id, 'badge_image_md', $badge['badge_image']['md'] );
	}
	if ( isset( $badge['badge_image']['lg'] ) ) {
		update_term_meta( $term_id, 'badge_image_lg', $badge['badge_image']['lg'] );
	}
	if ( ! empty( $badge['badge_description'] ) ) {
		update_term_meta( $term_id, 'badge_description', $badge['badge_description'] );
	}

	return $term_id;
}
