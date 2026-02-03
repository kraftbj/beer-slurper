<?php
/**
 * Import/Export Tests for Beer Slurper
 *
 * Tests for the import/export functionality including CSV parsing,
 * JSON import, row transformation, duplicate detection, and error handling.
 *
 * @package Kraft\Beer_Slurper\Import
 */

namespace Kraft\Beer_Slurper\Import;

/**
 * Tests for the Import/Export functions.
 *
 * Validates the import functionality including CSV and JSON parsing,
 * the csv_row_to_checkin transformation, duplicate detection,
 * and error handling for malformed files.
 *
 * References:
 *   - http://phpunit.de/manual/current/en/index.html
 *   - https://github.com/padraic/mockery
 *   - https://github.com/10up/wp_mock
 */

use Kraft\Beer_Slurper as Base;

class Import_Export_Tests extends Base\TestCase {

	protected $testFiles = [
		'functions/sync-status.php',
		'functions/api.php',
		'functions/oauth.php',
		'functions/post.php',
		'functions/brewery.php',
		'functions/venue.php',
		'functions/badge.php',
		'functions/checkin.php',
		'functions/companion.php',
		'functions/toast.php',
		'functions/import-export.php',
	];

	/**
	 * Temporary directory for test files.
	 *
	 * @var string
	 */
	protected $temp_dir;

