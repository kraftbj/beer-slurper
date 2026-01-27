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
 * This is the URL Untappd will redirect back to after authorization.
 * Users must also register this URL in their Untappd app settings.
 *
 * @return string The admin settings page URL used as the OAuth callback.
 */
function get_redirect_url() {
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
 * Handles the OAuth callback from Untappd.
 *
 * Hooked to admin_init. Detects the authorization code in the query string,
 * exchanges it for an access token, stores the token, and redirects to
 * clean the URL.
 *
 * @return void
 */
function handle_callback() {
	if ( ! isset( $_GET['code'] ) || ! isset( $_GET['page'] ) || 'beer-slurper-settings' !== $_GET['page'] ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$code          = sanitize_text_field( wp_unslash( $_GET['code'] ) );
	$client_id     = get_option( 'beer-slurper-key' );
	$client_secret = get_option( 'beer-slurper-secret' );

	if ( ! $client_id || ! $client_secret ) {
		return;
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
		wp_safe_redirect( add_query_arg( 'beer-slurper-oauth-error', 'exchange_failed', get_redirect_url() ) );
		exit;
	}

	$body    = wp_remote_retrieve_body( $response );
	$decoded = json_decode( $body, true );

	if ( ! is_array( $decoded ) || empty( $decoded['response']['access_token'] ) ) {
		error_log( 'Beer Slurper OAuth: Invalid token response - ' . substr( $body, 0, 500 ) );
		wp_safe_redirect( add_query_arg( 'beer-slurper-oauth-error', 'invalid_response', get_redirect_url() ) );
		exit;
	}

	update_option( 'beer-slurper-access-token', sanitize_text_field( $decoded['response']['access_token'] ) );

	wp_safe_redirect( get_redirect_url() );
	exit;
}
add_action( 'admin_init', __NAMESPACE__ . '\handle_callback' );

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

	wp_safe_redirect( get_redirect_url() );
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
