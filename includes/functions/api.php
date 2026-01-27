<?php
namespace Kraft\Beer_Slurper\API;

/**
 * Untappd API Functions
 *
 * Handles all API communication with the Untappd service, including
 * data retrieval, caching, and rate limiting.
 *
 * @package Kraft\Beer_Slurper
 */

/**
 * Queries the Untappd API directly.
 *
 * Performs raw HTTP requests to Untappd endpoints with built-in rate limiting
 * and response caching. Handles authentication, error checking, and response parsing.
 *
 * @since 1.0.0
 *
 * @uses \Kraft\Beer_Slurper\API\validate_endpoint() Confirms $endpoint variable is valid.
 * @uses get_option()                                Retrieves API credentials.
 * @uses is_wp_error()                               Checks for WP_Error instances.
 * @uses add_query_arg()                             Builds query string for API URL.
 * @uses get_transient()                             Retrieves cached API responses.
 * @uses set_transient()                             Caches API responses and rate limit counter.
 * @uses wp_safe_remote_get()                        Performs the HTTP request.
 * @uses wp_remote_retrieve_response_code()          Gets HTTP status code.
 * @uses wp_remote_retrieve_body()                   Gets response body.
 * @uses error_log()                                 Logs errors for debugging.
 *
 * @param string $endpoint  Required. The Untappd API endpoint to hit, sans a personalized end parameter.
 * @param string $parameter Optional. Personalized endpoint parameter. E.g. "USER" in "checkins/USER" endpoint.
 * @param array  $args      Optional. Query string additions to the API call.
 * @param string $ver       Optional. API version number. Default 'v4'.
 *
 * @return array|false|WP_Error Complete Untappd response with useful data in 'response' key,
 *                              false on invalid JSON, or WP_Error on failure.
 */
function get_untappd_data_raw( $endpoint, $parameter = null, array $args = null, $ver = 'v4' ){
	$untappd_url    = 'https://api.untappd.com/' . $ver . '/';
	$untappd_key    = get_option( 'beer-slurper-key' );
	$untappd_secret = get_option( 'beer-slurper-secret' );

	$endpoint = validate_endpoint( $endpoint, $parameter, $ver );
	if ( is_wp_error( $endpoint ) ) {
		return $endpoint;
	}

	if ( $args && ! is_array( $args ) ) {
		return new \WP_Error( 'poor_form', __( "The args must be in an array.", "beer_slurper" ) );
	}

	$access_token = \Kraft\Beer_Slurper\OAuth\get_access_token();

	if ( $access_token ) {
		$args['access_token'] = $access_token;
	} elseif ( $untappd_key && $untappd_secret ) {
		$args['client_id']     = $untappd_key;
		$args['client_secret'] = $untappd_secret;
	} else {
		error_log( 'Beer Slurper: API credentials missing' );
		return new \WP_Error( 'lacking_creds', __( "Somehow, you got to this point without API creds.", "beer_slurper" ) );
	}

	$untappd_url = $untappd_url . $endpoint . '/' . $parameter;
	$untappd_url = add_query_arg( $args, $untappd_url );
	$request_hash = md5( $untappd_url );

	$response = get_transient( 'beer_slurper_' . $request_hash ); // Just got under the 45 character limit!

	if ( $response === false ) {

		// Rate limiting check - only applies to actual API calls, not cache hits.
		$api_calls = get_transient( 'beer_slurper_api_calls' );
		if ( $api_calls === false ) {
			$api_calls = 0;
		}
		if ( $api_calls >= 90 ) {
			error_log( 'Beer Slurper: API rate limit exceeded' );
			return new \WP_Error( 'rate_limited', __( 'API rate limit exceeded. Please try again later.', 'beer_slurper' ) );
		}

		$response = wp_safe_remote_get( $untappd_url );

		// Sync rate limit counter from API response header when available.
		$remaining_header = wp_remote_retrieve_header( $response, 'x-ratelimit-remaining' );
		if ( '' !== $remaining_header && false !== $remaining_header ) {
			$remaining = (int) $remaining_header;
			$used = max( 0, 100 - $remaining );

			// Detect window rollover: remaining jumped above what we
			// previously saw, meaning Untappd started a fresh window.
			$prev_used = (int) get_transient( 'beer_slurper_api_calls' );
			if ( $used < $prev_used ) {
				delete_transient( 'beer_slurper_api_window_end' );
			}

			set_transient( 'beer_slurper_api_calls', $used, HOUR_IN_SECONDS );
		} else {
			// Fallback: increment our own counter.
			set_transient( 'beer_slurper_api_calls', $api_calls + 1, HOUR_IN_SECONDS );
		}

		// Store when this budget window expires (works with object cache).
		// Set once per window; cleared on rollover above.
		if ( false === get_transient( 'beer_slurper_api_window_end' ) ) {
			set_transient( 'beer_slurper_api_window_end', time() + HOUR_IN_SECONDS, HOUR_IN_SECONDS );
		}

		set_transient( 'beer_slurper_' . $request_hash, $response, HOUR_IN_SECONDS );
	}

	if ( is_wp_error( $response ) ) {
		error_log( 'Beer Slurper: API request failed - ' . $response->get_error_message() );
		return $response;
	}

	$status_code = wp_remote_retrieve_response_code( $response );
	$body = wp_remote_retrieve_body( $response );
	$decoded = json_decode( $body, true );

	if ( ! is_array( $decoded ) ){
		error_log( 'Beer Slurper: Invalid JSON response from API' );
		error_log( 'Beer Slurper: HTTP Status: ' . $status_code );
		error_log( 'Beer Slurper: Response body (first 500 chars): ' . substr( $body, 0, 500 ) );
		return false;
	}

	// Check for API-level errors in the response
	if ( isset( $decoded['meta']['error_type'] ) ) {
		$error_detail = $decoded['meta']['error_detail'] ?? $decoded['meta']['error_type'];
		error_log( 'Beer Slurper: API error - ' . $decoded['meta']['error_type'] . ': ' . $error_detail );
		return new \WP_Error( $decoded['meta']['error_type'], $error_detail );
	}

	return $decoded;
}

