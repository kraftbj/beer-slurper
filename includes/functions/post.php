<?php
namespace Kraft\Beer_Slurper\Post;

/**
 * Post Management Functions
 *
 * Handles creating, updating, and managing beer posts in WordPress,
 * including thumbnail attachments, brewery associations, and metadata.
 *
 * @package Kraft\Beer_Slurper
 */

/**
 * Inserts or updates a beer post from checkin data.
 *
 * Creates a new beer post or updates an existing one based on checkin data.
 * Handles duplicate detection, post metadata, taxonomy terms, thumbnails,
 * and brewery attachments.
 *
 * @since 1.0.0
 *
 * @uses is_wp_error()                       Checks for WP_Error instances.
 * @uses apply_filters()                     Applies beer_slurper_cpt and beer_slurper_tax_style filters.
 * @uses wp_insert_post()                    Creates new beer posts.
 * @uses get_date_from_gmt()                 Converts GMT date to local time.
 * @uses wp_update_post()                    Updates existing beer posts.
 * @uses wp_set_object_terms()               Assigns style taxonomy terms.
 * @uses update_post_meta()                  Saves post metadata.
 * @uses add_post_meta()                     Adds multiple metadata values.
 * @uses sanitize_title()                    Sanitizes badge names for file naming.
 * @uses get_permalink()                     Gets post permalink for thumbnail naming.
 * @uses basename()                          Extracts filename from permalink.
 *
 * @param array $checkin Checkin data array from Untappd API.
 * @param bool  $nodup   Optional. Whether to check for duplicates. Default true.
 *
 * @return int|WP_Error Post ID on success, WP_Error on failure.
 */
function insert_beer( $checkin, $nodup = true ){ // @todo do this better with more.
	$post_id = null;
	if (! $checkin ) {
		return new \WP_Error( 'no_checkin', __( "No information provided.", 'beer_slurper' ) );
	}

	if ( ! isset( $checkin['beer'] ) ) {
		return new \WP_Error( 'no_beer', __( "Checkin did not provide a beer!", 'beer_slurper' ) );
	}

	$beer_id = $checkin['beer']['bid'];
	$checkin_id = $checkin['checkin_id'];

	if ( $nodup ) {
		// First, check if this exact checkin has already been imported (prevents race conditions)
		$existing_checkin = find_existing_checkin( $checkin_id );
		if ( $existing_checkin ) {
			return new \WP_Error( 'already_done', __( "We've already added this exact checkin!", 'beer_slurper' ) );
		}

		// Then check for existing post with this beer to update instead of insert
		$existing_post = find_existing_post( $beer_id );
		if ( $existing_post ) {
			$post_id = $existing_post['id'];
			$existing_date = $existing_post['date'];
		}
	}

	$post_info = setup_post( $checkin );

	if ( is_wp_error( $post_info ) ) {
		return $post_info;
	}

	$post = array(
		'post_name'    => $post_info['slug'],
		'post_title'   => $post_info['title'],
		'post_excerpt' => $post_info['excerpt'],
		'post_type'    => apply_filters( 'beer_slurper_cpt', BEER_SLURPER_CPT ), // @todo Breakout beerlog into 3rd party file, set so works imports into post by default.
		'post_status'  => 'publish',
		);

	if ( ! $post_id ) { // create a new post for us.
		$post['post_date_gmt'] = $post_info['date'];
		$post['post_date']     = get_date_from_gmt( $post_info['date'] );
		$post['post_content']  = $post_info['content'];

		$post_id = wp_insert_post( $post );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
	}
	else { // update existing post
		// determine which is older
		$existing_timestamp = strtotime($existing_date);
		$checkin_timestamp  = strtotime($post_info['date']);
		if ( $existing_timestamp > $checkin_timestamp ) {
			$post['post_date_gmt'] = $post_info['date'];
			$post['post_date']     = get_date_from_gmt( $post_info['date'] );
		}
		$post['ID'] = $post_id;
		wp_update_post( $post );
	}

	wp_set_object_terms( $post_id, (int)$post_info['term_id'], apply_filters( 'beer_slurper_tax_style', BEER_SLURPER_TAX_STYLE ) , true);

	attach_brewery( $post_id, $post_info['brewery'], isset( $post_info['brewery_data'] ) ? $post_info['brewery_data'] : null );
	attach_collaborations( $post_id, $post_info['collabs'] );

	foreach ( $post_info['meta'] as $meta_key => $meta_value ) {
	 	update_post_meta( $post_id, $meta_key, $meta_value );
	 }

	 foreach ( $post_info['meta_multiple'] as $meta_key => $meta_value ) {
	 	add_post_meta( $post_id, $meta_key, $meta_value, false );
	 }

	 // Badges, then picture so the picture will persist as thumbnail, while storing all attached to the beer post.

	 if ( isset( $post_info['badges'] ) ) {
	 	foreach ( $post_info['badges'] as $badge ) {
	 		insert_thumbnail( $badge['badge_image'], $post_id, sanitize_title( $badge['badge_name']) );
	 	}
	 }

	 if ( isset( $post_info['img_src'] ) ) {
	 	insert_thumbnail( $post_info['img_src'], $post_id, basename( get_permalink( $post_id ) ) );
	 }

	 return $post_id;
}

