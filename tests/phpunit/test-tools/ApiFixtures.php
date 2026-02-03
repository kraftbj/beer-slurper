<?php
/**
 * API Fixtures for Beer Slurper Tests
 *
 * Provides sample Untappd API responses for testing purposes.
 *
 * @package Kraft\Beer_Slurper\Tests
 */

namespace Kraft\Beer_Slurper\Tests;

/**
 * API fixture provider for testing.
 *
 * Contains methods to generate realistic Untappd API response data.
 */
class ApiFixtures {

	/**
	 * Returns a sample checkin response.
	 *
	 * @param array $overrides Optional. Values to override in the checkin.
	 *
	 * @return array Checkin data structure.
	 */
	public static function checkin( $overrides = array() ) {
		$defaults = array(
			'checkin_id'      => 123456789,
			'created_at'      => '2024-01-15 14:30:00',
			'checkin_comment' => 'Great beer!',
			'rating_score'    => 4.25,
			'beer'            => self::beer_basic(),
			'brewery'         => self::brewery_basic(),
			'user'            => self::user(),
			'venue'           => self::venue(),
			'badges'          => array( 'count' => 0, 'items' => array() ),
			'tagged_friends'  => array( 'count' => 0, 'items' => array() ),
			'media'           => array( 'count' => 0, 'items' => array() ),
			'serving_type'    => 'Draft',
		);

		return array_replace_recursive( $defaults, $overrides );
	}

	/**
	 * Returns a basic beer data structure.
	 *
	 * @param array $overrides Optional. Values to override.
	 *
	 * @return array Beer data.
	 */
	public static function beer_basic( $overrides = array() ) {
		$defaults = array(
			'bid'           => 12345,
			'beer_name'     => 'Test IPA',
			'beer_slug'     => 'test-ipa',
			'beer_label'    => 'https://example.com/beer-label.jpg',
			'beer_abv'      => 6.5,
			'beer_ibu'      => 65,
			'beer_style'    => 'IPA - American',
			'stats'         => array(
				'user_count' => 5,
			),
		);

		return array_replace_recursive( $defaults, $overrides );
	}

	/**
	 * Returns a full beer info response (from beer/info endpoint).
	 *
	 * @param array $overrides Optional. Values to override.
	 *
	 * @return array Full beer info response.
	 */
	public static function beer_info( $overrides = array() ) {
		$defaults = array(
			'meta'     => array(
				'code' => 200,
			),
			'response' => array(
				'beer' => array(
					'bid'              => 12345,
					'beer_name'        => 'Test IPA',
					'beer_slug'        => 'test-ipa',
					'beer_label'       => 'https://example.com/beer-label.jpg',
					'beer_abv'         => 6.5,
					'beer_ibu'         => 65,
					'beer_style'       => 'IPA - American',
					'beer_description' => 'A delicious hoppy IPA.',
					'brewery'          => self::brewery_basic(),
					'collaborations_with' => array( 'count' => 0, 'items' => array() ),
				),
			),
		);

		return array_replace_recursive( $defaults, $overrides );
	}

	/**
	 * Returns a basic brewery data structure.
	 *
	 * @param array $overrides Optional. Values to override.
	 *
	 * @return array Brewery data.
	 */
	public static function brewery_basic( $overrides = array() ) {
		$defaults = array(
			'brewery_id'          => 54321,
			'brewery_name'        => 'Test Brewing Company',
			'brewery_slug'        => 'test-brewing-company',
			'brewery_type'        => 'Micro Brewery',
			'brewery_label'       => 'https://example.com/brewery-label.jpg',
			'brewery_description' => 'A craft brewery making great beers.',
			'location'            => array(
				'brewery_address' => '123 Beer Street',
				'brewery_city'    => 'Portland',
				'brewery_state'   => 'OR',
				'brewery_country' => 'United States',
				'brewery_lat'     => 45.5051,
				'brewery_lng'     => -122.6750,
			),
			'contact'             => array(
				'url'       => 'https://testbrewing.com',
				'twitter'   => 'testbrewing',
				'facebook'  => 'testbrewing',
				'instagram' => 'testbrewing',
			),
		);

		return array_replace_recursive( $defaults, $overrides );
	}