/**
 * Retrieves the response data from an Untappd API call.
 *
 * Wrapper around get_untappd_data_raw() that extracts only the 'response'
 * portion of the API response, simplifying access to the actual data.
 *
 * @since 1.0.0
 *
 * @uses \Kraft\Beer_Slurper\API\get_untappd_data_raw() Performs the actual API request.
 * @uses is_wp_error()                                  Checks for WP_Error instances.
 * @uses is_array()                                     Validates response structure.
 *
 * @param string $endpoint  Required. The Untappd API endpoint to hit.
 * @param string $parameter Optional. Personalized endpoint parameter.
 * @param array  $args      Optional. Query string additions to the API call.
 * @param string $ver       Optional. API version number. Default 'v4'.
 *
 * @return array|false|WP_Error The response data array, false on invalid response,
 *                              or WP_Error on failure.
 */
function get_untappd_data( $endpoint, $parameter = null, array $args = null, $ver = 'v4' ){
	$response = get_untappd_data_raw( $endpoint, $parameter, $args, $ver );
	if ( is_wp_error( $response ) || ! is_array( $response ) || ! isset( $response['response'] ) ) {
		return $response;
	}
	return $response['response'];
}

/**
 * Retrieves the most recent checkin for a user.
 *
 * Fetches the single most recent checkin from a user's activity feed.
 *
 * @since 1.0.0
 *
 * @uses \Kraft\Beer_Slurper\API\get_untappd_data() Retrieves checkin data from API.
 *
 * @param string $user The Untappd username.
 *
 * @return array The latest checkin data array.
 */
function get_latest_checkin( $user ){
	$args    = array(
		'limit' => 1,
		);
	$checkin = get_untappd_data( 'user/checkins', $user, $args );
	$checkin = $checkin['checkins']['items'][0];
	return $checkin;
}

/**
 * Retrieves part of a user's activity stream.
 *
 * Fetches checkins from a user's activity feed with optional pagination
 * parameters to control which checkins are returned.
 *
 * @since 1.0.0
 *
 * @uses \Kraft\Beer_Slurper\API\get_untappd_data() Retrieves checkin data from API.
 * @uses intval()                                   Sanitizes integer parameters.
 *
 * @param string $user   Untappd username.
 * @param int    $max_id Optional. The checkin ID that you want results to start with (newest in returned set).
 * @param int    $min_id Optional. Returns only checkins newer than this value.
 * @param int    $number Optional. Number of checkins. Default 25. Max 50.
 *
 * @return array User's feed data. Checkins contained within ['items'] key.
 *               Note: Response structure varies based on parameters used.
 */
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
	/*
	 * Fair warning. Sometimes Untappd API returns checkins within an 'checkins' away, sometimes not.
	 * So far, if using the min_id arg, it will NOT use an extra checkins array.
	 * Using the max_id arg or no arg specified, it will use an extra checkins away.
	 */
	return $checkin;
}

