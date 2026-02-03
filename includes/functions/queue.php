<?php
namespace Kraft\Beer_Slurper\Queue;

/**
 * Action Scheduler Queue Functions
 *
 * All recurring tasks (hourly import, daily maintenance) and
 * rate-limit-aware API call queuing use Action Scheduler.
 *
 * @package Kraft\Beer_Slurper
 */

/**
 * Maximum API calls to allow per hour.
 *
 * Untappd allows 100/hour; we reserve 10 for manual/overhead calls.
 */
const API_BUDGET_PER_HOUR = 90;

/**
 * The Action Scheduler group for all Beer Slurper actions.
 */
const AS_GROUP = 'beer-slurper';

/**
 * Max actions AS claims per batch (default 25).
 */
const AS_BATCH_SIZE = 5;

/**
 * Max seconds per AS queue run (default 30).
 */
const AS_TIME_LIMIT = 15;

/**
 * Max concurrent bs_process_checkin handlers.
 */
const MAX_CONCURRENT_CHECKINS = 2;

/**
 * TTL for a single checkin lock slot (seconds).
 */
const CHECKIN_LOCK_TTL = 60;

/**
 * Max pending bs_process_checkin actions allowed in the queue.
 */
const MAX_PENDING_CHECKINS = 50;

// Throttle Action Scheduler to avoid exhausting PHP-FPM workers.
add_filter( 'action_scheduler_queue_runner_batch_size', function () {
	return AS_BATCH_SIZE;
} );
add_filter( 'action_scheduler_queue_runner_time_limit', function () {
	return AS_TIME_LIMIT;
} );
add_filter( 'action_scheduler_queue_runner_concurrent_batches', function () {
	return 1;
} );

/**
 * Returns the number of API calls remaining in the current hour.
 *
 * @return int Remaining API budget.
 */
function get_remaining_budget() {
	// If the window has elapsed, treat budget as fully available even if
	// the api_calls transient hasn't expired yet (object-cache edge case).
	$window_end = get_transient( 'beer_slurper_api_window_end' );
	if ( false === $window_end || (int) $window_end <= time() ) {
		return API_BUDGET_PER_HOUR;
	}

	$used = get_transient( 'beer_slurper_api_calls' );
	if ( false === $used ) {
		return API_BUDGET_PER_HOUR;
	}
	return max( 0, API_BUDGET_PER_HOUR - (int) $used );
}

/**
 * Checks whether enough API budget remains for a given number of calls.
 *
 * @param int $needed Number of API calls required. Default 1.
 *
 * @return bool True if budget is sufficient.
 */
function has_budget( $needed = 1 ) {
	return get_remaining_budget() >= $needed;
}

/**
 * Records that API calls have been consumed against the hourly budget.
 *
 * Increments the transient counter so that subsequent budget checks
 * reflect the calls that have been scheduled or consumed.
 *
 * @param int $count Number of calls to record. Default 1.
 *
 * @return void
 */
function consume_budget( $count = 1 ) {
	$now        = time();
	$window_end = get_transient( 'beer_slurper_api_window_end' );

	// Start a fresh window if none exists or the previous one elapsed.
	if ( false === $window_end || (int) $window_end <= $now ) {
		$window_end = $now + HOUR_IN_SECONDS;
		set_transient( 'beer_slurper_api_window_end', $window_end, HOUR_IN_SECONDS );
		$used = 0;
	} else {
		$used = (int) get_transient( 'beer_slurper_api_calls' );
	}

	// Use the remaining window time as TTL so the counter expires with
	// the window — not HOUR_IN_SECONDS from now (which would slide).
	$ttl = max( 1, (int) $window_end - $now );
	set_transient( 'beer_slurper_api_calls', $used + $count, $ttl );
}

/**
 * Schedules a single async action if not already pending.
 *
 * Uses the pending-only status check to avoid blocking re-queues
 * from a currently running action (which has the same hook + args).
 *
 * @param string $hook The action hook name.
 * @param array  $args Arguments to pass to the action.
 * @param int    $delay Optional. Seconds to delay execution. Default 0.
 *
 * @return int|null The action ID, or null if already pending.
 */
function schedule_action( $hook, $args = array(), $delay = 0 ) {
	if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
		return null;
	}

	// Only skip if a genuinely pending action exists. as_has_scheduled_action()
	// also matches running actions, which blocks re-queues from within
	// a handler that needs to defer itself.
	$existing = as_get_scheduled_actions( array(
		'hook'     => $hook,
		'args'     => $args,
		'status'   => \ActionScheduler_Store::STATUS_PENDING,
		'group'    => AS_GROUP,
		'per_page' => 1,
	), 'ids' );

	if ( ! empty( $existing ) ) {
		return null;
	}

	return as_schedule_single_action(
		time() + $delay,
		$hook,
		$args,
		AS_GROUP
	);
}

