<?php
/**
 * Brewery Helper Functions
 *
 * This file provides functions to check if a brewery is already in the system,
 * inserting a new brewery, and managing brewery term metadata.
 *
 * @package Kraft\Beer_Slurper\Brewery
 */
namespace Kraft\Beer_Slurper\Brewery;

/**
 * Retrieves the term ID for a brewery, creating it if it does not exist.
 *
 * Queries the brewery taxonomy by Untappd brewery ID stored in term meta.
 * If the brewery does not exist, creates a new brewery term.
 *
 * @param int|null   $breweryid    The Untappd brewery ID to look up.
 * @param array|null $brewery_data Optional. Fallback brewery data from API response.
 *
 * @uses apply_filters()
 * @uses get_terms()
 * @uses is_wp_error()
 *
 * @return int|false The brewery term ID, or false on failure.
 */
function get_brewery_term_id( $breweryid = null, $brewery_data = null ){
	if ( empty( $breweryid ) ){
		return false;
	}
	// get_terms to call to access the brewery term
	$args = array(
		'taxonomy'   => apply_filters( 'beer_slurper_tax_brewery', BEER_SLURPER_TAX_BREWERY ),
		'hide_empty' => false,
		'number'     => 1,
		'meta_key'   => 'untappd_id',
		'meta_value' => $breweryid,
		);
	$term = get_terms( $args ); // array of WP_Term objects

	if ( empty( $term ) || is_wp_error( $term ) ){
		$term_id = add_brewery( $breweryid, $brewery_data );
		// If add_brewery fails, return false instead of WP_Error to avoid fatal errors
		if ( is_wp_error( $term_id ) ) {
			return false;
		}
		return $term_id;
	}

	return $term[0]->term_id;
}

/**
 * Adds a new brewery term to the taxonomy.
 *
 * Attempts to fetch full brewery data from the Untappd API. If the API call
 * fails, falls back to pre-fetched brewery data from the beer response.
 * Creates the term with appropriate metadata including Untappd ID and brewery type.
 * Handles parent breweries for brewery groups/owners.
 *
 * @param int        $breweryid    The Untappd brewery ID.
 * @param array|null $brewery_data Optional. Fallback brewery data if API fails.
 *
 * @uses is_wp_error()
 * @uses is_array()
 * @uses error_log()
 * @uses sanitize_title()
 * @uses apply_filters()
 * @uses wp_insert_term()
 * @uses get_term_by()
 * @uses update_term_meta()
 *
 * @return int|\WP_Error The term ID on success, or WP_Error on failure.
 */
function add_brewery( $breweryid, $brewery_data = null ){
	// Try full API call first to get owners/parent data
	$brewery = \Kraft\Beer_Slurper\API\get_brewery_info( $breweryid );

	if ( is_wp_error( $brewery ) || ! is_array( $brewery ) ) {
		// API failed - fall back to pre-fetched data from beer response if available
		if ( is_array( $brewery_data ) && isset( $brewery_data['brewery_name'] ) ) {
			error_log( 'Beer Slurper: Brewery API failed, using fallback data for brewery ' . $breweryid );
			$brewery = $brewery_data;
		} else {
			error_log( 'Beer Slurper: Failed to get brewery info - ' . ( is_wp_error( $brewery ) ? $brewery->get_error_message() : 'invalid response' ) );
			return is_wp_error( $brewery ) ? $brewery : new \WP_Error( 'invalid_brewery', __( 'Invalid brewery data from API.', 'beer_slurper' ) );
		}
	}

	if ( ! is_array( $brewery ) || ! isset( $brewery['brewery_name'] ) ) {
		error_log( 'Beer Slurper: Invalid brewery data structure' );
		return new \WP_Error( 'invalid_brewery', __( 'Invalid brewery data from API.', 'beer_slurper' ) );
	}

	// Generate slug from name if not provided
	$brewery_slug = isset( $brewery['brewery_slug'] ) ? $brewery['brewery_slug'] : sanitize_title( $brewery['brewery_name'] );

	$args = array(
		'slug'   => $brewery_slug,
		);

	// Only check for parent brewery if we have owners data (from full API call)
	if ( isset( $brewery['owners']['items'][0]['brewery_id'] ) ){
		$args['parent'] = (int) get_brewery_term_id( $brewery['owners']['items'][0]['brewery_id'] );
	}

	$term = wp_insert_term( $brewery['brewery_name'] , apply_filters( 'beer_slurper_tax_brewery', BEER_SLURPER_TAX_BREWERY ), $args );

	if ( is_wp_error( $term ) ) {
		// If term already exists, try to get it
		if ( $term->get_error_code() === 'term_exists' ) {
			$existing_term = get_term_by( 'slug', $brewery_slug, apply_filters( 'beer_slurper_tax_brewery', BEER_SLURPER_TAX_BREWERY ) );
			if ( $existing_term ) {
				return $existing_term->term_id;
			}
		}
		error_log( 'Beer Slurper: Failed to insert brewery term - ' . $term->get_error_message() );
		return $term;
	}

	$term_id = $term['term_id'];
	update_term_meta( $term_id, 'untappd_id', isset( $brewery['brewery_id'] ) ? $brewery['brewery_id'] : $breweryid );
	save_brewery_meta( $term_id, $brewery );

	return $term_id;
}

