<?php
namespace Kraft\Beer_Slurper\CLI;

/**
 * WP-CLI Commands for Beer Slurper
 *
 * @package Kraft\Beer_Slurper
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Manage the Beer Slurper plugin.
 */
class Beer_Slurper_Command extends \WP_CLI_Command {

	/**
	 * Delete all plugin data and start fresh.
	 *
	 * Removes all beer posts (and their attached media), checkin comments,
	 * taxonomy terms (style, brewery, venue, badge), options, transients,
	 * and scheduled actions.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp beer-slurper reset
	 *     wp beer-slurper reset --yes
	 *
	 * @subcommand reset
	 */
	public function reset( $args, $assoc_args ) {
		\WP_CLI::confirm( 'This will permanently delete ALL Beer Slurper data (posts, media, terms, comments, options, scheduled tasks). Continue?', $assoc_args );

		global $wpdb;

		// 1. Delete beer posts and their attached media.
		$post_ids = get_posts( array(
			'post_type'      => BEER_SLURPER_CPT,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'post_status'    => 'any',
		) );

		$post_count = count( $post_ids );
		if ( $post_count > 0 ) {
			$progress = \WP_CLI\Utils\make_progress_bar( 'Deleting beer posts', $post_count );
			foreach ( $post_ids as $post_id ) {
				// Delete attached media first.
				$attachments = get_posts( array(
					'post_type'      => 'attachment',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'post_parent'    => $post_id,
				) );
				foreach ( $attachments as $attachment_id ) {
					wp_delete_attachment( $attachment_id, true );
				}
				wp_delete_post( $post_id, true );
				$progress->tick();
			}
			$progress->finish();
			\WP_CLI::log( "Deleted {$post_count} beer post(s) and their media." );
		} else {
			\WP_CLI::log( 'No beer posts found.' );
		}

		// 2. Delete checkin comments.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$checkin_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type = 'beer_checkin'"
		);
		if ( $checkin_count > 0 ) {
			$wpdb->query(
				"DELETE cm FROM {$wpdb->commentmeta} cm
				INNER JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id
				WHERE c.comment_type = 'beer_checkin'"
			);
			$wpdb->query(
				"DELETE FROM {$wpdb->comments} WHERE comment_type = 'beer_checkin'"
			);
			\WP_CLI::log( "Deleted {$checkin_count} checkin comment(s)." );
		} else {
			\WP_CLI::log( 'No checkin comments found.' );
		}

		// 3. Delete taxonomy terms.
		$taxonomies = array(
			BEER_SLURPER_TAX_STYLE   => 'style',
			BEER_SLURPER_TAX_BREWERY => 'brewery',
			BEER_SLURPER_TAX_VENUE   => 'venue',
			BEER_SLURPER_TAX_BADGE   => 'badge',
		);