/**
 * Schedules a recurring action if not already scheduled.
 *
 * @param string $hook     The action hook name.
 * @param int    $interval Interval in seconds between runs.
 * @param array  $args     Arguments to pass to the action.
 *
 * @return int|null The action ID, or null if already scheduled.
 */
function schedule_recurring( $hook, $interval, $args = array() ) {
	if ( ! function_exists( 'as_has_scheduled_action' ) ) {
		return null;
	}

	if ( as_has_scheduled_action( $hook, $args, AS_GROUP ) ) {
		return null;
	}

	return as_schedule_recurring_action(
		time(),
		$interval,
		$hook,
		$args,
		AS_GROUP
	);
}

/**
 * Cancels all pending actions for a given hook.
 *
 * @param string $hook The action hook name.
 *
 * @return void
 */
function cancel_all( $hook ) {
	if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
		return;
	}

	as_unschedule_all_actions( $hook, array(), AS_GROUP );
}

/**
 * Returns the number of pending bs_process_checkin actions.
 *
 * @return int
 */
function get_pending_checkin_count() {
	if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
		return 0;
	}

	$actions = as_get_scheduled_actions( array(
		'hook'     => 'bs_process_checkin',
		'status'   => \ActionScheduler_Store::STATUS_PENDING,
		'group'    => AS_GROUP,
		'per_page' => -1,
	), 'ids' );

	return count( $actions );
}

/**
 * Calculates the schedule parameters for spreading checkin actions.
 *
 * Returns the full-rate interval, per-hour count, the number of slots
 * available in the current budget window, the interval for those slots,
 * and the timestamp where full-rate windows begin.
 *
 * @return array {
 *     @type int $per_hour          Checkins per full hourly window.
 *     @type int $interval          Seconds between checkins in a full window.
 *     @type int $current_slots     Slots available in the active window.
 *     @type int $current_interval  Seconds between current-window slots.
 *     @type int $full_start        Timestamp where full-rate windows begin.
 * }
 */
function get_spread_params() {
	$cost_per = 5;
	$per_hour = (int) floor( API_BUDGET_PER_HOUR / $cost_per );
	$interval = (int) floor( 3600 / $per_hour );
	$now      = time();

	$window_end = get_transient( 'beer_slurper_api_window_end' );
	$remaining  = get_remaining_budget();

	$current_slots    = 0;
	$current_interval = $interval;

	if ( $window_end && (int) $window_end > $now && $remaining >= $cost_per ) {
		$secs_left = (int) ( (int) $window_end - $now );

		// Only schedule current-window slots if there is enough time
		// to space them at least $interval seconds apart.
		$current_slots = min(
			(int) floor( $remaining / $cost_per ),
			(int) floor( $secs_left / $interval )
		);

		if ( $current_slots > 1 ) {
			$current_interval = (int) floor( $secs_left / $current_slots );
		} elseif ( 1 === $current_slots ) {
			$current_interval = 0; // Single slot runs immediately.
		}
	}

	$full_start = ( $window_end && (int) $window_end > $now )
		? (int) $window_end
		: $now;

	return array(
		'per_hour'         => $per_hour,
		'interval'         => $interval,
		'current_slots'    => $current_slots,
		'current_interval' => $current_interval,
		'full_start'       => $full_start,
	);
}

/**
 * Calculates the delay (from now) for a given queue slot number.
 *
 * Slot 0 is the first action to schedule. Already-pending actions
 * should be counted so new actions get slot numbers after them.
 *
 * @param int   $slot   The zero-based slot number.
 * @param array $params Spread parameters from get_spread_params().
 *
 * @return int Delay in seconds from now.
 */
function get_slot_delay( $slot, $params ) {
	$now = time();

	if ( $slot < $params['current_slots'] ) {
		return $slot * $params['current_interval'];
	}

	$offset       = $slot - $params['current_slots'];
	$hour_offset  = (int) floor( $offset / $params['per_hour'] );
	$slot_in_hour = $offset % $params['per_hour'];

	return ( $params['full_start'] - $now )
		+ ( $hour_offset * 3600 )
		+ ( $slot_in_hour * $params['interval'] );
}

/**
 * Queues a batch of checkins for processing.
 *
 * Each checkin is scheduled as its own action so that if one fails,
 * the rest still proceed. Actions are spread at 18 per hour (matching
 * the API budget at 5 calls per checkin). Already-pending actions are
 * counted so that repeated calls produce a contiguous, non-overlapping
 * schedule.
 *
 * @param array  $checkins Array of checkin data arrays.
 * @param string $source   Source context ('import_old' or 'import_new').
 *
 * @return int Number of actions queued.
 */
