<?php
/**
 * Untappd Data Fetcher (RSS + Scraper)
 *
 * Provides RSS feed parsing and web scraping functionality to fetch
 * public Untappd data without requiring API credentials. This enables
 * users without API access to sync their recent checkins.
 *
 * DATA FETCHING STRATEGY:
 * 1. RSS Feed (preferred) - Official, structured, requires user's RSS URL
 * 2. Page Scraping (fallback) - For additional details not in RSS
 *
 * The RSS feed is the "polite" way to detect new checkins. Page scraping
 * is only used to fill in details (beer_id, brewery_id, etc.) that aren't
 * available in the RSS feed's text summary.
 *
 * LIMITATIONS vs API:
 * - Only fetches ~25 most recent checkins (no deep pagination)
 * - Missing: badges, tagged friends, beer descriptions, label images
 * - Missing: detailed brewery metadata (description, logo, social links)
 * - Missing: detailed venue metadata (address, category, foursquare)
 *
 * @package Kraft\Beer_Slurper\Scraper
 */
namespace Kraft\Beer_Slurper\Scraper;

/**
 * User agent string identifying this as a personal beer log backup tool.
 * This makes the non-commercial, personal use nature clear.
 */
const USER_AGENT = 'personal-beerlog-backup/1.0 (WordPress plugin for personal beer history; non-commercial; https://github.com/kraftbj/beer-slurper)';

/**
 * Base URL for Untappd website.
 */
const UNTAPPD_BASE_URL = 'https://untappd.com';

/**
 * Cache duration for scraped data (15 minutes).
 */
const CACHE_DURATION = 900;

/**
 * Cache duration for RSS feed (5 minutes - more frequent polling).
 */
const RSS_CACHE_DURATION = 300;

/*
 * =============================================================================
 * RSS FEED FUNCTIONS
 * =============================================================================
 * RSS is the preferred method for detecting new checkins. It's official,
 * structured, and doesn't require scraping HTML.
 *
 * Users find their RSS URL at: untappd.com/account/settings
 * Format: https://untappd.com/rss/user/USERNAME?key=PERSONAL_KEY
 * =============================================================================
 */

/**
 * Fetches and parses checkins from a user's RSS feed.
 *
 * This is the preferred method for detecting new checkins as it uses
 * Untappd's official RSS feed rather than scraping HTML.
 *
 * @param string $rss_url The user's personal RSS feed URL (includes key).
 *
 * @return array|WP_Error Array of checkin data, or WP_Error on failure.
 */
function get_checkins_from_rss( $rss_url ) {
	if ( empty( $rss_url ) ) {
		return new \WP_Error( 'no_rss_url', __( 'No RSS feed URL configured.', 'beer_slurper' ) );
	}

	// Validate URL format.
	if ( ! filter_var( $rss_url, FILTER_VALIDATE_URL ) || strpos( $rss_url, 'untappd.com/rss/' ) === false ) {
		return new \WP_Error( 'invalid_rss_url', __( 'Invalid Untappd RSS feed URL.', 'beer_slurper' ) );
	}

	// Check cache first.
	$cache_key = 'beer_slurper_rss_' . md5( $rss_url );
	$cached    = get_transient( $cache_key );

	if ( false !== $cached ) {
		return $cached;
	}

	// Fetch the RSS feed.
	$response = fetch_rss( $rss_url );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	// Parse the RSS XML.
	$checkins = parse_rss_feed( $response['body'] );

	if ( is_wp_error( $checkins ) ) {
		return $checkins;
	}

	// Cache the results.
	set_transient( $cache_key, $checkins, RSS_CACHE_DURATION );

	return $checkins;
}

/**
 * Fetches an RSS feed with appropriate headers.
 *
 * @param string $url The RSS feed URL.
 *
 * @return array|WP_Error Response array with 'body', or WP_Error on failure.
 */
function fetch_rss( $url ) {
	$args = array(
		'timeout'     => 30,
		'redirection' => 5,
		'user-agent'  => USER_AGENT,
		'headers'     => array(
			'Accept'       => 'application/rss+xml, application/xml, text/xml, */*',
			'Cache-Control' => 'no-cache',
		),
	);

	$response = wp_remote_get( $url, $args );

	if ( is_wp_error( $response ) ) {
		error_log( 'Beer Slurper RSS: Request failed - ' . $response->get_error_message() );
		return $response;
	}

	$status_code = wp_remote_retrieve_response_code( $response );

	if ( 200 !== $status_code ) {
		error_log( 'Beer Slurper RSS: HTTP ' . $status_code . ' for RSS feed' );
		return new \WP_Error(
			'http_error',
			sprintf( __( 'HTTP error %d when fetching RSS feed. Check your RSS URL and key.', 'beer_slurper' ), $status_code )
		);
	}

	return array(
		'body'    => wp_remote_retrieve_body( $response ),
		'headers' => wp_remote_retrieve_headers( $response ),
	);
}

