<?php
/**
 * Mock HTTP Client for Beer Slurper Tests
 *
 * Intercepts wp_safe_remote_get and wp_remote_get calls during tests
 * to return predefined responses without making real HTTP requests.
 *
 * @package Kraft\Beer_Slurper\Tests
 */

namespace Kraft\Beer_Slurper\Tests;

/**
 * Mock HTTP client for testing API interactions.
 *
 * Registers filters to intercept WordPress HTTP API calls and
 * return mocked responses. Supports pattern matching on URLs.
 */
class MockHttpClient {
	/**
	 * Registered mock responses.
	 *
	 * @var array
	 */
	private static $mocks = array();

	/**
	 * Whether the filter is currently hooked.
	 *
	 * @var bool
	 */
	private static $hooked = false;

	/**
	 * Log of all intercepted requests.
	 *
	 * @var array
	 */
	private static $request_log = array();

	/**
	 * Initializes the mock HTTP client.
	 *
	 * Hooks into the WordPress HTTP API to intercept requests.
	 *
	 * @return void
	 */
	public static function init() {
		if ( self::$hooked ) {
			return;
		}

		add_filter( 'pre_http_request', array( __CLASS__, 'intercept_request' ), 10, 3 );
		self::$hooked = true;
	}

	/**
	 * Cleans up the mock HTTP client.
	 *
	 * Removes hooks and clears all registered mocks.
	 *
	 * @return void
	 */
	public static function cleanup() {
		remove_filter( 'pre_http_request', array( __CLASS__, 'intercept_request' ), 10 );
		self::$hooked = false;
		self::$mocks = array();
		self::$request_log = array();
	}

	/**
	 * Registers a mock response for a URL pattern.
	 *
	 * @param string $pattern  URL pattern to match (supports * wildcards).
	 * @param array  $response Response array matching WP HTTP API format.
	 *
	 * @return void
	 */
	public static function mock( $pattern, $response ) {
		self::$mocks[ $pattern ] = $response;
	}

	/**
	 * Registers a mock JSON response.
	 *
	 * @param string $pattern URL pattern to match.
	 * @param array  $data    Data to JSON encode as the response body.
	 * @param int    $code    HTTP status code. Default 200.
	 * @param array  $headers Optional response headers.
	 *
	 * @return void
	 */
	public static function mock_json( $pattern, $data, $code = 200, $headers = array() ) {
		$headers = array_merge(
			array(
				'content-type' => 'application/json',
			),
			$headers
		);

		self::mock(
			$pattern,
			array(
				'response' => array(
					'code'    => $code,
					'message' => 'OK',
				),
				'headers'  => $headers,
				'body'     => json_encode( $data ),
			)
		);
	}

	/**
	 * Registers a mock error response.
	 *
	 * @param string $pattern URL pattern to match.
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 *
	 * @return void
	 */
	public static function mock_error( $pattern, $code, $message ) {
		self::mock(
			$pattern,
			new \WP_Error( $code, $message )
		);
	}

	/**
	 * Intercepts HTTP requests and returns mocked responses.
	 *
	 * @param false|array|\WP_Error $preempt     Whether to preempt the request.
	 * @param array                 $parsed_args Request arguments.
	 * @param string                $url         Request URL.
	 *
	 * @return false|array|\WP_Error Mock response or false to allow real request.
	 */
	public static function intercept_request( $preempt, $parsed_args, $url ) {
		self::$request_log[] = array(
			'url'  => $url,
			'args' => $parsed_args,
			'time' => time(),
		);

		foreach ( self::$mocks as $pattern => $response ) {
			if ( self::matches_pattern( $url, $pattern ) ) {
				// If response is callable, invoke it
				if ( is_callable( $response ) ) {
					return $response( $url, $parsed_args );
				}
				return $response;
			}
		}

		// No mock matched - return false to allow real request
		// (or error in tests to ensure all API calls are mocked)
		return new \WP_Error(
			'unmocked_request',
			sprintf( 'No mock registered for URL: %s', $url )
		);
	}

	/**
	 * Checks if a URL matches a pattern.
	 *
	 * @param string $url     The URL to check.
	 * @param string $pattern The pattern to match against.
	 *
	 * @return bool True if URL matches the pattern.
	 */
	private static function matches_pattern( $url, $pattern ) {
		// Convert wildcard pattern to regex
		$regex = '/^' . str_replace(
			array( '\*', '\?' ),
			array( '.*', '.' ),
			preg_quote( $pattern, '/' )
		) . '/';

		return (bool) preg_match( $regex, $url );
	}

	/**
	 * Returns the request log.
	 *
	 * @return array All intercepted requests.
	 */
	public static function get_request_log() {
		return self::$request_log;
	}

	/**
	 * Returns requests matching a URL pattern.
	 *
	 * @param string $pattern URL pattern to match.
	 *
	 * @return array Matching requests.
	 */
	public static function get_requests_matching( $pattern ) {
		return array_filter(
			self::$request_log,
			function ( $request ) use ( $pattern ) {
				return self::matches_pattern( $request['url'], $pattern );
			}
		);
	}

	/**
	 * Clears the request log.
	 *
	 * @return void
	 */
	public static function clear_log() {
		self::$request_log = array();
	}
}
