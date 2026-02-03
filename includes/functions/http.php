<?php
/**
 * HTTP Helper Functions
 *
 * Provides shared HTTP request functionality including caching,
 * request building, and unified fetch operations for RSS, scraping,
 * and API requests.
 *
 * @package Kraft\Beer_Slurper\HTTP
 */
namespace Kraft\Beer_Slurper\HTTP;

/**
 * User agent string identifying this as a personal beer log backup tool.
 * This makes the non-commercial, personal use nature clear.
 */
const SCRAPER_USER_AGENT = 'personal-beerlog-backup/1.0 (WordPress plugin for personal beer history; non-commercial; https://github.com/kraftbj/beer-slurper)';

/**
 * Cache duration for RSS feed (5 minutes - more frequent polling).
 */
const CACHE_DURATION_RSS = 300;

/**
 * Cache duration for scraped data (15 minutes).
 */
const CACHE_DURATION_SCRAPE = 900;

/**
 * Cache duration for API responses (1 hour).
 */
const CACHE_DURATION_API = 3600;

/**
 * Cache duration for entity data like beer/brewery info (1 hour).
 */
const CACHE_DURATION_ENTITY = 3600;

/**
 * Retrieves a cached value using a prefixed transient.
 *
 * @param string $prefix   The cache prefix (e.g., 'rss', 'scrape', 'api').
 * @param string $key      The cache key (typically a URL or identifier).
 * @param bool   $hash_key Whether to hash the key. Default true.
 *
 * @return mixed The cached value, or false if not found.
 */
function get_cached( $prefix, $key, $hash_key = true ) {
	$cache_key = 'beer_slurper_' . $prefix . '_' . ( $hash_key ? md5( $key ) : $key );
	return get_transient( $cache_key );
}

/**
 * Stores a value in the cache using a prefixed transient.
 *
 * @param string $prefix     The cache prefix (e.g., 'rss', 'scrape', 'api').
 * @param string $key        The cache key (typically a URL or identifier).
 * @param mixed  $data       The data to cache.
 * @param int    $expiration The cache expiration time in seconds.
 * @param bool   $hash_key   Whether to hash the key. Default true.
 *
 * @return bool True if the value was set, false otherwise.
 */
function set_cached( $prefix, $key, $data, $expiration, $hash_key = true ) {
	$cache_key = 'beer_slurper_' . $prefix . '_' . ( $hash_key ? md5( $key ) : $key );
	return set_transient( $cache_key, $data, $expiration );
}

/**
 * Builds HTTP request arguments for different request types.
 *
 * @param string $type The request type: 'html', 'rss', or 'api'.
 *
 * @return array The wp_remote_get compatible arguments array.
 */
function build_request_args( $type = 'html' ) {
	$args = array(
		'timeout'     => 30,
		'redirection' => 5,
		'user-agent'  => SCRAPER_USER_AGENT,
	);

	switch ( $type ) {
		case 'rss':
			$args['headers'] = array(
				'Accept'        => 'application/rss+xml, application/xml, text/xml, */*',
				'Cache-Control' => 'no-cache',
			);
			break;

		case 'api':
			$args['headers'] = array(
				'Accept'        => 'application/json',
				'Cache-Control' => 'no-cache',
			);
			break;

		case 'html':
		default:
			$args['headers'] = array(
				'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Language' => 'en-US,en;q=0.5',
				'Cache-Control'   => 'no-cache',
				'DNT'             => '1',
			);
			break;
	}

	return $args;
}

/**
 * Fetches a URL with unified error handling.
 *
 * @param string $url         The URL to fetch.
 * @param string $type        The request type: 'html', 'rss', or 'api'.
 * @param string $log_context The context string for log messages.
 *
 * @return array|WP_Error Response array with 'body' and 'headers', or WP_Error on failure.
 */
function fetch_url( $url, $type = 'html', $log_context = 'HTTP' ) {
	$args     = build_request_args( $type );
	$response = wp_remote_get( $url, $args );

	if ( is_wp_error( $response ) ) {
		\beer_slurper_log( 'Beer Slurper ' . $log_context . ': Request failed - ' . $response->get_error_message() );
		return $response;
	}

	$status_code = wp_remote_retrieve_response_code( $response );

	if ( 200 !== $status_code ) {
		\beer_slurper_log( 'Beer Slurper ' . $log_context . ': HTTP ' . $status_code . ' for ' . $url );
		return new \WP_Error(
			'http_error',
			sprintf( __( 'HTTP error %d when fetching %s', 'beer_slurper' ), $status_code, $url )
		);
	}

	return array(
		'body'    => wp_remote_retrieve_body( $response ),
		'headers' => wp_remote_retrieve_headers( $response ),
	);
}
