<?php
/**
 * Untappd Export Import Functions
 *
 * Handles importing checkin data from Untappd's data export feature.
 * Supports both CSV and JSON formats provided by Untappd Insider
 * or GDPR data requests.
 *
 * @package Kraft\Beer_Slurper\Import
 */
namespace Kraft\Beer_Slurper\Import;

/**
 * Expected CSV columns from Untappd export.
 *
 * @var array
 */
const CSV_COLUMNS = array(
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

/**
 * Imports checkins from an uploaded Untappd export file.
 *
 * @param string $file_path Path to the uploaded file.
 * @param string $format    File format: 'csv' or 'json'.
 *
 * @return array|WP_Error Import results with counts, or WP_Error on failure.
 */
function import_file( $file_path, $format = null ) {
	if ( ! file_exists( $file_path ) ) {
		return new \WP_Error( 'file_not_found', __( 'Import file not found.', 'beer_slurper' ) );
	}

	// Auto-detect format from extension if not specified.
	if ( null === $format ) {
		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		$format    = in_array( $extension, array( 'json' ), true ) ? 'json' : 'csv';
	}

	// If extension detection failed or was ambiguous, check file content.
	if ( 'csv' === $format ) {
		$content_sample = file_get_contents( $file_path, false, null, 0, 100 );
		if ( false !== $content_sample ) {
			$content_sample = ltrim( $content_sample );
			// JSON files start with { or [.
			if ( ! empty( $content_sample ) && ( '{' === $content_sample[0] || '[' === $content_sample[0] ) ) {
				$format = 'json';
			}
		}
	}

	if ( 'json' === $format ) {
		return import_json( $file_path );
	}

	return import_csv( $file_path );
}

/**
 * Imports checkins from a CSV file.
 *
 * Parses the CSV, validates checkins, and queues them for background
 * processing via Action Scheduler to avoid timeouts on large imports.
 *
 * @param string $file_path Path to the CSV file.
 *
 * @return array|WP_Error Import results, or WP_Error on failure.
 */
function import_csv( $file_path ) {
	$handle = fopen( $file_path, 'r' );

	if ( false === $handle ) {
		return new \WP_Error( 'file_open_failed', __( 'Could not open the CSV file.', 'beer_slurper' ) );
	}

	// Read header row.
	$header = fgetcsv( $handle, 0, ',', '"', '' );

	if ( false === $header || empty( $header ) ) {
		fclose( $handle );
		return new \WP_Error( 'invalid_csv', __( 'CSV file appears to be empty or invalid.', 'beer_slurper' ) );
	}

	// Normalize header names (lowercase, trim).
	$header = array_map( function( $col ) {
		return strtolower( trim( $col ) );
	}, $header );

	// Validate required columns exist.
	$required = array( 'beer_name', 'checkin_id', 'created_at' );
	$missing  = array_diff( $required, $header );

	if ( ! empty( $missing ) ) {
		fclose( $handle );
		return new \WP_Error(
			'missing_columns',
			sprintf( __( 'CSV is missing required columns: %s', 'beer_slurper' ), implode( ', ', $missing ) )
		);
	}

	$valid_checkins = array();
	$errors         = array();
	$skipped        = 0;
	$total          = 0;
	$row_number     = 1;

	while ( ( $row = fgetcsv( $handle, 0, ',', '"', '' ) ) !== false ) {
		$row_number++;
		$total++;

		// Skip empty rows.
		if ( empty( array_filter( $row ) ) ) {
			$skipped++;
			continue;
		}

		// Map row to associative array.
		if ( count( $row ) !== count( $header ) ) {
			$errors[] = sprintf( __( 'Row %d: Column count mismatch.', 'beer_slurper' ), $row_number );
			$skipped++;
			continue;
		}

		$data = array_combine( $header, $row );

		// Convert CSV row to checkin format.
		$checkin = csv_row_to_checkin( $data );

		if ( is_wp_error( $checkin ) ) {
			$errors[] = sprintf( __( 'Row %d: %s', 'beer_slurper' ), $row_number, $checkin->get_error_message() );
			$skipped++;
			continue;
		}

		// Mark source for tracking.
		$checkin['_import_source'] = 'untappd_export';
		$valid_checkins[] = $checkin;
	}

	fclose( $handle );

	// Queue valid checkins for background processing.
	$queued = queue_import_batches( $valid_checkins );

	return array(
		'total'    => $total,
		'imported' => 0, // Will be updated by background processing.
		'skipped'  => $skipped,
		'queued'   => $queued,
		'errors'   => $errors,
	);
}

/**
 * Imports checkins from a JSON file.
 *
 * Parses the JSON, validates checkins, and queues them for background
 * processing via Action Scheduler to avoid timeouts on large imports.
 *
 * @param string $file_path Path to the JSON file.
 *
 * @return array|WP_Error Import results, or WP_Error on failure.
 */
function import_json( $file_path ) {
	if ( filesize( $file_path ) > 10485760 ) {
		\beer_slurper_log( 'Beer Slurper: Large JSON import file (' . size_format( filesize( $file_path ) ) . '). This may use significant memory.' );
	}

	$content = file_get_contents( $file_path );

	if ( false === $content ) {
		return new \WP_Error( 'file_read_failed', __( 'Could not read the JSON file.', 'beer_slurper' ) );
	}

	$data = json_decode( $content, true );

	if ( null === $data || json_last_error() !== JSON_ERROR_NONE ) {
		return new \WP_Error( 'invalid_json', __( 'Invalid JSON format.', 'beer_slurper' ) );
	}

	// Handle different JSON structures from Untappd.
	$checkins = array();

	if ( isset( $data['checkins'] ) ) {
		// Standard export format.
		$checkins = $data['checkins'];
	} elseif ( isset( $data[0]['beer_name'] ) ) {
		// Array of checkin objects.
		$checkins = $data;
	} elseif ( isset( $data['response']['checkins']['items'] ) ) {
		// API-like response format.
		$checkins = $data['response']['checkins']['items'];
	} else {
		return new \WP_Error( 'unknown_format', __( 'Unrecognized JSON structure.', 'beer_slurper' ) );
	}

	// Convert and validate all checkins upfront.
	$valid_checkins = array();
	$errors         = array();

	foreach ( $checkins as $index => $item ) {
		// Detect if this is CSV-style flat format or API-style nested format.
		if ( isset( $item['beer_name'] ) && ! isset( $item['beer'] ) ) {
			$checkin = csv_row_to_checkin( $item );
		} else {
			$checkin = $item;
		}

		if ( is_wp_error( $checkin ) ) {
			$errors[] = sprintf( __( 'Item %d: %s', 'beer_slurper' ), $index + 1, $checkin->get_error_message() );
			continue;
		}

		// Mark source for tracking.
		$checkin['_import_source'] = 'untappd_export';
		$valid_checkins[] = $checkin;
	}

	// Queue valid checkins for background processing.
	$queued = queue_import_batches( $valid_checkins );

	return array(
		'total'    => count( $checkins ),
		'imported' => 0, // Will be updated by background processing.
		'skipped'  => count( $errors ),
		'queued'   => $queued,
		'errors'   => $errors,
	);
}

/**
 * Converts a flat CSV row to the nested checkin format expected by the plugin.
 *
 * @param array $row Associative array from CSV row.
 *
 * @return array|WP_Error Checkin data in API-compatible format, or WP_Error.
 */
function csv_row_to_checkin( $row ) {
	// Validate required fields.
	if ( empty( $row['beer_name'] ) || empty( $row['checkin_id'] ) ) {
		return new \WP_Error( 'missing_data', __( 'Missing required fields (beer_name, checkin_id).', 'beer_slurper' ) );
	}

	$checkin = array(
		'checkin_id'      => (int) $row['checkin_id'],
		'checkin_comment' => isset( $row['comment'] ) ? $row['comment'] : '',
		'created_at'      => isset( $row['created_at'] ) ? $row['created_at'] : current_time( 'mysql', true ),
		'rating_score'    => isset( $row['rating_score'] ) ? (float) $row['rating_score'] : 0,
		'serving_type'    => isset( $row['serving_type'] ) ? $row['serving_type'] : '',
		'beer'            => array(
			'bid'        => isset( $row['bid'] ) ? (int) $row['bid'] : 0,
			'beer_name'  => $row['beer_name'],
			'beer_style' => isset( $row['beer_type'] ) ? $row['beer_type'] : '',
			'beer_abv'   => isset( $row['beer_abv'] ) ? (float) $row['beer_abv'] : 0,
		),
		'brewery'         => array(
			'brewery_id'   => isset( $row['brewery_id'] ) ? (int) $row['brewery_id'] : 0,
			'brewery_name' => isset( $row['brewery_name'] ) ? $row['brewery_name'] : '',
			'location'     => array(
				'brewery_city'    => isset( $row['brewery_city'] ) ? $row['brewery_city'] : '',
				'brewery_state'   => isset( $row['brewery_state'] ) ? $row['brewery_state'] : '',
				'brewery_country' => isset( $row['brewery_country'] ) ? $row['brewery_country'] : '',
			),
		),
		'user'            => array(
			'user_name' => \Kraft\Beer_Slurper\Sync_Status\get_configured_user() ?: 'imported',
		),
		'media'           => array( 'items' => array() ),
		'badges'          => array( 'items' => array() ),
	);

	// Add beer IBU if available.
	if ( isset( $row['beer_ibu'] ) && '' !== $row['beer_ibu'] ) {
		$checkin['beer']['beer_ibu'] = (int) $row['beer_ibu'];
	}

	// Add venue if present.
	if ( ! empty( $row['venue_name'] ) ) {
		$checkin['venue'] = array(
			'venue_name' => $row['venue_name'],
			'location'   => array(
				'venue_city'    => isset( $row['venue_city'] ) ? $row['venue_city'] : '',
				'venue_state'   => isset( $row['venue_state'] ) ? $row['venue_state'] : '',
				'venue_country' => isset( $row['venue_country'] ) ? $row['venue_country'] : '',
				'lat'           => isset( $row['venue_lat'] ) && '' !== $row['venue_lat'] ? (float) $row['venue_lat'] : null,
				'lng'           => isset( $row['venue_lng'] ) && '' !== $row['venue_lng'] ? (float) $row['venue_lng'] : null,
			),
		);

		// Try to extract venue ID from URL if available.
		if ( ! empty( $row['venue_url'] ) && preg_match( '/\/v\/[^\/]+\/(\d+)/', $row['venue_url'], $matches ) ) {
			$checkin['venue']['venue_id'] = (int) $matches[1];
		}
	}

	// Add photo if present.
	if ( ! empty( $row['photo_url'] ) ) {
		$checkin['media']['items'][] = array(
			'photo' => array(
				'photo_img_og' => $row['photo_url'],
			),
		);
	}

	// Add flavor profiles if present (store as meta later).
	if ( ! empty( $row['flavor_profiles'] ) ) {
		$checkin['_import_meta'] = array(
			'flavor_profiles' => $row['flavor_profiles'],
		);
	}

	// Source tracking for imported checkins.
	$checkin['_import_source'] = 'untappd_export';

	return $checkin;
}

/**
 * Processes a batch of checkins for import.
 *
 * @param array $checkins Array of checkin data arrays.
 *
 * @return array Results with imported/skipped counts and errors.
 */
function process_checkin_batch( $checkins ) {
	$results = array(
		'imported' => 0,
		'skipped'  => 0,
		'errors'   => array(),
	);

	foreach ( $checkins as $checkin ) {
		// Check if checkin already exists.
		if ( \Kraft\Beer_Slurper\Post\find_existing_checkin( $checkin['checkin_id'] ) ) {
			$results['skipped']++;
			continue;
		}

		// Use the existing insert_beer function which handles everything.
		$result = \Kraft\Beer_Slurper\Post\insert_beer( $checkin );

		if ( is_wp_error( $result ) ) {
			if ( 'already_done' === $result->get_error_code() ) {
				$results['skipped']++;
			} else {
				$results['errors'][] = sprintf(
					__( 'Checkin %d: %s', 'beer_slurper' ),
					$checkin['checkin_id'],
					$result->get_error_message()
				);
				$results['skipped']++;
			}
		} else {
			$results['imported']++;

			// Store import metadata if present.
			if ( isset( $checkin['_import_meta'] ) && is_array( $checkin['_import_meta'] ) ) {
				foreach ( $checkin['_import_meta'] as $key => $value ) {
					update_post_meta( $result, '_beer_slurper_' . $key, $value );
				}
			}

			// Mark as imported from export.
			if ( isset( $checkin['_import_source'] ) ) {
				update_post_meta( $result, '_beer_slurper_import_source', $checkin['_import_source'] );
			}
		}
	}

	return $results;
}

/**
 * Handles the AJAX file upload for import.
 *
 * @return void
 */
function ajax_handle_import() {
	check_ajax_referer( 'beer_slurper_import', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array(
			'message' => __( 'You do not have permission to import data.', 'beer_slurper' ),
		) );
	}

	if ( empty( $_FILES['import_file'] ) ) {
		wp_send_json_error( array(
			'message' => __( 'No file uploaded.', 'beer_slurper' ),
		) );
	}

	$file = $_FILES['import_file'];

	// Validate file type.
	$allowed_types = array( 'text/csv', 'application/json', 'text/plain', 'application/vnd.ms-excel' );
	$file_type     = wp_check_filetype( $file['name'] );

	if ( ! in_array( $file['type'], $allowed_types, true ) && ! in_array( $file_type['ext'], array( 'csv', 'json' ), true ) ) {
		wp_send_json_error( array(
			'message' => __( 'Invalid file type. Please upload a CSV or JSON file.', 'beer_slurper' ),
		) );
	}

	// Move to temp location.
	$upload_dir  = wp_upload_dir();
	$temp_file   = $upload_dir['basedir'] . '/beer-slurper-import-' . wp_generate_uuid4() . '.' . $file_type['ext'];

	if ( ! move_uploaded_file( $file['tmp_name'], $temp_file ) ) {
		wp_send_json_error( array(
			'message' => __( 'Failed to process uploaded file.', 'beer_slurper' ),
		) );
	}

	// Server-side MIME detection if finfo is available.
	if ( function_exists( 'finfo_open' ) ) {
		$finfo     = finfo_open( FILEINFO_MIME_TYPE );
		$mime_type = finfo_file( $finfo, $temp_file );
		finfo_close( $finfo );

		$allowed_mimes = array( 'text/csv', 'text/plain', 'application/json', 'application/csv' );
		if ( ! in_array( $mime_type, $allowed_mimes, true ) ) {
			wp_delete_file( $temp_file );
			wp_send_json_error( array(
				'message' => __( 'Invalid file type detected. Please upload a CSV or JSON file.', 'beer_slurper' ),
			) );
		}
	}

	// Basic content validation.
	$content_sample = file_get_contents( $temp_file, false, null, 0, 1024 );
	if ( false !== $content_sample ) {
		$content_sample = ltrim( $content_sample );
		if ( 'json' === $file_type['ext'] ) {
			// JSON files should start with { or [.
			if ( '' !== $content_sample && '{' !== $content_sample[0] && '[' !== $content_sample[0] ) {
				wp_delete_file( $temp_file );
				wp_send_json_error( array(
					'message' => __( 'Invalid JSON file. File does not appear to contain valid JSON.', 'beer_slurper' ),
				) );
			}
		} elseif ( 'csv' === $file_type['ext'] ) {
			// CSV files should contain a delimiter (comma, semicolon, or tab).
			if ( '' !== $content_sample && false === strpos( $content_sample, ',' ) && false === strpos( $content_sample, ';' ) && false === strpos( $content_sample, "\t" ) ) {
				wp_delete_file( $temp_file );
				wp_send_json_error( array(
					'message' => __( 'Invalid CSV file. File does not appear to contain valid CSV data.', 'beer_slurper' ),
				) );
			}
		}
	}

	// Run the import with try-finally to ensure temp file cleanup.
	try {
		$results = import_file( $temp_file );
	} finally {
		// Clean up temp file.
		wp_delete_file( $temp_file );
	}

	if ( is_wp_error( $results ) ) {
		wp_send_json_error( array(
			'message' => $results->get_error_message(),
		) );
	}

	// Limit error messages returned.
	$error_summary = array_slice( $results['errors'], 0, 10 );
	if ( count( $results['errors'] ) > 10 ) {
		$error_summary[] = sprintf( __( '... and %d more errors.', 'beer_slurper' ), count( $results['errors'] ) - 10 );
	}

	// Check if this was a queued import (background processing).
	if ( isset( $results['queued'] ) && $results['queued'] > 0 ) {
		wp_send_json_success( array(
			'message'  => sprintf(
				__( 'Import queued: %d checkins will be processed in the background. Check back in a few minutes.', 'beer_slurper' ),
				$results['queued']
			),
			'queued'   => $results['queued'],
			'total'    => $results['total'],
			'skipped'  => $results['skipped'],
			'errors'   => $error_summary,
		) );
	}

	wp_send_json_success( array(
		'message'  => sprintf(
			__( 'Import complete: %d imported, %d skipped out of %d total.', 'beer_slurper' ),
			$results['imported'],
			$results['skipped'],
			$results['total']
		),
		'imported' => $results['imported'],
		'skipped'  => $results['skipped'],
		'total'    => $results['total'],
		'errors'   => $error_summary,
	) );
}
add_action( 'wp_ajax_beer_slurper_import', __NAMESPACE__ . '\ajax_handle_import' );