/**
 * Parses an Untappd RSS feed into checkin data.
 *
 * @param string $xml_content The RSS feed XML content.
 *
 * @return array|WP_Error Array with checkins data, or WP_Error on failure.
 */
function parse_rss_feed( $xml_content ) {
	if ( empty( $xml_content ) ) {
		return new \WP_Error( 'empty_rss', __( 'Empty RSS feed response.', 'beer_slurper' ) );
	}

	// Suppress XML parsing errors.
	libxml_use_internal_errors( true );

	$xml = simplexml_load_string( $xml_content );

	if ( false === $xml ) {
		$errors = libxml_get_errors();
		libxml_clear_errors();
		error_log( 'Beer Slurper RSS: XML parse error - ' . ( $errors ? $errors[0]->message : 'unknown' ) );
		return new \WP_Error( 'xml_parse_error', __( 'Failed to parse RSS feed XML.', 'beer_slurper' ) );
	}

	libxml_clear_errors();

	$checkins = array();

	// Standard RSS 2.0 structure: channel/item.
	if ( ! isset( $xml->channel->item ) ) {
		return new \WP_Error( 'invalid_rss', __( 'RSS feed has no items.', 'beer_slurper' ) );
	}

	foreach ( $xml->channel->item as $item ) {
		$checkin = parse_rss_item( $item );

		if ( $checkin && ! empty( $checkin['checkin_id'] ) ) {
			$checkins[] = $checkin;
		}
	}

	if ( empty( $checkins ) ) {
		return new \WP_Error( 'no_checkins', __( 'No checkins found in RSS feed.', 'beer_slurper' ) );
	}

	return array(
		'checkins' => array(
			'count' => count( $checkins ),
			'items' => $checkins,
		),
		'source' => 'rss',
	);
}

/**
 * Parses a single RSS item into checkin data.
 *
 * RSS items contain:
 * - <title>: "Username is drinking Beer Name by Brewery Name"
 * - <link>: Checkin URL (contains checkin_id)
 * - <description>: HTML with rating, comment, badges, location, photo
 * - <pubDate>: RFC 2822 date
 * - <enclosure>: Photo URL (if present)
 *
 * @param \SimpleXMLElement $item The RSS item element.
 *
 * @return array|null Checkin data array, or null if parsing failed.
 */
function parse_rss_item( $item ) {
	$checkin = array(
		'checkin_id'      => null,
		'beer'            => array(),
		'brewery'         => array(),
		'venue'           => array(),
		'user'            => array(),
		'checkin_comment' => '',
		'rating_score'    => 0,
		'created_at'      => '',
		'media'           => array( 'items' => array() ),
		'_source'         => 'rss',
	);

	// Extract checkin ID from link URL.
	$link = (string) $item->link;
	if ( preg_match( '/\/checkin\/(\d+)/', $link, $matches ) ) {
		$checkin['checkin_id'] = (int) $matches[1];
	}

	if ( empty( $checkin['checkin_id'] ) ) {
		return null;
	}

	// Parse title: "Username is drinking Beer Name by Brewery Name"
	$title = (string) $item->title;
	if ( preg_match( '/^(\w+) is drinking (.+?) by (.+)$/', $title, $matches ) ) {
		$checkin['user']['user_name']       = $matches[1];
		$checkin['beer']['beer_name']       = trim( $matches[2] );
		$checkin['brewery']['brewery_name'] = trim( $matches[3] );
	}

	// Parse publication date.
	$pub_date = (string) $item->pubDate;
	if ( $pub_date ) {
		$timestamp = strtotime( $pub_date );
		if ( $timestamp ) {
			$checkin['created_at'] = gmdate( 'Y-m-d H:i:s', $timestamp );
		}
	}

	// Parse description HTML for additional details.
	$description = (string) $item->description;
	if ( $description ) {
		$checkin = parse_rss_description( $checkin, $description );
	}

	// Check for photo enclosure.
	if ( isset( $item->enclosure ) ) {
		$photo_url = (string) $item->enclosure['url'];
		if ( $photo_url && strpos( $photo_url, 'placeholder' ) === false ) {
			$checkin['media']['items'][] = array(
				'photo' => array(
					'photo_img_og' => $photo_url,
				),
			);
		}
	}

	return $checkin;
}

