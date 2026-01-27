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
 * Schedules a single async action if not already pending.
 *
 * @param string $hook The action hook name.
 * @param array  $args Arguments to pass to the action.
 * @param int    $delay Optional. Seconds to delay execution. Default 0.
 *
 * @return int|null The action ID, or null if already pending.
 */
function schedule_action( $hook, $args = array(), $delay = 0 ) {
	if ( ! function_exists( 'as_has_scheduled_action' ) ) {
		return null;
	}

	if ( as_has_scheduled_action( $hook, $args, AS_GROUP ) ) {
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
 * the rest still proceed. Actions are staggered by 2 seconds to
 * avoid rate limit bursts.
 *
 * @param array  $checkins Array of checkin data arrays.
 * @param string $source   Source context ('import_old' or 'import_new').
 *
 * @return int Number of actions queued.
 */
function queue_checkin_batch( $checkins, $source = 'import' ) {
	$budget = get_remaining_budget();
	$queued = 0;

	foreach ( $checkins as $index => $checkin ) {
		if ( ! isset( $checkin['checkin_id'] ) ) {
			continue;
		}

		// Each checkin needs ~2 API calls (checkin insert + beer info).
		// If we're running low, stop queueing and let the next cron pick up.
		if ( $budget < 3 ) {
			break;
		}

		schedule_action(
			'bs_process_checkin',
			array(
				'checkin' => $checkin,
				'source'  => $source,
			),
			$index * 2 // Stagger by 2 seconds.
		);

		$budget -= 2;
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
	if ( get_remaining_budget() < 2 ) {
		// Re-queue with a delay to wait for budget refresh.
		schedule_action(
			'bs_process_checkin',
			array(
				'checkin' => $checkin,
				'source'  => $source,
			),
			300 // Retry in 5 minutes.
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
	if ( get_remaining_budget() < 2 ) {
		// Re-queue with a delay to wait for budget refresh.
		schedule_action(
			'bs_backfill_companion',
			array(
				'checkin_id' => $checkin_id,
				'post_id'    => $post_id,
			),
			300 // Retry in 5 minutes.
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
 * @return void
 */
function process_daily_maintenance() {
	\Kraft\Beer_Slurper\Stats\refresh_user_stats();
	\Kraft\Beer_Slurper\Brewery\backfill_missing_meta();
	\Kraft\Beer_Slurper\Venue\backfill_missing_meta();
	\Kraft\Beer_Slurper\Badge\backfill_missing_descriptions();
}
add_action( 'bs_daily_maintenance', __NAMESPACE__ . '\process_daily_maintenance' );

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

	// Legacy hook names from older versions.
	cancel_all( 'bs_as_daily_maintenance' );
}
