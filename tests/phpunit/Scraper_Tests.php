<?php
/**
 * Scraper Tests for Beer Slurper
 *
 * Tests for the RSS feed parsing and web scraping functionality that
 * provides alternative data fetching methods when API access is unavailable.
 *
 * @package Kraft\Beer_Slurper\Scraper
 */

namespace Kraft\Beer_Slurper\Scraper;

/**
 * Tests for the Scraper functions.
 *
 * Validates RSS feed parsing, URL validation, checkin data extraction from RSS,
 * HTML parsing for user checkins, and error handling for various edge cases.
 *
 * References:
 *   - http://phpunit.de/manual/current/en/index.html
 *   - https://github.com/padraic/mockery
 *   - https://github.com/10up/wp_mock
 */

use Kraft\Beer_Slurper as Base;

class Scraper_Tests extends Base\TestCase {

	protected $testFiles = [
		'functions/scraper.php'
	];

	/*
	 * =========================================================================
	 * RSS URL VALIDATION TESTS
	 * =========================================================================
	 */

	/**
	 * Tests is_valid_rss_url() returns true for valid Untappd RSS URLs.
	 *
	 * Verifies that the function correctly validates properly formatted
	 * Untappd RSS feed URLs with username and key parameters.
	 */
	public function test_is_valid_rss_url_returns_true_for_valid_url() {
		$url = 'https://untappd.com/rss/user/testuser?key=abc123';

		$result = is_valid_rss_url( $url );

		$this->assertTrue( $result );
	}

	/**
	 * Tests is_valid_rss_url() returns true for HTTP URLs.
	 *
	 * Verifies that the function accepts HTTP (non-HTTPS) URLs as valid.
	 */
	public function test_is_valid_rss_url_returns_true_for_http_url() {
		$url = 'http://untappd.com/rss/user/testuser?key=abc123';

		$result = is_valid_rss_url( $url );

		$this->assertTrue( $result );
	}

	/**
	 * Tests is_valid_rss_url() returns false for empty URLs.
	 *
	 * Verifies that the function returns false when passed an empty string.
	 */
	public function test_is_valid_rss_url_returns_false_for_empty_url() {
		$result = is_valid_rss_url( '' );

		$this->assertFalse( $result );
	}

	/**
	 * Tests is_valid_rss_url() returns false for non-Untappd URLs.
	 *
	 * Verifies that the function rejects URLs from other domains.
	 */
	public function test_is_valid_rss_url_returns_false_for_non_untappd_url() {
		$url = 'https://example.com/rss/user/testuser';

		$result = is_valid_rss_url( $url );

		$this->assertFalse( $result );
	}

	/**
	 * Tests is_valid_rss_url() returns false for invalid URL format.
	 *
	 * Verifies that the function rejects strings that aren't valid URLs.
	 */
	public function test_is_valid_rss_url_returns_false_for_invalid_url() {
		$url = 'not-a-valid-url';

		$result = is_valid_rss_url( $url );

		$this->assertFalse( $result );
	}

	/**
	 * Tests is_valid_rss_url() returns false for Untappd non-RSS URLs.
	 *
	 * Verifies that the function rejects Untappd URLs that aren't RSS feeds.
	 */
	public function test_is_valid_rss_url_returns_false_for_untappd_non_rss_url() {
		$url = 'https://untappd.com/user/testuser';

		$result = is_valid_rss_url( $url );

		$this->assertFalse( $result );
	}

	/*
	 * =========================================================================
	 * RSS FEED PARSING TESTS
	 * =========================================================================
	 */

	/**
	 * Tests parse_rss_feed() returns error for empty content.
	 *
	 * Verifies that the function returns a WP_Error when passed empty XML content.
	 */
	public function test_parse_rss_feed_returns_error_for_empty_content() {
		$result = parse_rss_feed( '' );

		$this->assertInstanceOf( '\WP_Error', $result );
		$this->assertEquals( 'empty_rss', $result->get_error_code() );
	}

	/**
	 * Tests parse_rss_feed() returns error for invalid XML.
	 *
	 * Verifies that the function returns a WP_Error when passed malformed XML.
	 */
	public function test_parse_rss_feed_returns_error_for_invalid_xml() {
		$xml = '<invalid><xml><not-closed>';

		$result = parse_rss_feed( $xml );

		$this->assertInstanceOf( '\WP_Error', $result );
		$this->assertEquals( 'xml_parse_error', $result->get_error_code() );
	}