/**
 * Parses the HTML description of an RSS item for additional checkin details.
 *
 * The description typically contains:
 * - Rating (e.g., "Rated: 4.0")
 * - Comment text
 * - Location/venue
 * - Badges earned
 * - Photo
 *
 * @param array  $checkin     The checkin data array to populate.
 * @param string $description The HTML description content.
 *
 * @return array Updated checkin data.
 */
function parse_rss_description( $checkin, $description ) {
	// Extract rating.
	if ( preg_match( '/Rated:\s*([\d.]+)/i', $description, $matches ) ) {
		$checkin['rating_score'] = (float) $matches[1];
	}

	// Extract venue/location.
	if ( preg_match( '/(?:at|@)\s*<a[^>]*>([^<]+)<\/a>/i', $description, $matches ) ) {
		$checkin['venue']['venue_name'] = trim( $matches[1] );
	} elseif ( preg_match( '/(?:at|@)\s+([^<\n]+)/i', $description, $matches ) ) {
		$checkin['venue']['venue_name'] = trim( $matches[1] );
	}

	// Extract comment (text that's not part of the structured data).
	// This is tricky as the format varies. Look for quoted text or standalone paragraphs.
	if ( preg_match( '/"([^"]+)"/', $description, $matches ) ) {
		$checkin['checkin_comment'] = trim( $matches[1] );
	}

	// Extract photo from img tag if not already set from enclosure.
	if ( empty( $checkin['media']['items'] ) && preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/', $description, $matches ) ) {
		$photo_url = $matches[1];
		if ( strpos( $photo_url, 'placeholder' ) === false && strpos( $photo_url, 'badge' ) === false ) {
			$checkin['media']['items'][] = array(
				'photo' => array(
					'photo_img_og' => $photo_url,
				),
			);
		}
	}

	// Extract beer URL to get beer_id.
	if ( preg_match( '/href=["\'][^"\']*\/b\/([^\/]+)\/(\d+)["\']/', $description, $matches ) ) {
		$checkin['beer']['beer_slug'] = $matches[1];
		$checkin['beer']['bid']       = (int) $matches[2];
	}

	// Extract brewery URL to get brewery_id.
	if ( preg_match( '/href=["\'][^"\']*\/w\/([^\/]+)\/(\d+)["\']/', $description, $matches ) ) {
		$checkin['brewery']['brewery_slug'] = $matches[1];
		$checkin['brewery']['brewery_id']   = (int) $matches[2];
	}

	// Extract venue URL to get venue_id.
	if ( preg_match( '/href=["\'][^"\']*\/v\/([^\/]+)\/(\d+)["\']/', $description, $matches ) ) {
		$checkin['venue']['venue_slug'] = $matches[1];
		$checkin['venue']['venue_id']   = (int) $matches[2];
	}

	return $checkin;
}

/**
 * Gets the configured RSS feed URL.
 *
 * @return string|null The RSS feed URL, or null if not configured.
 */
function get_rss_url() {
	return get_option( 'beer_slurper_rss_url', '' );
}

/**
 * Validates an Untappd RSS feed URL.
 *
 * @param string $url The URL to validate.
 *
 * @return bool True if valid Untappd RSS URL.
 */
function is_valid_rss_url( $url ) {
	if ( empty( $url ) ) {
		return false;
	}

	if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
		return false;
	}

	// Must be an Untappd RSS URL.
	return (bool) preg_match( '/^https?:\/\/untappd\.com\/rss\/user\/\w+/', $url );
}

/*
 * =============================================================================
 * PAGE SCRAPING FUNCTIONS (Fallback)
 * =============================================================================
 * Page scraping is used as a fallback when:
 * 1. RSS URL is not configured
 * 2. We need additional details not available in RSS (beer_id, brewery_id, etc.)
 *
 * The RSS-first approach means we scrape less frequently and more targeted.
 * =============================================================================
 */

/**
 * Makes an HTTP request to Untappd with appropriate headers.
 *
 * @param string $url The URL to fetch.
 *
 * @return array|WP_Error Response array with 'body' and 'headers', or WP_Error on failure.
 */
