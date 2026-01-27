<?php
namespace Kraft\Beer_Slurper\OAuth;

/**
 * Untappd OAuth Functions
 *
 * Handles OAuth authentication with Untappd, including authorization,
 * token exchange, and connection management.
 *
 * @package Kraft\Beer_Slurper
 */

/**
 * Returns the OAuth redirect URL (callback URL).
 *
 * Uses a REST API endpoint to avoid query parameter conflicts.
 * Untappd appends ?code=X to the callback, so the base URL must
 * not contain query parameters.
 *
 * @return string The REST API endpoint URL used as the OAuth callback.
 */
function get_redirect_url() {
	return rest_url( 'beer-slurper/v1/oauth/callback' );
}

/**
 * Returns the settings page URL.
 *
 * @return string The admin settings page URL.
 */
function get_settings_url() {
	return admin_url( 'options-general.php?page=beer-slurper-settings' );
}

/**
 * Builds the Untappd OAuth authorization URL.
 *
 * Constructs the URL that initiates the OAuth flow by redirecting the user
 * to Untappd to authorize the application.
 *
 * @return string|false The authorization URL, or false if client_id is not configured.
 */
function get_authorize_url() {
	$client_id = get_option( 'beer-slurper-key' );

	if ( ! $client_id ) {
		return false;
	}

	return add_query_arg(
		array(
			'client_id'     => $client_id,
			'response_type' => 'code',
			'redirect_url'  => get_redirect_url(),
		),
		'https://untappd.com/oauth/authenticate/'
	);
}

/**
 * Registers the REST API route for the OAuth callback.
 *
 * @return void
 */
function register_rest_route() {
	\register_rest_route( 'beer-slurper/v1', '/oauth/callback', array(
		'methods'             => 'GET',
		'callback'            => __NAMESPACE__ . '\handle_callback',
		'permission_callback' => '__return_true',
	) );
}
add_action( 'rest_api_init', __NAMESPACE__ . '\register_rest_route' );

/**
 * Handles the OAuth callback from Untappd.
 *
 * Receives the authorization code via the REST API endpoint,
 * exchanges it for an access token, stores the token, and redirects
 * to the settings page.
 *
 * @param \WP_REST_Request $request The REST request object.
 * @return \WP_REST_Response|\WP_Error Response on failure, or redirect on success.
 */
function handle_callback( $request ) {
	$code = $request->get_param( 'code' );

	if ( ! $code ) {
		wp_safe_redirect( add_query_arg( 'beer-slurper-oauth-error', 'missing_code', get_settings_url() ) );
		exit;
	}

	// REST API cookie auth requires a nonce, which isn't present on OAuth
	// redirects. Fall back to validating the logged-in cookie directly.
	$user_id = current_user_can( 'manage_options' )
		? get_current_user_id()
		: wp_validate_auth_cookie( '', 'logged_in' );

	if ( ! $user_id || ! user_can( $user_id, 'manage_options' ) ) {
		wp_safe_redirect( add_query_arg( 'beer-slurper-oauth-error', 'unauthorized', get_settings_url() ) );
		exit;
	}

	$code          = sanitize_text_field( $code );
	$client_id     = get_option( 'beer-slurper-key' );
	$client_secret = get_option( 'beer-slurper-secret' );

	if ( ! $client_id || ! $client_secret ) {
		wp_safe_redirect( add_query_arg( 'beer-slurper-oauth-error', 'missing_credentials', get_settings_url() ) );
		exit;
	}

	$token_url = add_query_arg(
		array(
			'client_id'     => $client_id,
			'client_secret' => $client_secret,
			'response_type' => 'code',
			'redirect_url'  => get_redirect_url(),
			'code'          => $code,
		),
		'https://untappd.com/oauth/authorize/'
	);

	$response = wp_remote_get( $token_url );

	if ( is_wp_error( $response ) ) {
		error_log( 'Beer Slurper OAuth: Token exchange failed - ' . $response->get_error_message() );
		wp_safe_redirect( add_query_arg( 'beer-slurper-oauth-error', 'exchange_failed', get_settings_url() ) );
		exit;
	}

	$body    = wp_remote_retrieve_body( $response );
	$decoded = json_decode( $body, true );

	if ( ! is_array( $decoded ) || empty( $decoded['response']['access_token'] ) ) {
		error_log( 'Beer Slurper OAuth: Invalid token response - ' . substr( $body, 0, 500 ) );
		wp_safe_redirect( add_query_arg( 'beer-slurper-oauth-error', 'invalid_response', get_settings_url() ) );
		exit;
	}

	$access_token = sanitize_text_field( $decoded['response']['access_token'] );
	update_option( 'beer-slurper-access-token', $access_token );

	// Fetch the authenticated user's username from the API.
	$user_response = wp_safe_remote_get(
		add_query_arg( 'access_token', $access_token, 'https://api.untappd.com/v4/user/info/' )
	);

	if ( ! is_wp_error( $user_response ) ) {
		$user_body    = wp_remote_retrieve_body( $user_response );
		$user_decoded = json_decode( $user_body, true );

		if ( is_array( $user_decoded ) && ! empty( $user_decoded['response']['user']['user_name'] ) ) {
			$username = sanitize_user( $user_decoded['response']['user']['user_name'] );
			update_option( 'beer-slurper-user', $username );
			\bs_start_import( $username );
		}
	}

	wp_safe_redirect( get_settings_url() );
	exit;
}

/**
 * Handles disconnecting the OAuth token.
 *
 * Hooked to admin_init. Detects the disconnect action, verifies the nonce,
 * deletes the stored token, and redirects to clean the URL.
 *
 * @return void
 */
function disconnect() {
	if ( ! isset( $_GET['beer-slurper-disconnect'] ) || ! isset( $_GET['page'] ) || 'beer-slurper-settings' !== $_GET['page'] ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'beer_slurper_disconnect' ) ) {
		return;
	}

	delete_option( 'beer-slurper-access-token' );
	delete_option( 'beer-slurper-user' );

	wp_safe_redirect( get_settings_url() );
	exit;
}
add_action( 'admin_init', __NAMESPACE__ . '\disconnect' );

/**
 * Returns the stored OAuth access token.
 *
 * @return string|false The access token, or false if not set.
 */
function get_access_token() {
	return get_option( 'beer-slurper-access-token' );
}

/**
 * Checks whether a valid OAuth token exists.
 *
 * @return bool True if an access token is stored, false otherwise.
 */
function is_connected() {
	return (bool) get_access_token();
}
