<?php
namespace Kraft\Beer_Slurper\Brewery;


// \Kraft\Beer_Slurper\Brewery\get_brewery_term_id( 217984 );
/**
 * This file provides functions to check if a brewery is already in the system, inserting a new brewery, etc.
 */

// use get_terms to query by meta to check for untappd brewery id

// add function to check if we already have a brewery in out system

function get_brewery_term_id( $breweryid = null ){
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

	if ( empty( $term ) ){
		$term_id = add_brewery( $breweryid );
		return $term_id;
	}

	return $term[0]->term_id;
}

function add_brewery( $breweryid ){
	$brewery = \Kraft\Beer_Slurper\API\get_brewery_info( $breweryid );
	$args = array(
		'slug'   => $brewery['brewery_slug'],
		);

	if ( isset( $brewery['owners']['items'][0]['brewery_id'] ) ){
		(int) $args['parent'] = get_brewery_term_id( $brewery['owners']['items'][0]['brewery_id'] );
	}

	$term = wp_insert_term( $brewery['brewery_name'] , apply_filters( 'beer_slurper_tax_brewery', BEER_SLURPER_TAX_BREWERY ), $args );
	$term_id = $term['term_id'];
	update_term_meta( $term_id, 'untappd_id', $brewery['brewery_id'] );
	update_term_meta( $term_id, 'brewery_type', $brewery['brewery_type'] );

	return $term_id;
}