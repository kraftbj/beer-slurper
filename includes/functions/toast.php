<?php
namespace Kraft\Beer_Slurper\Toast;

/**
 * Toast (Likes) Comment Functions
 *
 * Stores Untappd toasts as WordPress comments on beer posts,
 * with metadata linking to the companion term for the toaster.
 *
 * @package Kraft\Beer_Slurper
 */

/**
 * Inserts a toast as a comment on a beer post.
 *
 * Creates a WP comment with type 'beer_toast' containing the toast
 * data. Deduplicates via comment meta before inserting.
 *
 * @param array $toast   The raw toast data from the Untappd API.
 * @param int   $post_id The beer post ID to attach the comment to.
 *
 * @return int|false The comment ID on success, or false if duplicate/failure.
 */
function insert_toast( $toast, $post_id ) {
	if ( empty( $toast['like_id'] ) || empty( $post_id ) ) {
		return false;
	}

	$toast_id = $toast['like_id'];

	if ( get_toast_exists( $toast_id ) ) {
		return false;
	}

	// Get or create the companion term for the toaster.
	$toaster_term_id = null;
	if ( ! empty( $toast['user']['uid'] ) ) {
		$toaster_term_id = \Kraft\Beer_Slurper\Companion\get_companion_term_id(
			$toast['user']['uid'],
			$toast['user']
		);
	}

	$comment_data = array(
		'comment_type'     => 'beer_toast',
		'comment_content'  => '',
		'comment_date_gmt' => date( 'Y-m-d H:i:s', strtotime( $toast['created_at'] ) ),
		'comment_date'     => get_date_from_gmt( date( 'Y-m-d H:i:s', strtotime( $toast['created_at'] ) ) ),
		'comment_author'   => isset( $toast['user']['user_name'] ) ? $toast['user']['user_name'] : '',
		'comment_approved' => 1,
		'comment_post_ID'  => $post_id,
		'comment_agent'    => 'Beer Slurper',
	);

	$comment_id = wp_insert_comment( $comment_data );

	if ( ! $comment_id ) {
		return false;
	}

	update_comment_meta( $comment_id, '_beer_slurper_toast_id', $toast_id );

	if ( $toaster_term_id ) {
		update_comment_meta( $comment_id, '_beer_slurper_toaster_term_id', $toaster_term_id );
		// Attach toaster to the beer post for easy querying/display.
		wp_set_object_terms( $post_id, (int) $toaster_term_id, BEER_SLURPER_TAX_COMPANION, true );
	}

	if ( isset( $toast['created_at'] ) ) {
		update_comment_meta( $comment_id, '_beer_slurper_toast_date', $toast['created_at'] );
	}

	return $comment_id;
}

/**
 * Checks whether a toast has already been imported as a comment.
 *
 * @param int $toast_id The Untappd like_id.
 *
 * @return bool True if the toast comment already exists.
 */
function get_toast_exists( $toast_id ) {
	global $wpdb;

	$exists = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->commentmeta}
			WHERE meta_key = '_beer_slurper_toast_id'
			AND meta_value = %s",
			$toast_id
		)
	);

	return (int) $exists > 0;
}

/**
 * Attaches toasts from a checkin to a beer post.
 *
 * @param array $checkin The raw checkin data from the Untappd API.
 * @param int   $post_id The beer post ID.
 *
 * @return int Number of toasts attached.
 */
function attach_toasts( $checkin, $post_id ) {
	if ( empty( $checkin['toasts']['items'] ) ) {
		return 0;
	}

	$attached = 0;

	foreach ( $checkin['toasts']['items'] as $toast ) {
		if ( empty( $toast['like_id'] ) ) {
			continue;
		}

		$result = insert_toast( $toast, $post_id );
		if ( $result ) {
			$attached++;
		}
	}

	return $attached;
}

/**
 * Excludes beer_toast comments from the standard WordPress comments loop.
 *
 * Toasts are displayed by a dedicated block (if implemented).
 * Without this filter, WP_Comment_Query returns all comment types,
 * causing toasts to appear in the regular comments section.
 *
 * @param \WP_Comment_Query $query The comment query object.
 * @return void
 */
function exclude_toast_from_default_comments( $query ) {
	if ( ! is_singular( BEER_SLURPER_CPT ) ) {
		return;
	}

	// Don't interfere with queries explicitly requesting beer_toast.
	$type = isset( $query->query_vars['type'] ) ? $query->query_vars['type'] : '';
	if ( 'beer_toast' === $type ) {
		return;
	}

	// Append beer_toast to existing type__not_in, or create new array.
	$not_in = isset( $query->query_vars['type__not_in'] ) ? (array) $query->query_vars['type__not_in'] : array();
	$not_in[] = 'beer_toast';
	$query->query_vars['type__not_in'] = array_unique( $not_in );
}
add_action( 'pre_get_comments', __NAMESPACE__ . '\exclude_toast_from_default_comments' );

/**
 * Gets the toaster companion term for a toast comment.
 *
 * @param int $comment_id The toast comment ID.
 *
 * @return \WP_Term|false The companion term, or false if not found.
 */
function get_toaster_term( $comment_id ) {
	$term_id = get_comment_meta( $comment_id, '_beer_slurper_toaster_term_id', true );

	if ( empty( $term_id ) ) {
		return false;
	}

	return get_term( (int) $term_id, BEER_SLURPER_TAX_COMPANION );
}

/**
 * Gets all toasts for a beer post.
 *
 * @param int $post_id The beer post ID.
 *
 * @return array Array of toast comment objects.
 */
function get_toasts( $post_id ) {
	return get_comments( array(
		'post_id' => $post_id,
		'type'    => 'beer_toast',
		'status'  => 'approve',
		'orderby' => 'comment_date_gmt',
		'order'   => 'DESC',
	) );
}

/**
 * Gets the total toast count for a beer post.
 *
 * @param int $post_id The beer post ID.
 *
 * @return int Number of toasts.
 */
function get_toast_count( $post_id ) {
	return (int) get_comments( array(
		'post_id' => $post_id,
		'type'    => 'beer_toast',
		'status'  => 'approve',
		'count'   => true,
	) );
}
