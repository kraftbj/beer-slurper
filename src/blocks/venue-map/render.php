<?php
/**
 * Venue Map Block - Server-side render.
 *
 * @package Kraft\Beer_Slurper
 */

$height = isset( $attributes['height'] ) ? $attributes['height'] : '400px';
$zoom   = isset( $attributes['zoom'] ) ? (int) $attributes['zoom'] : 4;

$venues = get_terms( array(
	'taxonomy'   => BEER_SLURPER_TAX_VENUE,
	'hide_empty' => false,
	'meta_query' => array(
		array(
			'key'     => 'venue_lat',
			'compare' => 'EXISTS',
		),
	),
) );

$venue_data = array();
if ( ! is_wp_error( $venues ) && ! empty( $venues ) ) {
	foreach ( $venues as $venue ) {
		$lat = get_term_meta( $venue->term_id, 'venue_lat', true );
		$lng = get_term_meta( $venue->term_id, 'venue_lng', true );
		if ( empty( $lat ) || empty( $lng ) ) {
			continue;
		}
		$venue_data[] = array(
			'name'    => $venue->name,
			'lat'     => (float) $lat,
			'lng'     => (float) $lng,
			'city'    => get_term_meta( $venue->term_id, 'venue_city', true ),
			'url'     => get_term_link( $venue ),
			'count'   => (int) $venue->count,
		);
	}
}

$venue_count = count( $venue_data );

wp_enqueue_style(
	'leaflet',
	'https://unpkg.com/leaflet@1/dist/leaflet.css',
	array(),
	'1'
);

wp_enqueue_script(
	'leaflet',
	'https://unpkg.com/leaflet@1/dist/leaflet.js',
	array(),
	'1',
	true
);
?>
<div
	<?php echo get_block_wrapper_attributes( array( 'class' => 'venue-map-block' ) ); ?>
	data-venues="<?php echo esc_attr( wp_json_encode( $venue_data ) ); ?>"
	data-zoom="<?php echo esc_attr( $zoom ); ?>"
	style="height: <?php echo esc_attr( $height ); ?>;"
>
	<?php if ( 0 === $venue_count ) : ?>
		<p class="venue-map-empty"><?php esc_html_e( 'No venues with location data found. Check in at some venues to see them on the map.', 'beer_slurper' ); ?></p>
	<?php endif; ?>
</div>