function fetch_page( $url ) {
	$args = array(
		'timeout'     => 30,
		'redirection' => 5,
		'user-agent'  => USER_AGENT,
		'headers'     => array(
			'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language' => 'en-US,en;q=0.5',
			'Cache-Control'   => 'no-cache',
			'DNT'             => '1',
		),
	);

	$response = wp_remote_get( $url, $args );

	if ( is_wp_error( $response ) ) {
		error_log( 'Beer Slurper Scraper: Request failed - ' . $response->get_error_message() );
		return $response;
	}

	$status_code = wp_remote_retrieve_response_code( $response );

	if ( 200 !== $status_code ) {
		error_log( 'Beer Slurper Scraper: HTTP ' . $status_code . ' for ' . $url );
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

/**
 * Fetches and parses a user's recent checkins from their public profile.
 *
 * @param string $username The Untappd username.
 *
 * @return array|WP_Error Array of checkin data, or WP_Error on failure.
 */
function get_user_checkins( $username ) {
	$username = sanitize_user( $username );

	if ( empty( $username ) ) {
		return new \WP_Error( 'invalid_user', __( 'Invalid username provided.', 'beer_slurper' ) );
	}

	// Check cache first.
	$cache_key = 'beer_slurper_scrape_' . md5( $username );
	$cached    = get_transient( $cache_key );

	if ( false !== $cached ) {
		return $cached;
	}

	$url      = UNTAPPD_BASE_URL . '/user/' . $username;
	$response = fetch_page( $url );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$checkins = parse_user_checkins( $response['body'], $username );

	if ( is_wp_error( $checkins ) ) {
		return $checkins;
	}

	// Cache the results.
	set_transient( $cache_key, $checkins, CACHE_DURATION );

	return $checkins;
}

/**
 * Parses checkin data from a user profile HTML page.
 *
 * @param string $html     The HTML content of the user profile page.
 * @param string $username The username (for context in error messages).
 *
 * @return array|WP_Error Array of checkin data arrays, or WP_Error on failure.
 */
function parse_user_checkins( $html, $username ) {
	if ( empty( $html ) ) {
		return new \WP_Error( 'empty_response', __( 'Empty response from Untappd.', 'beer_slurper' ) );
	}

	// Suppress HTML parsing errors.
	libxml_use_internal_errors( true );

	$doc = new \DOMDocument();
	$doc->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ), LIBXML_NOWARNING | LIBXML_NOERROR );

	libxml_clear_errors();

	$xpath = new \DOMXPath( $doc );

	// Find checkin items - Untappd uses .checkin class for each checkin entry.
	$checkin_nodes = $xpath->query( "//div[contains(@class, 'checkin')]" );

	if ( 0 === $checkin_nodes->length ) {
		// Try alternative selector - the activity stream.
		$checkin_nodes = $xpath->query( "//div[@id='main-stream']//div[contains(@class, 'item')]" );
	}

	if ( 0 === $checkin_nodes->length ) {
		error_log( 'Beer Slurper Scraper: No checkins found for user ' . $username );
		return new \WP_Error( 'no_checkins', __( 'No checkins found. The profile may be private or the page structure has changed.', 'beer_slurper' ) );
	}

	$checkins = array();

	foreach ( $checkin_nodes as $node ) {
		$checkin = parse_single_checkin( $node, $xpath );

		if ( $checkin && ! empty( $checkin['checkin_id'] ) ) {
			$checkins[] = $checkin;
		}
	}

	if ( empty( $checkins ) ) {
		return new \WP_Error( 'parse_failed', __( 'Failed to parse any checkins from the page.', 'beer_slurper' ) );
	}

	return array(
		'checkins' => array(
			'count' => count( $checkins ),
			'items' => $checkins,
		),
	);
}

/**
 * Parses a single checkin from a DOM node.
 *
 * @param \DOMElement $node  The checkin DOM node.
 * @param \DOMXPath   $xpath The XPath object for queries.
 *
 * @return array|null Checkin data array, or null if parsing failed.
 */
