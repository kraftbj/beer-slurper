<?php
/**
 * OAuth Tests for Beer Slurper
 *
 * Tests for the OAuth authentication functions including URL construction,
 * token storage, and connection state management.
 *
 * @package Kraft\Beer_Slurper\OAuth
 */

namespace Kraft\Beer_Slurper\OAuth;

use Kraft\Beer_Slurper as Base;

/**
 * Tests for the OAuth functions.
 */
class OAuth_Tests extends Base\TestCase {

	protected $testFiles = [
		'functions/oauth.php',
	];

	/**
	 * Tests get_redirect_url() returns REST API endpoint.
	 */
	public function test_get_redirect_url_returns_rest_endpoint() {
		$url = get_redirect_url();

		$this->assertStringContainsString( 'beer-slurper/v1/oauth/callback', $url );
	}

	/**
	 * Tests get_settings_url() returns admin settings page URL.
	 */
	public function test_get_settings_url_returns_admin_url() {
		$url = get_settings_url();

		$this->assertStringContainsString( 'options-general.php', $url );
		$this->assertStringContainsString( 'page=beer-slurper-settings', $url );
	}

	/**
	 * Tests get_authorize_url() returns false when no client ID.
	 */
	public function test_get_authorize_url_returns_false_without_client_id() {
		$result = get_authorize_url();

		$this->assertFalse( $result );
	}

	/**
	 * Tests get_authorize_url() constructs proper URL.
	 */
	public function test_get_authorize_url_constructs_proper_url() {
		update_option( 'beer-slurper-key', 'test_client_id' );

		$url = get_authorize_url();

		$this->assertStringStartsWith( 'https://untappd.com/oauth/authenticate/', $url );
		$this->assertStringContainsString( 'client_id=test_client_id', $url );
		$this->assertStringContainsString( 'response_type=code', $url );
		$this->assertStringContainsString( 'redirect_url=', $url );
	}

	/**
	 * Tests get_access_token() returns stored token.
	 */
	public function test_get_access_token_returns_stored_token() {
		update_option( 'beer-slurper-access-token', 'stored_token_123' );

		$result = get_access_token();

		$this->assertEquals( 'stored_token_123', $result );
	}

	/**
	 * Tests get_access_token() returns false when not set.
	 */
	public function test_get_access_token_returns_false_when_not_set() {
		$result = get_access_token();

		$this->assertFalse( $result );
	}

	/**
	 * Tests is_connected() returns true when token exists.
	 */
	public function test_is_connected_returns_true_with_token() {
		update_option( 'beer-slurper-access-token', 'valid_token' );

		$result = is_connected();

		$this->assertTrue( $result );
	}

	/**
	 * Tests is_connected() returns false when no token.
	 */
	public function test_is_connected_returns_false_without_token() {
		$result = is_connected();

		$this->assertFalse( $result );
	}

	/**
	 * Tests REST route registration function exists.
	 *
	 * Note: Full REST route testing requires a complete WP environment.
	 * We verify the function exists, as the hook is added when the file
	 * is first loaded but may be cleared by test isolation.
	 */
	public function test_rest_route_function_exists() {
		$this->assertTrue(
			function_exists( 'Kraft\Beer_Slurper\OAuth\register_rest_route' ),
			'register_rest_route function should exist'
		);
	}
}