	/**
	 * Sets up the test environment before each test.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();

		$this->temp_dir = sys_get_temp_dir() . '/beer-slurper-tests-' . uniqid();
		mkdir( $this->temp_dir );
	}

	/**
	 * Cleans up the test environment after each test.
	 *
	 * @return void
	 */
	protected function tear_down(): void {
		// Clean up temporary files.
		if ( is_dir( $this->temp_dir ) ) {
			$files = glob( $this->temp_dir . '/*' );
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					unlink( $file );
				}
			}
			rmdir( $this->temp_dir );
		}

		parent::tear_down();
	}

	/**
	 * Creates a temporary CSV file with the given content.
	 *
	 * @param string $content CSV content.
	 * @return string Path to the temporary file.
	 */
	protected function create_temp_csv( $content ) {
		$path = $this->temp_dir . '/test-' . uniqid() . '.csv';
		file_put_contents( $path, $content );
		return $path;
	}

	/**
	 * Creates a temporary JSON file with the given data.
	 *
	 * @param array $data Data to encode as JSON.
	 * @return string Path to the temporary file.
	 */
	protected function create_temp_json( $data ) {
		$path = $this->temp_dir . '/test-' . uniqid() . '.json';
		file_put_contents( $path, json_encode( $data ) );
		return $path;
	}

	// =========================================================================
	// csv_row_to_checkin Tests
	// =========================================================================

	/**
	 * Tests csv_row_to_checkin() transforms a complete CSV row correctly.
	 *
	 * Verifies that all fields from a standard Untappd export CSV row are
	 * correctly transformed into the nested checkin format.
	 */
	public function test_csv_row_to_checkin_transforms_complete_row() {
		// Set up configured user using real WordPress option.
		update_option( 'beer-slurper-user', 'testuser' );

		$row = array(
			'beer_name'       => 'Test IPA',
			'brewery_name'    => 'Test Brewery',
			'beer_type'       => 'IPA - American',
			'beer_abv'        => '6.5',
			'beer_ibu'        => '65',
			'comment'         => 'Great beer!',
			'venue_name'      => 'Test Bar',
			'venue_city'      => 'Portland',
			'venue_state'     => 'OR',
			'venue_country'   => 'United States',
			'venue_lat'       => '45.5231',
			'venue_lng'       => '-122.6765',
			'rating_score'    => '4.25',
			'created_at'      => '2024-01-20 18:30:00',
			'checkin_url'     => 'https://untappd.com/user/testuser/checkin/123456',
			'beer_url'        => 'https://untappd.com/b/test-ipa/789',
			'brewery_url'     => 'https://untappd.com/w/test-brewery/456',
			'brewery_country' => 'United States',
			'brewery_city'    => 'San Diego',
			'brewery_state'   => 'CA',
			'flavor_profiles' => 'hoppy, citrus, bitter',
			'purchase_venue'  => '',
			'serving_type'    => 'Draft',
			'checkin_id'      => '123456',
			'bid'             => '789',
			'brewery_id'      => '456',
			'photo_url'       => 'https://untappd.akamaized.net/photos/123.jpg',
		);

		$result = csv_row_to_checkin( $row );

		$this->assertIsArray( $result );
		$this->assertEquals( 123456, $result['checkin_id'] );
		$this->assertEquals( 'Great beer!', $result['checkin_comment'] );
		$this->assertEquals( '2024-01-20 18:30:00', $result['created_at'] );
		$this->assertEquals( 4.25, $result['rating_score'] );
		$this->assertEquals( 'Draft', $result['serving_type'] );

		// Beer assertions.
		$this->assertEquals( 789, $result['beer']['bid'] );
		$this->assertEquals( 'Test IPA', $result['beer']['beer_name'] );
		$this->assertEquals( 'IPA - American', $result['beer']['beer_style'] );
		$this->assertEquals( 6.5, $result['beer']['beer_abv'] );
		$this->assertEquals( 65, $result['beer']['beer_ibu'] );

		// Brewery assertions.
		$this->assertEquals( 456, $result['brewery']['brewery_id'] );
		$this->assertEquals( 'Test Brewery', $result['brewery']['brewery_name'] );
		$this->assertEquals( 'San Diego', $result['brewery']['location']['brewery_city'] );
		$this->assertEquals( 'CA', $result['brewery']['location']['brewery_state'] );
		$this->assertEquals( 'United States', $result['brewery']['location']['brewery_country'] );

		// Venue assertions.
		$this->assertEquals( 'Test Bar', $result['venue']['venue_name'] );
		$this->assertEquals( 'Portland', $result['venue']['location']['venue_city'] );
		$this->assertEquals( 'OR', $result['venue']['location']['venue_state'] );
		$this->assertEquals( 'United States', $result['venue']['location']['venue_country'] );
		$this->assertEquals( 45.5231, $result['venue']['location']['lat'] );
		$this->assertEquals( -122.6765, $result['venue']['location']['lng'] );

		// User assertions.
		$this->assertEquals( 'testuser', $result['user']['user_name'] );

		// Media assertions.
		$this->assertCount( 1, $result['media']['items'] );
		$this->assertEquals( 'https://untappd.akamaized.net/photos/123.jpg', $result['media']['items'][0]['photo']['photo_img_og'] );

		// Import meta assertions.
		$this->assertEquals( 'hoppy, citrus, bitter', $result['_import_meta']['flavor_profiles'] );
		$this->assertEquals( 'untappd_export', $result['_import_source'] );
	}

	/**
	 * Tests csv_row_to_checkin() handles minimal required fields.
	 *
	 * Verifies that the function works with only the required fields
	 * (beer_name and checkin_id).
	 */
	public function test_csv_row_to_checkin_handles_minimal_fields() {
		// No configured user - delete the option to ensure null return.
		delete_option( 'beer-slurper-user' );
		// current_time() works natively in WorDBless.

		$row = array(
			'beer_name'  => 'Minimal Beer',
			'checkin_id' => '999',
		);

		$result = csv_row_to_checkin( $row );

		$this->assertIsArray( $result );
		$this->assertEquals( 999, $result['checkin_id'] );
		$this->assertEquals( 'Minimal Beer', $result['beer']['beer_name'] );
		// Verify created_at is a valid MySQL datetime (Y-m-d H:i:s format).
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $result['created_at'] );
		$this->assertEquals( 'imported', $result['user']['user_name'] );
		$this->assertArrayNotHasKey( 'venue', $result );
		$this->assertEmpty( $result['media']['items'] );
	}

	/**
	 * Tests csv_row_to_checkin() returns WP_Error for missing beer_name.
	 *
	 * Verifies that the function returns an error when beer_name is missing.
	 */
	public function test_csv_row_to_checkin_error_missing_beer_name() {
		$row = array(
			'checkin_id' => '123',
		);

		$result = csv_row_to_checkin( $row );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'missing_data', $result->get_error_code() );
	}

	/**
	 * Tests csv_row_to_checkin() returns WP_Error for missing checkin_id.
	 *
	 * Verifies that the function returns an error when checkin_id is missing.
	 */
	public function test_csv_row_to_checkin_error_missing_checkin_id() {
		$row = array(
			'beer_name' => 'Test Beer',
		);

		$result = csv_row_to_checkin( $row );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'missing_data', $result->get_error_code() );
	}

	/**
	 * Tests csv_row_to_checkin() returns WP_Error for empty required fields.
	 *
	 * Verifies that the function returns an error when required fields are empty strings.
	 */
	public function test_csv_row_to_checkin_error_empty_required_fields() {
		$row = array(
			'beer_name'  => '',
			'checkin_id' => '123',
		);

		$result = csv_row_to_checkin( $row );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'missing_data', $result->get_error_code() );
	}

	/**
	 * Tests csv_row_to_checkin() handles empty venue_name correctly.
	 *
	 * Verifies that venue is not added when venue_name is empty.
	 */
	public function test_csv_row_to_checkin_no_venue_when_empty() {
		// Set up configured user using real WordPress option.
		update_option( 'beer-slurper-user', 'testuser' );

		$row = array(
			'beer_name'    => 'Test Beer',
			'checkin_id'   => '123',
			'created_at'   => '2024-01-20 12:00:00',
			'venue_name'   => '',
			'venue_city'   => 'Portland',
			'venue_state'  => 'OR',
		);

		$result = csv_row_to_checkin( $row );

		$this->assertIsArray( $result );
		$this->assertArrayNotHasKey( 'venue', $result );
	}

	/**
	 * Tests csv_row_to_checkin() handles empty photo_url correctly.
	 *
	 * Verifies that media items are not added when photo_url is empty.
	 */
	public function test_csv_row_to_checkin_no_photo_when_empty() {
		// Set up configured user using real WordPress option.
		update_option( 'beer-slurper-user', 'testuser' );

		$row = array(
			'beer_name'  => 'Test Beer',
			'checkin_id' => '123',
			'created_at' => '2024-01-20 12:00:00',
			'photo_url'  => '',
		);

		$result = csv_row_to_checkin( $row );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result['media']['items'] );
	}

	/**
	 * Tests csv_row_to_checkin() extracts venue ID from URL.
	 *
	 * Verifies that venue_id is extracted from the venue_url field.
	 */
	public function test_csv_row_to_checkin_extracts_venue_id_from_url() {
		// Set up configured user using real WordPress option.
		update_option( 'beer-slurper-user', 'testuser' );

		$row = array(
			'beer_name'  => 'Test Beer',
			'checkin_id' => '123',
			'created_at' => '2024-01-20 12:00:00',
			'venue_name' => 'Test Bar',
			'venue_url'  => 'https://untappd.com/v/test-bar/12345',
		);

		$result = csv_row_to_checkin( $row );

		$this->assertIsArray( $result );
		$this->assertEquals( 12345, $result['venue']['venue_id'] );
	}

	/**
	 * Tests csv_row_to_checkin() handles null latitude/longitude.
	 *
	 * Verifies that lat/lng are set to null when not provided.
	 */
	public function test_csv_row_to_checkin_handles_null_coordinates() {
		// Set up configured user using real WordPress option.
		update_option( 'beer-slurper-user', 'testuser' );

		$row = array(
			'beer_name'    => 'Test Beer',
			'checkin_id'   => '123',
			'created_at'   => '2024-01-20 12:00:00',
			'venue_name'   => 'Test Bar',
			'venue_lat'    => '',
			'venue_lng'    => '',
		);

		$result = csv_row_to_checkin( $row );

		$this->assertIsArray( $result );
		$this->assertNull( $result['venue']['location']['lat'] );
		$this->assertNull( $result['venue']['location']['lng'] );
	}

	/**
	 * Tests csv_row_to_checkin() handles flavor profiles.
	 *
	 * Verifies that flavor_profiles are stored in _import_meta.
	 */
	public function test_csv_row_to_checkin_stores_flavor_profiles() {
		// Set up configured user using real WordPress option.
		update_option( 'beer-slurper-user', 'testuser' );

		$row = array(
			'beer_name'       => 'Test Beer',
			'checkin_id'      => '123',
			'created_at'      => '2024-01-20 12:00:00',
			'flavor_profiles' => 'hoppy, malty, citrus',
		);

		$result = csv_row_to_checkin( $row );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( '_import_meta', $result );
		$this->assertEquals( 'hoppy, malty, citrus', $result['_import_meta']['flavor_profiles'] );
	}

	/**
	 * Tests csv_row_to_checkin() does not add _import_meta when no flavor_profiles.
	 *
	 * Verifies that _import_meta is not added when flavor_profiles is empty.
	 */
	public function test_csv_row_to_checkin_no_import_meta_when_no_flavors() {
		// Set up configured user using real WordPress option.
		update_option( 'beer-slurper-user', 'testuser' );

		$row = array(
			'beer_name'       => 'Test Beer',
			'checkin_id'      => '123',
			'created_at'      => '2024-01-20 12:00:00',
			'flavor_profiles' => '',
		);

		$result = csv_row_to_checkin( $row );

		$this->assertIsArray( $result );
		$this->assertArrayNotHasKey( '_import_meta', $result );
	}

	// =========================================================================
	// import_file Tests (Format Detection)
	// =========================================================================

	/**
	 * Tests import_file() returns error for non-existent file.
	 *
	 * Verifies that the function returns a WP_Error when the file does not exist.
	 */
	public function test_import_file_error_file_not_found() {
		$result = import_file( '/path/to/nonexistent/file.csv' );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'file_not_found', $result->get_error_code() );
	}

	/**
	 * Tests import_file() auto-detects CSV format from extension.
	 *
	 * Verifies that the function correctly detects CSV format from file extension.
	 */
	public function test_import_file_auto_detects_csv_format() {
		$csv_content = "beer_name,checkin_id,created_at\n";
		$path = $this->create_temp_csv( $csv_content );

		// CSV with only header should return missing columns error since no data rows.
		$result = import_file( $path );

		// The function will call import_csv which will process the file.
		// With just a header, it should return results with 0 total.
		$this->assertIsArray( $result );
		$this->assertEquals( 0, $result['total'] );
	}

	/**
	 * Tests import_file() auto-detects JSON format from extension.
	 *
	 * Verifies that the function correctly detects JSON format from file extension.
	 */
	public function test_import_file_auto_detects_json_format() {
		$path = $this->create_temp_json( array( 'checkins' => array() ) );

		$result = import_file( $path );

		$this->assertIsArray( $result );
		$this->assertEquals( 0, $result['total'] );
	}

	// =========================================================================
	// import_csv Tests
	// =========================================================================

	/**
	 * Tests import_csv() returns error when file cannot be opened.
	 *
	 * Verifies that the function returns a WP_Error when the file cannot be opened.
	 * Note: We suppress the PHP warning from fopen() on non-existent files.
	 */
	public function test_import_csv_error_file_open_failed() {
		// Suppress warnings from fopen() on non-existent files.
		$result = @import_csv( '/path/to/nonexistent/file.csv' );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'file_open_failed', $result->get_error_code() );
	}

	/**
	 * Tests import_csv() returns error for empty file.
	 *
	 * Verifies that the function returns a WP_Error for an empty CSV file.
	 */
	public function test_import_csv_error_empty_file() {
		$path = $this->create_temp_csv( '' );

		$result = import_csv( $path );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'invalid_csv', $result->get_error_code() );
	}

	/**
	 * Tests import_csv() returns error for missing required columns.
	 *
	 * Verifies that the function returns a WP_Error when required columns are missing.
	 */
	public function test_import_csv_error_missing_required_columns() {
		$csv_content = "beer_name,brewery_name\nTest Beer,Test Brewery\n";
		$path = $this->create_temp_csv( $csv_content );

		$result = import_csv( $path );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'missing_columns', $result->get_error_code() );
	}

	/**
	 * Tests import_csv() normalizes header column names.
	 *
	 * Verifies that column names are normalized (lowercase, trimmed).
	 */
	public function test_import_csv_normalizes_header_names() {
		// Set up configured user using real WordPress option.
		update_option( 'beer-slurper-user', 'testuser' );

		// Create existing post so find_existing_checkin returns true (skipped).
		$post_id = $this->create_beer_post();
		add_post_meta( $post_id, '_beer_slurper_untappd_id', '123' );

		$csv_content = "Beer_Name,CHECKIN_ID, Created_At \nTest Beer,123,2024-01-20 12:00:00\n";
		$path = $this->create_temp_csv( $csv_content );

		$result = import_csv( $path );

		$this->assertIsArray( $result );
		$this->assertEquals( 1, $result['total'] );
	}

	/**
	 * Tests import_csv() skips empty rows.
	 *
	 * Verifies that empty rows in the CSV are skipped.
	 */
	public function test_import_csv_skips_empty_rows() {
		// Set up configured user using real WordPress option.
		update_option( 'beer-slurper-user', 'testuser' );

		// Create existing posts so find_existing_checkin returns true (skipped).
		$post1 = $this->create_beer_post();
		add_post_meta( $post1, '_beer_slurper_untappd_id', '123' );
		$post2 = $this->create_beer_post();
		add_post_meta( $post2, '_beer_slurper_untappd_id', '456' );

		$csv_content = "beer_name,checkin_id,created_at\nTest Beer,123,2024-01-20 12:00:00\n,,,\nAnother Beer,456,2024-01-21 12:00:00\n";
		$path = $this->create_temp_csv( $csv_content );

		$result = import_csv( $path );

		$this->assertIsArray( $result );
		$this->assertEquals( 3, $result['total'] );
		// One empty row should be skipped.
		$this->assertGreaterThanOrEqual( 1, $result['skipped'] );
	}

	/**
	 * Tests import_csv() handles column count mismatch.
	 *
	 * Verifies that rows with mismatched column counts are reported as errors.
	 */
	public function test_import_csv_handles_column_mismatch() {
		$csv_content = "beer_name,checkin_id,created_at\nTest Beer,123\n";
		$path = $this->create_temp_csv( $csv_content );

		$result = import_csv( $path );

		$this->assertIsArray( $result );
		$this->assertEquals( 1, $result['total'] );
		$this->assertEquals( 1, $result['skipped'] );
		$this->assertNotEmpty( $result['errors'] );
		$this->assertStringContainsString( 'Column count mismatch', $result['errors'][0] );
	}

	// =========================================================================
	// import_json Tests
	// =========================================================================

	/**
	 * Tests import_json() returns error when file content is not readable JSON.
	 *
	 * Note: Testing actual file read failures is difficult due to PHP's permission handling.
	 * The file_read_failed error path in import_json() requires file_get_contents to return false,
	 * which happens with permission issues or streams. For this test environment, we verify
	 * other error paths are correctly triggered.
	 *
	 * This test is kept as a placeholder to document the expected behavior.
	 */
	public function test_import_json_error_file_read_failed() {
		// Create a file that exists but contains content that will trigger file_get_contents to fail.
		// Using a directory as a path would trigger warnings, so we test with non-existent file.
		// Since file_get_contents on non-existent file returns false (with warning),
		// we suppress the warning and test the error handling.
		$result = @import_json( '/path/to/nonexistent/file.json' );

		// For non-existent file, file_get_contents returns false, so we expect file_read_failed.
		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'file_read_failed', $result->get_error_code() );
	}

	/**
	 * Tests import_json() returns error for invalid JSON.
	 *
	 * Verifies that the function returns a WP_Error for invalid JSON content.
	 */
	public function test_import_json_error_invalid_json() {
		$path = $this->temp_dir . '/invalid.json';
		file_put_contents( $path, 'not valid json {{{' );

		$result = import_json( $path );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'invalid_json', $result->get_error_code() );
	}

	/**
	 * Tests import_json() returns error for unrecognized JSON structure.
	 *
	 * Verifies that the function returns a WP_Error for unknown JSON format.
	 */
	public function test_import_json_error_unknown_format() {
		$path = $this->create_temp_json( array(
			'unknown_key' => 'unknown_value',
		) );

		$result = import_json( $path );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'unknown_format', $result->get_error_code() );
	}

	/**
	 * Tests import_json() handles standard export format with 'checkins' key.
	 *
	 * Verifies that the function correctly parses the standard Untappd export format.
	 */
	public function test_import_json_handles_standard_export_format() {
		// Set up configured user using real WordPress option.
		update_option( 'beer-slurper-user', 'testuser' );

		// Create existing post so find_existing_checkin returns true (skipped).
		$post_id = $this->create_beer_post();
		add_post_meta( $post_id, '_beer_slurper_untappd_id', '123' );

		$path = $this->create_temp_json( array(
			'checkins' => array(
				array(
					'beer_name'  => 'Test Beer',
					'checkin_id' => '123',
					'created_at' => '2024-01-20 12:00:00',
				),
			),
		) );

		$result = import_json( $path );

		$this->assertIsArray( $result );
		$this->assertEquals( 1, $result['total'] );
	}

	/**
	 * Tests import_json() handles array of checkin objects format.
	 *
	 * Verifies that the function correctly parses an array of flat checkin objects.
	 */
	public function test_import_json_handles_array_format() {
		// Set up configured user using real WordPress option.
		update_option( 'beer-slurper-user', 'testuser' );

		// Create existing posts so find_existing_checkin returns true (skipped).
		$post1 = $this->create_beer_post();
		add_post_meta( $post1, '_beer_slurper_untappd_id', '123' );
		$post2 = $this->create_beer_post();
		add_post_meta( $post2, '_beer_slurper_untappd_id', '456' );

		$path = $this->create_temp_json( array(
			array(
				'beer_name'  => 'Test Beer 1',
				'checkin_id' => '123',
				'created_at' => '2024-01-20 12:00:00',
			),
			array(
				'beer_name'  => 'Test Beer 2',
				'checkin_id' => '456',
				'created_at' => '2024-01-21 12:00:00',
			),
		) );

		$result = import_json( $path );

		$this->assertIsArray( $result );
		$this->assertEquals( 2, $result['total'] );
	}

	/**
	 * Tests import_json() handles API-like response format.
	 *
	 * Verifies that the function correctly parses API-style nested response format.
	 */
	public function test_import_json_handles_api_response_format() {
		// Create existing post so find_existing_checkin returns true (skipped).
		$post_id = $this->create_beer_post();
		add_post_meta( $post_id, '_beer_slurper_untappd_id', '123' );

		$path = $this->create_temp_json( array(
			'response' => array(
				'checkins' => array(
					'items' => array(
						array(
							'checkin_id' => 123,
							'beer'       => array(
								'bid'       => 789,
								'beer_name' => 'Test Beer',
							),
							'brewery'    => array(
								'brewery_id'   => 456,
								'brewery_name' => 'Test Brewery',
							),
						),
					),
				),
			),
		) );

		$result = import_json( $path );

		$this->assertIsArray( $result );
		$this->assertEquals( 1, $result['total'] );
	}

	/**
	 * Tests import_json() handles nested API format without transformation.
	 *
	 * Verifies that checkins with nested 'beer' key are used as-is.
	 */
	public function test_import_json_uses_nested_format_as_is() {
		// Create existing post so find_existing_checkin returns true (skipped).
		$post_id = $this->create_beer_post();
		add_post_meta( $post_id, '_beer_slurper_untappd_id', '123' );

		$checkin_data = array(
			'checkin_id' => 123,
			'beer'       => array(
				'bid'       => 789,
				'beer_name' => 'Already Nested Beer',
			),
		);

		$path = $this->create_temp_json( array(
			'checkins' => array( $checkin_data ),
		) );

		$result = import_json( $path );

		$this->assertIsArray( $result );
		$this->assertEquals( 1, $result['total'] );
	}

	// =========================================================================
	// process_checkin_batch Tests (Duplicate Detection)
	// =========================================================================

	/**
	 * Tests process_checkin_batch() skips existing checkins.
	 *
	 * Verifies that checkins that already exist are skipped.
	 */
	public function test_process_checkin_batch_skips_duplicates() {
		// Create existing post so find_existing_checkin returns true.
		$post_id = $this->create_beer_post();
		add_post_meta( $post_id, '_beer_slurper_untappd_id', '123' );

		$checkins = array(
			array( 'checkin_id' => 123 ),
		);

		$result = process_checkin_batch( $checkins );

		$this->assertEquals( 0, $result['imported'] );
		$this->assertEquals( 1, $result['skipped'] );
		$this->assertEmpty( $result['errors'] );
	}

	/**
	 * Tests process_checkin_batch() imports new checkins.
	 *
	 * Verifies that new checkins are imported successfully.
	 */
	public function test_process_checkin_batch_imports_new_checkins() {
		// Enable mock HTTP for API calls.
		$this->use_mock_http = true;
		\Kraft\Beer_Slurper\Tests\MockHttpClient::init();

		// Set up API credentials.
		$this->set_api_credentials( 'key', 'secret', 'token' );

		// Mock beer info API response.
		\Kraft\Beer_Slurper\Tests\MockHttpClient::mock_json(
			'*api.untappd.com*/v4/beer/info/*',
			\Kraft\Beer_Slurper\Tests\ApiFixtures::beer_info()
		);

		// Mock brewery info API response.
		\Kraft\Beer_Slurper\Tests\MockHttpClient::mock_json(
			'*api.untappd.com*/v4/brewery/info/*',
			\Kraft\Beer_Slurper\Tests\ApiFixtures::brewery_info()
		);

		// Create a complete checkin for import.
		$checkins = array(
			\Kraft\Beer_Slurper\Tests\ApiFixtures::checkin( array(
				'checkin_id'     => wp_rand( 900000, 999999 ),
				'_import_source' => 'untappd_export',
			) ),
		);

		$result = process_checkin_batch( $checkins );

		$this->assertEquals( 1, $result['imported'] );
		$this->assertEquals( 0, $result['skipped'] );
		$this->assertEmpty( $result['errors'] );
	}

	/**
	 * Tests process_checkin_batch() handles 'already_done' error as skip.
	 *
	 * Verifies that already_done errors are counted as skipped, not errors.
	 */
	public function test_process_checkin_batch_handles_already_done_error() {
		// Enable mock HTTP for API calls.
		$this->use_mock_http = true;
		\Kraft\Beer_Slurper\Tests\MockHttpClient::init();

		// Set up API credentials.
		$this->set_api_credentials( 'key', 'secret', 'token' );

		// Mock beer info API response.
		\Kraft\Beer_Slurper\Tests\MockHttpClient::mock_json(
			'*api.untappd.com*/v4/beer/info/*',
			\Kraft\Beer_Slurper\Tests\ApiFixtures::beer_info()
		);

		// Mock brewery info API response.
		\Kraft\Beer_Slurper\Tests\MockHttpClient::mock_json(
			'*api.untappd.com*/v4/brewery/info/*',
			\Kraft\Beer_Slurper\Tests\ApiFixtures::brewery_info()
		);

		// Create a checkin and import it first to create the "already done" scenario.
		$checkin_id = wp_rand( 800000, 899999 );
		$checkin = \Kraft\Beer_Slurper\Tests\ApiFixtures::checkin( array( 'checkin_id' => $checkin_id ) );
		\Kraft\Beer_Slurper\Post\insert_beer( $checkin );

		// Now try to import the same checkin again - should get already_done.
		$checkins = array( $checkin );
		$result = process_checkin_batch( $checkins );

		$this->assertEquals( 0, $result['imported'] );
		$this->assertEquals( 1, $result['skipped'] );
		$this->assertEmpty( $result['errors'] );
	}

	/**
	 * Tests process_checkin_batch() records other errors.
	 *
	 * Verifies that non-already_done errors are recorded in the errors array.
	 */
	public function test_process_checkin_batch_records_other_errors() {
		// Don't enable mock HTTP - API calls will fail, causing insert_beer to return error.
		// Set up API credentials so the code attempts to make API calls.
		$this->set_api_credentials( 'key', 'secret', 'token' );

		// Incomplete checkin missing required data will cause errors.
		$checkins = array(
			array( 'checkin_id' => 123 ),
		);

		$result = process_checkin_batch( $checkins );

		$this->assertEquals( 0, $result['imported'] );
		$this->assertEquals( 1, $result['skipped'] );
		$this->assertNotEmpty( $result['errors'] );
		$this->assertStringContainsString( 'Checkin 123', $result['errors'][0] );
	}

	/**
	 * Tests process_checkin_batch() stores import metadata.
	 *
	 * Verifies that _import_meta values are stored as post meta.
	 */
	public function test_process_checkin_batch_stores_import_metadata() {
		// Enable mock HTTP for API calls.
		$this->use_mock_http = true;
		\Kraft\Beer_Slurper\Tests\MockHttpClient::init();

		// Set up API credentials.
		$this->set_api_credentials( 'key', 'secret', 'token' );

		// Mock beer info API response.
		\Kraft\Beer_Slurper\Tests\MockHttpClient::mock_json(
			'*api.untappd.com*/v4/beer/info/*',
			\Kraft\Beer_Slurper\Tests\ApiFixtures::beer_info()
		);

		// Mock brewery info API response.
		\Kraft\Beer_Slurper\Tests\MockHttpClient::mock_json(
			'*api.untappd.com*/v4/brewery/info/*',
			\Kraft\Beer_Slurper\Tests\ApiFixtures::brewery_info()
		);

		$checkin_id = wp_rand( 700000, 799999 );
		$checkin = \Kraft\Beer_Slurper\Tests\ApiFixtures::checkin( array(
			'checkin_id' => $checkin_id,
		) );
		$checkin['_import_meta'] = array(
			'flavor_profiles' => 'hoppy, citrus',
		);
		$checkin['_import_source'] = 'untappd_export';

		$result = process_checkin_batch( array( $checkin ) );

		$this->assertEquals( 1, $result['imported'] );

		// Verify the metadata was stored.
		$posts = get_posts( array(
			'post_type'  => BEER_SLURPER_CPT,
			'meta_key'   => '_beer_slurper_untappd_id',
			'meta_value' => $checkin_id,
		) );
		$this->assertCount( 1, $posts );

		$flavor = get_post_meta( $posts[0]->ID, '_beer_slurper_flavor_profiles', true );
		$this->assertEquals( 'hoppy, citrus', $flavor );

		$source = get_post_meta( $posts[0]->ID, '_beer_slurper_import_source', true );
		$this->assertEquals( 'untappd_export', $source );
	}

	// =========================================================================
	// CSV_COLUMNS Constant Test
	// =========================================================================

	/**
	 * Tests CSV_COLUMNS constant contains expected columns.
	 *
	 * Verifies that the CSV_COLUMNS constant contains all expected Untappd export columns.
	 */
	public function test_csv_columns_constant_contains_expected_columns() {
		$expected_columns = array(
			'beer_name',
			'brewery_name',
			'beer_type',
			'beer_abv',
			'beer_ibu',
			'comment',
			'venue_name',
			'venue_city',
			'venue_state',
			'venue_country',
			'venue_lat',
			'venue_lng',
			'rating_score',
			'created_at',
			'checkin_url',
			'beer_url',
			'brewery_url',
			'brewery_country',
			'brewery_city',
			'brewery_state',
			'flavor_profiles',
			'purchase_venue',
			'serving_type',
			'checkin_id',
			'bid',
			'brewery_id',
			'photo_url',
		);

		$this->assertEquals( $expected_columns, CSV_COLUMNS );
	}
}
