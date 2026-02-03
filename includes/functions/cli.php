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
	 * Pages through the user's Untappd checkin history (using user/checkins
	 * which includes tagged_friends) and attaches companions to matching
	 * local beer posts. The checkin/view endpoint does NOT return tagged
	 * friends, so this is the only way to backfill.
	 *
	 * By default, schedules page fetches via Action Scheduler to respect
	 * API rate limits. Use --sync to run immediately (may exhaust budget).
	 *
	 * ## OPTIONS
	 *
	 * [--pages=<number>]
	 * : Maximum pages to fetch (25 checkins each). Default: unlimited.
	 *
	 * [--sync]
	 * : Run synchronously instead of scheduling via Action Scheduler.
	 *   Warning: may exhaust API budget quickly.
	 *
	 * [--dry-run]
	 * : Show what would be attached without making changes (implies --sync).
	 *
	 * ## EXAMPLES
	 *
	 *     wp beer-slurper backfill-companions
	 *     wp beer-slurper backfill-companions --pages=10
	 *     wp beer-slurper backfill-companions --sync
	 *     wp beer-slurper backfill-companions --dry-run
	 *
	 * @subcommand backfill-companions
	 */
	public function backfill_companions( $args, $assoc_args ) {
		global $wpdb;

		$user = \Kraft\Beer_Slurper\Sync_Status\get_configured_user();

		if ( ! $user ) {
			\WP_CLI::error( 'No Untappd user configured. Connect via OAuth first.' );
		}

		$max_pages = isset( $assoc_args['pages'] ) ? (int) $assoc_args['pages'] : 0;
		$dry_run   = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$sync      = \WP_CLI\Utils\get_flag_value( $assoc_args, 'sync', false ) || $dry_run;

		// Build a lookup of checkin_id => post_id from existing checkin comments.
		$rows = $wpdb->get_results(
			"SELECT c.comment_post_ID AS post_id, cm.meta_value AS checkin_id
			FROM {$wpdb->comments} c
			INNER JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id
			WHERE c.comment_type = 'beer_checkin'
			AND cm.meta_key = '_beer_slurper_checkin_id'"
		);

		$checkin_to_post = array();
		foreach ( $rows as $row ) {
			$checkin_to_post[ $row->checkin_id ] = (int) $row->post_id;
		}

		if ( empty( $checkin_to_post ) ) {
			\WP_CLI::warning( 'No checkin comments found to backfill.' );
			return;
		}

		\WP_CLI::log( sprintf( 'Found %d local checkins.', count( $checkin_to_post ) ) );

		// Use Action Scheduler for rate-limited execution unless --sync is specified.
		if ( ! $sync ) {
			$session_id = substr( md5( uniqid( 'backfill', true ) ), 0, 8 );
			$remaining  = \Kraft\Beer_Slurper\Queue\get_remaining_budget();
			$params     = \Kraft\Beer_Slurper\Queue\get_page_spread_params();

			\WP_CLI::log( '' );
			\WP_CLI::log( sprintf( 'API budget remaining: %d calls', $remaining ) );
			\WP_CLI::log( sprintf( 'Rate: %d pages/hour (%ds between pages)', $params['per_hour'], $params['interval'] ) );

			if ( $max_pages > 0 ) {
				$hours_needed = ceil( $max_pages / $params['per_hour'] );
				\WP_CLI::log( sprintf( 'Estimated time for %d pages: ~%d hour(s)', $max_pages, $hours_needed ) );
			} else {
				\WP_CLI::log( 'Pages: unlimited (will process until end of history)' );
			}

			\WP_CLI::log( '' );
			\WP_CLI::log( 'Scheduling first page fetch...' );

			\Kraft\Beer_Slurper\Queue\schedule_action(
				'bs_backfill_companions_page',
				array(
					'user'       => $user,
					'page'       => 1,
					'max_pages'  => $max_pages,
					'session_id' => $session_id,
					'max_id'     => null,
				),
				0
			);

			\WP_CLI::success( sprintf(
				'Backfill companions job scheduled (session: %s). Progress will be logged to error_log.',
				$session_id
			) );
			\WP_CLI::log( 'Run `wp action-scheduler run` to process immediately, or wait for WP-Cron.' );
			return;
		}

		// Synchronous mode (original behavior).
		\WP_CLI::log( 'Running in synchronous mode. Fetching from Untappd...' );

		$page       = 0;
		$max_id     = null;
		$matched    = 0;
		$companions = 0;
		$attached   = 0;
		$skipped    = 0;

		while ( true ) {
			$page++;

			if ( $max_pages > 0 && $page > $max_pages ) {
				\WP_CLI::log( "Reached --pages limit ({$max_pages})." );
				break;
			}

			if ( ! \Kraft\Beer_Slurper\Queue\has_budget( 1 ) ) {
				\WP_CLI::warning( 'API budget exhausted. Run again after the rate limit resets.' );
				break;
			}

			$response = \Kraft\Beer_Slurper\API\get_checkins( $user, $max_id, null, '25' );

			if ( is_wp_error( $response ) ) {
				\WP_CLI::warning( 'API error: ' . $response->get_error_message() );
				break;
			}

			if ( ! is_array( $response ) || empty( $response['checkins']['items'] ) ) {
				\WP_CLI::log( 'No more checkins to fetch.' );
				break;
			}

			$items = $response['checkins']['items'];
			$count = count( $items );
			$page_matched = 0;
			$page_companions = 0;

			foreach ( $items as $checkin ) {
				$cid = (string) $checkin['checkin_id'];

				if ( ! isset( $checkin_to_post[ $cid ] ) ) {
					continue;
				}

				$post_id = $checkin_to_post[ $cid ];
				$matched++;
				$page_matched++;

				if ( empty( $checkin['tagged_friends']['items'] ) ) {
					continue;
				}

				$friend_count = count( $checkin['tagged_friends']['items'] );
				$companions  += $friend_count;
				$page_companions += $friend_count;

				if ( $dry_run ) {
					\WP_CLI::log( sprintf(
						'[DRY RUN] Checkin %s (post %d): would attach %d companion(s)',
						$cid,
						$post_id,
						$friend_count
					) );
				} else {
					// Track individual attachment success/failure.
					foreach ( $checkin['tagged_friends']['items'] as $item ) {
						if ( empty( $item['user']['uid'] ) ) {
							$skipped++;
							continue;
						}

						$term_id = \Kraft\Beer_Slurper\Companion\get_companion_term_id(
							$item['user']['uid'],
							$item['user']
						);

						if ( $term_id ) {
							wp_set_object_terms( $post_id, (int) $term_id, BEER_SLURPER_TAX_COMPANION, true );
							$attached++;
						} else {
							$skipped++;
							\WP_CLI::debug( sprintf(
								'Failed to create companion for uid %s (user_name: %s)',
								$item['user']['uid'] ?? 'unknown',
								$item['user']['user_name'] ?? 'missing'
							) );
						}
					}
				}
			}

			\WP_CLI::log( sprintf(
				'Page %d: fetched %d, matched %d local, %d companions on page',
				$page,
				$count,
				$page_matched,
				$page_companions
			) );

			// Update pagination cursor.
			$max_id = $response['pagination']['max_id'] ?? null;

			if ( $count < 25 || empty( $max_id ) ) {
				\WP_CLI::log( 'Reached end of checkin history.' );
				break;
			}
		}

		\WP_CLI::log( '' );
		if ( $dry_run ) {
			\WP_CLI::success( sprintf(
				'Dry run complete. Would have attached %d companion(s) across %d page(s).',
				$companions,
				$page
			) );
		} else {
			\WP_CLI::success( sprintf(
				'Backfill complete. Matched %d checkins, found %d companions, attached %d, skipped %d across %d page(s).',
				$matched,
				$companions,
				$attached,
				$skipped,
				$page
			) );
		}
	}

	/**
	 * Fetch all outstanding checkins and queue them for processing.
	 *
	 * Pages through the Untappd checkin history, spending API budget on
	 * list fetches (1 call per 25 checkins) and queuing every discovered
	 * checkin via Action Scheduler. Processing then happens automatically
	 * over subsequent hours as budget allows.
	 *
	 * By default, schedules page fetches via Action Scheduler to respect
	 * API rate limits. Use --sync to run immediately (may exhaust budget).
	 *
	 * This command also attaches companions to any already-imported checkins
	 * found in each page, so you don't need to run backfill-companions
	 * separately for missing companions on existing checkins.
	 *
	 * ## OPTIONS
	 *
	 * [--pages=<number>]
	 * : Maximum pages to fetch (25 checkins each). Default: all remaining.
	 *
	 * [--sync]
	 * : Run synchronously instead of scheduling via Action Scheduler.
	 *   Warning: may exhaust API budget quickly.
	 *
	 * ## EXAMPLES
	 *
	 *     wp beer-slurper prime-queue
	 *     wp beer-slurper prime-queue --pages=10
	 *     wp beer-slurper prime-queue --sync
	 *
	 * @subcommand prime-queue
	 */
	public function prime_queue( $args, $assoc_args ) {
		$user = \Kraft\Beer_Slurper\Sync_Status\get_configured_user();

		if ( ! $user ) {
			\WP_CLI::error( 'No Untappd user configured. Connect via OAuth first.' );
		}

		$max_pages = isset( $assoc_args['pages'] ) ? (int) $assoc_args['pages'] : 0;
		$sync      = \WP_CLI\Utils\get_flag_value( $assoc_args, 'sync', false );
		$is_backfilling = \Kraft\Beer_Slurper\Sync_Status\is_backfilling( $user );

		if ( ! $is_backfilling ) {
			\WP_CLI::log( 'Not currently backfilling — fetching new checkins only.' );
			$result = \Kraft\Beer_Slurper\Walker\import_new( $user );
			\WP_CLI::log( is_wp_error( $result ) ? $result->get_error_message() : $result );
			\WP_CLI::success( 'Done.' );
			return;
		}

		// Use Action Scheduler for rate-limited execution unless --sync is specified.
		if ( ! $sync ) {
			$session_id = substr( md5( uniqid( 'prime', true ) ), 0, 8 );
			$remaining  = \Kraft\Beer_Slurper\Queue\get_remaining_budget();
			$params     = \Kraft\Beer_Slurper\Queue\get_page_spread_params();

			\WP_CLI::log( "Scheduling historical checkin fetch for {$user}..." );
			\WP_CLI::log( '' );
			\WP_CLI::log( sprintf( 'API budget remaining: %d calls', $remaining ) );
			\WP_CLI::log( sprintf( 'Rate: %d pages/hour (%ds between pages)', $params['per_hour'], $params['interval'] ) );

			if ( $max_pages > 0 ) {
				$hours_needed = ceil( $max_pages / $params['per_hour'] );
				\WP_CLI::log( sprintf( 'Estimated time for %d pages: ~%d hour(s)', $max_pages, $hours_needed ) );
			} else {
				\WP_CLI::log( 'Pages: unlimited (will process until end of history)' );
			}

			\WP_CLI::log( '' );
			\WP_CLI::log( 'Note: This will also attach companions to any already-imported checkins found.' );
			\WP_CLI::log( '' );
			\WP_CLI::log( 'Scheduling first page fetch...' );

			\Kraft\Beer_Slurper\Queue\schedule_action(
				'bs_prime_queue_page',
				array(
					'user'       => $user,
					'page'       => 1,
					'max_pages'  => $max_pages,
					'session_id' => $session_id,
				),
				0
			);

			\WP_CLI::success( sprintf(
				'Prime queue job scheduled (session: %s). Progress will be logged to error_log.',
				$session_id
			) );
			\WP_CLI::log( 'Run `wp action-scheduler run` to process immediately, or wait for WP-Cron.' );
			return;
		}

		// Synchronous mode (original behavior).
		\WP_CLI::log( "Fetching historical checkins for {$user} (synchronous mode)..." );

		$total_fetched = 0;
		$total_queued  = 0;
		$companions_attached = 0;
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
			$new_max_id = $checkins['pagination']['max_id'] ?? null;
			if ( $new_max_id ) {
				update_option( 'beer_slurper_' . $user . '_max', $new_max_id, false );
			}

			if ( ! get_option( 'beer_slurper_' . $user . '_since' ) ) {
				$since_url = wp_parse_args( parse_url( $checkins['pagination']['since_url'] ?? '', PHP_URL_QUERY ) );
				$since_id = isset( $since_url['min_id'] ) ? intval( $since_url['min_id'] ) : 0;
				if ( $since_id ) {
					update_option( 'beer_slurper_' . $user . '_since', $since_id, false );
				}
			}

			// Queue for processing.
			$queued = \Kraft\Beer_Slurper\Queue\queue_checkin_batch( $items, 'import_old' );
			$total_queued += $queued;

			// Also attach companions to any already-imported checkins.
			$attached = \Kraft\Beer_Slurper\Queue\attach_companions_to_existing( $items );
			$companions_attached += $attached;

			\WP_CLI::log( sprintf(
				'Page %d: fetched %d checkins, queued %d, attached companions to %d existing (%d/%d/%d total)',
				$page,
				$count,
				$queued,
				$attached,
				$total_fetched,
				$total_queued,
				$companions_attached
			) );

			// End of history?
			if ( $count < 25 || empty( $new_max_id ) ) {
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
			'Fetched %d checkins across %d pages, %d queued for processing, companions attached to %d existing.',
			$total_fetched,
			$page,
			$total_queued,
			$companions_attached
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

		$table       = $wpdb->prefix . 'actionscheduler_actions';
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

		$params = \Kraft\Beer_Slurper\Queue\get_spread_params();
		$now    = time();

		if ( $params['current_slots'] > 0 ) {
			$window_end = get_transient( 'beer_slurper_api_window_end' );
			\WP_CLI::log( sprintf(
				'Active window: %d slots, resets %s.',
				$params['current_slots'],
				date_i18n( 'Y-m-d H:i:s', $window_end )
			) );
		}

		$progress = \WP_CLI\Utils\make_progress_bar( 'Spreading actions', $total );

		foreach ( $action_ids as $index => $action_id ) {
			$delay       = \Kraft\Beer_Slurper\Queue\get_slot_delay( $index, $params );
			$target_time = $now + $delay;
			$gmt_date    = gmdate( 'Y-m-d H:i:s', $target_time );
			$local_date  = get_date_from_gmt( $gmt_date );

			// Build a new serialized schedule so the AS admin UI reflects
			// the updated time (it reads from this column, not the date columns).
			$schedule = new \ActionScheduler_SimpleSchedule(
				new \DateTime( '@' . $target_time )
			);

			$wpdb->update(
				$table,
				array(
					'scheduled_date_gmt'   => $gmt_date,
					'scheduled_date_local' => $local_date,
					'schedule'             => serialize( $schedule ),
				),
				array( 'action_id' => $action_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);

			$progress->tick();
		}

		$progress->finish();

		$full_window_actions = max( 0, $total - $params['current_slots'] );
		$full_hours          = (int) ceil( $full_window_actions / $params['per_hour'] );
		$completion          = $params['full_start'] + ( $full_hours * 3600 );

		\WP_CLI::log( '' );
		\WP_CLI::log( sprintf( 'Total actions:       %d', $total ) );
		if ( $params['current_slots'] > 0 ) {
			\WP_CLI::log( sprintf( 'Current window:      %d checkins', $params['current_slots'] ) );
		}
		\WP_CLI::log( sprintf( 'Full windows start:  %s', date_i18n( 'Y-m-d H:i:s', $params['full_start'] ) ) );
		\WP_CLI::log( sprintf( 'Per hour:            %d (every %ds)', $params['per_hour'], $params['interval'] ) );
		\WP_CLI::log( sprintf( 'Full hours needed:   %d', $full_hours ) );
		\WP_CLI::log( sprintf( 'Est. completion:     %s', date_i18n( 'Y-m-d H:i:s', $completion ) ) );
		\WP_CLI::success( 'Queue spread complete.' );
	}

	/**
	 * Re-queue failed checkin actions for another attempt.
	 *
	 * Finds all bs_process_checkin actions in "failed" status, extracts
	 * the checkin IDs, schedules new properly-spread actions for them,
	 * and deletes the old failed entries.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Show what would be re-queued without actually doing it.
	 *
	 * ## EXAMPLES
	 *
	 *     wp beer-slurper retry-failed
	 *     wp beer-slurper retry-failed --dry-run
	 *
	 * @subcommand retry-failed
	 */
	public function retry_failed( $args, $assoc_args ) {
		global $wpdb;

		$dry_run     = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$table       = $wpdb->prefix . 'actionscheduler_actions';
		$group_table = $wpdb->prefix . 'actionscheduler_groups';

		// Resolve the group ID for our AS group.
		$group_id = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT group_id FROM {$group_table} WHERE slug = %s",
			\Kraft\Beer_Slurper\Queue\AS_GROUP
		) );

		if ( ! $group_id ) {
			\WP_CLI::error( 'Action Scheduler group not found.' );
		}

		// Fetch all failed checkin actions.
		$failed = $wpdb->get_results( $wpdb->prepare(
			"SELECT action_id, args FROM {$table}
			WHERE hook = %s AND status = %s AND group_id = %d
			ORDER BY scheduled_date_gmt ASC",
			'bs_process_checkin',
			'failed',
			$group_id
		) );

		if ( empty( $failed ) ) {
			\WP_CLI::success( 'No failed bs_process_checkin actions found.' );
			return;
		}

		$total = count( $failed );
		\WP_CLI::log( sprintf( 'Found %d failed action(s).', $total ) );

		if ( $dry_run ) {
			\WP_CLI::log( '' );
			\WP_CLI::log( 'Checkin IDs that would be re-queued:' );
			foreach ( $failed as $row ) {
				$action_args = json_decode( $row->args, true );
				$checkin_id  = $action_args['checkin_id'] ?? 'unknown';
				\WP_CLI::log( sprintf( '  - %s (action %d)', $checkin_id, $row->action_id ) );
			}
			\WP_CLI::log( '' );
			\WP_CLI::success( 'Dry run complete. No changes made.' );
			return;
		}

		$params   = \Kraft\Beer_Slurper\Queue\get_spread_params();
		$existing = \Kraft\Beer_Slurper\Queue\get_pending_checkin_count();
		$slot     = $existing;
		$queued   = 0;

		$progress = \WP_CLI\Utils\make_progress_bar( 'Re-queuing failed actions', $total );

		foreach ( $failed as $row ) {
			$action_args = json_decode( $row->args, true );
			$checkin_id  = $action_args['checkin_id'] ?? null;
			$source      = $action_args['source'] ?? 'retry';

			if ( ! $checkin_id ) {
				$progress->tick();
				continue;
			}

			// Skip if this checkin was already imported (maybe succeeded on a later retry).
			if ( \Kraft\Beer_Slurper\Post\find_existing_checkin( $checkin_id ) ) {
				// Delete the stale failed action.
				$wpdb->delete( $table, array( 'action_id' => $row->action_id ), array( '%d' ) );
				$progress->tick();
				continue;
			}

			$delay = \Kraft\Beer_Slurper\Queue\get_slot_delay( $slot, $params );

			\Kraft\Beer_Slurper\Queue\schedule_action(
				'bs_process_checkin',
				array(
					'checkin_id' => (int) $checkin_id,
					'source'     => $source,
				),
				$delay
			);

			// Delete the old failed action.
			$wpdb->delete( $table, array( 'action_id' => $row->action_id ), array( '%d' ) );

			$slot++;
			$queued++;
			$progress->tick();
		}

		$progress->finish();

		\WP_CLI::success( sprintf(
			'Re-queued %d checkin(s) with proper spreading. %d skipped (already imported or invalid).',
			$queued,
			$total - $queued
		) );
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

	/**
	 * Refresh taxonomy term metadata from the Untappd API.
	 *
	 * Queues Action Scheduler jobs to re-fetch metadata for breweries,
	 * venues, or all taxonomies. Respects API rate limits.
	 *
	 * ## OPTIONS
	 *
	 * <type>
	 * : What to refresh. One of: breweries, venues, all
	 *
	 * [--dry-run]
	 * : Show what would be queued without scheduling.
	 *
	 * ## EXAMPLES
	 *
	 *     wp beer-slurper refresh breweries
	 *     wp beer-slurper refresh venues
	 *     wp beer-slurper refresh all
	 *     wp beer-slurper refresh breweries --dry-run
	 *
	 * @subcommand refresh
	 */
	public function refresh( $args, $assoc_args ) {
		$type    = $args[0] ?? '';
		$dry_run = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

		if ( ! in_array( $type, array( 'breweries', 'venues', 'all' ), true ) ) {
			\WP_CLI::error( 'Invalid type. Use: breweries, venues, or all' );
		}

		$types_to_refresh = ( 'all' === $type )
			? array( 'breweries', 'venues' )
			: array( $type );

		$total_queued = 0;

		foreach ( $types_to_refresh as $refresh_type ) {
			$queued = $this->queue_refresh( $refresh_type, $dry_run );
			$total_queued += $queued;
		}

		if ( $dry_run ) {
			\WP_CLI::success( sprintf( 'Dry run complete. Would queue %d refresh job(s).', $total_queued ) );
		} else {
			\WP_CLI::success( sprintf( 'Queued %d refresh job(s) via Action Scheduler.', $total_queued ) );
		}
	}

	/**
	 * Queues refresh jobs for a specific taxonomy type.
	 *
	 * @param string $type    The type: 'breweries' or 'venues'.
	 * @param bool   $dry_run Whether to skip actual scheduling.
	 *
	 * @return int Number of jobs queued.
	 */
	private function queue_refresh( $type, $dry_run ) {
		$taxonomy = '';
		$hook     = '';
		$id_key   = '';

		switch ( $type ) {
			case 'breweries':
				$taxonomy = BEER_SLURPER_TAX_BREWERY;
				$hook     = 'bs_refresh_brewery';
				$id_key   = 'brewery_id';
				break;
			case 'venues':
				$taxonomy = BEER_SLURPER_TAX_VENUE;
				$hook     = 'bs_refresh_venue';
				$id_key   = 'venue_id';
				break;
			default:
				return 0;
		}

		$terms = get_terms( array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'number'     => 0,
		) );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			\WP_CLI::log( sprintf( 'No %s terms found.', $type ) );
			return 0;
		}

		\WP_CLI::log( sprintf( 'Found %d %s to refresh.', count( $terms ), $type ) );

		$params  = \Kraft\Beer_Slurper\Queue\get_spread_params();
		$queued  = 0;

		foreach ( $terms as $term ) {
			$untappd_id = get_term_meta( $term->term_id, 'untappd_id', true );

			if ( empty( $untappd_id ) ) {
				continue;
			}

			if ( $dry_run ) {
				\WP_CLI::log( sprintf(
					'[DRY RUN] Would queue %s refresh for %s (term %d, untappd %s)',
					$type,
					$term->name,
					$term->term_id,
					$untappd_id
				) );
			} else {
				$delay = \Kraft\Beer_Slurper\Queue\get_slot_delay( $queued, $params );

				\Kraft\Beer_Slurper\Queue\schedule_action(
					$hook,
					array(
						$id_key   => (int) $untappd_id,
						'term_id' => $term->term_id,
					),
					$delay
				);
			}

			$queued++;
		}

		return $queued;
	}
}

\WP_CLI::add_command( 'beer-slurper', __NAMESPACE__ . '\Beer_Slurper_Command' );