function parse_single_checkin( $node, $xpath ) {
	$checkin = array(
		'checkin_id'      => null,
		'beer'            => array(),
		'brewery'         => array(),
		'venue'           => array(),
		'user'            => array(),
		'checkin_comment' => '',
		'rating_score'    => 0,
		'created_at'      => '',
		'media'           => array( 'items' => array() ),
	);

	// Get checkin ID from data attribute or link.
	$checkin_id = $node->getAttribute( 'data-checkin-id' );
	if ( empty( $checkin_id ) ) {
		// Try to find it in a link.
		$checkin_links = $xpath->query( ".//a[contains(@href, '/checkin/')]", $node );
		if ( $checkin_links->length > 0 ) {
			$href = $checkin_links->item( 0 )->getAttribute( 'href' );
			if ( preg_match( '/\/checkin\/(\d+)/', $href, $matches ) ) {
				$checkin_id = $matches[1];
			}
		}
	}

	if ( empty( $checkin_id ) ) {
		// Try the ID attribute of the node itself.
		$id_attr = $node->getAttribute( 'id' );
		if ( preg_match( '/checkin[_-]?(\d+)/i', $id_attr, $matches ) ) {
			$checkin_id = $matches[1];
		}
	}

	$checkin['checkin_id'] = $checkin_id;

	// Get beer info.
	$beer_link = $xpath->query( ".//a[contains(@href, '/b/')]", $node );
	if ( $beer_link->length > 0 ) {
		$beer_element = $beer_link->item( 0 );
		$beer_href    = $beer_element->getAttribute( 'href' );
		$beer_name    = trim( $beer_element->textContent );

		// Extract beer ID from URL: /b/beer-slug/12345.
		if ( preg_match( '/\/b\/([^\/]+)\/(\d+)/', $beer_href, $matches ) ) {
			$checkin['beer']['beer_slug'] = $matches[1];
			$checkin['beer']['bid']       = (int) $matches[2];
		}

		$checkin['beer']['beer_name'] = $beer_name;
	}

	// Get brewery info.
	$brewery_link = $xpath->query( ".//a[contains(@href, '/w/')]", $node );
	if ( $brewery_link->length > 0 ) {
		$brewery_element = $brewery_link->item( 0 );
		$brewery_href    = $brewery_element->getAttribute( 'href' );
		$brewery_name    = trim( $brewery_element->textContent );

		// Extract brewery ID from URL: /w/brewery-slug/12345 or /brewery/12345.
		if ( preg_match( '/\/w\/([^\/]+)\/(\d+)/', $brewery_href, $matches ) ) {
			$checkin['brewery']['brewery_slug'] = $matches[1];
			$checkin['brewery']['brewery_id']   = (int) $matches[2];
		} elseif ( preg_match( '/\/brewery\/(\d+)/', $brewery_href, $matches ) ) {
			$checkin['brewery']['brewery_id'] = (int) $matches[1];
		}

		$checkin['brewery']['brewery_name'] = $brewery_name;
	}

	// Get venue info.
	$venue_link = $xpath->query( ".//a[contains(@href, '/v/')]", $node );
	if ( $venue_link->length > 0 ) {
		$venue_element = $venue_link->item( 0 );
		$venue_href    = $venue_element->getAttribute( 'href' );
		$venue_name    = trim( $venue_element->textContent );

		// Extract venue ID from URL: /v/venue-slug/12345.
		if ( preg_match( '/\/v\/([^\/]+)\/(\d+)/', $venue_href, $matches ) ) {
			$checkin['venue']['venue_slug'] = $matches[1];
			$checkin['venue']['venue_id']   = (int) $matches[2];
		}

		$checkin['venue']['venue_name'] = $venue_name;
	}

	// Get rating.
	$rating_element = $xpath->query( ".//*[contains(@class, 'rating')]/*[contains(@class, 'caps')]", $node );
	if ( $rating_element->length > 0 ) {
		$rating_text = trim( $rating_element->item( 0 )->textContent );
		if ( preg_match( '/([\d.]+)/', $rating_text, $matches ) ) {
			$checkin['rating_score'] = (float) $matches[1];
		}
	}

	// Alternative rating from data attribute.
	if ( empty( $checkin['rating_score'] ) ) {
		$rating_node = $xpath->query( ".//*[@data-rating]", $node );
		if ( $rating_node->length > 0 ) {
			$checkin['rating_score'] = (float) $rating_node->item( 0 )->getAttribute( 'data-rating' );
		}
	}

	// Get comment.
	$comment_element = $xpath->query( ".//*[contains(@class, 'comment-text')]", $node );
	if ( $comment_element->length > 0 ) {
		$checkin['checkin_comment'] = trim( $comment_element->item( 0 )->textContent );
	}

	// Get date/time.
	$time_element = $xpath->query( ".//a[contains(@class, 'time')]", $node );
	if ( $time_element->length > 0 ) {
		// Try data-gregtime attribute first (Unix timestamp).
		$gregtime = $time_element->item( 0 )->getAttribute( 'data-gregtime' );
		if ( $gregtime ) {
			$checkin['created_at'] = gmdate( 'Y-m-d H:i:s', (int) $gregtime );
		} else {
			// Fall back to parsing the text.
			$time_text = trim( $time_element->item( 0 )->textContent );
			$checkin['created_at'] = parse_relative_time( $time_text );
		}
	}

	// Get photo if present.
	$photo_element = $xpath->query( ".//img[contains(@class, 'photo')]", $node );
	if ( $photo_element->length > 0 ) {
		$photo_src = $photo_element->item( 0 )->getAttribute( 'src' );
		// Also check data-original for lazy-loaded images.
		$photo_original = $photo_element->item( 0 )->getAttribute( 'data-original' );
		$photo_url      = $photo_original ? $photo_original : $photo_src;

		if ( $photo_url && strpos( $photo_url, 'placeholder' ) === false ) {
			$checkin['media']['items'][] = array(
				'photo' => array(
					'photo_img_og' => $photo_url,
				),
			);
		}
	}

	// Get user info (should be the profile owner for their own checkins).
	$user_link = $xpath->query( ".//a[contains(@href, '/user/')]", $node );
	if ( $user_link->length > 0 ) {
		$user_element = $user_link->item( 0 );
		$user_href    = $user_element->getAttribute( 'href' );

		if ( preg_match( '/\/user\/([^\/\?]+)/', $user_href, $matches ) ) {
			$checkin['user']['user_name'] = $matches[1];
		}
	}

	return $checkin;
}