function queue_checkin_batch( $checkins, $source = 'import' ) {
	$existing = get_pending_checkin_count();

	// Respect the pending-action cap.
	$room = MAX_PENDING_CHECKINS - $existing;
	if ( $room <= 0 ) {
		return 0;
	}

	$params = get_spread_params();
	$slot   = $existing; // Start after already-pending actions.
	$queued = 0;

	foreach ( $checkins as $checkin ) {
		if ( $queued >= $room ) {
			break;
		}

		if ( ! isset( $checkin['checkin_id'] ) ) {
			continue;
		}

		// Skip checkins that have already been imported.
		if ( \Kraft\Beer_Slurper\Post\find_existing_checkin( $checkin['checkin_id'] ) ) {
			continue;
		}

		$delay = get_slot_delay( $slot, $params );

		// Store toasts in a transient since checkin/view doesn't return them.
		// This is the only time we have this data (from user/checkins list response).
		if ( ! empty( $checkin['toasts']['items'] ) ) {
			set_transient(
				'bs_toasts_' . $checkin['checkin_id'],
				$checkin['toasts']['items'],
				WEEK_IN_SECONDS
			);
		}

		// Only store the checkin ID — the full payload is too large for
		// Action Scheduler's args column. process_checkin() fetches the
		// data from the API when it runs.
		schedule_action(
			'bs_process_checkin',
			array(
				'checkin_id' => (int) $checkin['checkin_id'],
				'source'     => $source,
			),
			$delay
		);

		$slot++;
		$queued++;
	}

	return $queued;
}

/**
 * Attempts to acquire one of the concurrency lock slots.
 *
 * Uses numbered transients (bs_checkin_lock_0 … _N-1) as a simple
 * semaphore. Returns the slot index on success, or false if all slots
 * are occupied.
 *
 * @return int|false Slot index, or false if lock is full.
 */
function acquire_checkin_lock() {
	for ( $i = 0; $i < MAX_CONCURRENT_CHECKINS; $i++ ) {
		$key = 'bs_checkin_lock_' . $i;
		if ( false === get_transient( $key ) ) {
			set_transient( $key, time(), CHECKIN_LOCK_TTL );
			return $i;
		}
	}
	return false;
}

/**
 * Releases a previously acquired concurrency lock slot.
 *
 * @param int $slot The slot index returned by acquire_checkin_lock().
 *
 * @return void
 */
function release_checkin_lock( $slot ) {
	delete_transient( 'bs_checkin_lock_' . $slot );
}

/**
 * Processes a single queued checkin.
 *
 * Accepts either a checkin ID (current format) or a full checkin array
 * (legacy actions queued before the args-size fix). Fetches the checkin
 * from the API when only the ID is provided.
 *
 * @param int|array $checkin_or_id Checkin ID or legacy checkin data array.
 * @param string    $source        Source context.
 *
 * @return void
 */
