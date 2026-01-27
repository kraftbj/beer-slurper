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
 * Returns the number of API calls remaining in the current hour.
 *
 * @return int Remaining API budget.
 */
function get_remaining_budget() {
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
	$used = get_transient( 'beer_slurper_api_calls' );
	if ( false === $used ) {
		$used = 0;
	}
	set_transient( 'beer_slurper_api_calls', (int) $used + $count, HOUR_IN_SECONDS );
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
 * Queues a batch of checkins for processing.
 *
 * Each checkin is scheduled as its own action so that if one fails,
 * the rest still proceed. Actions within the current budget are staggered
 * by 2 seconds. Overflow items are scheduled after the rate limit resets
 * (1 hour), ensuring we use as much budget as possible without exceeding it.
 *
 * @param array  $checkins Array of checkin data arrays.
 * @param string $source   Source context ('import_old' or 'import_new').
 *
 * @return int Number of actions queued.
 */
function queue_checkin_batch( $checkins, $source = 'import' ) {
	// Each checkin costs up to ~4 API calls:
	// 1 beer/info + 1 brewery/info (if new) + 1 parent brewery + 1 collab.
	// Most repeat checkins cost 1 (beer info only, brewery already exists).
	$cost_per = 4;

	$budget = get_remaining_budget();
	$queued = 0;
	$delay  = 0;

	// Reserve a small buffer so other work (daily maintenance, etc.) can proceed.
	$usable = max( 0, $budget - 2 );

	foreach ( $checkins as $checkin ) {
		if ( ! isset( $checkin['checkin_id'] ) ) {
			continue;
		}

		// Skip checkins that have already been imported.
		if ( \Kraft\Beer_Slurper\Post\find_existing_checkin( $checkin['checkin_id'] ) ) {
			continue;
		}

		if ( $usable >= $cost_per ) {
			// Schedule within current budget window.
			$usable -= $cost_per;
		} else {
			// Budget exhausted — jump to the next hourly window.
			// Only jump once; subsequent items stagger from there.
			if ( $delay < HOUR_IN_SECONDS ) {
				$delay = HOUR_IN_SECONDS;
			}
		}

		schedule_action(
			'bs_process_checkin',
			array(
				'checkin' => $checkin,
				'source'  => $source,
			),
			$delay
		);

		$delay += 2; // Stagger by 2 seconds within each window.
		$queued++;
	}

	return $queued;
}

/**
 * Processes a single queued checkin.
 *
 * @param array  $checkin The checkin data array.
 * @param string $source  Source context.
 *
 * @return void
 */
function process_checkin( $checkin, $source = 'import' ) {
	if ( ! has_budget( 4 ) ) {
		// Re-queue after the rate limit resets.
		schedule_action(
			'bs_process_checkin',
			array(
				'checkin' => $checkin,
				'source'  => $source,
			),
			HOUR_IN_SECONDS
		);
		return;
	}

	$result = \Kraft\Beer_Slurper\Post\insert_beer( $checkin );

	if ( is_wp_error( $result ) && 'already_done' !== $result->get_error_code() ) {
		error_log( 'Beer Slurper Queue: Failed to process checkin ' . $checkin['checkin_id'] . ' - ' . $result->get_error_message() );
	}
}
add_action( 'bs_process_checkin', __NAMESPACE__ . '\process_checkin', 10, 2 );

/**
 * Processes a single companion backfill job.
 *
 * Fetches the checkin from the API and attaches any tagged friends
 * as companion taxonomy terms. Re-queues with delay if rate-limited.
 *
 * @param int $checkin_id The Untappd checkin ID.
 * @param int $post_id    The beer post ID.
 *
 * @return void
 */
function process_companion_backfill( $checkin_id, $post_id ) {
	if ( ! has_budget( 2 ) ) {
		// Re-queue after the rate limit resets.
		schedule_action(
			'bs_backfill_companion',
			array(
				'checkin_id' => $checkin_id,
				'post_id'    => $post_id,
			),
			HOUR_IN_SECONDS
		);
		return;
	}

	$response = \Kraft\Beer_Slurper\API\get_untappd_data( 'checkin/view', $checkin_id );

	if ( is_wp_error( $response ) || ! is_array( $response ) || empty( $response['checkin'] ) ) {
		return;
	}

	$checkin_data = $response['checkin'];

	if ( ! empty( $checkin_data['tagged_friends']['items'] ) ) {
		\Kraft\Beer_Slurper\Companion\attach_companions( $checkin_data, $post_id );
	}
}
add_action( 'bs_backfill_companion', __NAMESPACE__ . '\process_companion_backfill', 10, 2 );

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
 * Cleans up all Action Scheduler actions on deactivation or reset.
 *
 * @return void
 */
function cleanup() {
	if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
		return;
	}

	cancel_all( 'bs_process_checkin' );
	cancel_all( 'bs_backfill_companion' );
	cancel_all( 'bs_hourly_import' );
	cancel_all( 'bs_daily_maintenance' );
	cancel_all( 'bs_maintenance_stats' );
	cancel_all( 'bs_maintenance_brewery_backfill' );
	cancel_all( 'bs_maintenance_venue_backfill' );
	cancel_all( 'bs_maintenance_badge_backfill' );

	// Legacy hook names from older versions.
	cancel_all( 'bs_as_daily_maintenance' );
}
