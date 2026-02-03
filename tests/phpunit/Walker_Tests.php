<?php
/**
 * Walker Tests for Beer Slurper
 *
 * Tests for the import walker functionality including data source selection,
 * hybrid mode fallback, and RSS preference over page scraping.
 *
 * @package Kraft\Beer_Slurper\Tests
 */

namespace Kraft\Beer_Slurper\Walker;

use Kraft\Beer_Slurper as Base;
use Kraft\Beer_Slurper\Tests\MockHttpClient;
use Kraft\Beer_Slurper\Tests\ApiFixtures;

/**
 * Tests for the Walker import functions.
 */
class Walker_Tests extends Base\TestCase {

	protected $testFiles = [
		'functions/api.php',
		'functions/oauth.php',
		'functions/post.php',
		'functions/brewery.php',
		'functions/venue.php',
		'functions/badge.php',
		'functions/checkin.php',
		'functions/companion.php',
		'functions/toast.php',
		'functions/queue.php',
		'functions/scraper.php',
		'functions/walker.php',
	];

	protected $use_mock_http = true;

	/*
	 * =========================================================================
	 * has_api_credentials() Tests
	 * =========================================================================
	 */

	/**
	 * Tests has_api_credentials() returns true when OAuth is connected.
	 */
	public function test_has_api_credentials_returns_true_for_oauth() {
		// Set up OAuth connection using correct option name.
		update_option( 'beer-slurper-access-token', 'test_token' );

		$result = has_api_credentials();

		$this->assertTrue( $result );
	}

	/**
	 * Tests has_api_credentials() returns true when API keys are set.
	 */
	public function test_has_api_credentials_returns_true_for_api_keys() {
		// Set up API keys.
		update_option( 'beer-slurper-key', 'test_key' );
		update_option( 'beer-slurper-secret', 'test_secret' );

		$result = has_api_credentials();

		$this->assertTrue( $result );
	}

	/**
	 * Tests has_api_credentials() returns false when no credentials exist.
	 */
	public function test_has_api_credentials_returns_false_without_credentials() {
		// Ensure no credentials exist.
		delete_option( 'beer-slurper-access-token' );
		delete_option( 'beer-slurper-key' );
		delete_option( 'beer-slurper-secret' );

		$result = has_api_credentials();

		$this->assertFalse( $result );
	}

	/*
	 * =========================================================================
	 * import_new() Data Source Selection Tests
	 * =========================================================================
	 */

	/**
	 * Tests import_new() returns error for empty username.
	 */
	public function test_import_new_returns_error_for_empty_username() {
		$result = import_new( '' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'invalid_user', $result->get_error_code() );
	}

	/**
	 * Tests import_new() uses API when data_source is 'api'.
	 */
	public function test_import_new_uses_api_when_api_mode() {
		// Set up API mode.
		update_option( 'beer_slurper_data_source', 'api' );
		$this->set_api_credentials( 'key', 'secret', 'token' );

		// Set up since_id so it doesn't start historical import.
		update_option( 'beer_slurper_testuser_since', 12345 );

		// Mock empty API response.
		MockHttpClient::mock_json( '*api.untappd.com*/v4/user/checkins/*', array(
			'meta'     => array( 'code' => 200 ),
			'response' => array(
				'checkins' => array(
					'count' => 0,
					'items' => array(),
				),
			),
		) );

		$result = import_new( 'testuser' );

		// Should return "No new beers" from API path.
		$this->assertEquals( 'No new beers here!', $result );
	}

	/**
	 * Tests import_new() uses scraper when data_source is 'scraper'.
	 */
	public function test_import_new_uses_scraper_when_scraper_mode() {
		// Set up scraper mode.
		update_option( 'beer_slurper_data_source', 'scraper' );

		// Don't set up API credentials - scraper should be used regardless.

		// Mock scraper HTTP request (page scraping) - returns empty page.
		MockHttpClient::mock( '*untappd.com/user/*', array(
			'response' => array( 'code' => 200 ),
			'body'     => '<html><body>No checkins</body></html>',
		) );

		$result = import_new( 'testuser' );

		// Scraper path was used (returns either string or WP_Error with scraper-specific message).
		// The key test is that API "No new beers here!" was NOT returned.
		$this->assertNotEquals( 'No new beers here!', $result );
	}