function process_checkin( $checkin_or_id, $source = 'import' ) {
	$slot = acquire_checkin_lock();
	if ( false === $slot ) {
		// All slots occupied — re-queue with a short delay.
		$checkin_id = is_array( $checkin_or_id ) ? $checkin_or_id['checkin_id'] : (int) $checkin_or_id;
		schedule_action(
			'bs_process_checkin',
			array(
				'checkin_id' => (int) $checkin_id,
				'source'     => $source,
			),
			30
		);
		return;
	}

	try {
		// Handle legacy actions that stored the full checkin payload.
		if ( is_array( $checkin_or_id ) ) {
			$checkin    = $checkin_or_id;
			$checkin_id = $checkin['checkin_id'];

			if ( ! has_budget( 4 ) ) {
				schedule_action(
					'bs_process_checkin',
					array(
						'checkin_id' => (int) $checkin_id,
						'source'     => $source,
					),
					HOUR_IN_SECONDS
				);
				return;
			}

			$result = \Kraft\Beer_Slurper\Post\insert_beer( $checkin );

			if ( is_wp_error( $result ) && 'already_done' !== $result->get_error_code() ) {
				error_log( 'Beer Slurper Queue: Failed to process checkin ' . $checkin_id . ' - ' . $result->get_error_message() );
			} else {
				// Success — check if we can accelerate the next action.
				maybe_accelerate_next_checkin();
			}
			return;
		}

		// Current format: just the checkin ID.
		$checkin_id = (int) $checkin_or_id;

		if ( ! has_budget( 5 ) ) {
			schedule_action(
				'bs_process_checkin',
				array(
					'checkin_id' => $checkin_id,
					'source'     => $source,
				),
				HOUR_IN_SECONDS
			);
			return;
		}

		$response = \Kraft\Beer_Slurper\API\get_untappd_data( 'checkin/view', $checkin_id );

		if ( is_wp_error( $response ) || ! is_array( $response ) || empty( $response['checkin'] ) ) {
			$msg = is_wp_error( $response ) ? $response->get_error_message() : 'Empty response';
			error_log( 'Beer Slurper Queue: Failed to fetch checkin ' . $checkin_id . ' - ' . $msg );
			return;
		}

		$result = \Kraft\Beer_Slurper\Post\insert_beer( $response['checkin'] );

		if ( is_wp_error( $result ) && 'already_done' !== $result->get_error_code() ) {
			error_log( 'Beer Slurper Queue: Failed to process checkin ' . $checkin_id . ' - ' . $result->get_error_message() );
		} else {
			// Attach toasts from the transient we saved during queueing.
			// checkin/view doesn't return toasts, so we stash it earlier.
			$toasts = get_transient( 'bs_toasts_' . $checkin_id );
			if ( ! empty( $toasts ) && is_int( $result ) ) {
				$checkin_with_toasts = array(
					'toasts' => array( 'items' => $toasts ),
				);
				\Kraft\Beer_Slurper\Toast\attach_toasts( $checkin_with_toasts, $result );
				delete_transient( 'bs_toasts_' . $checkin_id );
			}

			// Success — check if we can accelerate the next action.
			maybe_accelerate_next_checkin();
		}
	} finally {
		release_checkin_lock( $slot );
	}
}
add_action( 'bs_process_checkin', __NAMESPACE__ . '\process_checkin', 10, 2 );

/**
 * Performs the hourly import via Action Scheduler.
 *
 * @param string $user The Untappd username.
 * @return void
 */
function process_hourly_import( $user ) {
	if ( empty( $user ) ) {
		return;
	}

	\bs_import( $user );
}
add_action( 'bs_hourly_import', __NAMESPACE__ . '\process_hourly_import' );

/**
 * Performs daily maintenance via Action Scheduler.
 *
 * Instead of running all maintenance tasks synchronously (which can
 * burst through the API budget), this schedules each task as its own
 * action, staggered and budget-aware.
 *
 * @return void
 */
function process_daily_maintenance() {
	$delay = 0;

	$tasks = array(
		'bs_maintenance_stats',
		'bs_maintenance_brewery_backfill',
		'bs_maintenance_venue_backfill',
		'bs_maintenance_badge_backfill',
	);

	foreach ( $tasks as $hook ) {
		schedule_action( $hook, array(), $delay );
		$delay += 60; // Stagger by 1 minute to let earlier tasks claim budget.
	}
}
add_action( 'bs_daily_maintenance', __NAMESPACE__ . '\process_daily_maintenance' );

/**
 * Maintenance action: refresh user stats (1 API call).
 *
 * @return void
 */
function maintenance_stats() {
	if ( ! has_budget( 1 ) ) {
		schedule_action( 'bs_maintenance_stats', array(), HOUR_IN_SECONDS );
		return;
	}
	\Kraft\Beer_Slurper\Stats\refresh_user_stats();
}
add_action( 'bs_maintenance_stats', __NAMESPACE__ . '\maintenance_stats' );

/**
 * Maintenance action: backfill missing brewery metadata.
 *
 * Fetches up to 5 breweries, each requiring 1 API call.
 * Stops early if budget runs low and re-queues for the next window.
 *
 * @return void
 */
function maintenance_brewery_backfill() {
	if ( ! has_budget( 1 ) ) {
		schedule_action( 'bs_maintenance_brewery_backfill', array(), HOUR_IN_SECONDS );
		return;
	}
	\Kraft\Beer_Slurper\Brewery\backfill_missing_meta();
}
add_action( 'bs_maintenance_brewery_backfill', __NAMESPACE__ . '\maintenance_brewery_backfill' );

/**
 * Maintenance action: backfill missing venue metadata.
 *
 * @return void
 */
function maintenance_venue_backfill() {
	if ( ! has_budget( 1 ) ) {
		schedule_action( 'bs_maintenance_venue_backfill', array(), HOUR_IN_SECONDS );
		return;
	}
	\Kraft\Beer_Slurper\Venue\backfill_missing_meta();
}
add_action( 'bs_maintenance_venue_backfill', __NAMESPACE__ . '\maintenance_venue_backfill' );

/**
 * Maintenance action: backfill missing badge descriptions.
 *
 * @return void
 */
