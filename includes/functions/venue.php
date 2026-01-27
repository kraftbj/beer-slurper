<?php
namespace Kraft\Beer_Slurper\Venue;

/**
 * Venue Taxonomy Management
 *
 * Handles creating and managing venue taxonomy terms with location
 * metadata from Untappd checkins.
 *
 * @package Kraft\Beer_Slurper
 */

/**
 * Retrieves the term ID for a venue, creating it if it does not exist.
 *
 * @param int        $venue_id   The Untappd venue ID.
 * @param array|null $venue_data Optional. Venue data from the checkin.
 *
 * @return int|false The venue term ID, or false on failure.
 */
function get_venue_term_id( $venue_id = null, $venue_data = null ) {
	if ( empty( $venue_id ) ) {
		return false;
	}

	$args = array(
		'taxonomy'   => BEER_SLURPER_TAX_VENUE,
		'hide_empty' => false,
		'number'     => 1,
		'meta_key'   => 'untappd_id',
		'meta_value' => $venue_id,
	);
	$term = get_terms( $args );

	if ( empty( $term ) || is_wp_error( $term ) ) {
		$term_id = add_venue( $venue_id, $venue_data );
		if ( is_wp_error( $term_id ) ) {
			return false;
		}
		return $term_id;
	}

	return $term[0]->term_id;
}

/**
 * Adds a new venue term to the taxonomy.
 *
 * @param int        $venue_id   The Untappd venue ID.
 * @param array|null $venue_data Venue data from the Untappd API.
 *
 * @return int|\WP_Error The term ID on success, or WP_Error on failure.
 */
function add_venue( $venue_id, $venue_data = null ) {
	if ( ! is_array( $venue_data ) || empty( $venue_data['venue_name'] ) ) {
		return new \WP_Error( 'invalid_venue', __( 'Invalid venue data.', 'beer_slurper' ) );
	}

	$venue_slug = isset( $venue_data['venue_slug'] ) ? $venue_data['venue_slug'] : sanitize_title( $venue_data['venue_name'] );

	$term = wp_insert_term(
		$venue_data['venue_name'],
		BEER_SLURPER_TAX_VENUE,
		array( 'slug' => $venue_slug )
	);

	if ( is_wp_error( $term ) ) {
		if ( $term->get_error_code() === 'term_exists' ) {
			$existing_term = get_term_by( 'slug', $venue_slug, BEER_SLURPER_TAX_VENUE );
			if ( $existing_term ) {
				return $existing_term->term_id;
			}
		}
		return $term;
	}

	$term_id = $term['term_id'];

	save_venue_meta( $term_id, $venue_id, $venue_data );

	return $term_id;
}

/**
 * Saves venue metadata to a term.
 *
 * @param int   $term_id    The term ID.
 * @param int   $venue_id   The Untappd venue ID.
 * @param array $venue_data The venue data array.
 *
 * @return void
 */
function save_venue_meta( $term_id, $venue_id, $venue_data ) {
	update_term_meta( $term_id, 'untappd_id', $venue_id );

	$location = isset( $venue_data['location'] ) ? $venue_data['location'] : array();

	$meta_map = array(
		'venue_address'  => isset( $location['venue_address'] ) ? $location['venue_address'] : '',
		'venue_city'     => isset( $location['venue_city'] ) ? $location['venue_city'] : '',
		'venue_state'    => isset( $location['venue_state'] ) ? $location['venue_state'] : '',
		'venue_country'  => isset( $location['venue_country'] ) ? $location['venue_country'] : '',
		'venue_lat'      => isset( $location['lat'] ) ? (float) $location['lat'] : '',
		'venue_lng'      => isset( $location['lng'] ) ? (float) $location['lng'] : '',
	);

	if ( isset( $venue_data['venue_url'] ) ) {
		$meta_map['venue_url'] = $venue_data['venue_url'];
	}
	if ( isset( $venue_data['primary_category'] ) ) {
		$meta_map['venue_category'] = $venue_data['primary_category'];
	}
	if ( isset( $venue_data['venue_icon']['sm'] ) ) {
		$meta_map['venue_icon'] = $venue_data['venue_icon']['sm'];
	}
	if ( isset( $venue_data['foursquare']['foursquare_id'] ) ) {
		$meta_map['foursquare_id'] = $venue_data['foursquare']['foursquare_id'];
	}

	foreach ( $meta_map as $key => $value ) {
		if ( '' !== $value ) {
			update_term_meta( $term_id, $key, $value );
		}
	}
}

/**
 * Backfills missing venue metadata from the API.
 *
 * Finds venue terms missing lat/lng data and re-fetches from the API.
 * Limited to 5 per run to stay within rate limits.
 *
 * @return int Number of venues updated.
 */
function backfill_missing_meta() {
	$terms = get_terms( array(
		'taxonomy'   => BEER_SLURPER_TAX_VENUE,
		'hide_empty' => false,
		'number'     => 5,
		'meta_query' => array(
			'relation' => 'AND',
			array(
				'key'     => 'untappd_id',
				'compare' => 'EXISTS',
			),
			array(
				'key'     => 'venue_lat',
				'compare' => 'NOT EXISTS',
			),
		),
	) );

	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return 0;
	}

	$updated = 0;
	foreach ( $terms as $term ) {
		if ( ! \Kraft\Beer_Slurper\Queue\has_budget( 1 ) ) {
			break;
		}

		$venue_id = get_term_meta( $term->term_id, 'untappd_id', true );
		if ( ! $venue_id ) {
			continue;
		}

		$venue_data = \Kraft\Beer_Slurper\API\get_untappd_data( 'venue/info', $venue_id );
		if ( is_wp_error( $venue_data ) || ! is_array( $venue_data ) || ! isset( $venue_data['venue'] ) ) {
			continue;
		}

		save_venue_meta( $term->term_id, $venue_id, $venue_data['venue'] );
		$updated++;
	}

	return $updated;
}
