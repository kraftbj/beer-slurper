<?php
namespace Kraft\Beer_Slurper\Core;

/**
 * Default setup routine
 *
 * @uses add_action()
 * @uses do_action()
 *
 * @return void
 */
function setup() {
	$n = function( $function ) {
		return __NAMESPACE__ . "\\$function";
	};

	add_action( 'init',       $n( 'i18n'         ) );
	add_action( 'init',       $n( 'init'         ) );
	add_action( 'admin_init', $n( 'setting_init' ) );
	add_action( 'admin_menu', $n( 'setting_menu' ) );
	add_action( 'admin_enqueue_scripts', $n( 'enqueue_admin_assets' ) );
	add_action( 'wp_ajax_beer_slurper_sync_now', $n( 'ajax_sync_now' ) );

	do_action( 'beer_slurper_loaded' );
}

/**
 * Registers the default textdomain.
 *
 * @uses apply_filters()
 * @uses get_locale()
 * @uses load_textdomain()
 * @uses load_plugin_textdomain()
 * @uses plugin_basename()
 *
 * @return void
 */
function i18n() {
	$locale = apply_filters( 'plugin_locale', get_locale(), 'beer_slurper' );
	load_textdomain( 'beer_slurper', WP_LANG_DIR . '/beer_slurper/beer_slurper-' . $locale . '.mo' );
	load_plugin_textdomain( 'beer_slurper', false, plugin_basename( BEER_SLURPER_PATH ) . '/languages/' );
}

/**
 * Initializes the plugin and fires an action other plugins can hook into.
 *
 * @uses do_action()
 *
 * @return void
 */
function init() {
	default_settings(); // Converts PHP constants to settings.
	do_action( 'beer_slurper_init' );
}

/**
 * Activate the plugin
 *
 * @uses init()
 * @uses flush_rewrite_rules()
 *
 * @return void
 */
function activate() {
	// First load the init scripts in case any rewrite functionality is being loaded
	init();
	flush_rewrite_rules();
}

/**
 * Deactivate the plugin
 *
 * Uninstall routines should be in uninstall.php
 *
 * @return void
 */
function deactivate() {
	wp_clear_scheduled_hook( 'bs_hourly_importer' );
}

/**
 * Register the settings and whatnot.
 *
 * @return void
 */
function setting_init() {
	$n = function( $function ) {
		return __NAMESPACE__ . "\\$function";
	};

	add_settings_section( 'untappd_settings', 'Untappd Settings', null, 'beer-slurper-settings');

	add_settings_field( 'beer-slurper-key', __( 'Untappd Key', 'beer_slurper' ), $n( 'setting_key' ), 'beer-slurper-settings', 'untappd_settings', array( 'label_for' => 'beer-slurper-key' ) );
	register_setting( 'beer-slurper-settings', 'beer-slurper-key', 'strip_tags' );

	add_settings_field( 'beer-slurper-secret', __( 'Untappd Secret', 'beer_slurper' ), $n( 'setting_secret' ), 'beer-slurper-settings', 'untappd_settings', array( 'label_for' => 'beer-slurper-secret' ) );
	register_setting( 'beer-slurper-settings', 'beer-slurper-secret', 'strip_tags' );

	add_settings_field( 'beer-slurper-user', __( 'User to Import', 'beer_slurper' ), $n( 'setting_user' ), 'beer-slurper-settings', 'untappd_settings', array( 'label_for' => 'beer-slurper-user' ) );
	register_setting( 'beer-slurper-settings', 'beer-slurper-user', 'sanitize_user' );

	add_settings_field( 'beer-slurper-gallery', __( 'Auto-append Gallery', 'beer_slurper' ), $n( 'setting_gallery' ), 'beer-slurper-settings', 'untappd_settings', array( 'label_for' => 'beer-slurper-gallery' ) );
	register_setting( 'beer-slurper-settings', 'beer-slurper-gallery', 'boolval' );

	// Sync Status section
	add_settings_section( 'sync_status_settings', __( 'Sync Status', 'beer_slurper' ), $n( 'sync_status_section_callback' ), 'beer-slurper-settings' );
}