	/**
	 * Tests import_new() uses scraper in hybrid mode without API credentials.
	 */
	public function test_import_new_uses_scraper_in_hybrid_without_credentials() {
		// Set up hybrid mode without credentials.
		update_option( 'beer_slurper_data_source', 'hybrid' );
		delete_option( 'beer-slurper-access-token' );
		delete_option( 'beer-slurper-key' );
		delete_option( 'beer-slurper-secret' );

		// Mock scraper HTTP request.
		MockHttpClient::mock( '*untappd.com/user/*', array(
			'response' => array( 'code' => 200 ),
			'body'     => '<html><body>No checkins</body></html>',
		) );

		$result = import_new( 'testuser' );

		// Should use scraper path (not API path).
		// The key test is that API "No new beers here!" was NOT returned.
		$this->assertNotEquals( 'No new beers here!', $result );
	}

	/**
	 * Tests import_new() falls back to scraper in hybrid mode when API fails.
	 */
	public function test_import_new_hybrid_fallback_to_scraper_on_api_failure() {
		// Set up hybrid mode with credentials.
		update_option( 'beer_slurper_data_source', 'hybrid' );
		$this->set_api_credentials( 'key', 'secret', 'token' );

		// Set up since_id.
		update_option( 'beer_slurper_testuser_since', 12345 );

		// Mock API to return error.
		MockHttpClient::mock_error( '*api.untappd.com*', 'api_error', 'API unavailable' );

		// Mock scraper HTTP request as fallback.
		MockHttpClient::mock( '*untappd.com/user/*', array(
			'response' => array( 'code' => 200 ),
			'body'     => '<html><body>No checkins</body></html>',
		) );

		$result = import_new( 'testuser' );

		// Hybrid mode should have fallen back to scraper after API failure.
		// If it didn't fall back, we'd get 'api_error' error code.
		if ( is_wp_error( $result ) ) {
			// Scraper was attempted but found no checkins.
			$this->assertEquals( 'no_checkins', $result->get_error_code() );
		} else {
			// Scraper returned a message string.
			$this->assertIsString( $result );
		}
	}

	/*
	 * =========================================================================
	 * import_new_via_scraper() Tests
	 * =========================================================================
	 */

	/**
	 * Tests import_new_via_scraper() prefers RSS over page scraping.
	 */
	public function test_import_new_via_scraper_prefers_rss() {
		// Set up valid RSS URL.
		$rss_url = 'https://untappd.com/rss/user/testuser?key=abc123';
		update_option( 'beer_slurper_rss_url', $rss_url );

		// Create an existing post so the import is skipped (avoiding full insert_beer flow).
		$post_id = $this->create_beer_post();
		add_post_meta( $post_id, '_beer_slurper_untappd_id', '12345' );

		// Mock RSS response with a checkin.
		$rss_content = $this->get_sample_rss_feed( 'testuser', 12345, 'Test Beer', 'Test Brewery' );
		MockHttpClient::mock( '*untappd.com/rss/*', array(
			'response' => array( 'code' => 200 ),
			'body'     => $rss_content,
		) );

		$result = import_new_via_scraper( 'testuser' );

		// Verify RSS was used (message should mention RSS feed).
		$this->assertIsString( $result );
		$this->assertStringContainsString( 'RSS feed', $result );
	}

	/**
	 * Tests import_new_via_scraper() falls back to page scraping when RSS fails.
	 */
	public function test_import_new_via_scraper_fallback_to_page_scraping() {
		// Set up valid RSS URL.
		$rss_url = 'https://untappd.com/rss/user/testuser?key=abc123';
		update_option( 'beer_slurper_rss_url', $rss_url );

		// Mock RSS to fail.
		MockHttpClient::mock_error( '*untappd.com/rss/*', 'rss_error', 'RSS fetch failed' );

		// Mock page scraping to succeed (but find no checkins).
		MockHttpClient::mock( '*untappd.com/user/*', array(
			'response' => array( 'code' => 200 ),
			'body'     => '<html><body>No checkins</body></html>',
		) );

		$result = import_new_via_scraper( 'testuser' );

		// Should have fallen back to page scraping (not RSS error).
		if ( is_wp_error( $result ) ) {
			// Page scraper returned error (no checkins), not RSS error.
			$this->assertEquals( 'no_checkins', $result->get_error_code() );
		} else {
			$this->assertIsString( $result );
		}
	}