/**
 * Saves all available brewery metadata to a term.
 *
 * @param int   $term_id The term ID.
 * @param array $brewery The brewery data array from the API.
 *
 * @return void
 */
function save_brewery_meta( $term_id, $brewery ) {
	$simple_fields = array(
		'brewery_type'        => 'brewery_type',
		'brewery_label'       => 'brewery_label',
		'brewery_description' => 'brewery_description',
	);

	foreach ( $simple_fields as $meta_key => $api_key ) {
		if ( isset( $brewery[ $api_key ] ) && '' !== $brewery[ $api_key ] ) {
			update_term_meta( $term_id, $meta_key, $brewery[ $api_key ] );
		}
	}

	// Location fields.
	if ( isset( $brewery['location'] ) && is_array( $brewery['location'] ) ) {
		$location = $brewery['location'];
		$location_fields = array(
			'brewery_lat'     => 'brewery_lat',
			'brewery_lng'     => 'brewery_lng',
			'brewery_address' => 'brewery_address',
		);
		foreach ( $location_fields as $meta_key => $api_key ) {
			if ( isset( $location[ $api_key ] ) && '' !== $location[ $api_key ] ) {
				update_term_meta( $term_id, $meta_key, $location[ $api_key ] );
			}
		}
	}

	// City/state/country may be top-level or nested.
	$geo_fields = array( 'brewery_city', 'brewery_state', 'brewery_country' );
	foreach ( $geo_fields as $field ) {
		if ( isset( $brewery[ $field ] ) && '' !== $brewery[ $field ] ) {
			update_term_meta( $term_id, $field, $brewery[ $field ] );
		} elseif ( isset( $brewery['location'][ $field ] ) && '' !== $brewery['location'][ $field ] ) {
			update_term_meta( $term_id, $field, $brewery['location'][ $field ] );
		}
	}

	// Contact fields.
	if ( isset( $brewery['contact'] ) && is_array( $brewery['contact'] ) ) {
		$contact = $brewery['contact'];
		$contact_fields = array(
			'brewery_url'       => 'url',
			'brewery_twitter'   => 'twitter',
			'brewery_facebook'  => 'facebook',
			'brewery_instagram' => 'instagram',
		);
		foreach ( $contact_fields as $meta_key => $api_key ) {
			if ( isset( $contact[ $api_key ] ) && '' !== $contact[ $api_key ] ) {
				update_term_meta( $term_id, $meta_key, $contact[ $api_key ] );
			}
		}
	}
}

/**
 * Backfills missing brewery metadata from the API.
 *
 * Finds brewery terms missing lat/lng data and re-fetches from the API.
 * Limited to 5 per run to stay within rate limits.
 *
 * @return int Number of breweries updated.
 */
function backfill_missing_meta() {
	$terms = get_terms( array(
		'taxonomy'   => apply_filters( 'beer_slurper_tax_brewery', BEER_SLURPER_TAX_BREWERY ),
		'hide_empty' => false,
		'number'     => 5,
		'meta_query' => array(
			'relation' => 'AND',
			array(
				'key'     => 'untappd_id',
				'compare' => 'EXISTS',
			),
			array(
				'key'     => 'brewery_lat',
				'compare' => 'NOT EXISTS',
			),
		),
	) );

	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return 0;
	}

	$updated = 0;
	foreach ( $terms as $term ) {
		$brewery_id = get_term_meta( $term->term_id, 'untappd_id', true );
		if ( ! $brewery_id ) {
			continue;
		}

		$brewery = \Kraft\Beer_Slurper\API\get_brewery_info( $brewery_id );
		if ( is_wp_error( $brewery ) || ! is_array( $brewery ) ) {
			continue;
		}

		save_brewery_meta( $term->term_id, $brewery );
		$updated++;
	}

	return $updated;
}
