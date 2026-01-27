<?php
namespace Kraft\Beer_Slurper\Taxonomy_Admin;

/**
 * Taxonomy Admin UI
 *
 * Displays term meta on the admin list tables and edit-term screens
 * for brewery, venue, and badge taxonomies.
 *
 * @package Kraft\Beer_Slurper
 */

// --- Brewery columns -----------------------------------------------------------

add_filter( 'manage_edit-' . BEER_SLURPER_TAX_BREWERY . '_columns', __NAMESPACE__ . '\brewery_columns' );
add_filter( 'manage_' . BEER_SLURPER_TAX_BREWERY . '_custom_column', __NAMESPACE__ . '\brewery_column_content', 10, 3 );

/**
 * Adds custom columns to the brewery list table.
 *
 * @param array $columns Existing columns.
 * @return array Modified columns.
 */
function brewery_columns( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'name' === $key ) {
			$new['brewery_type']     = __( 'Type', 'beer_slurper' );
			$new['brewery_location'] = __( 'Location', 'beer_slurper' );
		}
	}
	return $new;
}

/**
 * Renders custom column content for the brewery list table.
 *
 * @param string $content    Existing content.
 * @param string $column     Column name.
 * @param int    $term_id    Term ID.
 * @return string Column content.
 */
function brewery_column_content( $content, $column, $term_id ) {
	switch ( $column ) {
		case 'brewery_type':
			$val = get_term_meta( $term_id, 'brewery_type', true );
			return $val ? esc_html( $val ) : '&mdash;';

		case 'brewery_location':
			$parts = array_filter( array(
				get_term_meta( $term_id, 'brewery_city', true ),
				get_term_meta( $term_id, 'brewery_state', true ),
				get_term_meta( $term_id, 'brewery_country', true ),
			) );
			return $parts ? esc_html( implode( ', ', $parts ) ) : '&mdash;';
	}
	return $content;
}

// --- Brewery edit form ---------------------------------------------------------

add_action( BEER_SLURPER_TAX_BREWERY . '_edit_form_fields', __NAMESPACE__ . '\brewery_edit_fields', 10, 2 );

/**
 * Displays brewery term meta on the edit-term screen.
 *
 * @param \WP_Term $term     The term object.
 * @param string   $taxonomy The taxonomy slug.
 * @return void
 */
function brewery_edit_fields( $term, $taxonomy ) {
	$fields = array(
		'untappd_id'          => __( 'Untappd ID', 'beer_slurper' ),
		'brewery_type'        => __( 'Type', 'beer_slurper' ),
		'brewery_label'       => __( 'Label', 'beer_slurper' ),
		'brewery_description' => __( 'Description', 'beer_slurper' ),
		'brewery_address'     => __( 'Address', 'beer_slurper' ),
		'brewery_city'        => __( 'City', 'beer_slurper' ),
		'brewery_state'       => __( 'State', 'beer_slurper' ),
		'brewery_country'     => __( 'Country', 'beer_slurper' ),
		'brewery_lat'         => __( 'Latitude', 'beer_slurper' ),
		'brewery_lng'         => __( 'Longitude', 'beer_slurper' ),
		'brewery_url'         => __( 'Website', 'beer_slurper' ),
		'brewery_twitter'     => __( 'Twitter', 'beer_slurper' ),
		'brewery_facebook'    => __( 'Facebook', 'beer_slurper' ),
		'brewery_instagram'   => __( 'Instagram', 'beer_slurper' ),
	);

	render_meta_section( __( 'Untappd Data', 'beer_slurper' ), $term->term_id, $fields );
}

// --- Venue columns -------------------------------------------------------------

add_filter( 'manage_edit-' . BEER_SLURPER_TAX_VENUE . '_columns', __NAMESPACE__ . '\venue_columns' );
add_filter( 'manage_' . BEER_SLURPER_TAX_VENUE . '_custom_column', __NAMESPACE__ . '\venue_column_content', 10, 3 );

/**
 * Adds custom columns to the venue list table.
 *
 * @param array $columns Existing columns.
 * @return array Modified columns.
 */
function venue_columns( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'name' === $key ) {
			$new['venue_location'] = __( 'Location', 'beer_slurper' );
			$new['venue_category'] = __( 'Category', 'beer_slurper' );
		}
	}
	return $new;
}

/**
 * Renders custom column content for the venue list table.
 *
 * @param string $content    Existing content.
 * @param string $column     Column name.
 * @param int    $term_id    Term ID.
 * @return string Column content.
 */
function venue_column_content( $content, $column, $term_id ) {
	switch ( $column ) {
		case 'venue_location':
			$parts = array_filter( array(
				get_term_meta( $term_id, 'venue_city', true ),
				get_term_meta( $term_id, 'venue_state', true ),
				get_term_meta( $term_id, 'venue_country', true ),
			) );
			return $parts ? esc_html( implode( ', ', $parts ) ) : '&mdash;';

		case 'venue_category':
			$val = get_term_meta( $term_id, 'venue_category', true );
			return $val ? esc_html( $val ) : '&mdash;';
	}
	return $content;
}

// --- Venue edit form -----------------------------------------------------------

add_action( BEER_SLURPER_TAX_VENUE . '_edit_form_fields', __NAMESPACE__ . '\venue_edit_fields', 10, 2 );

/**
 * Displays venue term meta on the edit-term screen.
 *
 * @param \WP_Term $term     The term object.
 * @param string   $taxonomy The taxonomy slug.
 * @return void
 */
