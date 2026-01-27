<?php
namespace Kraft\Beer_Slurper\Badge;

/**
 * Badge Taxonomy Management
 *
 * Handles creating and managing badge taxonomy terms from
 * Untappd checkin badge data.
 *
 * Badges with multiple levels (e.g. "Brewery Pioneer (Level 5)")
 * are stored as a single term using the base name. The highest
 * level earned is tracked as term meta.
 *
 * @package Kraft\Beer_Slurper
 */

/**
 * Parses a badge name into its base name and level.
 *
 * Untappd badge names include the level in parentheses,
 * e.g. "Cheers to Independent U.S. Craft Breweries (Level 5)".
 * This extracts the base name and the numeric level.
 *
 * @param string $badge_name The full badge name from the API.
 * @return array {
 *     @type string $base_name The badge name without the level suffix.
 *     @type int    $level     The level number, or 0 if not a leveled badge.
 * }
 */
function parse_badge_name( $badge_name ) {
	if ( preg_match( '/^(.+?)\s*\(Level\s+(\d+)\)\s*$/i', $badge_name, $matches ) ) {
		return array(
			'base_name' => trim( $matches[1] ),
			'level'     => (int) $matches[2],
		);
	}

	return array(
		'base_name' => trim( $badge_name ),
		'level'     => 0,
	);
}

/**
 * Retrieves the term ID for a badge, creating it if it does not exist.
 *
 * Looks up badges by the base name slug so that all levels of the same
 * badge map to a single term. If the badge already exists and the
 * incoming level is higher, the term meta is updated.
 *
 * @param int   $badge_id The Untappd badge ID.
 * @param array $badge    Optional. Badge data from the checkin.
 *
 * @return int|false The badge term ID, or false on failure.
 */
function get_badge_term_id( $badge_id, $badge = null ) {
	if ( empty( $badge_id ) || ! is_array( $badge ) || empty( $badge['badge_name'] ) ) {
		return false;
	}

	$parsed    = parse_badge_name( $badge['badge_name'] );
	$base_slug = sanitize_title( $parsed['base_name'] );

	// Look up by slug derived from the base name.
	$existing = get_term_by( 'slug', $base_slug, BEER_SLURPER_TAX_BADGE );

	if ( $existing ) {
		maybe_update_level( $existing->term_id, $badge, $parsed['level'] );
		return $existing->term_id;
	}

	// Term doesn't exist yet — create it.
	$term_id = add_badge( $badge, $parsed );
	if ( is_wp_error( $term_id ) ) {
		return false;
	}

	return $term_id;
}

/**
 * Adds a new badge term to the taxonomy.
 *
 * @param array $badge  Badge data from the Untappd API.
 * @param array $parsed Pre-parsed badge name with base_name and level keys.
 *
 * @return int|\WP_Error The term ID on success, or WP_Error on failure.
 */
function add_badge( $badge, $parsed = null ) {
	if ( ! is_array( $badge ) || empty( $badge['badge_name'] ) ) {
		return new \WP_Error( 'invalid_badge', __( 'Invalid badge data.', 'beer_slurper' ) );
	}

	if ( null === $parsed ) {
		$parsed = parse_badge_name( $badge['badge_name'] );
	}

	$base_slug = sanitize_title( $parsed['base_name'] );

	$term = wp_insert_term(
		$parsed['base_name'],
		BEER_SLURPER_TAX_BADGE,
		array( 'slug' => $base_slug )
	);

	if ( is_wp_error( $term ) ) {
		if ( $term->get_error_code() === 'term_exists' ) {
			$existing_term = get_term_by( 'slug', $base_slug, BEER_SLURPER_TAX_BADGE );
			if ( $existing_term ) {
				maybe_update_level( $existing_term->term_id, $badge, $parsed['level'] );
				return $existing_term->term_id;
			}
		}
		return $term;
	}

	$term_id = $term['term_id'];

	save_badge_meta( $term_id, $badge, $parsed['level'] );

	return $term_id;
}

/**
 * Saves badge metadata to a term.
 *
 * @param int   $term_id The term ID.
 * @param array $badge   The badge data array.
 * @param int   $level   The badge level.
 * @return void
 */
