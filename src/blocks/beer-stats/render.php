<?php
/**
 * Beer Stats Block - Server-side render.
 *
 * @package Kraft\Beer_Slurper
 */

$user_stats = \Kraft\Beer_Slurper\Stats\get_user_stats();
$total_beers    = \Kraft\Beer_Slurper\Sync_Status\get_total_beers();
$total_pictures = \Kraft\Beer_Slurper\Sync_Status\get_total_pictures();
$total_breweries = \Kraft\Beer_Slurper\Sync_Status\get_total_breweries();

$avatar = ! empty( $user_stats['user_avatar_hd'] ) ? $user_stats['user_avatar_hd'] : ( ! empty( $user_stats['user_avatar'] ) ? $user_stats['user_avatar'] : '' );
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'beer-stats-block' ) ); ?>>
	<?php if ( $avatar ) : ?>
		<div class="beer-stats-avatar">
			<img src="<?php echo esc_url( $avatar ); ?>" alt="<?php esc_attr_e( 'User avatar', 'beer_slurper' ); ?>" width="80" height="80" />
		</div>
	<?php endif; ?>

	<div class="beer-stats-grid">
		<h3><?php esc_html_e( 'Untappd Stats', 'beer_slurper' ); ?></h3>
		<dl class="beer-stats-list">
			<?php if ( ! empty( $user_stats['total_checkins'] ) ) : ?>
				<div class="beer-stats-item">
					<dt><?php esc_html_e( 'Total Checkins', 'beer_slurper' ); ?></dt>
					<dd><?php echo number_format_i18n( $user_stats['total_checkins'] ); ?></dd>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $user_stats['total_beers'] ) ) : ?>
				<div class="beer-stats-item">
					<dt><?php esc_html_e( 'Unique Beers', 'beer_slurper' ); ?></dt>
					<dd><?php echo number_format_i18n( $user_stats['total_beers'] ); ?></dd>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $user_stats['total_badges'] ) ) : ?>
				<div class="beer-stats-item">
					<dt><?php esc_html_e( 'Badges Earned', 'beer_slurper' ); ?></dt>
					<dd><?php echo number_format_i18n( $user_stats['total_badges'] ); ?></dd>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $user_stats['total_friends'] ) ) : ?>
				<div class="beer-stats-item">
					<dt><?php esc_html_e( 'Friends', 'beer_slurper' ); ?></dt>
					<dd><?php echo number_format_i18n( $user_stats['total_friends'] ); ?></dd>
				</div>
			<?php endif; ?>
		</dl>

		<h3><?php esc_html_e( 'Site Stats', 'beer_slurper' ); ?></h3>
		<dl class="beer-stats-list">
			<div class="beer-stats-item">
				<dt><?php esc_html_e( 'Imported Beers', 'beer_slurper' ); ?></dt>
				<dd><?php echo number_format_i18n( $total_beers ); ?></dd>
			</div>
			<div class="beer-stats-item">
				<dt><?php esc_html_e( 'Pictures', 'beer_slurper' ); ?></dt>
				<dd><?php echo number_format_i18n( $total_pictures ); ?></dd>
			</div>
			<div class="beer-stats-item">
				<dt><?php esc_html_e( 'Breweries', 'beer_slurper' ); ?></dt>
				<dd><?php echo number_format_i18n( $total_breweries ); ?></dd>
			</div>
		</dl>
	</div>

	<?php if ( empty( $user_stats ) ) : ?>
		<p class="beer-stats-empty"><?php esc_html_e( 'No stats available yet. Connect to Untappd and run a sync to see your stats.', 'beer_slurper' ); ?></p>
	<?php endif; ?>
</div>
