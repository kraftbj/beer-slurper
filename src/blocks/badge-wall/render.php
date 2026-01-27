<?php
/**
 * Badge Wall Block - Server-side render.
 *
 * @package Kraft\Beer_Slurper
 */

$columns = isset( $attributes['columns'] ) ? (int) $attributes['columns'] : 4;
$limit   = isset( $attributes['limit'] ) ? (int) $attributes['limit'] : 0;

$term_args = array(
	'taxonomy'   => BEER_SLURPER_TAX_BADGE,
	'hide_empty' => false,
	'orderby'    => 'term_id',
	'order'      => 'DESC',
);

if ( $limit > 0 ) {
	$term_args['number'] = $limit;
}

$badges = get_terms( $term_args );

if ( is_wp_error( $badges ) || empty( $badges ) ) {
	?>
	<div <?php echo get_block_wrapper_attributes( array( 'class' => 'badge-wall-block' ) ); ?>>
		<p class="badge-wall-empty"><?php esc_html_e( 'No badges earned yet. Start checking in beers to earn badges!', 'beer_slurper' ); ?></p>
	</div>
	<?php
	return;
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'badge-wall-block' ) ); ?>>
	<div class="badge-wall-grid" style="display: grid; grid-template-columns: repeat(<?php echo esc_attr( $columns ); ?>, 1fr); gap: 16px;">
		<?php foreach ( $badges as $badge ) :
			$image_url = get_term_meta( $badge->term_id, 'badge_image_lg', true );
			if ( empty( $image_url ) ) {
				$image_url = get_term_meta( $badge->term_id, 'badge_image_md', true );
			}
			if ( empty( $image_url ) ) {
				$image_url = get_term_meta( $badge->term_id, 'badge_image_sm', true );
			}
			$badge_link  = get_term_link( $badge );
			$badge_level = (int) get_term_meta( $badge->term_id, 'badge_level', true );
			?>
			<div class="badge-wall-item" style="text-align: center;">
				<?php if ( $image_url ) : ?>
					<?php if ( ! is_wp_error( $badge_link ) ) : ?>
						<a href="<?php echo esc_url( $badge_link ); ?>">
					<?php endif; ?>
					<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $badge->name ); ?>" width="100" height="100" loading="lazy" />
					<?php if ( ! is_wp_error( $badge_link ) ) : ?>
						</a>
					<?php endif; ?>
				<?php endif; ?>
				<p class="badge-wall-name">
					<?php if ( ! is_wp_error( $badge_link ) ) : ?>
						<a href="<?php echo esc_url( $badge_link ); ?>"><?php echo esc_html( $badge->name ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $badge->name ); ?>
					<?php endif; ?>
					<?php if ( $badge_level > 0 ) : ?>
						<span class="badge-wall-level"><?php printf( esc_html__( 'Level %d', 'beer_slurper' ), $badge_level ); ?></span>
					<?php endif; ?>
				</p>
			</div>
		<?php endforeach; ?>
	</div>
</div>
