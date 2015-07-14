<?php
namespace Kraft\Beer_Slurper\Walker;

/* This file contains the "walkers" to import old data or new data after last check-in. */

function import_new( $user ) {

	$user = sanitize_user( $user ); // Just to be safe. Not sure what Untappd users, but ¯\_(ツ)_/¯
	// check for an option of the since_id for the $user indicated.
	// e.g. get_option('beer_slurper_' . $user)
	$since_id = get_option( 'beer_slurper_' . $user . '_since' );

	if ( ! $since_id ) {
		// this means we have never pulled in data for this user. Let's kick off the import process?
		import_old( $user );
		return;
	}

	$checkins = \Kraft\Beer_Slurper\API\get_checkins( $user, null, $since_id, '25' );

	print_r($checkins);

	if ( ! isset( $checkins['count'] ) || $checkins['count'] == 0 ) {
		return "No new beers here!";
	}

	if ( isset( $checkins['count'] ) && $checkins['count'] == 25 ) {
		// do special stuff since there are likely more than we thought.
	}

	foreach ( $checkins['items'] as $checkin ){
		\Kraft\Beer_Slurper\Post\insert_beer( $checkin );
	}

	$since_id = $checkins['items'][0]['checkin_id'];
	update_option( 'beer_slurper_' . $user . '_since', $since_id, false );

	$message = $checkins['count'] . " beer(s) imported.";
	return $message;
}


/**
 * Incredible function to import all old Untappd data!
 *
 * The idea is to grab the latest 25 checkins, set the since_id (see import_new())
 * Then, walk through those 25 to import those. Set the max_id, spin up a cron for the next set.
 * @param $user Untappd user name
 * @return void
 **/
function import_old( $user ) {
	$user = sanitize_user( $user );
	$args = array();
	$max_id = get_option( 'beer_slurper_' . $user . '_max' );

	$checkins = \Kraft\Beer_Slurper\API\get_checkins( $user, $max_id, null, '25' ); // set 25 for the cron check.

	foreach ( $checkins['checkins']['items'] as $checkin ){
		\Kraft\Beer_Slurper\Post\insert_beer( $checkin );
	}

	$max_id = $checkins['pagination']['max_id'];
	update_option( 'beer_slurper_' . $user . '_max', $max_id, false );
	if ( ! get_option( 'beer_slurper_' . $user . '_since') ) { //first time to import anything from this user
		$since_url = wp_parse_args( parse_url( $checkins['pagination']['since_url'], PHP_URL_QUERY ) );
		$since_id = intval( $since_url['min_id'] );
		update_option( 'beer_slurper_' . $user . '_since', $since_id, false );
	}

	if ( isset( $checkins['checkins']['count'] ) && $checkins['checkins']['count'] < 25 ) {
		delete_option( 'beer_slurper_' . $user . '_import' );
	}

}