/**
 * Prepares post data from checkin information.
 *
 * Processes raw checkin data from Untappd and structures it for WordPress
 * post creation, including title, content, metadata, and taxonomy terms.
 *
 * @since 1.0.0
 *
 * @uses is_wp_error()       Checks for WP_Error instances.
 * @uses is_array()          Validates data structures.
 * @uses sanitize_title()    Generates slug from beer name.
 * @uses wp_trim_words()     Trims description for excerpt.
 * @uses wp_strip_all_tags() Removes HTML from description.
 * @uses date()              Formats checkin date.
 * @uses strtotime()         Parses checkin timestamp.
 * @uses get_term_by()       Looks up existing style terms.
 * @uses apply_filters()     Applies beer_slurper_tax_style filter.
 * @uses wp_insert_term()    Creates new style terms.
 *
 * @param array $checkin Checkin data array from Untappd API.
 *
 * @return array|WP_Error Structured post data array or WP_Error on failure.
 */
function setup_post( $checkin ){
	if ( ! $checkin ) {
		return new \WP_Error( 'no_checkin', __( "No information provided.", 'beer_slurper' ) );
	}

	$beer     = $checkin['beer'];
	$beer_all = \Kraft\Beer_Slurper\API\get_beer_info( $beer['bid'] );

	if ( is_wp_error( $beer_all ) ) {
		return $beer_all;
	}

	if ( ! is_array( $beer_all ) ) {
		return new \WP_Error( 'invalid_beer_data', __( 'Invalid beer data from API.', 'beer_slurper' ) );
	}

	$brewery  = isset( $beer_all['brewery'] ) ? $beer_all['brewery'] : null;
	$style    = isset( $beer_all['beer_style'] ) ? $beer_all['beer_style'] : 'Unknown';
	$collabs  = isset( $beer_all['collaborations_with'] ) ? $beer_all['collaborations_with'] : array( 'count' => 0, 'items' => array() );
	$description = isset( $beer_all['beer_description'] ) ? $beer_all['beer_description'] : '';
	$post_info = array(
		'title'         => $beer['beer_name'],
		'slug'          => isset( $beer_all['beer_slug'] ) ? $beer_all['beer_slug'] : sanitize_title( $beer['beer_name'] ),
		'content'       => $description,
		'excerpt'       => $description ? wp_trim_words( wp_strip_all_tags( $description ), 55 ) : '',
		'date'          => date( "Y-m-d H:i:s", strtotime( $checkin['created_at'] ) ), // Untappd returns UTC.
		'meta'          => array(
			'_beer_slurper_id'   => $beer['bid'],
			'_beerlog_meta_abv'  => isset( $beer['beer_abv'] ) ? $beer['beer_abv'] : '',
			'_beerlog_meta_ibu'  => isset( $beer_all['beer_ibu'] ) ? $beer_all['beer_ibu'] : '',
			'_beer_slurper_desc' => $description,
			'_beer_slurper_brew' => is_array( $brewery ) && isset( $brewery['brewery_id'] ) ? $brewery['brewery_id'] : '',
			),
		'meta_multiple' => array(
			'_beer_slurper_date'       => date( "Y-m-d H:i:s", strtotime( $checkin['created_at'] ) ),
			'_beer_slurper_untappd_id' => $checkin['checkin_id'],
			),
		);
	if ( isset( $checkin['media']['items'][0]['photo']['photo_img_og'] ) ) {
		$post_info['img_src'] = $checkin['media']['items'][0]['photo']['photo_img_og'];
	}

	if ( isset( $checkin['venue']['venue_id'] ) ) {
		$post_info['meta']['_beer_slurper_venue'] = $checkin['venue']['venue_id'];
	}

	if ( isset( $checkin['badges']['items'] ) && ! empty( $checkin['badges']['items'] ) ) {
		foreach ($checkin['badges']['items'] as $badges ) {
			$post_info['badges'][] = array(
				'badge_id' => $badges['badge_id'],
				'badge_name' => $badges['badge_name'],
				'badge_image' => $badges['badge_image']['lg'],
				);
		}
	}

	if ( isset( $beer['stats']['user_count'] ) ) {
		$post_info['meta']['_beer_slurper_count'] = $beer['stats']['user_count'];
	}

	$maybe_term_id = get_term_by( 'name', $style, apply_filters( 'beer_slurper_tax_style', BEER_SLURPER_TAX_STYLE ));
	if ( $maybe_term_id ) {
		$term_id = $maybe_term_id->term_id;
	}
	else {
		$term = wp_insert_term( $style , apply_filters( 'beer_slurper_tax_style', BEER_SLURPER_TAX_STYLE ) );
		$term_id = $term['term_id'];
	}
	if ( isset( $term_id ) && ! is_wp_error( $term_id ) ) {
		$post_info['term_id'] = $term_id;
	}
	else {
		$post_info['term_id'] = null;
	}

	$post_info['brewery'] = is_array( $brewery ) && isset( $brewery['brewery_id'] ) ? $brewery['brewery_id'] : null;
	$post_info['brewery_data'] = is_array( $brewery ) ? $brewery : null;
	$post_info['collabs'] = array();
	if ( is_array( $collabs ) && isset( $collabs['count'] ) && $collabs['count'] > 0 && isset( $collabs['items'] ) ) { // We have some collaborators!
		foreach ( $collabs['items'] as $collab ) {
			if ( isset( $collab['brewery']['brewery_id'] ) ) {
				$post_info['collabs'][] = $collab['brewery']['brewery_id'];
			}
		}
	}

	return $post_info;
}