function maintenance_badge_backfill() {
	if ( ! has_budget( 1 ) ) {
		schedule_action( 'bs_maintenance_badge_backfill', array(), HOUR_IN_SECONDS );
		return;
	}
	\Kraft\Beer_Slurper\Badge\backfill_missing_descriptions();
}
add_action( 'bs_maintenance_badge_backfill', __NAMESPACE__ . '\maintenance_badge_backfill' );

/**
 * Processes a single brewery refresh job.
 *
 * @param int $brewery_id The Untappd brewery ID.
 * @param int $term_id    The WordPress term ID.
 *
 * @return void
 */
function process_refresh_brewery( $brewery_id, $term_id ) {
	if ( ! has_budget( 1 ) ) {
		schedule_action(
			'bs_refresh_brewery',
			array( 'brewery_id' => $brewery_id, 'term_id' => $term_id ),
			HOUR_IN_SECONDS
		);
		return;
	}

	$brewery = \Kraft\Beer_Slurper\API\get_brewery_info( $brewery_id );

	if ( is_wp_error( $brewery ) || ! is_array( $brewery ) ) {
		return;
	}

	\Kraft\Beer_Slurper\Brewery\save_brewery_meta( $term_id, $brewery );
}
add_action( 'bs_refresh_brewery', __NAMESPACE__ . '\process_refresh_brewery', 10, 2 );

/**
 * Processes a single venue refresh job.
 *
 * @param int $venue_id The Untappd venue ID.
 * @param int $term_id  The WordPress term ID.
 *
 * @return void
 */
function process_refresh_venue( $venue_id, $term_id ) {
	if ( ! has_budget( 1 ) ) {
		schedule_action(
			'bs_refresh_venue',
			array( 'venue_id' => $venue_id, 'term_id' => $term_id ),
			HOUR_IN_SECONDS
		);
		return;
	}

	$venue = \Kraft\Beer_Slurper\API\get_venue_info( $venue_id );

	if ( is_wp_error( $venue ) || ! is_array( $venue ) ) {
		return;
	}

	\Kraft\Beer_Slurper\Venue\save_venue_meta( $term_id, $venue_id, $venue );
}
add_action( 'bs_refresh_venue', __NAMESPACE__ . '\process_refresh_venue', 10, 2 );

/**
 * Initializes Action Scheduler recurring tasks.
 *
 * Schedules the hourly import (with user arg) and daily maintenance.
 *
 * @param string $user The Untappd username.
 *
 * @return void
 */
function init_scheduled_actions( $user ) {
	schedule_recurring( 'bs_hourly_import', HOUR_IN_SECONDS, array( $user ) );
	schedule_recurring( 'bs_daily_maintenance', DAY_IN_SECONDS );
}

/**
 * Returns the next scheduled timestamp for a given hook.
 *
 * @param string $hook The action hook name.
 * @param array  $args Optional. Action arguments for lookup.
 *
 * @return int|null Unix timestamp, or null if not scheduled.
 */
function get_next_scheduled( $hook, $args = array() ) {
	if ( ! function_exists( 'as_next_scheduled_action' ) ) {
		return null;
	}

	$timestamp = as_next_scheduled_action( $hook, $args, AS_GROUP );

	// as_next_scheduled_action returns false if nothing scheduled, or the timestamp.
	return $timestamp ? (int) $timestamp : null;
}

/**
 * Returns the next pending bs_process_checkin action.
 *
 * @return object|null Action row with action_id and scheduled_date_gmt, or null.
 */
function get_next_pending_checkin_action() {
	global $wpdb;

	$table       = $wpdb->prefix . 'actionscheduler_actions';
	$group_table = $wpdb->prefix . 'actionscheduler_groups';

	$group_id = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT group_id FROM {$group_table} WHERE slug = %s",
		AS_GROUP
	) );

	if ( ! $group_id ) {
		return null;
	}

	return $wpdb->get_row( $wpdb->prepare(
		"SELECT action_id, scheduled_date_gmt FROM {$table}
		WHERE hook = %s AND status = %s AND group_id = %d
		ORDER BY scheduled_date_gmt ASC
		LIMIT 1",
		'bs_process_checkin',
		'pending',
		$group_id
	) );
}

/**
 * Reschedules a single action to a new timestamp.
 *
 * Updates both the scheduled_date columns and the serialized schedule
 * object so the AS admin UI reflects the change.
 *
 * @param int $action_id The action ID to reschedule.
 * @param int $timestamp Unix timestamp for the new scheduled time.
 *
 * @return bool True on success, false on failure.
 */
