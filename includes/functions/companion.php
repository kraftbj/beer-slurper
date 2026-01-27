<?php
namespace Kraft\Beer_Slurper\Companion;

/**
 * Companion (Tagged Friends) Taxonomy Management
 *
 * Handles creating and managing companion taxonomy terms from
 * Untappd tagged_friends data on checkins.
 *
 * @package Kraft\Beer_Slurper
 */

/**
 * Retrieves the term ID for a companion, creating if needed.
 *
 * @param int        $uid       The Untappd user ID.
 * @param array|null $user_data Optional. User data from the checkin.
 *
 * @return int|false The companion term ID, or false on failure.
 */
function get_companion_term_id( $uid = null, $user_data = null ) {
	if ( empty( $uid ) ) {
		return false;
	}

	$terms = get_terms( array(
		'taxonomy'   => BEER_SLURPER_TAX_COMPANION,
		'hide_empty' => false,
		'number'     => 1,
		'meta_key'   => 'untappd_uid',
		'meta_value' => $uid,
	) );

	if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
		return $terms[0]->term_id;
	}

	return add_companion( $uid, $user_data );
}

/**
 * Adds a new companion term.
 *
 * @param int        $uid       The Untappd user ID.
 * @param array|null $user_data User data from the Untappd API.
 *
 * @return int|false The term ID on success, or false on failure.
 */
function add_companion( $uid, $user_data = null ) {
	if ( ! is_array( $user_data ) || empty( $user_data['user_name'] ) ) {
		return false;
	}

	$display_name = trim(
		( isset( $user_data['first_name'] ) ? $user_data['first_name'] : '' ) . ' ' .
		( isset( $user_data['last_name'] ) ? $user_data['last_name'] : '' )
	);
	if ( empty( $display_name ) ) {
		$display_name = $user_data['user_name'];
	}

	$term = wp_insert_term(
		$display_name,
		BEER_SLURPER_TAX_COMPANION,
		array( 'slug' => sanitize_title( $user_data['user_name'] ) )
	);

	if ( is_wp_error( $term ) ) {
		if ( $term->get_error_code() === 'term_exists' ) {
			$existing = get_term_by( 'slug', sanitize_title( $user_data['user_name'] ), BEER_SLURPER_TAX_COMPANION );
			if ( $existing ) {
				return $existing->term_id;
			}
		}
		return false;
	}

	$term_id = $term['term_id'];

	update_term_meta( $term_id, 'untappd_uid', $uid );

	if ( ! empty( $user_data['user_name'] ) ) {
		update_term_meta( $term_id, 'untappd_username', $user_data['user_name'] );
	}
	if ( ! empty( $user_data['user_avatar'] ) ) {
		update_term_meta( $term_id, 'avatar_url', $user_data['user_avatar'] );
	}

	return $term_id;
}

/**
 * Attaches tagged friends from a checkin to a beer post.
 *
 * @param array $checkin The raw checkin data from the Untappd API.
 * @param int   $post_id The beer post ID.
 *
 * @return void
 */
function attach_companions( $checkin, $post_id ) {
	if ( empty( $checkin['tagged_friends']['items'] ) ) {
		return;
	}

	foreach ( $checkin['tagged_friends']['items'] as $item ) {
		if ( empty( $item['user']['uid'] ) ) {
			continue;
		}

		$term_id = get_companion_term_id( $item['user']['uid'], $item['user'] );
		if ( $term_id ) {
			wp_set_object_terms( $post_id, (int) $term_id, BEER_SLURPER_TAX_COMPANION, true );
		}
	}
}
