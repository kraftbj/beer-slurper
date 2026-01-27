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
			BEER_SLURPER_TAX_STYLE     => 'style',
			BEER_SLURPER_TAX_BREWERY   => 'brewery',
			BEER_SLURPER_TAX_VENUE     => 'venue',
			BEER_SLURPER_TAX_BADGE     => 'badge',
			BEER_SLURPER_TAX_COMPANION => 'companion',
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

		$companion_count = wp_count_terms( array( 'taxonomy' => BEER_SLURPER_TAX_COMPANION, 'hide_empty' => false ) );
		\WP_CLI::log( sprintf( 'Companions:    %s', is_wp_error( $companion_count ) ? '0' : number_format_i18n( $companion_count ) ) );

		global $wpdb;
		$checkin_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type = 'beer_checkin'"
		);
		\WP_CLI::log( sprintf( 'Checkins:      %s', number_format_i18n( $checkin_count ) ) );
		\WP_CLI::log( '' );
	}

	/**
	 * Backfill companion terms from existing checkins.
	 *
	 * Schedules Action Scheduler jobs to re-fetch checkin data from the
	 * Untappd API and populate companion terms from tagged_friends.
	 * Respects API rate limits via the remaining budget.
	 *
	 * ## EXAMPLES
	 *
	 *     wp beer-slurper backfill-companions
	 *
	 * @subcommand backfill-companions
	 */
	public function backfill_companions( $args, $assoc_args ) {
		global $wpdb;

		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			\WP_CLI::error( 'Action Scheduler is required. Make sure it is installed and active.' );
		}

		// Get all checkin IDs + post IDs that haven't been processed yet.
		$checkins = $wpdb->get_results(
			"SELECT c.comment_post_ID, cm.meta_value AS checkin_id
			FROM {$wpdb->comments} c
			INNER JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id
			WHERE c.comment_type = 'beer_checkin'
			AND cm.meta_key = '_beer_slurper_checkin_id'
			ORDER BY c.comment_date DESC"
		);

		if ( empty( $checkins ) ) {
			\WP_CLI::warning( 'No checkin comments found.' );
			return;
		}

		$budget = \Kraft\Beer_Slurper\Queue\get_remaining_budget();
		$queued = 0;
		$delay  = 0;

		foreach ( $checkins as $row ) {
			\Kraft\Beer_Slurper\Queue\schedule_action(
				'bs_backfill_companion',
				array(
					'checkin_id' => (int) $row->checkin_id,
					'post_id'    => (int) $row->comment_post_ID,
				),
				$delay
			);

			$queued++;
			$delay += 3; // Stagger by 3 seconds.

			// After exhausting the budget, jump ahead by an hour.
			if ( $queued % $budget === 0 ) {
				$delay += HOUR_IN_SECONDS;
			}
		}

		\WP_CLI::success( sprintf(
			'Scheduled %d companion backfill jobs via Action Scheduler (staggered over %s).',
			$queued,
			human_time_diff( time(), time() + $delay )
		) );
	}

	/**
	 * Fetch all outstanding checkins and queue them for processing.
	 *
	 * Rapidly pages through the Untappd checkin history, spending API
	 * budget on list fetches (1 call per 25 checkins) and queuing every
	 * discovered checkin via Action Scheduler. Processing then happens
	 * automatically over subsequent hours as budget allows.
	 *
	 * ## OPTIONS
	 *
	 * [--pages=<number>]
	 * : Maximum pages to fetch (25 checkins each). Default: all remaining.
	 *
	 * ## EXAMPLES
	 *
	 *     wp beer-slurper prime-queue
	 *     wp beer-slurper prime-queue --pages=10
	 *
	 * @subcommand prime-queue
	 */
	public function prime_queue( $args, $assoc_args ) {
		$user = \Kraft\Beer_Slurper\Sync_Status\get_configured_user();

		if ( ! $user ) {
			\WP_CLI::error( 'No Untappd user configured. Connect via OAuth first.' );
		}

		$max_pages = isset( $assoc_args['pages'] ) ? (int) $assoc_args['pages'] : 0;
		$is_backfilling = \Kraft\Beer_Slurper\Sync_Status\is_backfilling( $user );

		if ( ! $is_backfilling ) {
			\WP_CLI::log( 'Not currently backfilling — fetching new checkins only.' );
			$result = \Kraft\Beer_Slurper\Walker\import_new( $user );
			\WP_CLI::log( is_wp_error( $result ) ? $result->get_error_message() : $result );
			\WP_CLI::success( 'Done.' );
			return;
		}

		\WP_CLI::log( "Fetching historical checkins for {$user}..." );

		$total_fetched = 0;
		$total_queued  = 0;
		$page          = 0;

		while ( true ) {
			$page++;

			if ( $max_pages > 0 && $page > $max_pages ) {
				\WP_CLI::log( "Reached --pages limit ({$max_pages})." );
				break;
			}

			// Each page costs 1 API call to fetch the list.
			if ( ! \Kraft\Beer_Slurper\Queue\has_budget( 1 ) ) {
				\WP_CLI::warning( 'API budget exhausted. Run again after the rate limit resets.' );
				break;
			}

			$max_id = get_option( 'beer_slurper_' . $user . '_max' );
			$checkins = \Kraft\Beer_Slurper\API\get_checkins( $user, $max_id, null, '25' );

			if ( is_wp_error( $checkins ) ) {
				\WP_CLI::warning( 'API error: ' . $checkins->get_error_message() );
				break;
			}

			if ( ! is_array( $checkins ) || ! isset( $checkins['checkins']['items'] ) || empty( $checkins['checkins']['items'] ) ) {
				\WP_CLI::log( 'No more checkins to fetch.' );
				delete_option( 'beer_slurper_' . $user . '_import' );
				break;
			}

			$items = $checkins['checkins']['items'];
			$count = count( $items );
			$total_fetched += $count;

			// Update pagination cursor.
			$max_id = $checkins['pagination']['max_id'];
			update_option( 'beer_slurper_' . $user . '_max', $max_id, false );

			if ( ! get_option( 'beer_slurper_' . $user . '_since' ) ) {
				$since_url = wp_parse_args( parse_url( $checkins['pagination']['since_url'], PHP_URL_QUERY ) );
				$since_id = intval( $since_url['min_id'] );
				update_option( 'beer_slurper_' . $user . '_since', $since_id, false );
			}

			// Queue for processing.
			$queued = \Kraft\Beer_Slurper\Queue\queue_checkin_batch( $items, 'import_old' );
			$total_queued += $queued;

			\WP_CLI::log( sprintf(
				'Page %d: fetched %d checkins, queued %d (%d fetched / %d queued total)',
				$page,
				$count,
				$queued,
				$total_fetched,
				$total_queued
			) );

			// End of history?
			if ( $count < 25 ) {
				\WP_CLI::log( 'Reached end of checkin history.' );
				delete_option( 'beer_slurper_' . $user . '_import' );
				break;
			}
		}

		// Also fetch new checkins if we have a since_id.
		if ( get_option( 'beer_slurper_' . $user . '_since' ) && \Kraft\Beer_Slurper\Queue\has_budget( 1 ) ) {
			$result = \Kraft\Beer_Slurper\Walker\import_new( $user );
			if ( ! is_wp_error( $result ) ) {
				\WP_CLI::log( $result );
			}
		}

		\WP_CLI::success( sprintf(
			'Fetched %d checkins across %d pages, %d queued for processing.',
			$total_fetched,
			$page,
			$total_queued
		) );
	}

	/**
	 * Spread pending checkin actions evenly across hourly windows.
	 *
	 * Reschedules all pending bs_process_checkin actions so they are
	 * staggered to respect the API budget. Does not cancel or remove
	 * any actions — only adjusts their scheduled times.
	 *
	 * ## EXAMPLES
	 *
	 *     wp beer-slurper spread-queue
	 *
	 * @subcommand spread-queue
	 */
	public function spread_queue( $args, $assoc_args ) {
		global $wpdb;

		$table = $wpdb->prefix . 'actionscheduler_actions';
		$group_table = $wpdb->prefix . 'actionscheduler_groups';

		// Resolve the group ID for our AS group.
		$group_id = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT group_id FROM {$group_table} WHERE slug = %s",
			\Kraft\Beer_Slurper\Queue\AS_GROUP
		) );

		if ( ! $group_id ) {
			\WP_CLI::error( 'Action Scheduler group not found.' );
		}

		// Fetch all pending checkin action IDs, ordered by scheduled date.
		$action_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT action_id FROM {$table}
			WHERE hook = %s AND status = %s AND group_id = %d
			ORDER BY scheduled_date_gmt ASC",
			'bs_process_checkin',
			'pending',
			$group_id
		) );

		$total = count( $action_ids );

		if ( 0 === $total ) {
			\WP_CLI::warning( 'No pending bs_process_checkin actions found.' );
			return;
		}

		// Calculate spread parameters.
		$per_hour   = (int) floor( \Kraft\Beer_Slurper\Queue\API_BUDGET_PER_HOUR / 5 );
		$interval   = (int) floor( 3600 / $per_hour );
		$hours      = (int) ceil( $total / $per_hour );

		$now = time();
		$progress = \WP_CLI\Utils\make_progress_bar( 'Spreading actions', $total );

		foreach ( $action_ids as $index => $action_id ) {
			$hour_offset   = (int) floor( $index / $per_hour );
			$slot_in_hour  = $index % $per_hour;
			$target_time   = $now + ( $hour_offset * 3600 ) + ( $slot_in_hour * $interval );

			$gmt_date   = gmdate( 'Y-m-d H:i:s', $target_time );
			$local_date = get_date_from_gmt( $gmt_date );

			$wpdb->update(
				$table,
				array(
					'scheduled_date_gmt'   => $gmt_date,
					'scheduled_date_local' => $local_date,
				),
				array( 'action_id' => $action_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			$progress->tick();
		}

		$progress->finish();

		$completion = $now + ( $hours * 3600 );

		\WP_CLI::log( '' );
		\WP_CLI::log( sprintf( 'Total actions:   %d', $total ) );
		\WP_CLI::log( sprintf( 'Per hour:        %d (every %ds)', $per_hour, $interval ) );
		\WP_CLI::log( sprintf( 'Hours needed:    %d', $hours ) );
		\WP_CLI::log( sprintf( 'Est. completion: %s', date_i18n( 'Y-m-d H:i:s', $completion ) ) );
		\WP_CLI::success( 'Queue spread complete.' );
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
