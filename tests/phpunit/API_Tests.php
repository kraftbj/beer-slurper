<?php
/**
 * API Tests for Beer Slurper
 *
 * Tests for the Untappd API functions including raw requests,
 * response caching, rate limiting, and endpoint validation.
 *
 * @package Kraft\Beer_Slurper\API
 */

namespace Kraft\Beer_Slurper\API;

use Kraft\Beer_Slurper as Base;
use Kraft\Beer_Slurper\Tests\MockHttpClient;
use Kraft\Beer_Slurper\Tests\ApiFixtures;

/**
 * Tests for the API functions.
 */
class API_Tests extends Base\TestCase {

	protected $testFiles = [
		'functions/api.php',
		'functions/oauth.php',
	];

	protected $use_mock_http = true;

	/**
	 * Tests validate_endpoint() accepts valid endpoints.
	 */
	public function test_validate_endpoint_accepts_valid_endpoints() {
		$valid_endpoints = array(
			'user/checkins',
			'beer/info',
			'brewery/info',
			'venue/info',
			'checkin/view',
			'search/beer',
		);

		foreach ( $valid_endpoints as $endpoint ) {
			$result = validate_endpoint( $endpoint, null, 'v4' );
			$this->assertEquals( $endpoint, $result, "Endpoint {$endpoint} should be valid." );
		}
	}

