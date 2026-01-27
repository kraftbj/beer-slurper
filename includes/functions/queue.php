<?php
namespace Kraft\Beer_Slurper\Queue;

/**
 * Action Scheduler Queue Functions
 *
 * Provides rate-limit-aware API call queuing using Action Scheduler.
 * Each API-consuming operation is queued as an action and processed
 * within the rate limit budget (90 calls/hour to stay under 100).
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
 * Processes the daily maintenance via Action Scheduler.
 *
 * @return void
 */
function process_daily_maintenance() {
	\Kraft\Beer_Slurper\Stats\refresh_user_stats();
	\Kraft\Beer_Slurper\Brewery\backfill_missing_meta();
	\Kraft\Beer_Slurper\Venue\backfill_missing_meta();
}
add_action( 'bs_as_daily_maintenance', __NAMESPACE__ . '\process_daily_maintenance' );

/**
 * Initializes Action Scheduler recurring tasks when the plugin starts import.
 *
 * @param string $user The Untappd username.
 *
 * @return void
 */
function init_scheduled_actions( $user ) {
	// Daily maintenance via Action Scheduler.
	schedule_recurring( 'bs_as_daily_maintenance', DAY_IN_SECONDS );
}

/**
 * Registers the Action Scheduler admin UI.
 *
 * When Action Scheduler is loaded as a library (not via WooCommerce),
 * the admin UI must be explicitly initialized. This adds the
 * "Scheduled Actions" page under Tools.
 *
 * @return void
 */
function register_admin_ui() {
	if ( ! class_exists( 'ActionScheduler_AdminView' ) ) {
		return;
	}

	\ActionScheduler_AdminView::instance()->init();
}
add_action( 'init', __NAMESPACE__ . '\register_admin_ui' );

/**
 * Cleans up Action Scheduler actions on deactivation.
 *
 * @return void
 */
function cleanup() {
	if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
		return;
	}

	cancel_all( 'bs_process_checkin' );
	cancel_all( 'bs_as_daily_maintenance' );
}