		foreach ( $taxonomies as $taxonomy => $label ) {
			$terms = get_terms( array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'fields'     => 'ids',
			) );

			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				\WP_CLI::log( "No {$label} terms found." );
				continue;
			}

			$term_count = count( $terms );
			foreach ( $terms as $term_id ) {
				wp_delete_term( $term_id, $taxonomy );
			}
			\WP_CLI::log( "Deleted {$term_count} {$label} term(s)." );
		}

		// 4. Delete known options via delete_option() so the object cache is invalidated.
		$known_options = array(
			'beer-slurper-access-token',
			'beer-slurper-user',
			'beer-slurper-gallery',
		);

		// Find all beer_slurper_* options (dynamic per-user options like beer_slurper_kraft_import).
		$dynamic_options = $wpdb->get_col(
			"SELECT option_name FROM {$wpdb->options}
			WHERE option_name LIKE 'beer_slurper_%'"
		);
		$all_options = array_merge( $known_options, $dynamic_options );

		$deleted_options = 0;
		foreach ( $all_options as $option_name ) {
			if ( delete_option( $option_name ) ) {
				$deleted_options++;
			}
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
		\WP_CLI::log( "Deleted {$deleted_options} option(s) (API key/secret preserved)." );

		// 5. Delete transients.
		$transient_names = $wpdb->get_col(
			"SELECT option_name FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_beer_slurper_%'"
		);
		foreach ( $transient_names as $transient_option ) {
			// Strip the '_transient_' prefix to get the transient name for delete_transient().
			$transient_key = substr( $transient_option, strlen( '_transient_' ) );
			delete_transient( $transient_key );
		}

		// 6. Clear scheduled actions.
		\Kraft\Beer_Slurper\Queue\cleanup();
		\WP_CLI::log( 'Cleared scheduled actions.' );

		// Clear legacy WP-Cron hooks from older versions (unschedule_hook clears all args).
		wp_unschedule_hook( 'bs_hourly_importer' );
		wp_unschedule_hook( 'bs_daily_maintenance' );

		\WP_CLI::success( 'All Beer Slurper data has been deleted.' );
	}

	/**
	 * Show plugin status and statistics.
	 *
	 * ## EXAMPLES
	 *
	 *     wp beer-slurper status
	 *
	 * @subcommand status
	 */
	public function status( $args, $assoc_args ) {
		$user        = \Kraft\Beer_Slurper\Sync_Status\get_configured_user();
		$connected   = \Kraft\Beer_Slurper\OAuth\is_connected();
		$last_sync   = \Kraft\Beer_Slurper\Sync_Status\get_last_sync_time();
		$last_error  = \Kraft\Beer_Slurper\Sync_Status\get_last_sync_error();
		$is_backfill = $user ? \Kraft\Beer_Slurper\Sync_Status\is_backfilling( $user ) : false;

		\WP_CLI::log( '' );
		\WP_CLI::log( 'Beer Slurper Status' );
		\WP_CLI::log( str_repeat( '─', 40 ) );
		\WP_CLI::log( sprintf( 'OAuth:         %s', $connected ? 'Connected' : 'Not connected' ) );
		\WP_CLI::log( sprintf( 'User:          %s', $user ? $user : 'Not configured' ) );
		\WP_CLI::log( sprintf( 'Sync state:    %s', $is_backfill ? 'Backfilling' : 'Caught up' ) );

		if ( $last_sync ) {
			\WP_CLI::log( sprintf( 'Last sync:     %s', date_i18n( 'Y-m-d H:i:s', $last_sync ) ) );
		} else {
			\WP_CLI::log( 'Last sync:     Never' );
		}

		if ( $last_error ) {
			\WP_CLI::warning( sprintf( 'Last error:    %s: %s', $last_error['code'], $last_error['message'] ) );
		}

		$next_hourly = $user ? \Kraft\Beer_Slurper\Queue\get_next_scheduled( 'bs_hourly_import', array( $user ) ) : null;
		$next_daily  = \Kraft\Beer_Slurper\Queue\get_next_scheduled( 'bs_daily_maintenance' );

		\WP_CLI::log( sprintf( 'Hourly sync:   %s', $next_hourly ? date_i18n( 'Y-m-d H:i:s', $next_hourly ) : 'Not scheduled' ) );
		\WP_CLI::log( sprintf( 'Daily maint:   %s', $next_daily ? date_i18n( 'Y-m-d H:i:s', $next_daily ) : 'Not scheduled' ) );

		\WP_CLI::log( '' );
		\WP_CLI::log( 'Statistics' );
		\WP_CLI::log( str_repeat( '─', 40 ) );
		\WP_CLI::log( sprintf( 'Beers:         %s', number_format_i18n( \Kraft\Beer_Slurper\Sync_Status\get_total_beers() ) ) );
		\WP_CLI::log( sprintf( 'Pictures:      %s', number_format_i18n( \Kraft\Beer_Slurper\Sync_Status\get_total_pictures() ) ) );
		\WP_CLI::log( sprintf( 'Breweries:     %s', number_format_i18n( \Kraft\Beer_Slurper\Sync_Status\get_total_breweries() ) ) );

		$venue_count = wp_count_terms( array( 'taxonomy' => BEER_SLURPER_TAX_VENUE, 'hide_empty' => false ) );
		$badge_count = wp_count_terms( array( 'taxonomy' => BEER_SLURPER_TAX_BADGE, 'hide_empty' => false ) );
		\WP_CLI::log( sprintf( 'Venues:        %s', is_wp_error( $venue_count ) ? '0' : number_format_i18n( $venue_count ) ) );
		\WP_CLI::log( sprintf( 'Badges:        %s', is_wp_error( $badge_count ) ? '0' : number_format_i18n( $badge_count ) ) );

		global $wpdb;
		$checkin_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type = 'beer_checkin'"
		);
		\WP_CLI::log( sprintf( 'Checkins:      %s', number_format_i18n( $checkin_count ) ) );
		\WP_CLI::log( '' );
	}

	/**
	 * Trigger a sync immediately.
	 *
	 * ## EXAMPLES
	 *
	 *     wp beer-slurper sync
	 *
	 * @subcommand sync
	 */
	public function sync( $args, $assoc_args ) {
		$user = \Kraft\Beer_Slurper\Sync_Status\get_configured_user();

		if ( ! $user ) {
			\WP_CLI::error( 'No Untappd user configured. Connect via OAuth first.' );
		}

		\WP_CLI::log( "Running sync for {$user}..." );
		$result = \bs_import( $user );
		\WP_CLI::log( $result );

		$last_error = \Kraft\Beer_Slurper\Sync_Status\get_last_sync_error();
		if ( $last_error ) {
			\WP_CLI::warning( $last_error['code'] . ': ' . $last_error['message'] );
		} else {
			\WP_CLI::success( 'Sync complete.' );
		}
	}
}

\WP_CLI::add_command( 'beer-slurper', __NAMESPACE__ . '\Beer_Slurper_Command' );
