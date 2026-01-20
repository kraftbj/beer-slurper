<?php
namespace Kraft\Beer_Slurper\Walker;

/**
 * Import Walker Functions
 *
 * Contains functions to walk through Untappd checkin history,
 * importing both new checkins and historical backfill data.
 *
 * @package Kraft\Beer_Slurper
 */

/**
 * Imports new checkins since the last sync.
 *
 * Fetches and imports checkins that occurred after the last recorded
 * checkin ID. If no previous sync exists, initiates historical import.
 *
 * @since 1.0.0
 *
 * @uses sanitize_user()                        Sanitizes the username.
 * @uses get_option()                           Retrieves last sync position.
 * @uses is_wp_error()                          Checks for API errors.
 * @uses update_option()                        Saves new sync position.
 * @uses \Kraft\Beer_Slurper\API\get_checkins() Fetches checkin data from API.
 * @uses \Kraft\Beer_Slurper\Post\insert_beer() Creates beer posts from checkins.
 *
 * @param string $user The Untappd username.
 *
 * @return string|WP_Error Success message with count, or WP_Error on failure.
 */
function import_new( $user ) {

	$user = sanitize_user( $user ); // Just to be safe. Not sure what Untappd uses, but \_(ツ)_/¯

	if ( empty( $user ) ) {
		return new \WP_Error( 'invalid_user', __( 'Invalid or empty username provided.', 'beer_slurper' ) );
	}

	// check for an option of the since_id for the $user indicated.
	$since_id = get_option( 'beer_slurper_' . $user . '_since' ); // @todo use an array instead of seperate options?

	if ( ! $since_id ) {
		// this means we have never pulled in data for this user. Let's kick off the import process?
		return import_old( $user );
	}

	$checkins = \Kraft\Beer_Slurper\API\get_checkins( $user, null, $since_id, '25' );

	if ( is_wp_error( $checkins ) ) {
		return $checkins;
	}

	if ( ! isset( $checkins['count'] ) || $checkins['count'] == 0 ) {
		return "No new beers here!";
	}

	if ( isset( $checkins['count'] ) && $checkins['count'] == 25 ) {
		// @todo do special stuff since there are likely more than we thought.
		// This also means you had more than 25 beers in an hour.
		// Even if you're at a homebrew conference, throw a governor on that engine, eh?
	}

	foreach ( $checkins['items'] as $checkin ){
		\Kraft\Beer_Slurper\Post\insert_beer( $checkin );
	}

	$since_id = $checkins['items'][0]['checkin_id'];
	update_option( 'beer_slurper_' . $user . '_since', $since_id, false );

	$message = $checkins['count'] . " beer(s) imported.";
	return $message;
}


/**
 * Imports historical checkin data in batches.
 *
 * Walks backwards through a user's checkin history, importing 25 checkins
 * at a time. Designed to be called repeatedly via cron until all historical
 * data has been imported.
 *
 * @since 1.0.0
 *
 * @uses sanitize_user()                        Sanitizes the username.
 * @uses get_option()                           Retrieves import progress markers.
 * @uses is_wp_error()                          Checks for API errors.
 * @uses is_array()                             Validates response structure.
 * @uses update_option()                        Saves import progress.
 * @uses wp_parse_args()                        Parses URL query parameters.
 * @uses parse_url()                            Extracts query string from pagination URL.
 * @uses intval()                               Sanitizes since_id value.
 * @uses delete_option()                        Clears import flag when complete.
 * @uses \Kraft\Beer_Slurper\API\get_checkins() Fetches checkin data from API.
 * @uses \Kraft\Beer_Slurper\Post\insert_beer() Creates beer posts from checkins.
 *
 * @param string $user The Untappd username.
 *
 * @return WP_Error|void WP_Error on failure, void on success.
 */
function import_old( $user ) {
	$user = sanitize_user( $user );

	if ( empty( $user ) ) {
		return new \WP_Error( 'invalid_user', __( 'Invalid or empty username provided.', 'beer_slurper' ) );
	}

	$args = array();
	$max_id = get_option( 'beer_slurper_' . $user . '_max' );

	$checkins = \Kraft\Beer_Slurper\API\get_checkins( $user, $max_id, null, '25' ); // set 25 for the cron check.

	if ( is_wp_error( $checkins ) ) {
		return $checkins;
	}

	if ( ! is_array( $checkins ) || ! isset( $checkins['checkins']['items'] ) ) {
		return new \WP_Error( 'invalid_response', __( 'Invalid response from Untappd API.', 'beer_slurper' ) );
	}

	foreach ( $checkins['checkins']['items'] as $checkin ){
		\Kraft\Beer_Slurper\Post\insert_beer( $checkin );
	}

	$max_id = $checkins['pagination']['max_id'];
	update_option( 'beer_slurper_' . $user . '_max', $max_id, false );
	if ( ! get_option( 'beer_slurper_' . $user . '_since') ) { //first time to import anything from this user
		$since_url = wp_parse_args( parse_url( $checkins['pagination']['since_url'], PHP_URL_QUERY ) );
		$since_id = intval( $since_url['min_id'] );
		update_option( 'beer_slurper_' . $user . '_since', $since_id, false );
	}

	if ( isset( $checkins['checkins']['count'] ) && $checkins['checkins']['count'] < 25 ) {
		// Fewer old beers than we asked for, so we should be all done here.
		delete_option( 'beer_slurper_' . $user . '_import' );
	}

}
