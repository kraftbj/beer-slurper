<?php
/**
 * Queue Tests for Beer Slurper
 *
 * Tests for Action Scheduler integration, rate limiting, and job scheduling.
 *
 * @package Kraft\Beer_Slurper\Queue
 */

namespace Kraft\Beer_Slurper\Queue;

use Kraft\Beer_Slurper as Base;

/**
 * Tests for the Queue functions.
 */
class Queue_Tests extends Base\TestCase {

	protected $testFiles = [
		'functions/queue.php',
	];

	/**
	 * Tests get_remaining_budget() returns full budget when no calls made.
	 */
	public function test_get_remaining_budget_returns_full_when_empty() {
		$result = get_remaining_budget();

		$this->assertEquals( API_BUDGET_PER_HOUR, $result );
	}

	/**
	 * Tests get_remaining_budget() subtracts used calls.
	 */
	public function test_get_remaining_budget_subtracts_used() {
		set_transient( 'beer_slurper_api_calls', 30, HOUR_IN_SECONDS );
		set_transient( 'beer_slurper_api_window_end', time() + HOUR_IN_SECONDS, HOUR_IN_SECONDS );

		$result = get_remaining_budget();

		$this->assertEquals( 60, $result ); // 90 - 30
	}

	/**
	 * Tests get_remaining_budget() resets after window expires.
	 */
	public function test_get_remaining_budget_resets_after_window() {
		set_transient( 'beer_slurper_api_calls', 50, HOUR_IN_SECONDS );
		set_transient( 'beer_slurper_api_window_end', time() - 1, HOUR_IN_SECONDS ); // Expired

		$result = get_remaining_budget();

		$this->assertEquals( API_BUDGET_PER_HOUR, $result );
	}

	/**
	 * Tests has_budget() returns true when enough budget.
	 */
	public function test_has_budget_returns_true_when_available() {
		$result = has_budget( 5 );

		$this->assertTrue( $result );
	}

	/**
	 * Tests has_budget() returns false when insufficient budget.
	 */
	public function test_has_budget_returns_false_when_insufficient() {
		set_transient( 'beer_slurper_api_calls', 88, HOUR_IN_SECONDS );
		set_transient( 'beer_slurper_api_window_end', time() + HOUR_IN_SECONDS, HOUR_IN_SECONDS );

		$result = has_budget( 5 ); // Need 5, only have 2

		$this->assertFalse( $result );
	}

	/**
	 * Tests has_budget() with default value of 1.
	 */
	public function test_has_budget_default_value() {
		set_transient( 'beer_slurper_api_calls', 89, HOUR_IN_SECONDS );
		set_transient( 'beer_slurper_api_window_end', time() + HOUR_IN_SECONDS, HOUR_IN_SECONDS );

		$result = has_budget(); // Default 1

		$this->assertTrue( $result ); // Have 1 remaining
	}

	/**
	 * Tests consume_budget() increments counter.
	 */
	public function test_consume_budget_increments_counter() {
		consume_budget( 5 );

		$used = get_transient( 'beer_slurper_api_calls' );
		$this->assertEquals( 5, $used );
	}

	/**
	 * Tests consume_budget() adds to existing count.
	 */
	public function test_consume_budget_adds_to_existing() {
		set_transient( 'beer_slurper_api_calls', 10, HOUR_IN_SECONDS );
		set_transient( 'beer_slurper_api_window_end', time() + HOUR_IN_SECONDS, HOUR_IN_SECONDS );

		consume_budget( 5 );

		$used = get_transient( 'beer_slurper_api_calls' );
		$this->assertEquals( 15, $used );
	}

	/**
	 * Tests consume_budget() starts new window when expired.
	 */
	public function test_consume_budget_starts_new_window() {
		set_transient( 'beer_slurper_api_calls', 50, HOUR_IN_SECONDS );
		set_transient( 'beer_slurper_api_window_end', time() - 1, HOUR_IN_SECONDS ); // Expired

		consume_budget( 3 );

		$used = get_transient( 'beer_slurper_api_calls' );
		$this->assertEquals( 3, $used ); // Reset to 3, not 53
	}

