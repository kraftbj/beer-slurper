<?php
/**
 * HTTP Helper Tests for Beer Slurper
 *
 * Tests for the HTTP helper functions including caching,
 * request building, and URL fetching.
 *
 * @package Kraft\Beer_Slurper\HTTP
 */

namespace Kraft\Beer_Slurper\HTTP;

use Kraft\Beer_Slurper as Base;
use Kraft\Beer_Slurper\Tests\MockHttpClient;

/**
 * Tests for the HTTP helper functions.
 */
class HTTP_Tests extends Base\TestCase {

	protected $testFiles = [
		'functions/http.php',
	];

	protected $use_mock_http = true;

	/**
	 * Tests get_cached() returns false when no cache exists.
	 */
	public function test_get_cached_returns_false_when_no_cache_exists() {
		$result = get_cached( 'test', 'nonexistent_key' );

		$this->assertFalse( $result );
	}

	/**
	 * Tests get_cached() returns cached data when it exists.
	 */
	public function test_get_cached_returns_cached_data_when_exists() {
		$test_data = array( 'foo' => 'bar', 'count' => 42 );

		// Set data via transient directly to simulate existing cache
		set_transient( 'beer_slurper_test_' . md5( 'my_key' ), $test_data, 3600 );

		$result = get_cached( 'test', 'my_key' );

		$this->assertEquals( $test_data, $result );
	}

	/**
	 * Tests get_cached() uses MD5 hashing by default.
	 */
	public function test_get_cached_uses_md5_hashing_by_default() {
		$test_data = 'hashed_value';
		$key = 'some_key';
		$expected_transient = 'beer_slurper_prefix_' . md5( $key );

		set_transient( $expected_transient, $test_data, 3600 );

		$result = get_cached( 'prefix', $key );

		$this->assertEquals( $test_data, $result );
	}

	/**
	 * Tests get_cached() skips MD5 hashing with hash_key false.
	 */
	public function test_get_cached_skips_md5_with_hash_key_false() {
		$test_data = 'unhashed_value';
		$key = 'raw_key';
		$expected_transient = 'beer_slurper_prefix_' . $key;

		set_transient( $expected_transient, $test_data, 3600 );

		$result = get_cached( 'prefix', $key, false );

		$this->assertEquals( $test_data, $result );
	}

	/**
	 * Tests set_cached() stores data correctly.
	 */
	public function test_set_cached_stores_data_correctly() {
		$test_data = array( 'stored' => true );

		$result = set_cached( 'cache', 'store_key', $test_data, 3600 );

		$this->assertTrue( $result );

		// Verify via direct transient lookup
		$stored = get_transient( 'beer_slurper_cache_' . md5( 'store_key' ) );
		$this->assertEquals( $test_data, $stored );
	}

	/**
	 * Tests set_cached() data is retrievable via get_cached().
	 */
	public function test_set_cached_data_retrievable_via_get_cached() {
		$test_data = array( 'name' => 'Test Beer', 'abv' => 5.5 );

		set_cached( 'beer', 'beer_123', $test_data, 3600 );

		$retrieved = get_cached( 'beer', 'beer_123' );

		$this->assertEquals( $test_data, $retrieved );
	}

	/**
	 * Tests build_request_args() with HTML type.
	 */
	public function test_build_request_args_html_type() {
		$args = build_request_args( 'html' );

		$this->assertEquals( 30, $args['timeout'] );
		$this->assertEquals( 5, $args['redirection'] );
		$this->assertEquals( SCRAPER_USER_AGENT, $args['user-agent'] );
		$this->assertStringContainsString( 'text/html', $args['headers']['Accept'] );
		$this->assertEquals( 'en-US,en;q=0.5', $args['headers']['Accept-Language'] );
		$this->assertEquals( 'no-cache', $args['headers']['Cache-Control'] );
		$this->assertEquals( '1', $args['headers']['DNT'] );
	}

	/**
	 * Tests build_request_args() with RSS type.
	 */
	public function test_build_request_args_rss_type() {
		$args = build_request_args( 'rss' );

		$this->assertEquals( 30, $args['timeout'] );
		$this->assertEquals( SCRAPER_USER_AGENT, $args['user-agent'] );
		$this->assertStringContainsString( 'application/rss+xml', $args['headers']['Accept'] );
		$this->assertEquals( 'no-cache', $args['headers']['Cache-Control'] );
		$this->assertArrayNotHasKey( 'Accept-Language', $args['headers'] );
		$this->assertArrayNotHasKey( 'DNT', $args['headers'] );
	}

	/**
	 * Tests build_request_args() with API type.
	 */
	public function test_build_request_args_api_type() {
		$args = build_request_args( 'api' );

		$this->assertEquals( 30, $args['timeout'] );
		$this->assertEquals( SCRAPER_USER_AGENT, $args['user-agent'] );
		$this->assertEquals( 'application/json', $args['headers']['Accept'] );
		$this->assertEquals( 'no-cache', $args['headers']['Cache-Control'] );
		$this->assertArrayNotHasKey( 'Accept-Language', $args['headers'] );
		$this->assertArrayNotHasKey( 'DNT', $args['headers'] );
	}

	/**
	 * Tests build_request_args() defaults to HTML type.
	 */
	public function test_build_request_args_defaults_to_html() {
		$default_args = build_request_args();
		$html_args = build_request_args( 'html' );

		$this->assertEquals( $html_args, $default_args );
	}

	/**
	 * Tests fetch_url() returns body and headers on success.
	 */
	public function test_fetch_url_returns_body_and_headers_on_success() {
		$body = '<html><body>Test content</body></html>';

		MockHttpClient::mock( '*example.com*', array(
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'headers'  => array(
				'content-type' => 'text/html',
				'x-custom'     => 'header-value',
			),
			'body'     => $body,
		) );

		$result = fetch_url( 'https://example.com/page', 'html', 'Test' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'body', $result );
		$this->assertArrayHasKey( 'headers', $result );
		$this->assertEquals( $body, $result['body'] );
	}

	/**
	 * Tests fetch_url() returns WP_Error on HTTP error.
	 */
	public function test_fetch_url_returns_wp_error_on_http_error() {
		MockHttpClient::mock_error( '*example.com*', 'http_request_failed', 'Connection refused' );

		$result = fetch_url( 'https://example.com/page', 'html', 'Test' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'http_request_failed', $result->get_error_code() );
	}

	/**
	 * Tests fetch_url() returns WP_Error on non-200 status.
	 */
	public function test_fetch_url_returns_wp_error_on_non_200_status() {
		MockHttpClient::mock( '*example.com*', array(
			'response' => array(
				'code'    => 404,
				'message' => 'Not Found',
			),
			'headers'  => array(),
			'body'     => 'Page not found',
		) );

		$result = fetch_url( 'https://example.com/missing', 'html', 'Test' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'http_error', $result->get_error_code() );
		$this->assertStringContainsString( '404', $result->get_error_message() );
	}
}