	/**
	 * Tests validate_endpoint() rejects invalid endpoints.
	 */
	public function test_validate_endpoint_rejects_invalid_endpoints() {
		$result = validate_endpoint( 'invalid/endpoint', null, 'v4' );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'invalid_endpoint', $result->get_error_code() );
	}

	/**
	 * Tests validate_endpoint() rejects empty endpoint.
	 */
	public function test_validate_endpoint_rejects_empty() {
		$result = validate_endpoint( '', null, 'v4' );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'nothing', $result->get_error_code() );
	}

	/**
	 * Tests validate_endpoint() passes through non-v4 versions.
	 */
	public function test_validate_endpoint_allows_other_versions() {
		$result = validate_endpoint( 'any/endpoint', null, 'v3' );
		$this->assertEquals( 'any/endpoint', $result );
	}

	/**
	 * Tests get_untappd_data_raw() requires credentials.
	 */
	public function test_get_untappd_data_raw_requires_credentials() {
		// No credentials set
		$result = get_untappd_data_raw( 'user/checkins', 'testuser' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'lacking_creds', $result->get_error_code() );
	}

	/**
	 * Tests get_untappd_data_raw() uses access token when available.
	 */
	public function test_get_untappd_data_raw_uses_access_token() {
		$this->set_api_credentials( 'key', 'secret', 'test_token' );

		MockHttpClient::mock_json( '*api.untappd.com*', array(
			'meta'     => array( 'code' => 200 ),
			'response' => array( 'data' => 'test' ),
		) );

		$result = get_untappd_data_raw( 'user/checkins', 'testuser' );

		$requests = MockHttpClient::get_request_log();
		$this->assertCount( 1, $requests );
		$this->assertStringContainsString( 'access_token=test_token', $requests[0]['url'] );
	}

	/**
	 * Tests get_untappd_data_raw() uses client credentials when no token.
	 */
	public function test_get_untappd_data_raw_uses_client_credentials() {
		$this->set_api_credentials( 'test_key', 'test_secret' );

		MockHttpClient::mock_json( '*api.untappd.com*', array(
			'meta'     => array( 'code' => 200 ),
			'response' => array( 'data' => 'test' ),
		) );

		$result = get_untappd_data_raw( 'user/checkins', 'testuser' );

		$requests = MockHttpClient::get_request_log();
		$this->assertCount( 1, $requests );
		$this->assertStringContainsString( 'client_id=test_key', $requests[0]['url'] );
		$this->assertStringContainsString( 'client_secret=test_secret', $requests[0]['url'] );
	}

	/**
	 * Tests get_untappd_data_raw() returns parsed JSON response.
	 */
	public function test_get_untappd_data_raw_returns_parsed_json() {
		$this->set_api_credentials( 'key', 'secret', 'token' );

		$expected_data = array(
			'meta'     => array( 'code' => 200 ),
			'response' => array( 'user' => array( 'name' => 'test' ) ),
		);

		MockHttpClient::mock_json( '*api.untappd.com*', $expected_data );

		$result = get_untappd_data_raw( 'user/info', 'testuser' );

		$this->assertIsArray( $result );
		$this->assertEquals( $expected_data, $result );
	}

	/**
	 * Tests get_untappd_data_raw() returns false for invalid JSON.
	 */
	public function test_get_untappd_data_raw_returns_false_for_invalid_json() {
		$this->set_api_credentials( 'key', 'secret', 'token' );

		MockHttpClient::mock( '*api.untappd.com*', array(
			'response' => array( 'code' => 200 ),
			'headers'  => array( 'content-type' => 'text/html' ),
			'body'     => '<html>Not JSON</html>',
		) );

		$result = get_untappd_data_raw( 'user/checkins', 'testuser' );

		$this->assertFalse( $result );
	}

	/**
	 * Tests get_untappd_data_raw() returns WP_Error for API errors.
	 */
	public function test_get_untappd_data_raw_returns_error_for_api_errors() {
		$this->set_api_credentials( 'key', 'secret', 'token' );

		MockHttpClient::mock_json( '*api.untappd.com*', array(
			'meta' => array(
				'code'         => 401,
				'error_type'   => 'invalid_auth',
				'error_detail' => 'Invalid access token.',
			),
			'response' => array(),
		) );

		$result = get_untappd_data_raw( 'user/checkins', 'testuser' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'invalid_auth', $result->get_error_code() );
	}

	/**
	 * Tests get_untappd_data_raw() caches responses.
	 */
	public function test_get_untappd_data_raw_caches_responses() {
		$this->set_api_credentials( 'key', 'secret', 'token' );

		MockHttpClient::mock_json( '*api.untappd.com*', array(
			'meta'     => array( 'code' => 200 ),
			'response' => array( 'data' => 'cached' ),
		) );

		// First call makes request
		$result1 = get_untappd_data_raw( 'user/checkins', 'testuser' );

		// Clear the mock to verify second call uses cache
		MockHttpClient::clear_log();

		// Second call should use cache (no new request)
		$result2 = get_untappd_data_raw( 'user/checkins', 'testuser' );

		$this->assertEquals( $result1, $result2 );
		$requests = MockHttpClient::get_request_log();
		$this->assertCount( 0, $requests, 'Second call should use cache.' );
	}

	/**
	 * Tests get_untappd_data_raw() respects rate limit.
	 */
	public function test_get_untappd_data_raw_respects_rate_limit() {
		$this->set_api_credentials( 'key', 'secret', 'token' );

		// Simulate 90+ API calls already made
		set_transient( 'beer_slurper_api_calls', 91, HOUR_IN_SECONDS );
		set_transient( 'beer_slurper_api_window_end', time() + HOUR_IN_SECONDS, HOUR_IN_SECONDS );

		$result = get_untappd_data_raw( 'user/checkins', 'testuser' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'rate_limited', $result->get_error_code() );
	}

	/**
	 * Tests get_untappd_data_raw() syncs rate limit from headers.
	 */
	public function test_get_untappd_data_raw_syncs_rate_limit_from_headers() {
		$this->set_api_credentials( 'key', 'secret', 'token' );

		MockHttpClient::mock_json(
			'*api.untappd.com*',
			array(
				'meta'     => array( 'code' => 200 ),
				'response' => array(),
			),
			200,
			array( 'x-ratelimit-remaining' => '75' )
		);

		get_untappd_data_raw( 'user/checkins', 'testuser' );

		// API says 75 remaining = 25 used (100 - 75)
		$used = get_transient( 'beer_slurper_api_calls' );
		$this->assertEquals( 25, $used );
	}

	/**
	 * Tests get_untappd_data_raw() accepts null args parameter.
	 *
	 * Note: PHP 8+ enforces type hints, so non-array args throw TypeError.
	 * This test verifies null is accepted as expected.
	 */
	public function test_get_untappd_data_raw_accepts_null_args() {
		$this->set_api_credentials( 'key', 'secret', 'token' );

		MockHttpClient::mock_json( '*api.untappd.com*', array(
			'meta'     => array( 'code' => 200 ),
			'response' => array( 'data' => 'test' ),
		) );

		$result = get_untappd_data_raw( 'user/checkins', 'testuser', null );

		$this->assertIsArray( $result );
	}

	/**
	 * Tests get_untappd_data() extracts response section.
	 */
	public function test_get_untappd_data_extracts_response() {
		$this->set_api_credentials( 'key', 'secret', 'token' );

		$response_data = array( 'checkins' => array( 'items' => array() ) );

		MockHttpClient::mock_json( '*api.untappd.com*', array(
			'meta'     => array( 'code' => 200 ),
			'response' => $response_data,
		) );

		$result = get_untappd_data( 'user/checkins', 'testuser' );

		$this->assertEquals( $response_data, $result );
	}

	/**
	 * Tests get_beer_info() returns beer data.
	 */
	public function test_get_beer_info_returns_beer_data() {
		$this->set_api_credentials( 'key', 'secret', 'token' );

		$beer_info = ApiFixtures::beer_info();
		MockHttpClient::mock_json( '*api.untappd.com*/v4/beer/info/*', $beer_info );

		$result = get_beer_info( 12345 );

		$this->assertIsArray( $result );
		$this->assertEquals( 'Test IPA', $result['beer_name'] );
		$this->assertEquals( 12345, $result['bid'] );
	}

	/**
	 * Tests get_beer_info() returns brewery section when requested.
	 */
	public function test_get_beer_info_returns_brewery_section() {
		$this->set_api_credentials( 'key', 'secret', 'token' );

		$beer_info = ApiFixtures::beer_info();
		MockHttpClient::mock_json( '*api.untappd.com*/v4/beer/info/*', $beer_info );

		$result = get_beer_info( 12345, true, 'brewery' );

		$this->assertIsArray( $result );
		$this->assertEquals( 'Test Brewing Company', $result['brewery_name'] );
	}

	/**
	 * Tests get_brewery_info() returns brewery data.
	 */
	public function test_get_brewery_info_returns_brewery_data() {
		$this->set_api_credentials( 'key', 'secret', 'token' );

		$brewery_info = ApiFixtures::brewery_info();
		MockHttpClient::mock_json( '*api.untappd.com*/v4/brewery/info/*', $brewery_info );

		$result = get_brewery_info( 54321 );

		$this->assertIsArray( $result );
		$this->assertEquals( 'Test Brewing Company', $result['brewery_name'] );
		$this->assertEquals( 54321, $result['brewery_id'] );
	}

	/**
	 * Tests get_venue_info() returns venue data.
	 */
	public function test_get_venue_info_returns_venue_data() {
		$this->set_api_credentials( 'key', 'secret', 'token' );

		$venue_info = ApiFixtures::venue_info();
		MockHttpClient::mock_json( '*api.untappd.com*/v4/venue/info/*', $venue_info );

		$result = get_venue_info( 99999 );

		$this->assertIsArray( $result );
		$this->assertEquals( 'Test Taproom', $result['venue_name'] );
		$this->assertEquals( 99999, $result['venue_id'] );
	}

	/**
	 * Tests get_checkins() with default parameters.
	 */
	public function test_get_checkins_default_params() {
		$this->set_api_credentials( 'key', 'secret', 'token' );

		$checkin = ApiFixtures::checkin();
		$response = ApiFixtures::user_checkins_response( array( $checkin ) );

		MockHttpClient::mock_json( '*api.untappd.com*/v4/user/checkins/*', $response );

		$result = get_checkins( 'testuser' );

		$this->assertIsArray( $result );
		$this->assertEquals( 1, $result['checkins']['count'] );
	}

	/**
	 * Tests get_checkins() with max_id parameter.
	 */
	public function test_get_checkins_with_max_id() {
		$this->set_api_credentials( 'key', 'secret', 'token' );

		MockHttpClient::mock_json( '*api.untappd.com*', array(
			'meta'     => array( 'code' => 200 ),
			'response' => array( 'checkins' => array( 'count' => 0, 'items' => array() ) ),
		) );

		get_checkins( 'testuser', 12345 );

		$requests = MockHttpClient::get_request_log();
		$this->assertStringContainsString( 'max_id=12345', $requests[0]['url'] );
	}

	/**
	 * Tests get_checkins() with min_id parameter.
	 */
	public function test_get_checkins_with_min_id() {
		$this->set_api_credentials( 'key', 'secret', 'token' );

		MockHttpClient::mock_json( '*api.untappd.com*', array(
			'meta'     => array( 'code' => 200 ),
			'response' => array( 'count' => 0, 'items' => array() ),
		) );

		get_checkins( 'testuser', null, 12345 );

		$requests = MockHttpClient::get_request_log();
		$this->assertStringContainsString( 'min_id=12345', $requests[0]['url'] );
	}

	/**
	 * Tests get_latest_checkin() returns single checkin.
	 */
	public function test_get_latest_checkin_returns_single() {
		$this->set_api_credentials( 'key', 'secret', 'token' );

		$checkin = ApiFixtures::checkin( array( 'checkin_id' => 999 ) );
		$response = ApiFixtures::user_checkins_response( array( $checkin ) );

		MockHttpClient::mock_json( '*api.untappd.com*/v4/user/checkins/*', $response );

		$result = get_latest_checkin( 'testuser' );

		$this->assertEquals( 999, $result['checkin_id'] );
	}
}