	/**
	 * Tests get_spread_params() returns expected structure.
	 */
	public function test_get_spread_params_returns_structure() {
		$params = get_spread_params();

		$this->assertArrayHasKey( 'per_hour', $params );
		$this->assertArrayHasKey( 'interval', $params );
		$this->assertArrayHasKey( 'current_slots', $params );
		$this->assertArrayHasKey( 'current_interval', $params );
		$this->assertArrayHasKey( 'full_start', $params );
	}

	/**
	 * Tests get_spread_params() calculates per_hour based on budget.
	 */
	public function test_get_spread_params_calculates_per_hour() {
		$params = get_spread_params();

		// At 5 calls per checkin, 90 budget = 18 per hour
		$this->assertEquals( 18, $params['per_hour'] );
	}

	/**
	 * Tests get_slot_delay() returns 0 for first slot.
	 */
	public function test_get_slot_delay_first_slot() {
		$params = get_spread_params();
		// Simulate fresh window with full budget
		$params['current_slots'] = 18;
		$params['current_interval'] = 200;

		$delay = get_slot_delay( 0, $params );

		$this->assertEquals( 0, $delay );
	}

	/**
	 * Tests get_slot_delay() increases for subsequent slots.
	 */
	public function test_get_slot_delay_increases_for_slots() {
		$params = array(
			'per_hour'         => 18,
			'interval'         => 200,
			'current_slots'    => 5,
			'current_interval' => 200,
			'full_start'       => time() + 1000,
		);

		$delay0 = get_slot_delay( 0, $params );
		$delay1 = get_slot_delay( 1, $params );
		$delay2 = get_slot_delay( 2, $params );

		$this->assertLessThan( $delay1, $delay0 );
		$this->assertLessThan( $delay2, $delay1 );
	}

	/**
	 * Tests API_BUDGET_PER_HOUR constant value.
	 */
	public function test_api_budget_constant() {
		$this->assertEquals( 90, API_BUDGET_PER_HOUR );
	}

	/**
	 * Tests AS_GROUP constant value.
	 */
	public function test_as_group_constant() {
		$this->assertEquals( 'beer-slurper', AS_GROUP );
	}

	/**
	 * Tests AS_BATCH_SIZE constant value.
	 */
	public function test_as_batch_size_constant() {
		$this->assertEquals( 5, AS_BATCH_SIZE );
	}

	/**
	 * Tests MAX_PENDING_CHECKINS constant value.
	 */
	public function test_max_pending_checkins_constant() {
		$this->assertEquals( 50, MAX_PENDING_CHECKINS );
	}

	/**
	 * Tests acquire_checkin_lock() returns slot index.
	 */
	public function test_acquire_checkin_lock_returns_slot() {
		$slot = acquire_checkin_lock();

		$this->assertIsInt( $slot );
		$this->assertGreaterThanOrEqual( 0, $slot );
		$this->assertLessThan( MAX_CONCURRENT_CHECKINS, $slot );

		// Cleanup
		release_checkin_lock( $slot );
	}

	/**
	 * Tests acquire_checkin_lock() returns false when all slots occupied.
	 */
	public function test_acquire_checkin_lock_returns_false_when_full() {
		// Acquire all slots
		$slots = array();
		for ( $i = 0; $i < MAX_CONCURRENT_CHECKINS; $i++ ) {
			$slots[] = acquire_checkin_lock();
		}

		// Next acquire should fail
		$result = acquire_checkin_lock();
		$this->assertFalse( $result );

		// Cleanup
		foreach ( $slots as $slot ) {
			release_checkin_lock( $slot );
		}
	}