/**
 * Parses a relative time string into an ISO date.
 *
 * Handles strings like "2 hours ago", "yesterday", "3 days ago", etc.
 *
 * @param string $time_text The relative time text.
 *
 * @return string ISO 8601 date string.
 */
function parse_relative_time( $time_text ) {
	$time_text = strtolower( trim( $time_text ) );
	$now       = time();

	// Direct date format (e.g., "Jan 15, 2024").
	$parsed = strtotime( $time_text );
	if ( false !== $parsed && $parsed > 0 ) {
		return gmdate( 'Y-m-d H:i:s', $parsed );
	}

	// Relative time patterns.
	if ( preg_match( '/(\d+)\s*min/', $time_text, $matches ) ) {
		return gmdate( 'Y-m-d H:i:s', $now - ( (int) $matches[1] * 60 ) );
	}
	if ( preg_match( '/(\d+)\s*hour/', $time_text, $matches ) ) {
		return gmdate( 'Y-m-d H:i:s', $now - ( (int) $matches[1] * 3600 ) );
	}
	if ( preg_match( '/(\d+)\s*day/', $time_text, $matches ) ) {
		return gmdate( 'Y-m-d H:i:s', $now - ( (int) $matches[1] * 86400 ) );
	}
	if ( preg_match( '/(\d+)\s*week/', $time_text, $matches ) ) {
		return gmdate( 'Y-m-d H:i:s', $now - ( (int) $matches[1] * 604800 ) );
	}
	if ( preg_match( '/(\d+)\s*month/', $time_text, $matches ) ) {
		return gmdate( 'Y-m-d H:i:s', $now - ( (int) $matches[1] * 2592000 ) );
	}
	if ( strpos( $time_text, 'yesterday' ) !== false ) {
		return gmdate( 'Y-m-d H:i:s', $now - 86400 );
	}
	if ( strpos( $time_text, 'just now' ) !== false || strpos( $time_text, 'moment' ) !== false ) {
		return gmdate( 'Y-m-d H:i:s', $now );
	}

	// Default to now if we can't parse.
	return gmdate( 'Y-m-d H:i:s', $now );
}

/**
 * Fetches beer details from the public beer page.
 *
 * Note: This provides less data than the API. Missing fields include:
 * - beer_description
 * - collaborations
 * - detailed stats
 *
 * @param int $beer_id The Untappd beer ID.
 *
 * @return array|WP_Error Beer data array, or WP_Error on failure.
 */
