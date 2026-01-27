<?php
namespace Kraft\Beer_Slurper\Stats;

/**
 * User Stats Cache Functions
 *
 * Fetches and caches Untappd user stats from the user/info API endpoint.
 *
 * @package Kraft\Beer_Slurper
 */

/**
 * Fetches user stats from the Untappd API and caches them.
 *
 * Uses the OAuth token to call user/info (no username needed).
 *
 * @return array|false The stats array on success, or false on failure.
 */
function refresh_user_stats() {
	$response = \Kraft\Beer_Slurper\API\get_untappd_data( 'user/info', '' );

	if ( is_wp_error( $response ) || ! is_array( $response ) || ! isset( $response['user'] ) ) {
		return false;
	}

	$user = $response['user'];
	$user_stats = isset( $user['stats'] ) ? $user['stats'] : array();

	$stats = array(
		'total_checkins'      => isset( $user_stats['total_checkins'] ) ? (int) $user_stats['total_checkins'] : 0,
		'total_beers'         => isset( $user_stats['total_beers'] ) ? (int) $user_stats['total_beers'] : 0,
		'total_badges'        => isset( $user_stats['total_badges'] ) ? (int) $user_stats['total_badges'] : 0,
		'total_friends'       => isset( $user_stats['total_friends'] ) ? (int) $user_stats['total_friends'] : 0,
		'total_created_beers' => isset( $user_stats['total_created_beers'] ) ? (int) $user_stats['total_created_beers'] : 0,
		'user_avatar'         => isset( $user['user_avatar'] ) ? $user['user_avatar'] : '',
		'user_avatar_hd'      => isset( $user['user_avatar_hd'] ) ? $user['user_avatar_hd'] : '',
		'last_refreshed'      => time(),
	);

	update_option( 'beer_slurper_user_stats', $stats, false );

	return $stats;
}

/**
 * Returns the cached user stats.
 *
 * @return array The cached stats array, or empty array if not yet fetched.
 */
function get_user_stats() {
	$stats = get_option( 'beer_slurper_user_stats' );
	return is_array( $stats ) ? $stats : array();
}