/**
 * Finds an existing post for a given beer ID.
 *
 * Searches for a previously imported beer post using the Untappd beer ID
 * stored in post metadata.
 *
 * @since 1.0.0
 *
 * @uses apply_filters() Applies beer_slurper_cpt filter.
 * @uses get_posts()     Queries for existing posts.
 *
 * @param int $beer_id The Untappd beer ID to search for.
 *
 * @return array|false Array with 'id' and 'date' keys if found, false otherwise.
 */
function find_existing_post( $beer_id ){
	$args = array(
		'post_type'      => apply_filters( 'beer_slurper_cpt', BEER_SLURPER_CPT ),
		'posts_per_page' => 1,
		'meta_key'       => '_beer_slurper_id',
		'meta_value'     => $beer_id,
		);

	$query = \get_posts( $args );
	if ( ! $query ) {
		return false;
	}

	$args = array(
		'id' => $query[0]->ID,
		'date' => $query[0]->post_date_gmt,
		);
	return $args;
}

/**
 * Checks if a checkin has already been imported.
 *
 * Searches for a post with the given Untappd checkin ID to prevent
 * duplicate imports.
 *
 * @since 1.0.0
 *
 * @uses apply_filters() Applies beer_slurper_cpt filter.
 * @uses get_posts()     Queries for existing posts.
 *
 * @param int $checkin_id The Untappd checkin ID to search for.
 *
 * @return bool True if checkin exists, false otherwise.
 */
function find_existing_checkin( $checkin_id ){
	$args = array(
		'post_type'      => apply_filters( 'beer_slurper_cpt', BEER_SLURPER_CPT ),
		'posts_per_page' => 1,
		'meta_key'       => '_beer_slurper_untappd_id',
		'meta_value'     => $checkin_id,
		);

	$query = \get_posts( $args );
	return ! empty( $query );
}