/**
 * Queues checkins for background import via Action Scheduler.
 *
 * Splits checkins into batches and schedules each batch as a separate
 * action to process in the background, preventing timeout issues.
 *
 * @param array $checkins Array of validated checkin data.
 * @param int   $batch_size Number of checkins per batch. Default 25.
 *
 * @return int Number of checkins queued.
 */
function queue_import_batches( $checkins, $batch_size = 25 ) {
	if ( empty( $checkins ) || ! function_exists( 'as_schedule_single_action' ) ) {
		return 0;
	}

	$batches = array_chunk( $checkins, $batch_size );
	$delay   = 0;

	foreach ( $batches as $batch ) {
		// Store batch in a transient (Action Scheduler args have size limits).
		$batch_id = 'bs_import_batch_' . wp_generate_uuid4();
		set_transient( $batch_id, $batch, HOUR_IN_SECONDS );

		// Schedule the batch with staggered delays (10 seconds apart).
		as_schedule_single_action(
			time() + $delay,
			'beer_slurper_process_import_batch',
			array( 'batch_id' => $batch_id ),
			'beer-slurper'
		);

		$delay += 10; // Stagger batches by 10 seconds.
	}

	// Store import progress for status tracking.
	update_option( 'beer_slurper_import_progress', array(
		'total'     => count( $checkins ),
		'processed' => 0,
		'imported'  => 0,
		'skipped'   => 0,
		'errors'    => array(),
		'started'   => time(),
	), false );

	return count( $checkins );
}

