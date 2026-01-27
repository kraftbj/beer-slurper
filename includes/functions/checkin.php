<?php
namespace Kraft\Beer_Slurper\Checkin;

/**
 * Checkin-as-Comment Functions
 *
 * Stores Untappd checkins as WordPress comments on beer posts,
 * with metadata for rating, venue, and serving type.
 *
 * @package Kraft\Beer_Slurper
 */

/**
 * Inserts a checkin as a comment on a beer post.
 *
 * Creates a WP comment with type 'beer_checkin' containing the checkin
 * data. Deduplicates via comment meta before inserting.
 *
 * @param array $checkin The raw checkin data from the Untappd API.
 * @param int   $post_id The beer post ID to attach the comment to.
 *
 * @return int|false The comment ID on success, or false if duplicate/failure.
 */
function insert_checkin( $checkin, $post_id ) {
	if ( empty( $checkin['checkin_id'] ) || empty( $post_id ) ) {
		return false;
	}

	$checkin_id = $checkin['checkin_id'];

	if ( get_checkin_exists( $checkin_id ) ) {
		return false;
	}

	$comment_data = array(
		'comment_type'     => 'beer_checkin',
		'comment_content'  => isset( $checkin['checkin_comment'] ) ? $checkin['checkin_comment'] : '',
		'comment_date_gmt' => date( 'Y-m-d H:i:s', strtotime( $checkin['created_at'] ) ),
		'comment_date'     => get_date_from_gmt( date( 'Y-m-d H:i:s', strtotime( $checkin['created_at'] ) ) ),
		'comment_author'   => isset( $checkin['user']['user_name'] ) ? $checkin['user']['user_name'] : '',
		'comment_approved' => 1,
		'comment_post_ID'  => $post_id,
	);

	$comment_id = wp_insert_comment( $comment_data );

	if ( ! $comment_id ) {
		return false;
	}

	update_comment_meta( $comment_id, '_beer_slurper_checkin_id', $checkin_id );

	if ( isset( $checkin['rating_score'] ) ) {
		update_comment_meta( $comment_id, '_beer_slurper_rating', (float) $checkin['rating_score'] );
	}

	if ( isset( $checkin['venue']['venue_id'] ) ) {
		$venue_term_id = \Kraft\Beer_Slurper\Venue\get_venue_term_id(
			$checkin['venue']['venue_id'],
			$checkin['venue']
		);
		if ( $venue_term_id ) {
			update_comment_meta( $comment_id, '_beer_slurper_venue_id', $venue_term_id );
		}
	}

	if ( ! empty( $checkin['serving_type'] ) ) {
		update_comment_meta( $comment_id, '_beer_slurper_serving_type', $checkin['serving_type'] );
	}

	return $comment_id;
}

/**
 * Checks whether a checkin has already been imported as a comment.
 *
 * @param int $checkin_id The Untappd checkin ID.
 *
 * @return bool True if the checkin comment already exists.
 */
function get_checkin_exists( $checkin_id ) {
	global $wpdb;

	$exists = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->commentmeta}
			WHERE meta_key = '_beer_slurper_checkin_id'
			AND meta_value = %s",
			$checkin_id
		)
	);

	return (int) $exists > 0;
}

/**
 * Excludes beer_checkin comments from the standard WordPress comments loop.
 *
 * Checkins are displayed by the dedicated pint/checkin-list block.
 * Without this filter, WP_Comment_Query returns all comment types,
 * causing checkins to appear in both the checkin list and the
 * regular comments section.
 *
 * @param \WP_Comment_Query $query The comment query object.
 * @return void
 */
function exclude_checkin_from_default_comments( $query ) {
	if ( ! is_singular( BEER_SLURPER_CPT ) ) {
		return;
	}

	// Don't interfere with queries explicitly requesting beer_checkin
	// (e.g. the checkin-list block's own get_comments() call).
	$type = isset( $query->query_vars['type'] ) ? $query->query_vars['type'] : '';
	if ( 'beer_checkin' === $type ) {
		return;
	}

	$query->query_vars['type__not_in'] = array( 'beer_checkin' );
}
add_action( 'pre_get_comments', __NAMESPACE__ . '\exclude_checkin_from_default_comments' );