/**
 * Setup override of db settings.
 *
 **/
function default_settings() {

	if ( defined( 'UNTAPPD_KEY' ) && defined( 'UNTAPPD_SECRET' ) ) {
		add_filter( 'pre_option_beer-slurper-key',    function() { return UNTAPPD_KEY; } );
		add_filter( 'pre_option_beer-slurper-secret', function() { return UNTAPPD_SECRET; } );
	}
}

/**
 * Adds Beer settings page to menu
 *
 * @return void
 */
function setting_menu() {
	add_options_page(
		'Beer Slurper',
		'Beer',
		'manage_options',
		'beer-slurper-settings',
		'Kraft\Beer_Slurper\Core\setting_page' );
}


/**
 * Create settings page
 *
 * @return void
 */
function setting_page(){
	?>
	<div class="wrap">
		<h2><?php _e( 'Beer Slurper Settings', 'beer_slurper' ); ?></h2>
		<form method="post" action="options.php"><?php
			settings_fields( 'beer-slurper-settings' );
			do_settings_sections( 'beer-slurper-settings' );
			submit_button(); ?>
		</form>
	</div> <?php
}

/**
 * Echos the Untappd Key wrapper setting form field
 *
 * @return void
 * @since 1.0.0
 **/
function setting_key(){
	if ( defined( 'UNTAPPD_KEY' ) ) {
		_e( 'This setting has been set via code and must be changed there.', 'beer_slurper' );
	}
	else {
		$html = '<input type="text" id="beer-slurper-key" name="beer-slurper-key" value="' . esc_attr( get_option( 'beer-slurper-key' ) ) . '" size="40" />';
		echo $html;
	}
}

/**
 * Echos the Untappd Secret wrapper setting form field
 *
 * @return void
 * @since 1.0.0
 **/
function setting_secret(){
	if ( defined( 'UNTAPPD_SECRET' ) ) {
		_e( 'This setting has been set via code and must be changed there.', 'beer_slurper' );
	}
	else {
		$html = '<input type="text" id="beer-slurper-secret" name="beer-slurper-secret" value="' . esc_attr( get_option( 'beer-slurper-secret' ) ) . '" size="40" />';
		echo $html;
	}
}

/**
 * Echos the Untappd User wrapper setting form field
 *
 * @return void
 * @since 1.0.0
 **/
function setting_user(){
	if ( defined( 'UNTAPPD_USER' ) ) {
		_e( 'This setting has been set via code and must be changed there.', 'beer_slurper' );
	}
	else {
		$html = '<input type="text" id="beer-slurper-user" name="beer-slurper-user" value="' . esc_attr( get_option( 'beer-slurper-user' ) ) . '" size="40" />';
		$html .= '<br />Note: This doesn\'t actually do anything right now. Need to build out cron activation/deactivation based on this setting.';
		echo $html;
	}
}

/**
 * Echos the Gallery auto-append setting form field
 *
 * @return void
 */
function setting_gallery() {
	$checked = get_option( 'beer-slurper-gallery', true );
	$html = '<input type="checkbox" id="beer-slurper-gallery" name="beer-slurper-gallery" value="1" ' . checked( $checked, true, false ) . ' />';
	$html .= '<label for="beer-slurper-gallery">' . __( 'Automatically append [gallery] shortcode to beer posts', 'beer_slurper' ) . '</label>';
	echo $html;
}

/**
 * Renders the Sync Status section content.
 *
 * @return void
 */