function venue_edit_fields( $term, $taxonomy ) {
	$fields = array(
		'untappd_id'     => __( 'Untappd ID', 'beer_slurper' ),
		'venue_address'  => __( 'Address', 'beer_slurper' ),
		'venue_city'     => __( 'City', 'beer_slurper' ),
		'venue_state'    => __( 'State', 'beer_slurper' ),
		'venue_country'  => __( 'Country', 'beer_slurper' ),
		'venue_lat'      => __( 'Latitude', 'beer_slurper' ),
		'venue_lng'      => __( 'Longitude', 'beer_slurper' ),
		'venue_url'      => __( 'Website', 'beer_slurper' ),
		'venue_category' => __( 'Category', 'beer_slurper' ),
		'venue_icon'     => __( 'Icon', 'beer_slurper' ),
		'foursquare_id'  => __( 'Foursquare ID', 'beer_slurper' ),
	);

	render_meta_section( __( 'Untappd Data', 'beer_slurper' ), $term->term_id, $fields );
}

// --- Badge columns -------------------------------------------------------------

add_filter( 'manage_edit-' . BEER_SLURPER_TAX_BADGE . '_columns', __NAMESPACE__ . '\badge_columns' );
add_filter( 'manage_' . BEER_SLURPER_TAX_BADGE . '_custom_column', __NAMESPACE__ . '\badge_column_content', 10, 3 );

/**
 * Adds custom columns to the badge list table.
 *
 * @param array $columns Existing columns.
 * @return array Modified columns.
 */
function badge_columns( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'name' === $key ) {
			$new['badge_image'] = __( 'Image', 'beer_slurper' );
			$new['badge_level'] = __( 'Level', 'beer_slurper' );
		}
	}
	// Move description to end.
	if ( isset( $columns['description'] ) ) {
		unset( $new['description'] );
		$new['description'] = $columns['description'];
	}
	return $new;
}

/**
 * Renders custom column content for the badge list table.
 *
 * @param string $content    Existing content.
 * @param string $column     Column name.
 * @param int    $term_id    Term ID.
 * @return string Column content.
 */
function badge_column_content( $content, $column, $term_id ) {
	switch ( $column ) {
		case 'badge_image':
			$url = get_term_meta( $term_id, 'badge_image_sm', true );
			if ( $url ) {
				return '<img src="' . esc_url( $url ) . '" alt="" style="width:40px;height:40px;object-fit:contain;" />';
			}
			return '&mdash;';

		case 'badge_level':
			$level = (int) get_term_meta( $term_id, 'badge_level', true );
			return $level > 0 ? esc_html( $level ) : '&mdash;';
	}
	return $content;
}

// --- Badge edit form -----------------------------------------------------------

add_action( BEER_SLURPER_TAX_BADGE . '_edit_form_fields', __NAMESPACE__ . '\badge_edit_fields', 10, 2 );

/**
 * Displays badge term meta on the edit-term screen.
 *
 * @param \WP_Term $term     The term object.
 * @param string   $taxonomy The taxonomy slug.
 * @return void
 */
function badge_edit_fields( $term, $taxonomy ) {
	$fields = array(
		'untappd_id'        => __( 'Untappd ID', 'beer_slurper' ),
		'badge_level'       => __( 'Level', 'beer_slurper' ),
		'badge_description' => __( 'Badge Description', 'beer_slurper' ),
		'badge_image_sm'    => __( 'Image (Small)', 'beer_slurper' ),
		'badge_image_md'    => __( 'Image (Medium)', 'beer_slurper' ),
		'badge_image_lg'    => __( 'Image (Large)', 'beer_slurper' ),
	);

	render_meta_section( __( 'Untappd Data', 'beer_slurper' ), $term->term_id, $fields );
}

// --- Shared rendering ----------------------------------------------------------

/**
 * Renders a group of read-only term meta fields on the edit-term screen.
 *
 * @param string $heading Section heading.
 * @param int    $term_id Term ID.
 * @param array  $fields  Associative array of meta_key => label.
 * @return void
 */
function render_meta_section( $heading, $term_id, $fields ) {
	?>
	<tr class="form-field">
		<th scope="row" colspan="2">
			<h2 style="margin:1em 0 0;padding:0;font-size:1.2em;"><?php echo esc_html( $heading ); ?></h2>
		</th>
	</tr>
	<?php
	foreach ( $fields as $meta_key => $label ) {
		$value = get_term_meta( $term_id, $meta_key, true );
		render_meta_row( $label, $meta_key, $value );
	}
}

/**
 * Renders a single read-only term meta row.
 *
 * Image URLs are rendered as both a thumbnail and a link.
 * URL values are rendered as clickable links.
 * All other values are rendered as plain text.
 *
 * @param string $label    Field label.
 * @param string $meta_key Meta key (used for type detection).
 * @param mixed  $value    Meta value.
 * @return void
 */
function render_meta_row( $label, $meta_key, $value ) {
	$is_image = ( strpos( $meta_key, '_image' ) !== false || strpos( $meta_key, '_label' ) !== false || strpos( $meta_key, '_icon' ) !== false );
	$is_url   = ( strpos( $meta_key, '_url' ) !== false );
	?>
	<tr class="form-field">
		<th scope="row"><?php echo esc_html( $label ); ?></th>
		<td>
			<?php if ( empty( $value ) && 0 !== $value && '0' !== $value ) : ?>
				<span class="description">&mdash;</span>
			<?php elseif ( $is_image ) : ?>
				<img src="<?php echo esc_url( $value ); ?>" alt="" style="max-width:100px;max-height:100px;display:block;margin-bottom:4px;" />
				<code><?php echo esc_html( $value ); ?></code>
			<?php elseif ( $is_url ) : ?>
				<a href="<?php echo esc_url( $value ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $value ); ?></a>
			<?php else : ?>
				<code><?php echo esc_html( $value ); ?></code>
			<?php endif; ?>
		</td>
	</tr>
	<?php
}