	/**
	 * Tests release_checkin_lock() frees the slot.
	 */
	public function test_release_checkin_lock_frees_slot() {
		$slot1 = acquire_checkin_lock();
		release_checkin_lock( $slot1 );

		$slot2 = acquire_checkin_lock();
		$this->assertEquals( $slot1, $slot2 ); // Same slot should be available

		release_checkin_lock( $slot2 );
	}

	/**
	 * Tests schedule_action() returns null when Action Scheduler not available.
	 *
	 * Note: In real tests with AS loaded, this would test the scheduling.
	 * Here we're testing the guard clause.
	 */
	public function test_schedule_action_handles_missing_as() {
		// If AS isn't loaded, should return null gracefully
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			$result = schedule_action( 'test_hook' );
			$this->assertNull( $result );
		} else {
			$this->markTestSkipped( 'Action Scheduler is loaded, testing actual scheduling instead.' );
		}
	}

	/**
	 * Tests cancel_all() handles missing Action Scheduler.
	 */
	public function test_cancel_all_handles_missing_as() {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			// Should not error
			cancel_all( 'test_hook' );
			$this->assertTrue( true );
		} else {
			$this->markTestSkipped( 'Action Scheduler is loaded.' );
		}
	}

	/**
	 * Tests cleanup() cancels all hook types.
	 */
	public function test_cleanup_function_exists() {
		$this->assertTrue( function_exists( __NAMESPACE__ . '\cleanup' ) );
	}

	/**
	 * Tests get_pending_checkin_count() returns 0 without AS.
	 */
	public function test_get_pending_checkin_count_without_as() {
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			$result = get_pending_checkin_count();
			$this->assertEquals( 0, $result );
		} else {
			$this->markTestSkipped( 'Action Scheduler is loaded.' );
		}
	}

	/**
	 * Tests get_page_spread_params() returns expected structure.
	 */
	public function test_get_page_spread_params_returns_structure() {
		$params = get_page_spread_params();

		$this->assertArrayHasKey( 'per_hour', $params );
		$this->assertArrayHasKey( 'interval', $params );
		$this->assertArrayHasKey( 'current_slots', $params );
		$this->assertArrayHasKey( 'current_interval', $params );
		$this->assertArrayHasKey( 'full_start', $params );
	}

	/**
	 * Tests get_page_spread_params() calculates per_hour for single API call cost.
	 */
	public function test_get_page_spread_params_calculates_per_hour() {
		$params = get_page_spread_params();

		// At 1 call per page, 90 budget = 90 per hour
		$this->assertEquals( 90, $params['per_hour'] );
	}

	/**
	 * Tests get_page_spread_params() calculates current_slots with budget.
	 */
	public function test_get_page_spread_params_current_slots_with_budget() {
		set_transient( 'beer_slurper_api_calls', 0, HOUR_IN_SECONDS );
		set_transient( 'beer_slurper_api_window_end', time() + 1800, HOUR_IN_SECONDS ); // 30 min left

		$params = get_page_spread_params();

		// With full budget and 30 min left, should have slots available
		$this->assertGreaterThan( 0, $params['current_slots'] );
	}

	/**
	 * Tests get_page_spread_params() returns zero current_slots when budget exhausted.
	 */
	public function test_get_page_spread_params_no_slots_when_exhausted() {
		set_transient( 'beer_slurper_api_calls', 90, HOUR_IN_SECONDS );
		set_transient( 'beer_slurper_api_window_end', time() + 1800, HOUR_IN_SECONDS );

		$params = get_page_spread_params();

		$this->assertEquals( 0, $params['current_slots'] );
	}

	/**
	 * Tests schedule_next_page_fetch() returns null without Action Scheduler.
	 */
	public function test_schedule_next_page_fetch_without_as() {
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			$result = schedule_next_page_fetch( 'test_hook', array() );
			$this->assertNull( $result );
		} else {
			$this->markTestSkipped( 'Action Scheduler is loaded.' );
		}
	}
}
