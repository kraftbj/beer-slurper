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
