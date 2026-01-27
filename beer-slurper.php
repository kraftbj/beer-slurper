<?php
/**
 * Plugin Name: Beer Slurper
 * Plugin URI:  https://kraft.im/beer-slurper/
 * Description: Slurp data from Untappd into your site!
 * Version:     0.1.0
 * Author:      Brandon Kraft
 * Author URI:  https://kraft.im/
 * License:     GPLv2+
 * Text Domain: beer_slurper
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

/**
 * Copyright (c) 2015 Brandon Kraft (email : public@brandonkraft.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

/**
 * Built using yo wp-make:plugin
 * Copyright (c) 2015 10up, LLC
 * https://github.com/10up/generator-wp-make
 */

/**
 * Beer Slurper Main Plugin File
 *
 * This file serves as the main entry point for the Beer Slurper plugin.
 * It defines global constants, includes required files, and sets up
 * activation/deactivation hooks.
 *
 * @package Kraft\Beer_Slurper
 */

// Useful global constants
define( 'BEER_SLURPER_VERSION', '0.1.0' );
define( 'BEER_SLURPER_URL',     plugin_dir_url( __FILE__ ) );
define( 'BEER_SLURPER_PATH',    dirname( __FILE__ ) . '/' );
define( 'BEER_SLURPER_INC',     BEER_SLURPER_PATH . 'includes/' );

// temporary constants
if ( ! defined( 'BEER_SLURPER_CPT' ) ) {
	define( 'BEER_SLURPER_CPT', 'beerlog_beer');
}

if ( ! defined( 'BEER_SLURPER_TAX_STYLE') ) {
	define( 'BEER_SLURPER_TAX_STYLE', 'beerlog_style' );
}

if ( ! defined( 'BEER_SLURPER_TAX_BREWERY') ) {
	define( 'BEER_SLURPER_TAX_BREWERY', 'beerlog_brewery' );
}

// Include files
require_once BEER_SLURPER_INC . 'functions/core.php';
require_once BEER_SLURPER_INC . 'functions/cpt.php';
require_once BEER_SLURPER_INC . 'functions/brewery.php';
require_once BEER_SLURPER_INC . 'functions/oauth.php';
require_once BEER_SLURPER_INC . 'functions/api.php';
require_once BEER_SLURPER_INC . 'functions/post.php';
require_once BEER_SLURPER_INC . 'functions/walker.php';
require_once BEER_SLURPER_INC . 'functions/sync-status.php';

/**
 * Inserts the latest check-in for a user as a test.
 *
 * Retrieves the most recent check-in from Untappd for the specified user
 * and inserts it as a beer post. Primarily used for testing purposes.
 *
 * @param string $user The Untappd username. Defaults to 'kraft'.
 * @return void
 *
 * @uses \Kraft\Beer_Slurper\API\get_latest_checkin()
 * @uses \Kraft\Beer_Slurper\Post\insert_beer()
 */
function bs_test_insert( $user = 'kraft' ){
	// add validation
	$checkin = \Kraft\Beer_Slurper\API\get_latest_checkin( $user );
	\Kraft\Beer_Slurper\Post\insert_beer( $checkin );
}

/**
 * Initiates the import process for a user.
 *
 * Sets up the import option flag and schedules an hourly cron event
 * to perform the import if one is not already scheduled.
 *
 * @param string|null $user The Untappd username. Defaults to null.
 * @return string|void Error message if no user provided, void otherwise.
 *
 * @uses update_option()
 * @uses wp_next_scheduled()
 * @uses wp_schedule_event()
 */
function bs_start_import( $user = null ){
	if ( ! $user ){
		return "No user indicated.";
	}
	// @todo check for other user options. No need to restart the import if options suggest it has been done.
	update_option( 'beer_slurper_' . $user . '_import', true, false );
	if (! wp_next_scheduled( 'bs_hourly_importer', array( $user ) ) ) {
		wp_schedule_event( time(), 'hourly', 'bs_hourly_importer', array( $user ) );
	}
}

/**
 * Performs the import of check-ins for a user.
 *
 * Handles both backfilling historical check-ins and importing new check-ins.
 * Clears any previous error state, then processes both old and new check-ins
 * based on the current import state. Records success or error status.
 *
 * @param string|null $user The Untappd username. Defaults to null.
 * @return string Error message if no user provided, or "All done." on completion.
 *
 * @uses get_option()
 * @uses is_wp_error()
 * @uses \Kraft\Beer_Slurper\Sync_Status\clear_sync_error()
 * @uses \Kraft\Beer_Slurper\Sync_Status\record_sync_error()
 * @uses \Kraft\Beer_Slurper\Sync_Status\record_sync_success()
 * @uses \Kraft\Beer_Slurper\Walker\import_old()
 * @uses \Kraft\Beer_Slurper\Walker\import_new()
 */
function bs_import( $user = null ){
	if ( ! $user ){
		return "No user indicated.";
	}

	// Clear any previous error state at the start of a new sync
	\Kraft\Beer_Slurper\Sync_Status\clear_sync_error();

	$has_error = false;

	if ( get_option( 'beer_slurper_' . $user . '_import' ) ) {
		// If we are still backfilling, call in the next batch of 25 checkins.
		$result = \Kraft\Beer_Slurper\Walker\import_old( $user );
		if ( is_wp_error( $result ) ) {
			\Kraft\Beer_Slurper\Sync_Status\record_sync_error( $result );
			$has_error = true;
		}
	}
	if ( get_option( 'beer_slurper_' . $user . '_since' ) ) {
		// If we have pulled in at least one batch of old checkins, check for ones newer than the most recent.
		$result = \Kraft\Beer_Slurper\Walker\import_new( $user );
		if ( is_wp_error( $result ) ) {
			\Kraft\Beer_Slurper\Sync_Status\record_sync_error( $result );
			$has_error = true;
		}
	}

	// Record success only if no errors occurred
	if ( ! $has_error ) {
		\Kraft\Beer_Slurper\Sync_Status\record_sync_success( time() );
	}

	return "All done.";
}

add_action('bs_hourly_importer', 'bs_import', 10, 2 );

// Activation/Deactivation
register_activation_hook( __FILE__, '\Kraft\Beer_Slurper\Core\activate' );
register_deactivation_hook( __FILE__, '\Kraft\Beer_Slurper\Core\deactivate' );

/**
 * Appends a gallery shortcode to beer post content.
 *
 * Filters the content of singular beer posts to append a [gallery] shortcode
 * when the gallery option is enabled. This is a temporary function that will
 * be migrated once the beer CPT code is merged in.
 *
 * @param string $content The post content.
 * @return string The modified post content with gallery shortcode appended.
 *
 * @uses is_singular()
 * @uses get_option()
 */
add_filter( 'the_content', function( $content ){
	if ( is_singular( BEER_SLURPER_CPT ) && get_option( 'beer-slurper-gallery', true ) ) {
		$content .= "[gallery]";
	}
	return $content;
} );

// Bootstrap
Kraft\Beer_Slurper\Core\setup();
