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

	// If the API call failed (e.g. the since_id checkin was deleted on Untappd),
	// try to recover by using the latest checkin ID from local data.
	if ( is_wp_error( $checkins ) || ! is_array( $checkins ) ) {
		$recovered_id = get_latest_local_checkin_id();
		if ( $recovered_id && (string) $recovered_id !== (string) $since_id ) {
			update_option( 'beer_slurper_' . $user . '_since', $recovered_id, false );
			$checkins = \Kraft\Beer_Slurper\API\get_checkins( $user, null, $recovered_id, '25' );
		}
	}

	// If still failing (e.g. the since_id is older than the API allows),
	// retry without min_id so the API returns the most recent checkins.
	if ( is_wp_error( $checkins ) || ! is_array( $checkins ) ) {
		$checkins = \Kraft\Beer_Slurper\API\get_checkins( $user, null, null, '25' );
	}

	if ( is_wp_error( $checkins ) || ! is_array( $checkins ) ) {
		return is_wp_error( $checkins ) ? $checkins : new \WP_Error( 'invalid_response', __( 'Invalid response from Untappd API.', 'beer_slurper' ) );
	}

	// Without min_id the API wraps checkins inside a 'checkins' key.
	if ( isset( $checkins['checkins'] ) ) {
		$checkins = $checkins['checkins'];
	}

	if ( ! isset( $checkins['count'] ) || $checkins['count'] == 0 ) {
		return "No new beers here!";
	}

	// Update the since_id immediately so the next run doesn't re-fetch these.
	$since_id = $checkins['items'][0]['checkin_id'];
	update_option( 'beer_slurper_' . $user . '_since', $since_id, false );

	// Queue checkins via Action Scheduler instead of processing synchronously.
	// This respects the API rate limit — items that exceed the current budget
	// are automatically scheduled for the next hourly window.
	$queued = \Kraft\Beer_Slurper\Queue\queue_checkin_batch( $checkins['items'], 'import_new' );

	$message = $checkins['count'] . " beer(s) queued for import ({$queued} within current budget).";
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

	// Update pagination state immediately so the next run advances.
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

	// Queue checkins via Action Scheduler instead of processing synchronously.
	// Overflow items are scheduled for the next hourly window.
	\Kraft\Beer_Slurper\Queue\queue_checkin_batch( $checkins['checkins']['items'], 'import_old' );

}

/**
 * Finds the most recent checkin ID stored in local post meta.
 *
 * Queries the postmeta table for the highest _beer_slurper_untappd_id value.
 * Used to recover the since_id when the previously stored checkin has been
 * deleted on Untappd.
 *
 * @since 1.0.0
 *
 * @return string|null The latest checkin ID, or null if none found.
 */
function get_latest_local_checkin_id() {
	global $wpdb;

	return $wpdb->get_var(
		$wpdb->prepare(
			"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s ORDER BY CAST(meta_value AS UNSIGNED) DESC LIMIT 1",
			'_beer_slurper_untappd_id'
		)
	);
}
