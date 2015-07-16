<?php
/**
 * Plugin Name: Beer Slurper
 * Plugin URI:  https://kraft.im/
 * Description: Slurp data from Untappd into your site!
 * Version:     0.1.0
 * Author:      Brandon Kraft
 * Author URI:  https://kraft.im/
 * License:     GPLv2+
 * Text Domain: beer_slurper
 * Domain Path: /languages
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

// Useful global constants
define( 'BEER_SLURPER_VERSION', '0.1.0' );
define( 'BEER_SLURPER_URL',     plugin_dir_url( __FILE__ ) );
define( 'BEER_SLURPER_PATH',    dirname( __FILE__ ) . '/' );
define( 'BEER_SLURPER_INC',     BEER_SLURPER_PATH . 'includes/' );

// Include files
require_once BEER_SLURPER_INC . 'functions/core.php';
require_once BEER_SLURPER_INC . 'functions/temp.php'; // @todo temporary
require_once BEER_SLURPER_INC . 'functions/api.php';
require_once BEER_SLURPER_INC . 'functions/post.php';
require_once BEER_SLURPER_INC . 'functions/walker.php';

function bs_test_insert( $user = 'kraft' ){
	// add validation
	$checkin = \Kraft\Beer_Slurper\API\get_latest_checkin( $user );
	\Kraft\Beer_Slurper\Post\insert_beer( $checkin );
}

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

function bs_import( $user = null ){ // used to start backflow imports. need to add checks for new beer too and/or split.
	if ( ! $user ){
		return "No user indicated.";
	}

	if ( get_option( 'beer_slurper_' . $user . '_import' ) ) {
		// If we are still backfilling, call in the next batch of 25 checkins.
		\Kraft\Beer_Slurper\Walker\import_old( $user );
	}
	if ( get_option( 'beer_slurper_' . $user . '_since' ) ) {
		// If we have pulled in at least one batch of old checkins, check for ones newer than the most recent.
		\Kraft\Beer_Slurper\Walker\import_new( $user );
	}

	return "All done.";
}

add_action('bs_hourly_importer', 'bs_import', 10, 2 );

// Activation/Deactivation
register_activation_hook( __FILE__, '\Kraft\Beer_Slurper\Core\activate' );
register_deactivation_hook( __FILE__, '\Kraft\Beer_Slurper\Core\deactivate' );

// Bootstrap
Kraft\Beer_Slurper\Core\setup();