	/**
	 * Returns a full brewery info response (from brewery/info endpoint).
	 *
	 * @param array $overrides Optional. Values to override.
	 *
	 * @return array Full brewery info response.
	 */
	public static function brewery_info( $overrides = array() ) {
		$brewery = self::brewery_basic( $overrides['brewery'] ?? array() );

		return array(
			'meta'     => array(
				'code' => 200,
			),
			'response' => array(
				'brewery' => array_merge(
					$brewery,
					array(
						'owners' => array( 'count' => 0, 'items' => array() ),
					)
				),
			),
		);
	}

	/**
	 * Returns a user data structure.
	 *
	 * @param array $overrides Optional. Values to override.
	 *
	 * @return array User data.
	 */
	public static function user( $overrides = array() ) {
		$defaults = array(
			'uid'         => 11111,
			'user_name'   => 'testuser',
			'first_name'  => 'Test',
			'last_name'   => 'User',
			'user_avatar' => 'https://example.com/avatar.jpg',
			'url'         => 'https://testuser.example.com',
		);

		return array_replace_recursive( $defaults, $overrides );
	}

	/**
	 * Returns a venue data structure.
	 *
	 * @param array $overrides Optional. Values to override.
	 *
	 * @return array Venue data.
	 */
	public static function venue( $overrides = array() ) {
		$defaults = array(
			'venue_id'         => 99999,
			'venue_name'       => 'Test Taproom',
			'venue_slug'       => 'test-taproom',
			'primary_category' => 'Nightlife Spot',
			'location'         => array(
				'venue_address' => '456 Tap Street',
				'venue_city'    => 'Portland',
				'venue_state'   => 'OR',
				'venue_country' => 'United States',
				'lat'           => 45.5051,
				'lng'           => -122.6750,
			),
			'contact'          => array(
				'venue_url' => 'https://testtaproom.com',
			),
			'venue_icon'       => array(
				'sm' => 'https://example.com/venue-icon.png',
			),
			'foursquare'       => array(
				'foursquare_id' => '4abc123',
			),
		);

		return array_replace_recursive( $defaults, $overrides );
	}

	/**
	 * Returns a badge data structure.
	 *
	 * @param array $overrides Optional. Values to override.
	 *
	 * @return array Badge data.
	 */
	public static function badge( $overrides = array() ) {
		$defaults = array(
			'badge_id'          => 77777,
			'badge_name'        => 'Hopped Up (Level 5)',
			'badge_description' => 'Earned for drinking 5 IPAs.',
			'badge_image'       => array(
				'sm' => 'https://example.com/badge-sm.png',
				'md' => 'https://example.com/badge-md.png',
				'lg' => 'https://example.com/badge-lg.png',
			),
		);

		return array_replace_recursive( $defaults, $overrides );
	}

	/**
	 * Returns a user/checkins API response.
	 *
	 * @param array $checkins Array of checkin data.
	 * @param array $pagination Optional. Pagination data.
	 *
	 * @return array API response structure.
	 */
	public static function user_checkins_response( $checkins, $pagination = array() ) {
		$count = count( $checkins );

		$pagination = array_merge(
			array(
				'since_url' => '?min_id=' . ( $checkins[0]['checkin_id'] ?? 123456789 ),
				'max_id'    => $count > 0 ? $checkins[ $count - 1 ]['checkin_id'] - 1 : null,
			),
			$pagination
		);

		return array(
			'meta'     => array( 'code' => 200 ),
			'response' => array(
				'checkins'   => array(
					'count' => $count,
					'items' => $checkins,
				),
				'pagination' => $pagination,
			),
		);
	}

	/**
	 * Returns a checkin/view API response.
	 *
	 * @param array $checkin The checkin data.
	 *
	 * @return array API response structure.
	 */
	public static function checkin_view_response( $checkin ) {
		return array(
			'meta'     => array( 'code' => 200 ),
			'response' => array(
				'checkin' => $checkin,
			),
		);
	}