function get_beer_info( $beer_id ) {
	$beer_id = (int) $beer_id;

	if ( empty( $beer_id ) ) {
		return new \WP_Error( 'invalid_beer', __( 'Invalid beer ID.', 'beer_slurper' ) );
	}

	// Check cache.
	$cache_key = 'beer_slurper_scrape_beer_' . $beer_id;
	$cached    = get_transient( $cache_key );

	if ( false !== $cached ) {
		return $cached;
	}

	// We need to find the beer slug to construct the URL.
	// Try fetching with just the ID first (Untappd redirects).
	$url      = UNTAPPD_BASE_URL . '/beer/' . $beer_id;
	$response = fetch_page( $url );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$beer = parse_beer_page( $response['body'], $beer_id );

	if ( ! is_wp_error( $beer ) ) {
		set_transient( $cache_key, $beer, CACHE_DURATION * 4 ); // Cache beer info longer.
	}

	return $beer;
}

/**
 * Parses beer data from a beer page HTML.
 *
 * @param string $html    The HTML content.
 * @param int    $beer_id The beer ID for reference.
 *
 * @return array|WP_Error Beer data array, or WP_Error on failure.
 */
function parse_beer_page( $html, $beer_id ) {
	if ( empty( $html ) ) {
		return new \WP_Error( 'empty_response', __( 'Empty response from Untappd.', 'beer_slurper' ) );
	}

	libxml_use_internal_errors( true );

	$doc = new \DOMDocument();
	$doc->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ), LIBXML_NOWARNING | LIBXML_NOERROR );

	libxml_clear_errors();

	$xpath = new \DOMXPath( $doc );

	$beer = array(
		'bid'        => $beer_id,
		'beer_name'  => '',
		'beer_style' => '',
		'beer_abv'   => 0,
		'beer_ibu'   => 0,
		'brewery'    => array(),
	);

	// Get beer name from h1.
	$name_element = $xpath->query( "//h1[contains(@class, 'name')]" );
	if ( $name_element->length > 0 ) {
		$beer['beer_name'] = trim( $name_element->item( 0 )->textContent );
	}

	// Get style.
	$style_element = $xpath->query( "//p[contains(@class, 'style')]" );
	if ( $style_element->length > 0 ) {
		$beer['beer_style'] = trim( $style_element->item( 0 )->textContent );
	}

	// Get ABV and IBU from the details section.
	$details_text = '';
	$details_element = $xpath->query( "//*[contains(@class, 'details')]" );
	if ( $details_element->length > 0 ) {
		$details_text = $details_element->item( 0 )->textContent;
	}

	if ( preg_match( '/([\d.]+)\s*%\s*ABV/i', $details_text, $matches ) ) {
		$beer['beer_abv'] = (float) $matches[1];
	}
	if ( preg_match( '/(\d+)\s*IBU/i', $details_text, $matches ) ) {
		$beer['beer_ibu'] = (int) $matches[1];
	}

	// Get brewery info.
	$brewery_link = $xpath->query( "//a[contains(@href, '/w/')]" );
	if ( $brewery_link->length > 0 ) {
		$brewery_element = $brewery_link->item( 0 );
		$brewery_href    = $brewery_element->getAttribute( 'href' );
		$brewery_name    = trim( $brewery_element->textContent );

		if ( preg_match( '/\/w\/([^\/]+)\/(\d+)/', $brewery_href, $matches ) ) {
			$beer['brewery']['brewery_slug'] = $matches[1];
			$beer['brewery']['brewery_id']   = (int) $matches[2];
		}

		$beer['brewery']['brewery_name'] = $brewery_name;
	}

	if ( empty( $beer['beer_name'] ) ) {
		return new \WP_Error( 'parse_failed', __( 'Failed to parse beer details.', 'beer_slurper' ) );
	}

	return array( 'beer' => $beer );
}

/**
 * Checks if scraping is enabled in settings.
 *
 * @return bool True if scraping is enabled.
 */
function is_enabled() {
	$data_source = get_option( 'beer_slurper_data_source', 'api' );
	return in_array( $data_source, array( 'scraper', 'hybrid' ), true );
}

/**
 * Checks if scraping is the primary data source (no API).
 *
 * @return bool True if scraper is the primary source.
 */
function is_primary() {
	return 'scraper' === get_option( 'beer_slurper_data_source', 'api' );
}

/**
 * Gets the configured data source mode.
 *
 * @return string One of: 'api', 'scraper', 'hybrid'.
 */
function get_data_source() {
	return get_option( 'beer_slurper_data_source', 'api' );
}