/**
 * Processes a single batch of checkins from the import queue.
 *
 * This is the Action Scheduler callback that runs in the background.
 *
 * @param string $batch_id The transient key containing the batch data.
 *
 * @return void
 */
function process_import_batch( $batch_id ) {
	$batch = get_transient( $batch_id );

	if ( false === $batch || ! is_array( $batch ) ) {
		\beer_slurper_log( 'Beer Slurper Import: Batch not found or expired - ' . $batch_id );
		return;
	}

	// Delete the transient now that we've retrieved it.
	delete_transient( $batch_id );

	$results = process_checkin_batch( $batch );

	// Update progress tracking.
	$progress = get_option( 'beer_slurper_import_progress', array() );
	if ( is_array( $progress ) ) {
		$progress['processed'] = ( $progress['processed'] ?? 0 ) + count( $batch );
		$progress['imported']  = ( $progress['imported'] ?? 0 ) + $results['imported'];
		$progress['skipped']   = ( $progress['skipped'] ?? 0 ) + $results['skipped'];
		$progress['errors']    = array_merge( $progress['errors'] ?? array(), $results['errors'] );
		$progress['last_update'] = time();
		update_option( 'beer_slurper_import_progress', $progress, false );
	}

	\beer_slurper_log( sprintf(
		'Beer Slurper Import: Batch processed - %d imported, %d skipped',
		$results['imported'],
		$results['skipped']
	) );
}
add_action( 'beer_slurper_process_import_batch', __NAMESPACE__ . '\process_import_batch' );

