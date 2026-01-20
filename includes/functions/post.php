<?php
namespace Kraft\Beer_Slurper\Post;

/**
 * This file provides functions to check if a beer is already posted, inserting a new post, updating an existing post ( maybe ? ), etc.
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

	if ( $nodup ) {
		// need to search for existing post, then opt to update instead of insert.
		$existing_post = find_existing_post( $beer_id );
		if ( $existing_post ) {
			$post_id = $existing_post['id'];
			$existing_date = $existing_post['date'];

			// Now, check to ensure we haven't added that specific post before.
			if ( in_array( $checkin['checkin_id'], get_post_meta( $post_id, '_beer_slurper_untappd_id') ) ) {
				return new \WP_Error( 'already_done', __( "We've already added this exact checkin!", 'beer_slurper' ) );
			}
		}
	}

	$post_info = setup_post( $checkin );

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

	attach_brewery( $post_id, $post_info['brewery'] );
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

function setup_post( $checkin ){
	if ( ! $checkin ) {
		return new \WP_Error( 'no_checkin', __( "No information provided.", 'beer_slurper' ) );
	}

	$beer     = $checkin['beer'];
	$beer_all = \Kraft\Beer_Slurper\API\get_beer_info( $beer['bid'] );
	$brewery  = $beer_all['brewery']; // \Kraft\Beer_Slurper\API\get_brewery_info_by_beer( $beer['bid'] );
	$style    = $beer_all['beer_style'];
	$collabs  = $beer_all['collaborations_with'];
	$post_info = array(
		'title'         => $beer['beer_name'],
		'slug'          => $beer_all['beer_slug'],
		'content'       => $beer_all['beer_description'],
		'excerpt'       => '', // @todo Update this.
		'date'          => date( "Y-m-d H:i:s", strtotime( $checkin['created_at'] ) ), // Untappd returns UTC.
		'meta'          => array(
			'_beer_slurper_id'   => $beer['bid'],
			'_beerlog_meta_abv'  => $beer['beer_abv'],
			'_beerlog_meta_ibu'  => $beer_all['beer_ibu'],
			'_beer_slurper_desc' => $beer_all['beer_description'],
			'_beer_slurper_brew' => $brewery['brewery_id'],
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

	$post_info['brewery'] = $brewery['brewery_id'];
	$post_info['collabs'] = array();
	if ( $collabs['count'] > 0 ) { // We have some collaborators!
		foreach ( $collabs['items'] as $collab ) {
			$post_info['collabs'][] = $collab['brewery']['brewery_id'];
		}
	}

	return $post_info;
}

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
			@unlink( $file_array['tmp_name'] );
		}
		else {
			set_post_thumbnail( $post_id, $thumbnail_id );
		}
}

function attach_brewery( $post_id, $brewery_id = null ){ // uses Untappd Brewery ID.
	if ( empty( $brewery_id ) ){
		return;
	}

	$term_id = \Kraft\Beer_Slurper\Brewery\get_brewery_term_id( $brewery_id );
	wp_set_object_terms( $post_id, (int)$term_id, apply_filters( 'beer_slurper_tax_brewery', BEER_SLURPER_TAX_BREWERY ) , true);
}

function attach_collaborations( $post_id, $collabs = array() ){
	foreach ( $collabs as $collab ) {
		attach_brewery( $post_id, $collab );
	}

}
