<?php
/**
 * Untappd Web Scraper
 *
 * Provides web scraping functionality to fetch public Untappd data
 * without requiring API credentials. This enables users without API
 * access to sync their recent checkins.
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
const USER_AGENT = 'personal-beerlog-backup/1.0 (WordPress plugin for personal beer history; non-commercial; https://github.com/kraftbj/flavor-flavor)';

/**
 * Base URL for Untappd website.
 */
const UNTAPPD_BASE_URL = 'https://untappd.com';

/**
 * Cache duration for scraped data (15 minutes).
 */
const CACHE_DURATION = 900;

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
		$checkin = parse_single_checkin( $node, $xpath, $doc );

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
 * @param \DOMDocument $doc  The DOM document.
 *
 * @return array|null Checkin data array, or null if parsing failed.
 */
function parse_single_checkin( $node, $xpath, $doc ) {
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

		if ( $photo_url && ! str_contains( $photo_url, 'placeholder' ) ) {
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
	if ( str_contains( $time_text, 'yesterday' ) ) {
		return gmdate( 'Y-m-d H:i:s', $now - 86400 );
	}
	if ( str_contains( $time_text, 'just now' ) || str_contains( $time_text, 'moment' ) ) {
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