	/**
	 * Tests parse_rss_feed() returns error for RSS with no items.
	 *
	 * Verifies that the function returns a WP_Error when the RSS feed has no items.
	 */
	public function test_parse_rss_feed_returns_error_for_no_items() {
		$xml = '<?xml version="1.0" encoding="UTF-8"?>
		<rss version="2.0">
			<channel>
				<title>Test Feed</title>
			</channel>
		</rss>';

		$result = parse_rss_feed( $xml );

		$this->assertInstanceOf( '\WP_Error', $result );
		$this->assertEquals( 'invalid_rss', $result->get_error_code() );
	}

	/**
	 * Tests parse_rss_feed() parses valid RSS feed with checkins.
	 *
	 * Verifies that the function correctly parses a valid RSS feed and
	 * returns an array with checkins data.
	 */
	public function test_parse_rss_feed_parses_valid_feed() {
		$xml = $this->get_sample_rss_feed();

		$result = parse_rss_feed( $xml );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'checkins', $result );
		$this->assertArrayHasKey( 'source', $result );
		$this->assertEquals( 'rss', $result['source'] );
		$this->assertEquals( 2, $result['checkins']['count'] );
		$this->assertCount( 2, $result['checkins']['items'] );
	}

	/**
	 * Tests parse_rss_feed() extracts checkin IDs from links.
	 *
	 * Verifies that the function correctly extracts checkin IDs from the
	 * item link URLs.
	 */
	public function test_parse_rss_feed_extracts_checkin_ids() {
		$xml = $this->get_sample_rss_feed();

		$result = parse_rss_feed( $xml );

		$this->assertEquals( 123456789, $result['checkins']['items'][0]['checkin_id'] );
		$this->assertEquals( 123456788, $result['checkins']['items'][1]['checkin_id'] );
	}

	/*
	 * =========================================================================
	 * RSS ITEM PARSING TESTS
	 * =========================================================================
	 */

	/**
	 * Tests parse_rss_item() returns null for items without checkin ID.
	 *
	 * Verifies that the function returns null when it cannot extract a checkin ID.
	 */
	public function test_parse_rss_item_returns_null_without_checkin_id() {
		$xml = simplexml_load_string( '<?xml version="1.0" encoding="UTF-8"?>
			<item>
				<title>Username is drinking Beer by Brewery</title>
				<link>https://untappd.com/user/testuser</link>
				<pubDate>Mon, 01 Jan 2024 12:00:00 +0000</pubDate>
			</item>' );

		$result = parse_rss_item( $xml );

		$this->assertNull( $result );
	}

	/**
	 * Tests parse_rss_item() extracts beer and brewery names from title.
	 *
	 * Verifies that the function correctly parses the title format
	 * "Username is drinking Beer Name by Brewery Name".
	 */
	public function test_parse_rss_item_extracts_beer_and_brewery_from_title() {
		$xml = simplexml_load_string( '<?xml version="1.0" encoding="UTF-8"?>
			<item>
				<title>testuser is drinking IPA Deluxe by Craft Brewing Co</title>
				<link>https://untappd.com/user/testuser/checkin/123456</link>
				<pubDate>Mon, 01 Jan 2024 12:00:00 +0000</pubDate>
				<description></description>
			</item>' );

		$result = parse_rss_item( $xml );

		$this->assertEquals( 'testuser', $result['user']['user_name'] );
		$this->assertEquals( 'IPA Deluxe', $result['beer']['beer_name'] );
		$this->assertEquals( 'Craft Brewing Co', $result['brewery']['brewery_name'] );
	}

	/**
	 * Tests parse_rss_item() parses publication date correctly.
	 *
	 * Verifies that the function converts RFC 2822 dates to Y-m-d H:i:s format.
	 */
	public function test_parse_rss_item_parses_date() {
		$xml = simplexml_load_string( '<?xml version="1.0" encoding="UTF-8"?>
			<item>
				<title>testuser is drinking Beer by Brewery</title>
				<link>https://untappd.com/user/testuser/checkin/123456</link>
				<pubDate>Mon, 15 Jan 2024 18:30:00 +0000</pubDate>
				<description></description>
			</item>' );

		$result = parse_rss_item( $xml );

		$this->assertEquals( '2024-01-15 18:30:00', $result['created_at'] );
	}

	/**
	 * Tests parse_rss_item() extracts photo from enclosure.
	 *
	 * Verifies that the function correctly extracts photo URLs from enclosure elements.
	 */
	public function test_parse_rss_item_extracts_photo_from_enclosure() {
		$xml = simplexml_load_string( '<?xml version="1.0" encoding="UTF-8"?>
			<item>
				<title>testuser is drinking Beer by Brewery</title>
				<link>https://untappd.com/user/testuser/checkin/123456</link>
				<pubDate>Mon, 01 Jan 2024 12:00:00 +0000</pubDate>
				<description></description>
				<enclosure url="https://untappd.akamaized.net/photos/2024_01_01/abc123.jpg" type="image/jpeg" />
			</item>' );

		$result = parse_rss_item( $xml );

		$this->assertCount( 1, $result['media']['items'] );
		$this->assertEquals(
			'https://untappd.akamaized.net/photos/2024_01_01/abc123.jpg',
			$result['media']['items'][0]['photo']['photo_img_og']
		);
	}

	/**
	 * Tests parse_rss_item() ignores placeholder photos.
	 *
	 * Verifies that the function does not include placeholder image URLs.
	 */
	public function test_parse_rss_item_ignores_placeholder_photos() {
		$xml = simplexml_load_string( '<?xml version="1.0" encoding="UTF-8"?>
			<item>
				<title>testuser is drinking Beer by Brewery</title>
				<link>https://untappd.com/user/testuser/checkin/123456</link>
				<pubDate>Mon, 01 Jan 2024 12:00:00 +0000</pubDate>
				<description></description>
				<enclosure url="https://untappd.com/images/placeholder.png" type="image/png" />
			</item>' );

		$result = parse_rss_item( $xml );

		$this->assertEmpty( $result['media']['items'] );
	}

	/**
	 * Tests parse_rss_item() sets source marker.
	 *
	 * Verifies that the function sets _source to 'rss' for tracking purposes.
	 */
	public function test_parse_rss_item_sets_source_marker() {
		$xml = simplexml_load_string( '<?xml version="1.0" encoding="UTF-8"?>
			<item>
				<title>testuser is drinking Beer by Brewery</title>
				<link>https://untappd.com/user/testuser/checkin/123456</link>
				<pubDate>Mon, 01 Jan 2024 12:00:00 +0000</pubDate>
				<description></description>
			</item>' );

		$result = parse_rss_item( $xml );

		$this->assertEquals( 'rss', $result['_source'] );
	}

	/*
	 * =========================================================================
	 * RSS DESCRIPTION PARSING TESTS
	 * =========================================================================
	 */

	/**
	 * Tests parse_rss_description() extracts rating.
	 *
	 * Verifies that the function correctly extracts ratings from description text.
	 */
	public function test_parse_rss_description_extracts_rating() {
		$checkin = array(
			'rating_score'    => 0,
			'checkin_comment' => '',
			'venue'           => array(),
			'beer'            => array(),
			'brewery'         => array(),
			'media'           => array( 'items' => array() ),
		);
		$description = '<p>Rated: 4.25</p>';

		$result = parse_rss_description( $checkin, $description );

		$this->assertEquals( 4.25, $result['rating_score'] );
	}

	/**
	 * Tests parse_rss_description() extracts venue from linked text.
	 *
	 * Verifies that the function correctly extracts venue names from anchor tags.
	 */
	public function test_parse_rss_description_extracts_venue_from_link() {
		$checkin = array(
			'rating_score'    => 0,
			'checkin_comment' => '',
			'venue'           => array(),
			'beer'            => array(),
			'brewery'         => array(),
			'media'           => array( 'items' => array() ),
		);
		$description = '<p>at <a href="https://untappd.com/v/the-local-pub/12345">The Local Pub</a></p>';

		$result = parse_rss_description( $checkin, $description );

		$this->assertEquals( 'The Local Pub', $result['venue']['venue_name'] );
	}

	/**
	 * Tests parse_rss_description() extracts comment from quotes.
	 *
	 * Verifies that the function extracts quoted text as the checkin comment.
	 */
	public function test_parse_rss_description_extracts_comment() {
		$checkin = array(
			'rating_score'    => 0,
			'checkin_comment' => '',
			'venue'           => array(),
			'beer'            => array(),
			'brewery'         => array(),
			'media'           => array( 'items' => array() ),
		);
		$description = '<p>"Great hoppy flavor, love the citrus notes!"</p>';

		$result = parse_rss_description( $checkin, $description );

		$this->assertEquals( 'Great hoppy flavor, love the citrus notes!', $result['checkin_comment'] );
	}

	/**
	 * Tests parse_rss_description() extracts beer ID from URL.
	 *
	 * Verifies that the function correctly extracts beer IDs from href attributes.
	 */
	public function test_parse_rss_description_extracts_beer_id() {
		$checkin = array(
			'rating_score'    => 0,
			'checkin_comment' => '',
			'venue'           => array(),
			'beer'            => array(),
			'brewery'         => array(),
			'media'           => array( 'items' => array() ),
		);
		$description = '<a href="https://untappd.com/b/ipa-deluxe/789456">IPA Deluxe</a>';

		$result = parse_rss_description( $checkin, $description );

		$this->assertEquals( 789456, $result['beer']['bid'] );
		$this->assertEquals( 'ipa-deluxe', $result['beer']['beer_slug'] );
	}

	/**
	 * Tests parse_rss_description() extracts brewery ID from URL.
	 *
	 * Verifies that the function correctly extracts brewery IDs from href attributes.
	 */
	public function test_parse_rss_description_extracts_brewery_id() {
		$checkin = array(
			'rating_score'    => 0,
			'checkin_comment' => '',
			'venue'           => array(),
			'beer'            => array(),
			'brewery'         => array(),
			'media'           => array( 'items' => array() ),
		);
		$description = '<a href="https://untappd.com/w/craft-brewing-co/456123">Craft Brewing Co</a>';

		$result = parse_rss_description( $checkin, $description );

		$this->assertEquals( 456123, $result['brewery']['brewery_id'] );
		$this->assertEquals( 'craft-brewing-co', $result['brewery']['brewery_slug'] );
	}

	/**
	 * Tests parse_rss_description() extracts venue ID from URL.
	 *
	 * Verifies that the function correctly extracts venue IDs from href attributes.
	 */
	public function test_parse_rss_description_extracts_venue_id() {
		$checkin = array(
			'rating_score'    => 0,
			'checkin_comment' => '',
			'venue'           => array(),
			'beer'            => array(),
			'brewery'         => array(),
			'media'           => array( 'items' => array() ),
		);
		$description = '<p>at <a href="https://untappd.com/v/the-local-pub/654321">The Local Pub</a></p>';

		$result = parse_rss_description( $checkin, $description );

		$this->assertEquals( 654321, $result['venue']['venue_id'] );
		$this->assertEquals( 'the-local-pub', $result['venue']['venue_slug'] );
	}

	/**
	 * Tests parse_rss_description() extracts photo from img tag.
	 *
	 * Verifies that the function extracts photo URLs from img tags when no enclosure.
	 */
	public function test_parse_rss_description_extracts_photo_from_img() {
		$checkin = array(
			'rating_score'    => 0,
			'checkin_comment' => '',
			'venue'           => array(),
			'beer'            => array(),
			'brewery'         => array(),
			'media'           => array( 'items' => array() ),
		);
		$description = '<img src="https://untappd.akamaized.net/photos/2024_01_01/photo.jpg" />';

		$result = parse_rss_description( $checkin, $description );

		$this->assertCount( 1, $result['media']['items'] );
		$this->assertEquals(
			'https://untappd.akamaized.net/photos/2024_01_01/photo.jpg',
			$result['media']['items'][0]['photo']['photo_img_og']
		);
	}

	/**
	 * Tests parse_rss_description() ignores badge images.
	 *
	 * Verifies that the function does not include badge image URLs as photos.
	 */
	public function test_parse_rss_description_ignores_badge_images() {
		$checkin = array(
			'rating_score'    => 0,
			'checkin_comment' => '',
			'venue'           => array(),
			'beer'            => array(),
			'brewery'         => array(),
			'media'           => array( 'items' => array() ),
		);
		$description = '<img src="https://untappd.com/badge/badge-image.png" />';

		$result = parse_rss_description( $checkin, $description );

		$this->assertEmpty( $result['media']['items'] );
	}

	/*
	 * =========================================================================
	 * HTML PARSING TESTS (User Checkins)
	 * =========================================================================
	 */

	/**
	 * Tests parse_user_checkins() returns error for empty HTML.
	 *
	 * Verifies that the function returns a WP_Error for empty response.
	 */
	public function test_parse_user_checkins_returns_error_for_empty_html() {
		$result = parse_user_checkins( '', 'testuser' );

		$this->assertInstanceOf( '\WP_Error', $result );
		$this->assertEquals( 'empty_response', $result->get_error_code() );
	}

	/**
	 * Tests parse_user_checkins() returns error when no checkins found.
	 *
	 * Verifies that the function returns a WP_Error when HTML contains no checkin elements.
	 */
	public function test_parse_user_checkins_returns_error_for_no_checkins() {
		$html = '<!DOCTYPE html><html><body><div id="main"></div></body></html>';

		$result = parse_user_checkins( $html, 'testuser' );

		$this->assertInstanceOf( '\WP_Error', $result );
		$this->assertEquals( 'no_checkins', $result->get_error_code() );
	}

	/**
	 * Tests parse_user_checkins() parses valid HTML with checkins.
	 *
	 * Verifies that the function correctly parses HTML containing checkin elements.
	 */
	public function test_parse_user_checkins_parses_valid_html() {
		$html = $this->get_sample_user_checkins_html();

		$result = parse_user_checkins( $html, 'testuser' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'checkins', $result );
		$this->assertGreaterThan( 0, $result['checkins']['count'] );
	}

	/**
	 * Tests parse_user_checkins() extracts checkin data from HTML.
	 *
	 * Verifies that the function correctly extracts beer, brewery, and other data.
	 */
	public function test_parse_user_checkins_extracts_checkin_data() {
		$html = $this->get_sample_user_checkins_html();

		$result = parse_user_checkins( $html, 'testuser' );

		$checkin = $result['checkins']['items'][0];
		$this->assertEquals( '987654321', $checkin['checkin_id'] );
		$this->assertEquals( 'Sample IPA', $checkin['beer']['beer_name'] );
		$this->assertEquals( 123, $checkin['beer']['bid'] );
		$this->assertEquals( 'Test Brewery', $checkin['brewery']['brewery_name'] );
		$this->assertEquals( 456, $checkin['brewery']['brewery_id'] );
	}

	/*
	 * =========================================================================
	 * PARSE SINGLE CHECKIN TESTS
	 * =========================================================================
	 */

	/**
	 * Tests parse_single_checkin() extracts data from DOM node.
	 *
	 * Verifies that the function correctly extracts all checkin data from a DOM element.
	 */
	public function test_parse_single_checkin_extracts_data() {
		$html = $this->get_sample_checkin_node_html();
		$doc = new \DOMDocument();
		@$doc->loadHTML( $html, LIBXML_NOWARNING | LIBXML_NOERROR );
		$xpath = new \DOMXPath( $doc );
		$node = $xpath->query( "//div[@class='checkin']" )->item( 0 );

		$result = parse_single_checkin( $node, $xpath );

		$this->assertEquals( '111222333', $result['checkin_id'] );
		$this->assertEquals( 'Hazy IPA', $result['beer']['beer_name'] );
		$this->assertEquals( 777, $result['beer']['bid'] );
		$this->assertEquals( 'Awesome Brewery', $result['brewery']['brewery_name'] );
		$this->assertEquals( 888, $result['brewery']['brewery_id'] );
	}

	/**
	 * Tests parse_single_checkin() extracts venue information.
	 *
	 * Verifies that the function correctly extracts venue data when present.
	 */
	public function test_parse_single_checkin_extracts_venue() {
		$html = $this->get_sample_checkin_node_html_with_venue();
		$doc = new \DOMDocument();
		@$doc->loadHTML( $html, LIBXML_NOWARNING | LIBXML_NOERROR );
		$xpath = new \DOMXPath( $doc );
		$node = $xpath->query( "//div[@class='checkin']" )->item( 0 );

		$result = parse_single_checkin( $node, $xpath );

		$this->assertEquals( 'The Tap Room', $result['venue']['venue_name'] );
		$this->assertEquals( 999, $result['venue']['venue_id'] );
		$this->assertEquals( 'the-tap-room', $result['venue']['venue_slug'] );
	}

	/**
	 * Tests parse_single_checkin() extracts rating.
	 *
	 * Verifies that the function correctly extracts rating scores.
	 */
	public function test_parse_single_checkin_extracts_rating() {
		$html = $this->get_sample_checkin_node_html_with_rating();
		$doc = new \DOMDocument();
		@$doc->loadHTML( $html, LIBXML_NOWARNING | LIBXML_NOERROR );
		$xpath = new \DOMXPath( $doc );
		$node = $xpath->query( "//div[@class='checkin']" )->item( 0 );

		$result = parse_single_checkin( $node, $xpath );

		$this->assertEquals( 4.5, $result['rating_score'] );
	}

	/**
	 * Tests parse_single_checkin() extracts comment.
	 *
	 * Verifies that the function correctly extracts checkin comments.
	 */
	public function test_parse_single_checkin_extracts_comment() {
		$html = $this->get_sample_checkin_node_html_with_comment();
		$doc = new \DOMDocument();
		@$doc->loadHTML( $html, LIBXML_NOWARNING | LIBXML_NOERROR );
		$xpath = new \DOMXPath( $doc );
		$node = $xpath->query( "//div[@class='checkin']" )->item( 0 );

		$result = parse_single_checkin( $node, $xpath );

		$this->assertEquals( 'Excellent beer, highly recommend!', $result['checkin_comment'] );
	}

	/**
	 * Tests parse_single_checkin() extracts checkin ID from link when no data attribute.
	 *
	 * Verifies that the function can extract checkin ID from href when data-checkin-id is missing.
	 */
	public function test_parse_single_checkin_extracts_id_from_link() {
		$html = $this->get_sample_checkin_node_html_with_link_id();
		$doc = new \DOMDocument();
		@$doc->loadHTML( $html, LIBXML_NOWARNING | LIBXML_NOERROR );
		$xpath = new \DOMXPath( $doc );
		$node = $xpath->query( "//div[@class='checkin']" )->item( 0 );

		$result = parse_single_checkin( $node, $xpath );

		$this->assertEquals( '444555666', $result['checkin_id'] );
	}

	/*
	 * =========================================================================
	 * RELATIVE TIME PARSING TESTS
	 * =========================================================================
	 */

	/**
	 * Tests parse_relative_time() handles "X minutes ago".
	 *
	 * Verifies that the function correctly parses minute-based relative times.
	 */
	public function test_parse_relative_time_handles_minutes() {
		$result = parse_relative_time( '30 min ago' );

		// Should be approximately 30 minutes ago.
		$expected = gmdate( 'Y-m-d H:i', time() - 1800 );
		$actual = substr( $result, 0, 16 ); // Compare Y-m-d H:i

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Tests parse_relative_time() handles "X hours ago".
	 *
	 * Verifies that the function correctly parses hour-based relative times.
	 */
	public function test_parse_relative_time_handles_hours() {
		$result = parse_relative_time( '2 hours ago' );

		$expected = gmdate( 'Y-m-d H', time() - 7200 );
		$actual = substr( $result, 0, 13 ); // Compare Y-m-d H

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Tests parse_relative_time() handles "X days ago".
	 *
	 * Verifies that the function correctly parses day-based relative times.
	 */
	public function test_parse_relative_time_handles_days() {
		$result = parse_relative_time( '3 days ago' );

		$expected = gmdate( 'Y-m-d', time() - 259200 );
		$actual = substr( $result, 0, 10 );

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Tests parse_relative_time() handles "yesterday".
	 *
	 * Verifies that the function correctly parses "yesterday".
	 */
	public function test_parse_relative_time_handles_yesterday() {
		$result = parse_relative_time( 'yesterday' );

		$expected = gmdate( 'Y-m-d', time() - 86400 );
		$actual = substr( $result, 0, 10 );

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Tests parse_relative_time() handles "just now".
	 *
	 * Verifies that the function returns current time for "just now".
	 */
	public function test_parse_relative_time_handles_just_now() {
		$result = parse_relative_time( 'just now' );

		$expected = gmdate( 'Y-m-d H:i', time() );
		$actual = substr( $result, 0, 16 );

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Tests parse_relative_time() handles direct date formats.
	 *
	 * Verifies that the function correctly parses "Jan 15, 2024" style dates.
	 */
	public function test_parse_relative_time_handles_direct_date() {
		$result = parse_relative_time( 'Jan 15, 2024' );

		$this->assertStringStartsWith( '2024-01-15', $result );
	}

	/*
	 * =========================================================================
	 * GET CHECKINS FROM RSS TESTS (Integration with mocking)
	 * =========================================================================
	 */

	/**
	 * Tests get_checkins_from_rss() returns error for empty URL.
	 *
	 * Verifies that the function returns a WP_Error when no URL is provided.
	 */
	public function test_get_checkins_from_rss_returns_error_for_empty_url() {
		$result = get_checkins_from_rss( '' );

		$this->assertInstanceOf( '\WP_Error', $result );
		$this->assertEquals( 'no_rss_url', $result->get_error_code() );
	}

	/**
	 * Tests get_checkins_from_rss() returns error for invalid URL format.
	 *
	 * Verifies that the function validates URL format before making requests.
	 */
	public function test_get_checkins_from_rss_returns_error_for_invalid_url() {
		$result = get_checkins_from_rss( 'https://example.com/feed' );

		$this->assertInstanceOf( '\WP_Error', $result );
		$this->assertEquals( 'invalid_rss_url', $result->get_error_code() );
	}

	/**
	 * Tests get_checkins_from_rss() returns cached results when available.
	 *
	 * Verifies that the function uses transient caching for RSS results.
	 */
	public function test_get_checkins_from_rss_returns_cached_results() {
		$url = 'https://untappd.com/rss/user/testuser?key=abc123';
		$cached_data = array(
			'checkins' => array( 'count' => 1, 'items' => array() ),
			'source'   => 'rss',
		);

		// Set up cached data using real WordPress transient.
		set_transient( 'beer_slurper_rss_' . md5( $url ), $cached_data, HOUR_IN_SECONDS );

		$result = get_checkins_from_rss( $url );

		$this->assertEquals( $cached_data, $result );
	}

	/*
	 * =========================================================================
	 * GET USER CHECKINS TESTS (Integration with mocking)
	 * =========================================================================
	 */

	/**
	 * Tests get_user_checkins() returns error for empty username.
	 *
	 * Verifies that the function validates the username parameter.
	 */
	public function test_get_user_checkins_returns_error_for_empty_username() {
		// No mocking needed - sanitize_user() is a real WP function in WorDBless.
		$result = get_user_checkins( '' );

		$this->assertInstanceOf( '\WP_Error', $result );
		$this->assertEquals( 'invalid_user', $result->get_error_code() );
	}

	/**
	 * Tests get_user_checkins() returns cached results when available.
	 *
	 * Verifies that the function uses transient caching for scraped results.
	 */
	public function test_get_user_checkins_returns_cached_results() {
		$cached_data = array(
			'checkins' => array( 'count' => 1, 'items' => array() ),
		);

		// Set up cached data using real WordPress transient.
		set_transient( 'beer_slurper_scrape_' . md5( 'testuser' ), $cached_data, HOUR_IN_SECONDS );

		$result = get_user_checkins( 'testuser' );

		$this->assertEquals( $cached_data, $result );
	}

	/*
	 * =========================================================================
	 * HELPER CONFIGURATION TESTS
	 * =========================================================================
	 */

	/**
	 * Tests get_rss_url() returns option value.
	 *
	 * Verifies that the function retrieves the stored RSS URL option.
	 */
	public function test_get_rss_url_returns_option() {
		$url = 'https://untappd.com/rss/user/testuser?key=abc123';

		// Set up option using real WordPress function.
		update_option( 'beer_slurper_rss_url', $url );

		$result = get_rss_url();

		$this->assertEquals( $url, $result );
	}

	/**
	 * Tests is_enabled() returns true when scraper mode is active.
	 *
	 * Verifies that the function detects scraper data source configuration.
	 */
	public function test_is_enabled_returns_true_for_scraper_mode() {
		// Set up option using real WordPress function.
		update_option( 'beer_slurper_data_source', 'scraper' );

		$result = is_enabled();

		$this->assertTrue( $result );
	}

	/**
	 * Tests is_enabled() returns true for hybrid mode.
	 *
	 * Verifies that the function returns true when hybrid mode is active.
	 */
	public function test_is_enabled_returns_true_for_hybrid_mode() {
		// Set up option using real WordPress function.
		update_option( 'beer_slurper_data_source', 'hybrid' );

		$result = is_enabled();

		$this->assertTrue( $result );
	}

	/**
	 * Tests is_enabled() returns false for API mode.
	 *
	 * Verifies that the function returns false when API mode is active.
	 */
	public function test_is_enabled_returns_false_for_api_mode() {
		// Set up option using real WordPress function.
		update_option( 'beer_slurper_data_source', 'api' );

		$result = is_enabled();

		$this->assertFalse( $result );
	}

	/**
	 * Tests is_primary() returns true for scraper mode.
	 *
	 * Verifies that the function detects when scraper is the primary source.
	 */
	public function test_is_primary_returns_true_for_scraper_mode() {
		// Set up option using real WordPress function.
		update_option( 'beer_slurper_data_source', 'scraper' );

		$result = is_primary();

		$this->assertTrue( $result );
	}

	/**
	 * Tests is_primary() returns false for hybrid mode.
	 *
	 * Verifies that the function returns false when hybrid mode is active.
	 */
	public function test_is_primary_returns_false_for_hybrid_mode() {
		// Set up option using real WordPress function.
		update_option( 'beer_slurper_data_source', 'hybrid' );

		$result = is_primary();

		$this->assertFalse( $result );
	}

	/**
	 * Tests get_data_source() returns configured source.
	 *
	 * Verifies that the function retrieves the data source option.
	 */
	public function test_get_data_source_returns_option() {
		// Set up option using real WordPress function.
		update_option( 'beer_slurper_data_source', 'scraper' );

		$result = get_data_source();

		$this->assertEquals( 'scraper', $result );
	}

	/*
	 * =========================================================================
	 * HELPER METHODS FOR MOCK DATA
	 * =========================================================================
	 */

	/**
	 * Returns sample RSS feed XML for testing.
	 *
	 * @return string Sample RSS XML content.
	 */
	private function get_sample_rss_feed() {
		return '<?xml version="1.0" encoding="UTF-8"?>
		<rss version="2.0">
			<channel>
				<title>testuser\'s Recent Activity - Untappd</title>
				<link>https://untappd.com/user/testuser</link>
				<description>Recent beer activity for testuser on Untappd</description>
				<item>
					<title>testuser is drinking IPA Deluxe by Craft Brewing Co</title>
					<link>https://untappd.com/user/testuser/checkin/123456789</link>
					<description><![CDATA[
						<p>Rated: 4.5</p>
						<p>"Great hoppy beer!"</p>
						<p>at <a href="https://untappd.com/v/the-tap-room/111">The Tap Room</a></p>
						<a href="https://untappd.com/b/ipa-deluxe/555">IPA Deluxe</a> by
						<a href="https://untappd.com/w/craft-brewing-co/666">Craft Brewing Co</a>
					]]></description>
					<pubDate>Mon, 15 Jan 2024 18:30:00 +0000</pubDate>
					<enclosure url="https://untappd.akamaized.net/photos/2024_01_15/photo1.jpg" type="image/jpeg" />
				</item>
				<item>
					<title>testuser is drinking Stout Special by Dark Brew Co</title>
					<link>https://untappd.com/user/testuser/checkin/123456788</link>
					<description><![CDATA[
						<p>Rated: 4.0</p>
						<a href="https://untappd.com/b/stout-special/777">Stout Special</a> by
						<a href="https://untappd.com/w/dark-brew-co/888">Dark Brew Co</a>
					]]></description>
					<pubDate>Sun, 14 Jan 2024 12:00:00 +0000</pubDate>
				</item>
			</channel>
		</rss>';
	}

	/**
	 * Returns sample user checkins HTML for testing.
	 *
	 * @return string Sample HTML content.
	 */
	private function get_sample_user_checkins_html() {
		return '<!DOCTYPE html>
		<html>
		<head><title>testuser - Untappd</title></head>
		<body>
			<div id="main-stream">
				<div class="checkin" data-checkin-id="987654321">
					<div class="checkin-info">
						<a href="https://untappd.com/user/testuser" class="user">testuser</a>
						is drinking
						<a href="https://untappd.com/b/sample-ipa/123">Sample IPA</a>
						by
						<a href="https://untappd.com/w/test-brewery/456">Test Brewery</a>
					</div>
					<div class="rating">
						<span class="caps">4.0</span>
					</div>
					<a href="https://untappd.com/user/testuser/checkin/987654321" class="time" data-gregtime="1705340400">Jan 15, 2024</a>
				</div>
			</div>
		</body>
		</html>';
	}

	/**
	 * Returns sample checkin DOM node HTML for testing.
	 *
	 * @return string Sample checkin node HTML.
	 */
	private function get_sample_checkin_node_html() {
		return '<!DOCTYPE html>
		<html>
		<body>
			<div class="checkin" data-checkin-id="111222333">
				<a href="https://untappd.com/b/hazy-ipa/777">Hazy IPA</a>
				<a href="https://untappd.com/w/awesome-brewery/888">Awesome Brewery</a>
				<a href="https://untappd.com/user/testuser" class="user">testuser</a>
			</div>
		</body>
		</html>';
	}

	/**
	 * Returns sample checkin node HTML with venue for testing.
	 *
	 * @return string Sample checkin node HTML with venue.
	 */
	private function get_sample_checkin_node_html_with_venue() {
		return '<!DOCTYPE html>
		<html>
		<body>
			<div class="checkin" data-checkin-id="111222333">
				<a href="https://untappd.com/b/hazy-ipa/777">Hazy IPA</a>
				<a href="https://untappd.com/w/awesome-brewery/888">Awesome Brewery</a>
				<a href="https://untappd.com/v/the-tap-room/999">The Tap Room</a>
			</div>
		</body>
		</html>';
	}

	/**
	 * Returns sample checkin node HTML with rating for testing.
	 *
	 * @return string Sample checkin node HTML with rating.
	 */
	private function get_sample_checkin_node_html_with_rating() {
		return '<!DOCTYPE html>
		<html>
		<body>
			<div class="checkin" data-checkin-id="111222333">
				<a href="https://untappd.com/b/hazy-ipa/777">Hazy IPA</a>
				<a href="https://untappd.com/w/awesome-brewery/888">Awesome Brewery</a>
				<div class="rating">
					<span class="caps">4.5</span>
				</div>
			</div>
		</body>
		</html>';
	}

	/**
	 * Returns sample checkin node HTML with comment for testing.
	 *
	 * @return string Sample checkin node HTML with comment.
	 */
	private function get_sample_checkin_node_html_with_comment() {
		return '<!DOCTYPE html>
		<html>
		<body>
			<div class="checkin" data-checkin-id="111222333">
				<a href="https://untappd.com/b/hazy-ipa/777">Hazy IPA</a>
				<a href="https://untappd.com/w/awesome-brewery/888">Awesome Brewery</a>
				<p class="comment-text">Excellent beer, highly recommend!</p>
			</div>
		</body>
		</html>';
	}

	/**
	 * Returns sample checkin node HTML with link-based ID for testing.
	 *
	 * @return string Sample checkin node HTML without data-checkin-id attribute.
	 */
	private function get_sample_checkin_node_html_with_link_id() {
		return '<!DOCTYPE html>
		<html>
		<body>
			<div class="checkin">
				<a href="https://untappd.com/user/testuser/checkin/444555666">View Checkin</a>
				<a href="https://untappd.com/b/hazy-ipa/777">Hazy IPA</a>
				<a href="https://untappd.com/w/awesome-brewery/888">Awesome Brewery</a>
			</div>
		</body>
		</html>';
	}
}
