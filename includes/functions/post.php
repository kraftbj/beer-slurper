<?php
namespace Kraft\Beer_Slurper\Post;

/**
 * This file provides functions to check if a beer is already posted, inserting a new post, updating an existing post ( maybe ? ), etc.
 */

function insert_beer( $checkin ){ // @todo do this better with more.
	if (! $checkin ) {
		return new \WP_Error( 'no_checkin', __( "No information provided." ) );
	}
	$post_info = setup_post( $checkin );

	$post = array(
		'post_name'    => $post_info['slug'],
		'post_title'   => $post_info['title'],
		'post_content' => $post_info['content'],
		'post_excerpt' => $post_info['excerpt'],
		'post_type'    => 'beerlog_beer', // @todo Breakout beerlog into 3rd party file, set so works imports into post by default.
		'post_status'  => 'publish',
		);


	 $post_id = wp_insert_post( $post );

	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	foreach ( $post_info['meta'] as $meta_key => $meta_value ) {
	 	add_post_meta( $post_id, $meta_key, $meta_value, true );
	 }

	 if ( $post_info['img_src'] ) {
	 	require_once(ABSPATH . 'wp-admin/includes/media.php');
		require_once(ABSPATH . 'wp-admin/includes/file.php');
		require_once(ABSPATH . 'wp-admin/includes/image.php');
	 	// most of the below lifted from core. Clunky until Core allows media_handle_sideload to directly take an URL.
		preg_match( '/[^\?]+\.(jpe?g|jpe|gif|png)\b/i', $post_info['img_src'], $matches );
		$file_array = array();
		$ext = pathinfo( basename( $matches[0] ) );
		$ext = $ext['extension'];
		$file_array['name'] = basename( get_permalink( $post_id ) ) . '.' . $ext;
		var_dump($file_array['name']);

		// Download file to temp location.
		$file_array['tmp_name'] = download_url( $post_info['img_src'] );

		// If error storing temporarily, return the error.
		if ( ! is_wp_error( $file_array['tmp_name'] ) ) {
			$thumbnail_id = media_handle_sideload( $file_array, $post_id, null );
			var_dump($thumbnail_id);
		}

		if ( is_wp_error( $thumbnail_id ) ) {
			@unlink( $file_array['tmp_name'] );
		}

		else {
			set_post_thumbnail( $post_id, $thumbnail_id );
		}
	 }

}

function setup_post( $checkin ){
	if (! $checkin ) {
		return new \WP_Error( 'no_checkin', __( "No information provided." ) );
	}

	$beer     = $checkin['beer'];
	$beer_all = \Kraft\Beer_Slurper\API\get_beer_info( $beer['bid'] );
	$brewery   = $beer_all['beer']['brewery']; // \Kraft\Beer_Slurper\API\get_brewery_info_by_beer( $beer['bid'] );
	$post_info = array(
		'title'   => $brewery['brewery_name'] . ' ' . $beer['beer_name'],
		'slug'    => $beer_all['beer']['beer_slug'],
		'content' => $beer_all['beer']['beer_description'],
		'excerpt' => '', // @todo Update this.
		'img_src' => $checkin['media']['items'][0]['photo']['photo_img_og'],
		'meta'    => array(
			'_beer_slurper_id'  => $beer['bid'],
			'_beerlog_meta_abv' => $beer['beer_abv'],
			'_beerlog_meta_ibu' => $beer_all['beer']['beer_ibu'],
			),
		);
	return $post_info;
}