function reschedule_action_by_id( $action_id, $timestamp ) {
	global $wpdb;

	$table      = $wpdb->prefix . 'actionscheduler_actions';
	$gmt_date   = gmdate( 'Y-m-d H:i:s', $timestamp );
	$local_date = get_date_from_gmt( $gmt_date );

	$schedule = new \ActionScheduler_SimpleSchedule(
		new \DateTime( '@' . $timestamp )
	);

	$updated = $wpdb->update(
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

	return false !== $updated;
}

/**
 * Accelerates or adjusts the next pending checkin action based on budget.
 *
 * If budget remains, pulls the next action forward to run soon.
 * If no budget and the next action is scheduled more than an hour out,
 * moves it to run when the budget window resets.
 *
 * @return void
 */
function maybe_accelerate_next_checkin() {
	$next = get_next_pending_checkin_action();

	if ( ! $next ) {
		return;
	}

	$now            = time();
	$scheduled_time = strtotime( $next->scheduled_date_gmt . ' UTC' );

	if ( has_budget( 5 ) ) {
		// Budget available — if next action is more than 10 seconds out, pull it forward.
		if ( $scheduled_time > $now + 10 ) {
			reschedule_action_by_id( $next->action_id, $now + 5 );
		}
	} else {
		// No budget — ensure next action runs when the window resets.
		$window_end = get_transient( 'beer_slurper_api_window_end' );
		$reset_time = $window_end ? (int) $window_end : $now + HOUR_IN_SECONDS;

		// If scheduled more than 60 seconds after reset, pull it to reset time.
		if ( $scheduled_time > $reset_time + 60 ) {
			reschedule_action_by_id( $next->action_id, $reset_time );
		}
	}
}

/**
 * Calculates spreading parameters for page-fetch operations.
 *
 * Unlike get_spread_params() which calculates for checkin processing (5 API calls each),
 * this calculates for page fetches which cost 1 API call each.
 *
 * @return array {
 *     @type int $per_hour          Pages per full hourly window.
 *     @type int $interval          Seconds between pages in a full window.
 *     @type int $current_slots     Slots available in the active window.
 *     @type int $current_interval  Seconds between current-window slots.
 *     @type int $full_start        Timestamp where full-rate windows begin.
 * }
 */
function get_page_spread_params() {
	$cost_per = 1; // 1 API call per page fetch.
	$per_hour = (int) floor( API_BUDGET_PER_HOUR / $cost_per );
	$interval = (int) floor( 3600 / $per_hour ); // Seconds per page (e.g., 40 seconds with 90/hour budget).
	$now      = time();

	$window_end = get_transient( 'beer_slurper_api_window_end' );
	$remaining  = get_remaining_budget();

	$current_slots    = 0;
	$current_interval = $interval;

	if ( $window_end && (int) $window_end > $now && $remaining >= $cost_per ) {
		$secs_left = (int) ( (int) $window_end - $now );

		$current_slots = min(
			(int) floor( $remaining / $cost_per ),
			(int) floor( $secs_left / $interval )
		);

		if ( $current_slots > 1 ) {
			$current_interval = (int) floor( $secs_left / $current_slots );
		} elseif ( 1 === $current_slots ) {
			$current_interval = 0;
		}
	}

	$full_start = ( $window_end && (int) $window_end > $now )
		? (int) $window_end
		: $now;

	return array(
		'per_hour'         => $per_hour,
		'interval'         => $interval,
		'current_slots'    => $current_slots,
		'current_interval' => $current_interval,
		'full_start'       => $full_start,
	);
}

/**
 * Schedules the next page fetch action with rate limiting.
 *
 * If budget is available, schedules soon. Otherwise, schedules at the
 * start of the next budget window. If already scheduled more than an
 * hour away, reschedules to an hour from now.
 *
 * @param string $hook The action hook name.
 * @param array  $args The action arguments.
 *
 * @return int|null The action ID, or null if already pending.
 */
function schedule_next_page_fetch( $hook, $args ) {
	if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
		return null;
	}

	$now        = time();
	$window_end = get_transient( 'beer_slurper_api_window_end' );
	$remaining  = get_remaining_budget();

	if ( $remaining >= 1 ) {
		// Budget available — schedule in 5 seconds.
		$delay = 5;
	} else {
		// No budget — schedule at window reset.
		$delay = $window_end ? max( 1, (int) $window_end - $now ) : HOUR_IN_SECONDS;
	}

	// Check if an action is already pending.
	$existing = as_get_scheduled_actions( array(
		'hook'     => $hook,
		'args'     => $args,
		'status'   => \ActionScheduler_Store::STATUS_PENDING,
		'group'    => AS_GROUP,
		'per_page' => 1,
	), 'ids' );

	if ( ! empty( $existing ) ) {
		// Existing action found — check if it needs rescheduling.
		global $wpdb;
		$table = $wpdb->prefix . 'actionscheduler_actions';
		$row   = $wpdb->get_row( $wpdb->prepare(
			"SELECT action_id, scheduled_date_gmt FROM {$table} WHERE action_id = %d",
			$existing[0]
		) );

		if ( $row ) {
			$scheduled_time = strtotime( $row->scheduled_date_gmt . ' UTC' );
			$one_hour_out   = $now + HOUR_IN_SECONDS;

			// If scheduled more than an hour away and we have budget, pull it closer.
			if ( $scheduled_time > $one_hour_out && $remaining >= 1 ) {
				reschedule_action_by_id( $row->action_id, $now + 5 );
			} elseif ( $scheduled_time > $one_hour_out && $remaining < 1 ) {
				// No budget but scheduled too far — reschedule to window reset.
				$reset_time = $window_end ? (int) $window_end : $one_hour_out;
				reschedule_action_by_id( $row->action_id, $reset_time );
			}
		}
		return null;
	}

	return schedule_action( $hook, $args, $delay );
}

