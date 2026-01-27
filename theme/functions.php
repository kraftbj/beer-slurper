<?php
/**
 * Pint theme functions.
 *
 * @package Pint
 */

/**
 * Theme setup.
 */
function pint_setup() {
	add_theme_support( 'post-thumbnails' );
	add_image_size( 'pint-beer-card', 980, 980, true );
}
add_action( 'after_setup_theme', 'pint_setup' );

/**
 * Enqueue custom stylesheet.
 */
function pint_enqueue_styles() {
	wp_enqueue_style(
		'pint-custom',
		get_theme_file_uri( 'assets/css/custom.css' ),
		array(),
		wp_get_theme()->get( 'Version' )
	);
}
add_action( 'wp_enqueue_scripts', 'pint_enqueue_styles' );

/**
 * Enqueue load-more script on pages with the beer grid.
 */
function pint_enqueue_load_more() {
	wp_enqueue_script(
		'pint-load-more',
		get_theme_file_uri( 'assets/js/load-more.js' ),
		array(),
		wp_get_theme()->get( 'Version' ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'pint_enqueue_load_more' );

/**
 * Register pattern category.
 */
function pint_register_pattern_category() {
	register_block_pattern_category( 'pint', array(
		'label' => __( 'Pint', 'pint' ),
	) );
}
add_action( 'init', 'pint_register_pattern_category' );

/**
 * Register a stopgap beer-meta block for the single beer template.
 *
 * Displays ABV, IBU, and check-in count from post meta.
 * Skipped if the plugin provides its own beer-details block.
 */
function pint_register_beer_meta_block() {
	if ( \WP_Block_Type_Registry::get_instance()->is_registered( 'beer-slurper/beer-details' ) ) {
		return;
	}

	register_block_type( 'pint/beer-meta', array(
		'render_callback' => 'pint_render_beer_meta_block',
	) );
}
add_action( 'init', 'pint_register_beer_meta_block' );

/**
 * Register a stopgap venue-header block for the venue taxonomy template.
 *
 * Displays venue address and a Leaflet map from term meta.
 */
function pint_register_venue_header_block() {
	register_block_type( 'pint/venue-header', array(
		'render_callback' => 'pint_render_venue_header_block',
	) );
}
add_action( 'init', 'pint_register_venue_header_block' );

/**
 * Render callback for the pint/venue-header block.
 *
 * @return string Block HTML.
 */
function pint_render_venue_header_block() {
	$term = get_queried_object();
	if ( ! $term instanceof \WP_Term || BEER_SLURPER_TAX_VENUE !== $term->taxonomy ) {
		return '';
	}

	$term_id = $term->term_id;
	$address = get_term_meta( $term_id, 'venue_address', true );
	$city    = get_term_meta( $term_id, 'venue_city', true );
	$state   = get_term_meta( $term_id, 'venue_state', true );
	$country = get_term_meta( $term_id, 'venue_country', true );
	$lat     = get_term_meta( $term_id, 'venue_lat', true );
	$lng     = get_term_meta( $term_id, 'venue_lng', true );
	$url     = get_term_meta( $term_id, 'venue_url', true );

	$location_parts = array_filter( array( $city, $state, $country ) );
	$has_address     = $address || $location_parts;
	$has_coords      = $lat && $lng;

	if ( ! $has_address && ! $has_coords ) {
		return '';
	}

	$output = '<div class="pint-venue-details">';

	if ( $has_address ) {
		$output .= '<div class="pint-venue-address">';
		if ( $address ) {
			$output .= '<span class="pint-venue-street">' . esc_html( $address ) . '</span><br>';
		}
		if ( $location_parts ) {
			$output .= '<span class="pint-venue-location">' . esc_html( implode( ', ', $location_parts ) ) . '</span>';
		}
		if ( $url ) {
			$output .= '<br><a href="' . esc_url( $url ) . '" class="pint-venue-url" rel="noopener">' . esc_html( $url ) . '</a>';
		}
		$output .= '</div>';
	}

	if ( $has_coords ) {
		wp_enqueue_style( 'leaflet', 'https://unpkg.com/leaflet@1/dist/leaflet.css', array(), '1' );
		wp_enqueue_script( 'leaflet', 'https://unpkg.com/leaflet@1/dist/leaflet.js', array(), '1', true );

		$map_id  = 'pint-venue-map-' . $term_id;
		$output .= '<div id="' . esc_attr( $map_id ) . '" class="pint-venue-map"'
			. ' data-lat="' . esc_attr( $lat ) . '"'
			. ' data-lng="' . esc_attr( $lng ) . '"'
			. ' data-name="' . esc_attr( $term->name ) . '"'
			. '></div>';
		$output .= '<script>'
			. 'document.addEventListener("DOMContentLoaded",function(){'
			. 'if(typeof L==="undefined")return;'
			. 'var el=document.getElementById(' . wp_json_encode( $map_id ) . ');'
			. 'if(!el)return;'
			. 'var lat=parseFloat(el.dataset.lat),lng=parseFloat(el.dataset.lng);'
			. 'var map=L.map(el.id).setView([lat,lng],14);'
			. 'L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",{'
			. 'attribution:"&copy; OpenStreetMap contributors"}).addTo(map);'
			. 'L.marker([lat,lng]).addTo(map).bindPopup(el.dataset.name).openPopup();'
			. '});'
			. '</script>';
	}

	$output .= '</div>';

	return $output;
}

/**
 * Register a stopgap checkin-list block for the single beer template.
 *
 * Displays beer_checkin comments separately from regular comments.
 */
function pint_register_checkin_list_block() {
	register_block_type( 'pint/checkin-list', array(
		'render_callback' => 'pint_render_checkin_list_block',
	) );
}
add_action( 'init', 'pint_register_checkin_list_block' );

/**
 * Render callback for the pint/checkin-list block.
 *
 * @return string Block HTML.
 */
function pint_render_checkin_list_block() {
	$post_id = get_the_ID();
	if ( ! $post_id ) {
		return '';
	}

	$checkins = get_comments( array(
		'post_id' => $post_id,
		'type'    => 'beer_checkin',
		'status'  => 'approve',
		'orderby' => 'comment_date',
		'order'   => 'DESC',
	) );

	$count = count( $checkins );
	if ( 0 === $count ) {
		return '';
	}

	$output = '<div class="pint-checkins">';
	$output .= '<h3 class="pint-checkins__heading">';
	$output .= esc_html( sprintf(
		/* translators: %d: number of check-ins */
		_n( '%d Check-in', '%d Check-ins', $count, 'pint' ),
		$count
	) );
	$output .= '</h3>';
	$output .= '<ul class="pint-checkins__list">';

	foreach ( $checkins as $checkin ) {
		$rating  = get_comment_meta( $checkin->comment_ID, '_beer_slurper_rating', true );
		$serving = get_comment_meta( $checkin->comment_ID, '_beer_slurper_serving_type', true );
		$venue_id = get_comment_meta( $checkin->comment_ID, '_beer_slurper_venue_id', true );

		$output .= '<li class="pint-checkin">';
		$output .= '<div class="pint-checkin__meta">';
		$output .= '<strong class="pint-checkin__author">' . esc_html( $checkin->comment_author ) . '</strong>';
		$output .= '<time class="pint-checkin__date" datetime="' . esc_attr( $checkin->comment_date_gmt ) . '">';
		$output .= esc_html( date_i18n( get_option( 'date_format' ), strtotime( $checkin->comment_date ) ) );
		$output .= '</time>';
		$output .= '</div>';

		$details = array();
		if ( $rating ) {
			$stars = str_repeat( "\xe2\x98\x85", (int) round( (float) $rating ) );
			$details[] = '<span class="pint-checkin__rating" title="' . esc_attr( $rating ) . '/5">' . $stars . '</span>';
		}
		if ( $serving ) {
			$details[] = '<span class="pint-checkin__serving">' . esc_html( ucfirst( $serving ) ) . '</span>';
		}
		if ( $venue_id ) {
			$venue_term = get_term( (int) $venue_id, BEER_SLURPER_TAX_VENUE );
			if ( $venue_term && ! is_wp_error( $venue_term ) ) {
				$details[] = '<a href="' . esc_url( get_term_link( $venue_term ) ) . '" class="pint-checkin__venue">'
					. esc_html( $venue_term->name ) . '</a>';
			}
		}
		if ( $details ) {
			$output .= '<div class="pint-checkin__details">' . implode( ' &middot; ', $details ) . '</div>';
		}

		if ( ! empty( $checkin->comment_content ) ) {
			$output .= '<div class="pint-checkin__comment">' . esc_html( $checkin->comment_content ) . '</div>';
		}

		$output .= '</li>';
	}

	$output .= '</ul></div>';

	return $output;
}

/**
 * Render callback for the pint/beer-meta block.
 *
 * @return string Block HTML.
 */
function pint_render_beer_meta_block() {
	$post_id = get_the_ID();
	if ( ! $post_id ) {
		return '';
	}

	$abv   = get_post_meta( $post_id, '_beer_slurper_abv', true );
	$ibu   = get_post_meta( $post_id, '_beer_slurper_ibu', true );
	$count = get_post_meta( $post_id, '_beer_slurper_count', true );

	if ( ! $abv && ! $ibu && ! $count ) {
		return '';
	}

	$output = '<dl class="beer-details-block">';

	if ( $abv ) {
		$output .= '<div><dt>' . esc_html__( 'ABV', 'pint' ) . '</dt>';
		$output .= '<dd>' . esc_html( $abv ) . '%</dd></div>';
	}

	if ( $ibu ) {
		$output .= '<div><dt>' . esc_html__( 'IBU', 'pint' ) . '</dt>';
		$output .= '<dd>' . esc_html( $ibu ) . '</dd></div>';
	}

	if ( $count ) {
		$output .= '<div><dt>' . esc_html__( 'Check-ins', 'pint' ) . '</dt>';
		$output .= '<dd>' . esc_html( number_format_i18n( $count ) ) . '</dd></div>';
	}

	$output .= '</dl>';

	return $output;
}