function save_badge_meta( $term_id, $badge, $level ) {
	update_term_meta( $term_id, 'untappd_id', $badge['badge_id'] );
	update_term_meta( $term_id, 'badge_level', $level );

	if ( isset( $badge['badge_image']['sm'] ) ) {
		update_term_meta( $term_id, 'badge_image_sm', $badge['badge_image']['sm'] );
	}
	if ( isset( $badge['badge_image']['md'] ) ) {
		update_term_meta( $term_id, 'badge_image_md', $badge['badge_image']['md'] );
	}
	if ( isset( $badge['badge_image']['lg'] ) ) {
		update_term_meta( $term_id, 'badge_image_lg', $badge['badge_image']['lg'] );
	}
	if ( ! empty( $badge['badge_description'] ) ) {
		update_term_meta( $term_id, 'badge_description', $badge['badge_description'] );
	}
}

/**
 * Updates badge meta if the incoming level is higher than the stored level.
 *
 * @param int   $term_id The term ID.
 * @param array $badge   The badge data array.
 * @param int   $level   The incoming badge level.
 * @return void
 */
function maybe_update_level( $term_id, $badge, $level ) {
	$stored_level = (int) get_term_meta( $term_id, 'badge_level', true );

	if ( $level > $stored_level ) {
		save_badge_meta( $term_id, $badge, $level );
	} elseif ( empty( get_term_meta( $term_id, 'badge_description', true ) ) && ! empty( $badge['badge_description'] ) ) {
		// Backfill description if we didn't have one before.
		update_term_meta( $term_id, 'badge_description', $badge['badge_description'] );
	}
}

/**
 * Backfills missing badge descriptions from the user/badges API endpoint.
 *
 * Checkin responses contain sparse badge data without descriptions.
 * This fetches the authenticated user's full badge list and updates
 * any badge terms missing a description.
 *
 * @return int Number of badge descriptions updated.
 */
function backfill_missing_descriptions() {
	$terms = get_terms( array(
		'taxonomy'   => BEER_SLURPER_TAX_BADGE,
		'hide_empty' => false,
		'meta_query' => array(
			array(
				'key'     => 'badge_description',
				'compare' => 'NOT EXISTS',
			),
		),
	) );

	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return 0;
	}

	// Build a slug-indexed lookup of terms needing descriptions.
	$needed = array();
	foreach ( $terms as $term ) {
		$needed[ $term->slug ] = $term->term_id;
	}

	$user = \Kraft\Beer_Slurper\Sync_Status\get_configured_user();
	if ( ! $user ) {
		return 0;
	}

	$updated = 0;
	$offset  = 0;
	$limit   = 50;

	// Paginate through the user's badges (max 5 pages to stay within rate limits).
	for ( $page = 0; $page < 5; $page++ ) {
		$args = array( 'offset' => $offset );
		$response = \Kraft\Beer_Slurper\API\get_untappd_data( 'user/badges', $user, $args );

		if ( is_wp_error( $response ) || ! is_array( $response ) ) {
			break;
		}

		// Handle both possible response structures.
		$items = array();
		if ( isset( $response['items'] ) ) {
			$items = $response['items'];
		} elseif ( isset( $response['badges']['items'] ) ) {
			$items = $response['badges']['items'];
		}

		if ( empty( $items ) ) {
			break;
		}

		foreach ( $items as $badge ) {
			if ( empty( $badge['badge_description'] ) || empty( $badge['badge_name'] ) ) {
				continue;
			}

			$parsed = parse_badge_name( $badge['badge_name'] );
			$slug   = sanitize_title( $parsed['base_name'] );

			if ( isset( $needed[ $slug ] ) ) {
				update_term_meta( $needed[ $slug ], 'badge_description', $badge['badge_description'] );
				unset( $needed[ $slug ] );
				$updated++;
			}
		}

		// Stop early if all missing descriptions have been filled.
		if ( empty( $needed ) ) {
			break;
		}

		$offset += $limit;

		// If fewer items returned than the limit, we've reached the end.
		if ( count( $items ) < $limit ) {
			break;
		}
	}

	return $updated;
}