	/**
	 * Returns a venue/info API response.
	 *
	 * @param array $overrides Optional. Values to override.
	 *
	 * @return array API response structure.
	 */
	public static function venue_info( $overrides = array() ) {
		return array(
			'meta'     => array( 'code' => 200 ),
			'response' => array(
				'venue' => self::venue( $overrides ),
			),
		);
	}

	/**
	 * Returns an API error response.
	 *
	 * @param string $error_type   Error type code.
	 * @param string $error_detail Error message.
	 * @param int    $http_code    HTTP status code.
	 *
	 * @return array API error response.
	 */
	public static function error_response( $error_type, $error_detail, $http_code = 400 ) {
		return array(
			'meta' => array(
				'code'         => $http_code,
				'error_type'   => $error_type,
				'error_detail' => $error_detail,
			),
			'response' => array(),
		);
	}

	/**
	 * Returns a rate limit exceeded error response.
	 *
	 * @return array Rate limit error response.
	 */
	public static function rate_limit_error() {
		return self::error_response(
			'rate_limit_exceeded',
			'API rate limit exceeded. Please try again later.',
			429
		);
	}

	/**
	 * Returns companion/tagged friend data.
	 *
	 * @param array $overrides Optional. Values to override.
	 *
	 * @return array Tagged friend data structure.
	 */
	public static function tagged_friend( $overrides = array() ) {
		$defaults = array(
			'user' => self::user( array(
				'uid'       => 22222,
				'user_name' => 'drinkingbuddy',
				'first_name' => 'Drinking',
				'last_name'  => 'Buddy',
			) ),
		);

		return array_replace_recursive( $defaults, $overrides );
	}

	/**
	 * Returns a single toast (like) data structure.
	 *
	 * @param array $overrides Optional. Values to override.
	 *
	 * @return array Toast data structure.
	 */
	public static function toast( $overrides = array() ) {
		$defaults = array(
			'like_id'    => 987654321,
			'created_at' => '2024-01-15 15:00:00',
			'user'       => self::user( array(
				'uid'        => 33333,
				'user_name'  => 'toastmaster',
				'first_name' => 'Toast',
				'last_name'  => 'Master',
			) ),
		);

		return array_replace_recursive( $defaults, $overrides );
	}

	/**
	 * Returns a toasts array structure for a checkin.
	 *
	 * @param int   $count     Number of toasts to generate.
	 * @param array $overrides Optional. Values to override for each toast.
	 *
	 * @return array Toasts structure with count and items.
	 */
	public static function toasts( $count = 1, $overrides = array() ) {
		$items = array();

		for ( $i = 0; $i < $count; $i++ ) {
			$items[] = self::toast( array_merge(
				array(
					'like_id' => 987654321 + $i,
					'user'    => self::user( array(
						'uid'        => 33333 + $i,
						'user_name'  => 'toaster' . ( $i + 1 ),
						'first_name' => 'Toaster',
						'last_name'  => (string) ( $i + 1 ),
					) ),
				),
				$overrides
			) );
		}

		return array(
			'count' => $count,
			'items' => $items,
		);
	}

	/**
	 * Loads a fixture from a JSON file if it exists.
	 *
	 * @param string $name Fixture name (without .json extension).
	 *
	 * @return array|null Decoded fixture data or null if not found.
	 */
	public static function load_from_file( $name ) {
		$path = __DIR__ . '/../fixtures/api/' . $name . '.json';
		if ( ! file_exists( $path ) ) {
			return null;
		}

		$json = file_get_contents( $path );
		return json_decode( $json, true );
	}

	/**
	 * Saves fixture data to a JSON file.
	 *
	 * @param string $name Fixture name (without .json extension).
	 * @param array  $data Data to save.
	 *
	 * @return bool True on success.
	 */
	public static function save_to_file( $name, $data ) {
		$path = __DIR__ . '/../fixtures/api/' . $name . '.json';
		return (bool) file_put_contents(
			$path,
			json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
		);
	}
}