function sync_status_section_callback() {
	$user = \Kraft\Beer_Slurper\Sync_Status\get_configured_user();
	$last_sync = \Kraft\Beer_Slurper\Sync_Status\get_last_sync_time();
	$last_error = \Kraft\Beer_Slurper\Sync_Status\get_last_sync_error();
	$next_sync = $user ? \Kraft\Beer_Slurper\Sync_Status\get_next_scheduled_sync( $user ) : null;
	$is_backfilling = $user ? \Kraft\Beer_Slurper\Sync_Status\is_backfilling( $user ) : false;

	$total_beers = \Kraft\Beer_Slurper\Sync_Status\get_total_beers();
	$total_pictures = \Kraft\Beer_Slurper\Sync_Status\get_total_pictures();
	$total_breweries = \Kraft\Beer_Slurper\Sync_Status\get_total_breweries();

	?>
	<style>
		.beer-slurper-sync-status { margin-top: 10px; }
		.beer-slurper-sync-status dl { margin: 0; }
		.beer-slurper-sync-status dt { font-weight: 600; margin-top: 10px; }
		.beer-slurper-sync-status dd { margin-left: 0; margin-bottom: 5px; }
		.beer-slurper-error { color: #d63638; background: #fcf0f1; border-left: 4px solid #d63638; padding: 10px; margin: 10px 0; }
		.beer-slurper-warning { color: #996800; background: #fcf9e8; border-left: 4px solid #dba617; padding: 10px; margin: 10px 0; }
		.beer-slurper-success { color: #00a32a; }
		.beer-slurper-backfilling { color: #2271b1; }
		.beer-slurper-stats-table { border-collapse: collapse; margin: 10px 0; }
		.beer-slurper-stats-table th,
		.beer-slurper-stats-table td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #c3c4c7; }
		.beer-slurper-stats-table th { background: #f0f0f1; }
		#beer-slurper-sync-now { margin-top: 15px; }
		#beer-slurper-sync-message { margin-left: 10px; display: inline-block; }
	</style>

	<div class="beer-slurper-sync-status">
		<dl>
			<dt><?php _e( 'Untappd User', 'beer_slurper' ); ?></dt>
			<dd>
				<?php if ( $user ) : ?>
					<strong><?php echo esc_html( $user ); ?></strong>
				<?php else : ?>
					<em><?php _e( 'Not configured', 'beer_slurper' ); ?></em>
				<?php endif; ?>
			</dd>

			<dt><?php _e( 'Sync Status', 'beer_slurper' ); ?></dt>
			<dd>
				<?php if ( ! $user ) : ?>
					<em><?php _e( 'No user configured', 'beer_slurper' ); ?></em>
				<?php elseif ( $is_backfilling ) : ?>
					<span class="beer-slurper-backfilling"><?php _e( 'Backfilling historical data...', 'beer_slurper' ); ?></span>
				<?php else : ?>
					<span class="beer-slurper-success"><?php _e( 'Caught up', 'beer_slurper' ); ?></span>
				<?php endif; ?>
			</dd>

			<dt><?php _e( 'Last Sync', 'beer_slurper' ); ?></dt>
			<dd>
				<?php if ( $last_sync ) : ?>
					<?php
					$date_format = get_option( 'date_format' );
					$time_format = get_option( 'time_format' );
					$formatted_date = date_i18n( $date_format . ' ' . $time_format, $last_sync );
					$relative_time = \Kraft\Beer_Slurper\Sync_Status\get_relative_time( $last_sync );
					?>
					<?php echo esc_html( $formatted_date ); ?> (<?php echo esc_html( $relative_time ); ?>)
				<?php else : ?>
					<em><?php _e( 'Never', 'beer_slurper' ); ?></em>
				<?php endif; ?>
			</dd>

			<?php if ( $last_error ) : ?>
				<dt><?php _e( 'Last Error', 'beer_slurper' ); ?></dt>
				<dd>
					<div class="beer-slurper-error">
						<strong><?php echo esc_html( $last_error['code'] ); ?>:</strong>
						<?php echo esc_html( $last_error['message'] ); ?>
					</div>
				</dd>
			<?php endif; ?>

			<dt><?php _e( 'Next Scheduled Sync', 'beer_slurper' ); ?></dt>
			<dd>
				<?php if ( $next_sync ) : ?>
					<?php
					$date_format = get_option( 'date_format' );
					$time_format = get_option( 'time_format' );
					$formatted_next = date_i18n( $date_format . ' ' . $time_format, $next_sync );
					?>
					<?php echo esc_html( $formatted_next ); ?>
				<?php elseif ( $user ) : ?>
					<div class="beer-slurper-warning">
						<?php
						printf(
							/* translators: %s: function name */
							__( 'Cron not scheduled. Run %s or check WP-Cron configuration.', 'beer_slurper' ),
							'<code>bs_start_import(\'' . esc_html( $user ) . '\')</code>'
						);
						?>
					</div>
				<?php else : ?>
					<em><?php _e( 'N/A - No user configured', 'beer_slurper' ); ?></em>
				<?php endif; ?>
			</dd>
		</dl>

		<h4><?php _e( 'Statistics', 'beer_slurper' ); ?></h4>
		<table class="beer-slurper-stats-table">
			<thead>
				<tr>
					<th><?php _e( 'Metric', 'beer_slurper' ); ?></th>
					<th><?php _e( 'Count', 'beer_slurper' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php _e( 'Total Beers', 'beer_slurper' ); ?></td>
					<td><?php echo number_format_i18n( $total_beers ); ?></td>
				</tr>
				<tr>
					<td><?php _e( 'Total Pictures', 'beer_slurper' ); ?></td>
					<td><?php echo number_format_i18n( $total_pictures ); ?></td>
				</tr>
				<tr>
					<td><?php _e( 'Total Breweries', 'beer_slurper' ); ?></td>
					<td><?php echo number_format_i18n( $total_breweries ); ?></td>
				</tr>
			</tbody>
		</table>

		<?php if ( $user ) : ?>
			<button type="button" id="beer-slurper-sync-now" class="button button-secondary">
				<?php _e( 'Sync Now', 'beer_slurper' ); ?>
			</button>
			<span id="beer-slurper-sync-message"></span>
			<?php wp_nonce_field( 'beer_slurper_sync_now', 'beer_slurper_sync_nonce' ); ?>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Enqueues admin JavaScript and CSS for the settings page.
 *
 * @param string $hook The current admin page hook.
 * @return void
 */
function enqueue_admin_assets( $hook ) {
	if ( 'settings_page_beer-slurper-settings' !== $hook ) {
		return;
	}

	wp_enqueue_script(
		'beer-slurper-admin-sync-status',
		BEER_SLURPER_URL . 'assets/js/admin-sync-status.js',
		array(),
		BEER_SLURPER_VERSION,
		true
	);

	wp_localize_script(
		'beer-slurper-admin-sync-status',
		'beerSlurperSyncStatus',
		array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'beer_slurper_sync_now' ),
			'strings' => array(
				'syncing'      => __( 'Syncing...', 'beer_slurper' ),
				'syncComplete' => __( 'Sync complete!', 'beer_slurper' ),
				'syncError'    => __( 'Sync failed:', 'beer_slurper' ),
			),
		)
	);
}

/**
 * AJAX handler for the Sync Now button.
 *
 * @return void
 */
function ajax_sync_now() {
	check_ajax_referer( 'beer_slurper_sync_now', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array(
			'message' => __( 'You do not have permission to perform this action.', 'beer_slurper' ),
		) );
	}

	$user = \Kraft\Beer_Slurper\Sync_Status\get_configured_user();

	if ( ! $user ) {
		wp_send_json_error( array(
			'message' => __( 'No Untappd user configured.', 'beer_slurper' ),
		) );
	}

	$result = \bs_import( $user );

	$last_error = \Kraft\Beer_Slurper\Sync_Status\get_last_sync_error();

	if ( $last_error ) {
		wp_send_json_error( array(
			'message' => $last_error['code'] . ': ' . $last_error['message'],
		) );
	}

	wp_send_json_success( array(
		'message' => __( 'Sync completed successfully.', 'beer_slurper' ),
	) );
}