/**
 * Processes a single page of the prime-queue operation.
 *
 * Fetches one page of checkins from the API, queues them for processing,
 * and schedules the next page if needed.
 *
 * @param string $user       The Untappd username.
 * @param int    $page       Current page number (1-indexed).
 * @param int    $max_pages  Maximum pages to fetch (0 = unlimited).
 * @param string $session_id Unique session ID for this prime-queue run.
 *
 * @return void
 */
function process_prime_queue_page( $user, $page, $max_pages, $session_id ) {
	$option_prefix = 'beer_slurper_' . $user;
	$state_key     = 'beer_slurper_prime_state_' . $session_id;

	// Initialize or retrieve session state (use transient for better performance).
	$state = get_transient( $state_key );
	if ( false === $state ) {
		$state = array(
			'total_fetched' => 0,
			'total_queued'  => 0,
			'started'       => time(),
		);
	}

	// Check budget.
	if ( ! has_budget( 1 ) ) {
		// Reschedule for next window.
		schedule_next_page_fetch( 'bs_prime_queue_page', array(
			'user'       => $user,
			'page'       => $page,
			'max_pages'  => $max_pages,
			'session_id' => $session_id,
		) );
		return;
	}

	// Check page limit.
	if ( $max_pages > 0 && $page > $max_pages ) {
		finalize_prime_queue_session( $user, $state, $state_key, 'Reached page limit.' );
		return;
	}

	$max_id   = get_option( $option_prefix . '_max' );
	$checkins = \Kraft\Beer_Slurper\API\get_checkins( $user, $max_id, null, '25' );

	if ( is_wp_error( $checkins ) ) {
		error_log( 'Beer Slurper: prime-queue page ' . $page . ' API error: ' . $checkins->get_error_message() );
		finalize_prime_queue_session( $user, $state, $state_key, 'API error: ' . $checkins->get_error_message() );
		return;
	}

	if ( ! is_array( $checkins ) || ! isset( $checkins['checkins']['items'] ) || empty( $checkins['checkins']['items'] ) ) {
		delete_option( $option_prefix . '_import' );
		finalize_prime_queue_session( $user, $state, $state_key, 'Reached end of checkin history.' );
		return;
	}

	$items = $checkins['checkins']['items'];
	$count = count( $items );

	// Update pagination cursor.
	$new_max_id = $checkins['pagination']['max_id'] ?? null;
	if ( $new_max_id ) {
		update_option( $option_prefix . '_max', $new_max_id, false );
	}

	if ( ! get_option( $option_prefix . '_since' ) ) {
		$since_url = wp_parse_args( parse_url( $checkins['pagination']['since_url'] ?? '', PHP_URL_QUERY ) );
		$since_id  = isset( $since_url['min_id'] ) ? intval( $since_url['min_id'] ) : 0;
		if ( $since_id ) {
			update_option( $option_prefix . '_since', $since_id, false );
		}
	}

	// Queue for processing.
	$queued = queue_checkin_batch( $items, 'import_old' );

	// Update state.
	$state['total_fetched'] += $count;
	$state['total_queued']  += $queued;
	set_transient( $state_key, $state, DAY_IN_SECONDS );

	// Check if we've reached the end.
	if ( $count < 25 || empty( $new_max_id ) ) {
		delete_option( $option_prefix . '_import' );
		finalize_prime_queue_session( $user, $state, $state_key, 'Reached end of checkin history.' );
		return;
	}

	// Schedule next page.
	schedule_next_page_fetch( 'bs_prime_queue_page', array(
		'user'       => $user,
		'page'       => $page + 1,
		'max_pages'  => $max_pages,
		'session_id' => $session_id,
	) );
}
add_action( 'bs_prime_queue_page', __NAMESPACE__ . '\process_prime_queue_page', 10, 4 );