/**
 * Gets the current import progress.
 *
 * @return array|null Progress data or null if no import is running.
 */
function get_import_progress() {
	return get_option( 'beer_slurper_import_progress', null );
}

/**
 * Clears the import progress tracking.
 *
 * @return void
 */
function clear_import_progress() {
	delete_option( 'beer_slurper_import_progress' );
}

/**
 * AJAX handler for getting import progress.
 *
 * @return void
 */
function ajax_get_import_progress() {
	check_ajax_referer( 'beer_slurper_import', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'beer_slurper' ) ) );
	}

	$progress = get_import_progress();

	if ( ! $progress ) {
		wp_send_json_success( array(
			'active'   => false,
			'message'  => __( 'No import in progress.', 'beer_slurper' ),
		) );
	}

	// Check if there are pending batches in Action Scheduler.
	$pending_batches = 0;
	if ( function_exists( 'as_get_scheduled_actions' ) ) {
		$pending = as_get_scheduled_actions( array(
			'hook'   => 'beer_slurper_process_import_batch',
			'status' => \ActionScheduler_Store::STATUS_PENDING,
			'group'  => 'beer-slurper',
		), 'ids' );
		$pending_batches = count( $pending );
	}

	$is_complete = ( $progress['processed'] >= $progress['total'] ) && 0 === $pending_batches;

	wp_send_json_success( array(
		'active'          => ! $is_complete,
		'complete'        => $is_complete,
		'total'           => $progress['total'],
		'processed'       => $progress['processed'],
		'imported'        => $progress['imported'],
		'skipped'         => $progress['skipped'],
		'pending_batches' => $pending_batches,
		'started'         => $progress['started'] ?? 0,
		'last_update'     => $progress['last_update'] ?? 0,
		'errors'          => array_slice( $progress['errors'] ?? array(), 0, 10 ),
	) );
}
add_action( 'wp_ajax_beer_slurper_import_progress', __NAMESPACE__ . '\ajax_get_import_progress' );

/**
 * AJAX handler to clear/dismiss import progress.
 *
 * @return void
 */
function ajax_clear_import_progress() {
	check_ajax_referer( 'beer_slurper_import', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'beer_slurper' ) ) );
	}

	clear_import_progress();
	wp_send_json_success();
}
add_action( 'wp_ajax_beer_slurper_clear_import_progress', __NAMESPACE__ . '\ajax_clear_import_progress' );

/**
 * Gets the count of checkins that could be enriched with API data.
 *
 * These are checkins imported from export that lack full metadata.
 *
 * @return int Count of checkins needing enrichment.
 */
function get_enrichable_count() {
	global $wpdb;

	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type = %s
			AND pm.meta_key = '_beer_slurper_import_source'
			AND pm.meta_value = 'untappd_export'",
			\BEER_SLURPER_CPT
		)
	);
}