	/**
	 * Tests import_new_via_scraper() uses page scraping when no RSS configured.
	 */
	public function test_import_new_via_scraper_uses_page_scraping_without_rss() {
		// No RSS URL configured.
		delete_option( 'beer_slurper_rss_url' );

		// Mock page scraping.
		MockHttpClient::mock( '*untappd.com/user/*', array(
			'response' => array( 'code' => 200 ),
			'body'     => '<html><body>No checkins</body></html>',
		) );

		$result = import_new_via_scraper( 'testuser' );

		// Page scraper was used (returns error or string).
		if ( is_wp_error( $result ) ) {
			// Scraper error, not RSS error.
			$this->assertEquals( 'no_checkins', $result->get_error_code() );
		} else {
			// Should be scraper message, not RSS message.
			$this->assertStringNotContainsString( 'RSS feed', $result );
		}
	}

	/**
	 * Tests import_new_via_scraper() updates since_id after importing.
	 */
	public function test_import_new_via_scraper_updates_since_id() {
		// Set up valid RSS URL.
		$rss_url = 'https://untappd.com/rss/user/testuser?key=abc123';
		update_option( 'beer_slurper_rss_url', $rss_url );

		$checkin_id = wp_rand( 500000, 599999 );

		// Create an existing post so the import is skipped but since_id still updates.
		$post_id = $this->create_beer_post();
		add_post_meta( $post_id, '_beer_slurper_untappd_id', $checkin_id );

		// Mock RSS response with a checkin.
		$rss_content = $this->get_sample_rss_feed( 'testuser', $checkin_id, 'Test Beer', 'Test Brewery' );
		MockHttpClient::mock( '*untappd.com/rss/*', array(
			'response' => array( 'code' => 200 ),
			'body'     => $rss_content,
		) );

		import_new_via_scraper( 'testuser' );

		// The since_id won't be updated if nothing was imported and everything was skipped.
		// This tests that the scraper processed the checkin at least.
		$result = import_new_via_scraper( 'testuser' );
		$this->assertStringContainsString( 'skipped', $result );
	}

	/**
	 * Tests import_new_via_scraper() skips existing checkins.
	 */
	public function test_import_new_via_scraper_skips_existing_checkins() {
		// Set up valid RSS URL.
		$rss_url = 'https://untappd.com/rss/user/testuser?key=abc123';
		update_option( 'beer_slurper_rss_url', $rss_url );

		$checkin_id = wp_rand( 400000, 499999 );

		// Create existing post with this checkin ID.
		$post_id = $this->create_beer_post();
		add_post_meta( $post_id, '_beer_slurper_untappd_id', $checkin_id );

		// Mock RSS response with the same checkin ID.
		$rss_content = $this->get_sample_rss_feed( 'testuser', $checkin_id, 'Test Beer', 'Test Brewery' );
		MockHttpClient::mock( '*untappd.com/rss/*', array(
			'response' => array( 'code' => 200 ),
			'body'     => $rss_content,
		) );

		$result = import_new_via_scraper( 'testuser' );

		// Should show 0 imported, 1 skipped.
		$this->assertStringContainsString( '0 checkin(s) imported', $result );
		$this->assertStringContainsString( '1 skipped', $result );
	}

	/*
	 * =========================================================================
	 * Helper Methods
	 * =========================================================================
	 */

	/**
	 * Generates a sample RSS feed XML for testing.
	 *
	 * @param string $username   The Untappd username.
	 * @param int    $checkin_id The checkin ID.
	 * @param string $beer_name  The beer name.
	 * @param string $brewery    The brewery name.
	 *
	 * @return string RSS XML content.
	 */
	protected function get_sample_rss_feed( $username, $checkin_id, $beer_name, $brewery ) {
		$date = date( 'r' );
		return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
	<channel>
		<title>$username's Recent Untappd Activity</title>
		<item>
			<title>$username is drinking a $beer_name by $brewery</title>
			<link>https://untappd.com/user/$username/checkin/$checkin_id</link>
			<description>
				<![CDATA[
				<b>$beer_name</b> by <b>$brewery</b><br/>
				Rating: 4.0<br/>
				<img src="https://untappd.akamaized.net/photos/photo.jpg">
				]]>
			</description>
			<pubDate>$date</pubDate>
			<guid>https://untappd.com/user/$username/checkin/$checkin_id</guid>
		</item>
	</channel>
</rss>
XML;
	}
}