/**
 * Retrieves detailed information about a specific beer.
 *
 * Fetches beer data from Untappd by beer ID, with options to limit
 * response size and extract specific sections.
 *
 * @since 1.0.0
 *
 * @uses \Kraft\Beer_Slurper\API\get_untappd_data() Retrieves beer data from API.
 * @uses is_wp_error()                              Checks for WP_Error instances.
 * @uses is_array()                                 Validates response structure.
 *
 * @param int    $bid     The Untappd beer ID.
 * @param bool   $compact Optional. Limits response to exclude public media, checkins, etc. Default true.
 * @param string $section Optional. Section to return ('beer' or 'brewery'). Default 'beer'.
 *
 * @return array|WP_Error Beer data array, brewery data array if section is 'brewery',
 *                        or WP_Error on failure.
 */
function get_beer_info( $bid, $compact = true, $section = 'beer' ){
	$args    = array(
		'compact' => $compact, // This limits the response to exclude public media, checkins, etc.
		);
	$info = get_untappd_data( 'beer/info', $bid, $args );

	if ( is_wp_error( $info ) || ! is_array( $info ) || ! isset( $info['beer'] ) ) {
		return is_wp_error( $info ) ? $info : new \WP_Error( 'invalid_beer_response', __( 'Invalid beer response from API.', 'beer_slurper' ) );
	}

	if ($section == 'brewery' ){
		return isset( $info['beer']['brewery'] ) ? $info['beer']['brewery'] : new \WP_Error( 'no_brewery', __( 'No brewery data in response.', 'beer_slurper' ) );
	}
	else {
		return $info['beer'];
	}
}

/**
 * Retrieves brewery information using a beer ID.
 *
 * Convenience wrapper that extracts brewery data from a beer info request.
 *
 * @since 1.0.0
 *
 * @uses \Kraft\Beer_Slurper\API\get_beer_info() Retrieves beer data with brewery section.
 *
 * @param int $bid The Untappd beer ID.
 *
 * @return array|WP_Error Brewery data array or WP_Error on failure.
 */
function get_brewery_info_by_beer( $bid ){
	$brewery = get_beer_info( $bid, true, 'brewery' );
	return $brewery;
}

/**
 * Retrieves detailed information about a specific brewery.
 *
 * Fetches brewery data directly from Untappd by brewery ID.
 *
 * @since 1.0.0
 *
 * @uses \Kraft\Beer_Slurper\API\get_untappd_data() Retrieves brewery data from API.
 * @uses is_wp_error()                              Checks for WP_Error instances.
 * @uses is_array()                                 Validates response structure.
 *
 * @param int  $breweryid The Untappd brewery ID.
 * @param bool $compact   Optional. Limits response size. Default true.
 *
 * @return array|WP_Error Brewery data array or WP_Error on failure.
 */
function get_brewery_info( $breweryid, $compact = true ){
	$args = array(
		'compact' => $compact,
		);
	$info = get_untappd_data( 'brewery/info', $breweryid, $args);

	if ( is_wp_error( $info ) || ! is_array( $info ) || ! isset( $info['brewery'] ) ) {
		return is_wp_error( $info ) ? $info : new \WP_Error( 'invalid_brewery_response', __( 'Invalid brewery response from API.', 'beer_slurper' ) );
	}

	return $info['brewery'];
}

/**
 * Validates that an API endpoint is supported.
 *
 * Ensures the specified endpoint is in the list of known valid Untappd API
 * endpoints to prevent invalid API requests.
 *
 * @since 1.0.0
 *
 * @uses in_array() Checks endpoint against valid endpoints list.
 *
 * @param string $endpoint  Required. The Untappd API endpoint to validate.
 * @param string $parameter Optional. Personalized endpoint parameter.
 * @param string $ver       Optional. API version number. Default 'v4'.
 *
 * @return string|WP_Error The validated endpoint string or WP_Error if invalid.
 */
function validate_endpoint( $endpoint, $parameter, $ver ){

	if ( ! $endpoint ){
		return new \WP_Error( 'nothing', __( "You've indiated no endpoint. I'm not going to guess.", "beer_slurper") );
	}

	if ( $ver != 'v4') {
		return $endpoint; // We aren't validating other versions at this time. Just look the other way.
	}
	$valid_endpoints = array(
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
		'checkin/view',
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
	if ( ! in_array( $endpoint, $valid_endpoints, true ) ) {
		$endpoint = new \WP_Error( 'invalid_endpoint', __( 'The specified endpoint is not on the shortlist.', 'beer_slurper' ) );
	}
	return $endpoint;
}
