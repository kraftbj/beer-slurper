<?php
namespace Kraft\Beer_Slurper\API;

/**
 * This file does all of the dirty work of the API retrival.
 */

/**
 * Actually queries Untappd.
 *
 * @since 1.0.0
 *
 * @uses \Kraft\Beer_Slurper\API\validate_endpoint() Confirms $endpoint variable is valid.
 *
 * @param string $endpoint  Required. The Untappd API endpoint to hit, sans a personalized end parameter
 * @param string $parameter Optional. Personalized endpoint parameter. E.g. "USER" in "checkins/USER" endpoint.
 * @param array  $args      Optional. Query string additions to the API call.
 * @param string $ver       Optional. API version number. v4 by default.
 *
 * @return array Complete Untappd response. Useful data is in the 'response' key.
 **/

function get_untappd_data_raw( $endpoint, $parameter = null, array $args = null, $ver = 'v4' ){
	$untappd_url    = 'https://api.untappd.com/' . $ver . '/';
	$untappd_key    = get_option( 'beer-slurper-key' );
	$untappd_secret = get_option( 'beer-slurper-secret' );

	/* $endpoint = validate_endpoint( $endpoint, $paramteter, $ver ); // @todo reenable when validation does something.
	if ( is_wp_error( $endpoint ) {
		return $endpoint;
	} */

	if ( $args && ! is_array( $args ) ) {
		return new \WP_Error( 'poor_form', __( "The args must be in an array.", "beer_slurper" ) );
	}

	if (! $untappd_key || ! $untappd_secret ){
		return new \WP_Error( 'lacking_creds', __( "Somehow, you got to this point without API creds.", "beer_slurper" ) );
	}

	$args['client_id']     = $untappd_key;
	$args['client_secret'] = $untappd_secret;

	$untappd_url = $untappd_url . $endpoint . '/' . $parameter;
	$untappd_url = add_query_arg( $args, $untappd_url );
	$request_hash = md5( $untappd_url );

	$response = get_transient( 'beer_slurper_' . $request_hash ); // Just got under the 45 character limit!

	if ( $response === false ) {

		$response = wp_safe_remote_get( $untappd_url );
		set_transient( 'beer_slurper_' . $request_hash, $response, HOUR_IN_SECONDS );
	}

	if ( is_wp_error( $response ) ) {
		return;
	}

	$response = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $response ) ){
		return false;
	}
	return $response;
}

function get_untappd_data( $endpoint, $parameter = null, array $args = null, $ver = 'v4' ){
	$response = get_untappd_data_raw( $endpoint, $parameter, $args, $ver );
	$response = $response['response'];
	return $response;
}

function get_latest_checkin( $user ){
	$args    = array(
		'limit' => 1,
		);
	$checkin = get_untappd_data( 'user/checkins', $user, $args );
	$checkin = $checkin['checkins']['items'][0];
	return $checkin;
}

/**
 * Get part of the user's activity stream.
 *
 * @param string $user   Untappd user name.
 * @param int    $max_id The checking ID that you want the results to start with (e.g. the newest in the returned set).
 * @param int    $min_id Returns only checkins newer than this value.
 * @param int    $number Number of checkins. Default 25. Max 50.
 *
 * @return array User's feed. Checkins contained within ['items'];
 **/
function get_checkins( $user, $max_id = null, $min_id = null, $number = null ){
	$args = null;

	if ( intval( $max_id ) != 0 ) {
		$args['max_id'] = intval( $max_id );
	}
	if ( intval( $min_id ) !=0 ) {
		$args['min_id'] = intval( $min_id );
	}
	if ( intval( $number ) != 0 && $number < 50 ){
		$args['number'] = intval( $number );
	}
	$checkin = get_untappd_data( 'user/checkins', $user, $args );
	// $checkin = $checkin['checkins']; // @todo add isset check for private accts
	return $checkin;
}

function get_beer_info( $bid, $compact = true, $section = 'beer' ){
	$args    = array(
		'compact' => $compact,
		);
	$info = get_untappd_data( 'beer/info', $bid, $args );

	if ($section == 'brewery' ){
		return $info['beer']['brewery'];
	}
	else {
		return $info['beer'];
	}
}

function get_brewery_info_by_beer( $bid ){
	$brewery = get_beer_info( $bid, true, 'brewery' );
	return $brewery;
}

/**
 * Ensures that the specified endpoint is supported by our code.
 *
 * @param string $endpoint  Required. The Untappd API endpoint to hit, sans a personalized end parameter
 * @param string $parameter Optional. Personalized endpoint parameter. E.g. "USER" in "checkins/USER" endpoint.
 * @param string $ver       Optional. API version number. v4 by default.
 * @return string|WP_Error $endpoint The endpoint is returned if valid. WP_Error object if not.
 **/
function validate_endpoint( $endpoint, $parameter, $ver ){

	if ( ! $endpoint ){
		return new \WP_Error( 'nothing', __( "You've indiated no endpoint. I'm not going to guess.", "beer_slurper") );
	}

	if ( $ver != 'v4') {
		return $endpoint; // We aren't validating other versions at this time. Just look the other way.
	}
	$valid_endpoints = array( // @todo - Add version to the array and if parameter required/optional?
		'checkin/recent',              // Activity Feed
		'user/checkins',                // Also valid without user name when authenticated
		'thepub/local',                // The Pub (Local)
		'venue/checkins',              // Venue Activity Feed
		'beer/checkins',               // Beer Activity Feed
		'brewery/checkins',            // Brewery Activity Feed
		'notifications',               // Notifications for current user
		'user/info',                   // User Info
		'user/wishlist',               // User wishlist
		'user/friends',
		'user/badges',
		'user/beers',
		'brewery/info',
		'beer/info',
		'venue/info',
		'search/beer',
		'search/brewery',
		'checkin/add',
		'checkin/toast',
		'user/pending',
		'friend/request',
		'friend/reject',
		'friend/accept',
		'checkin/addcomment',
		'user/wishlist/add',
		'user/wishlist/delete',
		'venue/foursquare_lookup',
		);
	if ( ! in_array( $valid_endpoints, $endpoint) ) { // @todo verify this is the right function to use.
		$endpoint = new \WP_Error( 'invalid_endpoint', __( 'The specified endpoint is not on the shortlist.', 'beer_slurper' ) );
	}
	return $endpoint; // @todo Make this do something.
}