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

	$endpoint = validate_endpoint( $endpoint, $paramteter, $ver );
	if ( is_wp_error( $endpoint ) {
		return $endpoint;
	}

	if ( $args && ! is_array( $args ) ) {
		return new \WP_Error( 'poor_form', __( "The args must be in an array.", "beer_slurper" ) );
	}

	if (! $untappd_key || ! $untappd_secret ){
		return new \WP_Error( 'lacking_creds', __( "Somehow, you got to this point without API creds.", "beer_slurper" ) );
	}

	$args['client_id']     = $untappd_key;
	$args['client_secret'] = $untappd_secret;

	$untappd_url = $untappd_url . $endpoint . '/' . $parameter;

	$response = wp_safe_remote_get( add_query_arg( $args, $untappd_url ) );

	if ( is_wp_error( $response ) ) {
		return;
	}

	$response = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $response ) ){
		return false;
	}
	return $response;
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
		'user/checkins'                // Also valid without user name when authenticated
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
		'venue/foursquare_lookup'
		);
	if ( ! in_array( $valid_endpoints, $endpoint) ) { // @todo verify this is the right function to use.
		$endpoint = new \WP_Error( 'invalid_endpoint', __( 'The specified endpoint is not on the shortlist.', 'beer_slurper' ) );
	}
	return $endpoint; // @todo Make this do something.
}