<?php
namespace Kraft\Beer_Slurper\Sync_Status;

/**
 * Sync Status Helper Functions
 *
 * This file provides functions for tracking sync state, statistics, and status.
 */

/**
 * Retrieves the timestamp of the last successful sync.
 *
 * @return int|null Unix timestamp or null if never synced.
 */
function get_last_sync_time() {
	$timestamp = get_option( 'beer_slurper_last_sync' );
	return $timestamp ? (int) $timestamp : null;
}

/**
 * Retrieves the last sync error if one occurred.
 *
 * @return array|null Array with 'code' and 'message' keys, or null if no error.
 */
function get_last_sync_error() {
	$error = get_option( 'beer_slurper_last_sync_error' );
	if ( ! $error || ! is_array( $error ) ) {
		return null;
	}
	return $error;
}

/**
 * Records a successful sync timestamp and clears any previous error.
 *
 * @param int $timestamp Unix timestamp of the sync.
 * @return bool True on success, false on failure.
 */
function record_sync_success( $timestamp ) {
	$result = update_option( 'beer_slurper_last_sync', (int) $timestamp, false );
	clear_sync_error();
	return $result;
}

/**
 * Records a sync error from a WP_Error object.
 *
 * @param \WP_Error $wp_error The error object to record.
 * @return bool True on success, false on failure.
 */
function record_sync_error( $wp_error ) {
	if ( ! is_wp_error( $wp_error ) ) {
		return false;
	}

	$error_data = array(
		'code'    => $wp_error->get_error_code(),
		'message' => $wp_error->get_error_message(),
	);

	return update_option( 'beer_slurper_last_sync_error', $error_data, false );
}

/**
 * Clears any stored sync error.
 *
 * @return bool True on success, false on failure.
 */
function clear_sync_error() {
	return delete_option( 'beer_slurper_last_sync_error' );
}

/**
 * Gets the total count of published beer posts.
 *
 * @return int Total number of published beers.
 */
function get_total_beers() {
	$counts = wp_count_posts( BEER_SLURPER_CPT );
	return isset( $counts->publish ) ? (int) $counts->publish : 0;
}

/**
 * Gets the total count of pictures attached to beer posts.
 *
 * @return int Total number of beer pictures.
 */
function get_total_pictures() {
	global $wpdb;

	$count = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*)
			FROM {$wpdb->posts} AS attachments
			INNER JOIN {$wpdb->posts} AS parents ON attachments.post_parent = parents.ID
			WHERE attachments.post_type = 'attachment'
			AND attachments.post_mime_type LIKE %s
			AND parents.post_type = %s
			AND parents.post_status = 'publish'",
			'image%',
			BEER_SLURPER_CPT
		)
	);

	return (int) $count;
}

/**
 * Gets the total count of brewery terms.
 *
 * @return int Total number of breweries.
 */
function get_total_breweries() {
	$count = wp_count_terms( array(
		'taxonomy'   => BEER_SLURPER_TAX_BREWERY,
		'hide_empty' => false,
	) );

	if ( is_wp_error( $count ) ) {
		return 0;
	}

	return (int) $count;
}

/**
 * Gets the configured Untappd username.
 *
 * @return string|null The username or null if not configured.
 */
function get_configured_user() {
	if ( defined( 'UNTAPPD_USER' ) && UNTAPPD_USER ) {
		return UNTAPPD_USER;
	}

	$user = get_option( 'beer-slurper-user' );
	return $user ? $user : null;
}

/**
 * Gets the timestamp of the next scheduled sync for a user.
 *
 * @param string $user The Untappd username.
 * @return int|null Unix timestamp of next sync or null if not scheduled.
 */
function get_next_scheduled_sync( $user ) {
	if ( empty( $user ) ) {
		return null;
	}

	return \Kraft\Beer_Slurper\Queue\get_next_scheduled( 'bs_hourly_import', array( $user ) );
}

/**
 * Checks if the user is currently backfilling historical data.
 *
 * @param string $user The Untappd username.
 * @return bool True if backfilling, false if caught up.
 */
function is_backfilling( $user ) {
	if ( empty( $user ) ) {
		return false;
	}

	return (bool) get_option( 'beer_slurper_' . $user . '_import' );
}

/**
 * Formats a relative time string from a timestamp.
 *
 * @param int $timestamp Unix timestamp.
 * @return string Human-readable relative time.
 */
function get_relative_time( $timestamp ) {
	return human_time_diff( $timestamp, current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'beer_slurper' );
}
