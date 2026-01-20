<?php
namespace Kraft\Beer_Slurper\Brewery;


// \Kraft\Beer_Slurper\Brewery\get_brewery_term_id( 217984 );
/**
 * This file provides functions to check if a brewery is already in the system, inserting a new brewery, etc.
 */

// use get_terms to query by meta to check for untappd brewery id

// add function to check if we already have a brewery in out system

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
	update_term_meta( $term_id, 'brewery_type', isset( $brewery['brewery_type'] ) ? $brewery['brewery_type'] : '' );

	return $term_id;
}