/**
 * Downloads and attaches an image as a post thumbnail.
 *
 * Downloads an image from a remote URL and sets it as the featured image
 * for the specified post.
 *
 * @since 1.0.0
 *
 * @uses require_once()          Loads WordPress media handling files.
 * @uses preg_match()            Extracts image extension from URL.
 * @uses pathinfo()              Parses filename components.
 * @uses basename()              Gets filename from path.
 * @uses download_url()          Downloads image to temp location.
 * @uses media_handle_sideload() Processes and attaches the image.
 * @uses is_wp_error()           Checks for download errors.
 * @uses wp_delete_file()        Cleans up temp file on error.
 * @uses set_post_thumbnail()    Sets the featured image.
 *
 * @param string $img_src The URL of the image to download.
 * @param int    $post_id The post ID to attach the thumbnail to.
 * @param string $name    The name to use for the image file (without extension).
 *
 * @return void
 */
function insert_thumbnail( $img_src, $post_id, $name ) {
		require_once(ABSPATH . 'wp-admin/includes/media.php');
		require_once(ABSPATH . 'wp-admin/includes/file.php');
		require_once(ABSPATH . 'wp-admin/includes/image.php');
	 	// most of the below lifted from core. Clunky until Core allows media_handle_sideload to directly take an URL.
		preg_match( '/[^\?]+\.(jpe?g|jpe|gif|png)\b/i', $img_src, $matches );
		$file_array = array();
		$ext = pathinfo( basename( $matches[0] ) );
		$ext = $ext['extension'];
		$file_array['name'] = $name . '.' . $ext;

		// Download file to temp location.
		$file_array['tmp_name'] = download_url( $img_src );

		// If error storing temporarily, return the error.
		if ( ! is_wp_error( $file_array['tmp_name'] ) ) {
			$thumbnail_id = media_handle_sideload( $file_array, $post_id, null );
		}

		if ( is_wp_error( $thumbnail_id ) ) {
			wp_delete_file( $file_array['tmp_name'] );
		}
		else {
			set_post_thumbnail( $post_id, $thumbnail_id );
		}
}

/**
 * Attaches a brewery to a beer post.
 *
 * Associates a beer post with a brewery taxonomy term, creating the term
 * if it does not exist.
 *
 * @since 1.0.0
 *
 * @uses is_wp_error()                                     Checks for errors.
 * @uses wp_set_object_terms()                             Assigns brewery term to post.
 * @uses apply_filters()                                   Applies beer_slurper_tax_brewery filter.
 * @uses \Kraft\Beer_Slurper\Brewery\get_brewery_term_id() Gets or creates brewery term.
 *
 * @param int        $post_id      The post ID to attach the brewery to.
 * @param int|null   $brewery_id   Optional. The Untappd brewery ID.
 * @param array|null $brewery_data Optional. Brewery data array for term creation.
 *
 * @return void
 */
function attach_brewery( $post_id, $brewery_id = null, $brewery_data = null ){ // uses Untappd Brewery ID.
	if ( empty( $brewery_id ) ){
		return;
	}

	$term_id = \Kraft\Beer_Slurper\Brewery\get_brewery_term_id( $brewery_id, $brewery_data );
	if ( $term_id && ! is_wp_error( $term_id ) ) {
		wp_set_object_terms( $post_id, (int)$term_id, apply_filters( 'beer_slurper_tax_brewery', BEER_SLURPER_TAX_BREWERY ) , true);
	}
}

/**
 * Attaches collaboration breweries to a beer post.
 *
 * Associates multiple breweries with a beer post for collaboration beers.
 *
 * @since 1.0.0
 *
 * @uses \Kraft\Beer_Slurper\Post\attach_brewery() Attaches each collaboration brewery.
 *
 * @param int   $post_id The post ID to attach breweries to.
 * @param array $collabs Optional. Array of Untappd brewery IDs. Default empty array.
 *
 * @return void
 */
function attach_collaborations( $post_id, $collabs = array() ){
	foreach ( $collabs as $collab ) {
		attach_brewery( $post_id, $collab );
	}

}