/**
 * Processes a single toast backfill job.
 *
 * Fetches the full checkin data from the API and attaches toasts
 * to the corresponding beer post.
 *
 * @param int    $checkin_id The Untappd checkin ID.
 * @param string $session_id Session ID for logging.
 *
 * @return void
 */
function process_backfill_toast( $checkin_id, $session_id = '' ) {
	if ( ! has_budget( 1 ) ) {
		schedule_action(
			'bs_backfill_toast',
			array(
				'checkin_id' => (int) $checkin_id,
				'session_id' => $session_id,
			),
			HOUR_IN_SECONDS
		);
		return;
	}

	// Find the beer post for this checkin.
	$post_id = get_post_id_for_checkin( $checkin_id );

	if ( ! $post_id ) {
		error_log( sprintf(
			'Beer Slurper: backfill-toast skipped checkin %d - no matching post found.',
			$checkin_id
		) );
		return;
	}

	// Fetch full checkin data from API.
	$response = \Kraft\Beer_Slurper\API\get_untappd_data( 'checkin/view', $checkin_id );

	if ( is_wp_error( $response ) || ! is_array( $response ) || empty( $response['checkin'] ) ) {
		$msg = is_wp_error( $response ) ? $response->get_error_message() : 'Empty response';
		error_log( sprintf(
			'Beer Slurper: backfill-toast failed to fetch checkin %d - %s',
			$checkin_id,
			$msg
		) );
		return;
	}

	$checkin = $response['checkin'];

	// Attach toasts.
	$attached = \Kraft\Beer_Slurper\Toast\attach_toasts( $checkin, $post_id );

	if ( $attached > 0 ) {
		error_log( sprintf(
			'Beer Slurper: backfill-toast attached %d toasts to post %d (checkin %d)',
			$attached,
			$post_id,
			$checkin_id
		) );
	}
}
add_action( 'bs_backfill_toast', __NAMESPACE__ . '\process_backfill_toast', 10, 2 );

/**
 * Gets the beer post ID for a given checkin ID.
 *
 * @param int $checkin_id The Untappd checkin ID.
 *
 * @return int|false The post ID, or false if not found.
 */
function get_post_id_for_checkin( $checkin_id ) {
	global $wpdb;

	$post_id = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT c.comment_post_ID FROM {$wpdb->comments} c
			INNER JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id
			WHERE c.comment_type = 'beer_checkin'
			AND cm.meta_key = '_beer_slurper_checkin_id'
			AND cm.meta_value = %s
			LIMIT 1",
			$checkin_id
		)
	);

	return $post_id ? (int) $post_id : false;
}

/**
 * Finalizes a prime-queue session and logs the result.
 *
 * @param string $user      The Untappd username.
 * @param array  $state     Session state with totals.
 * @param string $state_key Option key for the session state.
 * @param string $reason    Reason for completion.
 *
 * @return void
 */
function finalize_prime_queue_session( $user, $state, $state_key, $reason ) {
	$elapsed = time() - ( $state['started'] ?? time() );

	error_log( sprintf(
		'Beer Slurper: prime-queue complete for %s. %s Fetched %d checkins, queued %d in %d seconds.',
		$user,
		$reason,
		$state['total_fetched'],
		$state['total_queued'],
		$elapsed
	) );

	delete_transient( $state_key );
}

/**
 * Cleans up all Action Scheduler actions on deactivation or reset.
 *
 * @return void
 */
function cleanup() {
	if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
		return;
	}

	cancel_all( 'bs_process_checkin' );
	cancel_all( 'bs_backfill_toast' );
	cancel_all( 'bs_prime_queue_page' );
	cancel_all( 'bs_hourly_import' );
	cancel_all( 'bs_daily_maintenance' );
	cancel_all( 'bs_maintenance_stats' );
	cancel_all( 'bs_maintenance_brewery_backfill' );
	cancel_all( 'bs_maintenance_venue_backfill' );
	cancel_all( 'bs_maintenance_badge_backfill' );
	cancel_all( 'bs_refresh_brewery' );
	cancel_all( 'bs_refresh_venue' );

	// Legacy hook names from older versions.
	cancel_all( 'bs_as_daily_maintenance' );

	// Session state transients (beer_slurper_prime_state_*) are not cleaned up here because:
	// 1. They have DAY_IN_SECONDS TTL and will self-expire
	// 2. With object caching, transients may not be in the database
	// 3. Direct SQL queries would miss object-cached transients
}
