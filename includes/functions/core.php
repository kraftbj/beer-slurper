<?php
/**
 * Core Plugin Functions
 *
 * This file provides the core setup, initialization, and settings management
 * functions for the Beer Slurper plugin.
 *
 * @package Kraft\Beer_Slurper\Core
 */
namespace Kraft\Beer_Slurper\Core;

/**
 * Default setup routine.
 *
 * Registers all necessary action hooks for plugin initialization,
 * admin settings, and AJAX handlers.
 *
 * The anonymous function `$n` is a namespace helper that prefixes function
 * names with the current namespace for use in hook callbacks.
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

	// Trigger sync when username is set/changed (for non-OAuth users).
	add_action( 'update_option_beer-slurper-user', $n( 'on_username_updated' ), 10, 2 );
	add_action( 'add_option_beer-slurper-user', $n( 'on_username_added' ), 10, 2 );

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
	\Kraft\Beer_Slurper\Queue\cleanup();

	// Clear legacy WP-Cron hooks in case the site upgraded from an older version.
	wp_unschedule_hook( 'bs_hourly_importer' );
	wp_unschedule_hook( 'bs_daily_maintenance' );
}

/**
 * Registers the plugin settings, sections, and fields.
 *
 * The anonymous function `$n` is a namespace helper that prefixes function
 * names with the current namespace for use in settings field callbacks.
 *
 * @uses add_settings_section()
 * @uses add_settings_field()
 * @uses register_setting()
 * @uses __()
 *
 * @return void
 */
function setting_init() {
	$n = function( $function ) {
		return __NAMESPACE__ . "\\$function";
	};

	// Data Source section FIRST - determines what other sections to show.
	add_settings_section( 'data_source_settings', __( 'Data Source', 'beer_slurper' ), $n( 'data_source_section_callback' ), 'beer-slurper-settings' );

	add_settings_field( 'beer-slurper-data-source', __( 'Data Source Mode', 'beer_slurper' ), $n( 'setting_data_source' ), 'beer-slurper-settings', 'data_source_settings', array( 'label_for' => 'beer-slurper-data-source' ) );
	register_setting( 'beer-slurper-settings', 'beer_slurper_data_source', 'sanitize_text_field' );

	// Scraper-specific fields (RSS URL and Username) - shown only when scraper mode.
	add_settings_field( 'beer-slurper-rss-url', __( 'RSS Feed URL', 'beer_slurper' ), $n( 'setting_rss_url' ), 'beer-slurper-settings', 'data_source_settings', array( 'label_for' => 'beer-slurper-rss-url', 'class' => 'beer-slurper-scraper-field' ) );
	register_setting( 'beer-slurper-settings', 'beer_slurper_rss_url', array(
		'type'              => 'string',
		'sanitize_callback' => 'esc_url_raw',
	) );

	add_settings_field( 'beer-slurper-scraper-user', __( 'Untappd Username', 'beer_slurper' ), $n( 'setting_scraper_username' ), 'beer-slurper-settings', 'data_source_settings', array( 'label_for' => 'beer-slurper-scraper-user', 'class' => 'beer-slurper-scraper-field' ) );
	register_setting( 'beer-slurper-settings', 'beer-slurper-user', array(
		'type'              => 'string',
		'sanitize_callback' => 'sanitize_user',
	) );

	// API Settings section - shown only when API mode.
	add_settings_section( 'untappd_settings', __( 'API Settings', 'beer_slurper' ), null, 'beer-slurper-settings');

	add_settings_field( 'beer-slurper-key', __( 'Untappd Key', 'beer_slurper' ), $n( 'setting_key' ), 'beer-slurper-settings', 'untappd_settings', array( 'label_for' => 'beer-slurper-key' ) );
	register_setting( 'beer-slurper-settings', 'beer-slurper-key', 'strip_tags' );

	add_settings_field( 'beer-slurper-secret', __( 'Untappd Secret', 'beer_slurper' ), $n( 'setting_secret' ), 'beer-slurper-settings', 'untappd_settings', array( 'label_for' => 'beer-slurper-secret' ) );
	register_setting( 'beer-slurper-settings', 'beer-slurper-secret', 'strip_tags' );

	add_settings_field( 'beer-slurper-gallery', __( 'Auto-append Gallery', 'beer_slurper' ), $n( 'setting_gallery' ), 'beer-slurper-settings', 'untappd_settings', array( 'label_for' => 'beer-slurper-gallery' ) );
	register_setting( 'beer-slurper-settings', 'beer-slurper-gallery', 'boolval' );

	// Untappd Connection section - shown only when API mode.
	add_settings_section( 'untappd_connection', __( 'Untappd Connection', 'beer_slurper' ), $n( 'oauth_section_callback' ), 'beer-slurper-settings' );

	// Sync Status section - always visible.
	add_settings_section( 'sync_status_settings', __( 'Sync Status', 'beer_slurper' ), $n( 'sync_status_section_callback' ), 'beer-slurper-settings' );

	// API Rate Limit section - shown only when API mode.
	add_settings_section( 'api_rate_limit', __( 'API Rate Limit', 'beer_slurper' ), $n( 'rate_limit_section_callback' ), 'beer-slurper-settings' );

	// Import section - always visible.
	add_settings_section( 'import_settings', __( 'Import from Untappd Export', 'beer_slurper' ), $n( 'import_section_callback' ), 'beer-slurper-settings' );
}

/**
 * Sets up override of database settings with PHP constants.
 *
 * When UNTAPPD_KEY and UNTAPPD_SECRET constants are defined, this function
 * registers filters that return those constant values instead of database options.
 *
 * The anonymous functions registered as filters simply return the constant values,
 * effectively making the settings read-only when constants are defined.
 *
 * @uses add_filter()
 *
 * @return void
 */
function default_settings() {

	if ( defined( 'UNTAPPD_KEY' ) && defined( 'UNTAPPD_SECRET' ) ) {
		add_filter( 'pre_option_beer-slurper-key',    function() { return UNTAPPD_KEY; } );
		add_filter( 'pre_option_beer-slurper-secret', function() { return UNTAPPD_SECRET; } );
	}
}

/**
 * Adds Beer settings page to the admin menu.
 *
 * @uses add_options_page()
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
 * Renders the settings page HTML.
 *
 * @uses _e()
 * @uses settings_fields()
 * @uses do_settings_sections()
 * @uses submit_button()
 *
 * @return void
 */
function setting_page(){
	$current_source = get_option( 'beer_slurper_data_source', 'api' );
	?>
	<div class="wrap">
		<h2><?php _e( 'Beer Slurper Settings', 'beer_slurper' ); ?></h2>
		<form method="post" action="options.php"><?php
			settings_fields( 'beer-slurper-settings' );
			do_settings_sections( 'beer-slurper-settings' );
			submit_button(); ?>
		</form>
	</div>
	<script>
	jQuery(document).ready(function($) {
		function updateSectionsVisibility() {
			var selected = $('input[name="beer_slurper_data_source"]:checked').val() || '<?php echo esc_js( $current_source ); ?>';
			var isApi = (selected === 'api' || selected === 'hybrid');
			var isScraper = (selected === 'scraper' || selected === 'hybrid');

			// API-only sections: API Settings, Untappd Connection, API Rate Limit.
			$('#untappd_settings, #untappd_settings + table').toggle(isApi);
			$('#untappd_connection, #untappd_connection + div, #untappd_connection + p').toggle(isApi);
			$('#api_rate_limit, #api_rate_limit + div, #api_rate_limit + p').toggle(isApi);

			// Scraper-only fields: RSS URL and Username.
			$('.beer-slurper-scraper-field').toggle(isScraper);

			// Handle section headers (h2 elements in WP settings).
			$('h2').each(function() {
				var $h2 = $(this);
				var text = $h2.text().trim();

				if (text === '<?php echo esc_js( __( 'API Settings', 'beer_slurper' ) ); ?>' ||
				    text === '<?php echo esc_js( __( 'Untappd Connection', 'beer_slurper' ) ); ?>' ||
				    text === '<?php echo esc_js( __( 'API Rate Limit', 'beer_slurper' ) ); ?>') {
					$h2.toggle(isApi);
					// Also toggle the following table or content until next h2.
					$h2.nextUntil('h2').toggle(isApi);
				}
			});
		}

		// Initial visibility.
		updateSectionsVisibility();

		// Update on change.
		$(document).on('change', 'input[name="beer_slurper_data_source"]', updateSectionsVisibility);
	});
	</script>
	<?php
}

/**
 * Renders the Untappd Key setting form field.
 *
 * Displays a message if the key is defined via constant, otherwise
 * renders a text input field.
 *
 * @since 1.0.0
 *
 * @uses _e()
 * @uses esc_attr()
 * @uses get_option()
 *
 * @return void
 */
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
 * Renders the Untappd Secret setting form field.
 *
 * Displays a message if the secret is defined via constant, otherwise
 * renders a text input field.
 *
 * @since 1.0.0
 *
 * @uses _e()
 * @uses esc_attr()
 * @uses get_option()
 *
 * @return void
 */
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
 * Renders the Gallery auto-append setting form field.
 *
 * Displays a checkbox to enable automatic gallery shortcode appending
 * to beer posts.
 *
 * @uses get_option()
 * @uses checked()
 * @uses __()
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
 * Renders the Untappd Connection section content.
 *
 * Displays the OAuth connection status, connect/disconnect buttons,
 * and the redirect URL that must be registered in the Untappd app.
 *
 * @return void
 */
function oauth_section_callback() {
	$connected       = \Kraft\Beer_Slurper\OAuth\is_connected();
	$has_credentials = get_option( 'beer-slurper-key' ) && get_option( 'beer-slurper-secret' );
	$redirect_url    = \Kraft\Beer_Slurper\OAuth\get_redirect_url();
	$username        = \Kraft\Beer_Slurper\Sync_Status\get_configured_user();

	?>
	<div class="beer-slurper-oauth-status">
		<p>
			<strong><?php _e( 'Redirect URL:', 'beer_slurper' ); ?></strong><br />
			<code><?php echo esc_html( $redirect_url ); ?></code><br />
			<span class="description">
				<?php _e( 'Enter this URL as the Callback URL in your Untappd app settings at', 'beer_slurper' ); ?>
				<a href="https://untappd.com/api/dashboard" target="_blank">untappd.com/api/dashboard</a>.
			</span>
		</p>

		<?php if ( $connected ) : ?>
			<p class="beer-slurper-success">
				<strong>
					<?php
					if ( $username ) {
						printf(
							/* translators: %s: Untappd username */
							__( 'Status: Connected as %s', 'beer_slurper' ),
							esc_html( $username )
						);
					} else {
						_e( 'Status: Connected', 'beer_slurper' );
					}
					?>
				</strong>
			</p>
			<?php
			$disconnect_url = wp_nonce_url(
				add_query_arg(
					array(
						'page'                      => 'beer-slurper-settings',
						'beer-slurper-disconnect'    => '1',
					),
					admin_url( 'options-general.php' )
				),
				'beer_slurper_disconnect'
			);
			?>
			<a href="<?php echo esc_url( $disconnect_url ); ?>" class="button button-secondary">
				<?php _e( 'Disconnect from Untappd', 'beer_slurper' ); ?>
			</a>
		<?php elseif ( $has_credentials ) : ?>
			<p>
				<strong><?php _e( 'Status: Not connected', 'beer_slurper' ); ?></strong>
			</p>
			<a href="<?php echo esc_url( \Kraft\Beer_Slurper\OAuth\get_authorize_url() ); ?>" class="button button-primary">
				<?php _e( 'Connect with Untappd', 'beer_slurper' ); ?>
			</a>
		<?php else : ?>
			<p>
				<em><?php _e( 'Enter your Untappd API Key and Secret above, then save settings to enable OAuth connection.', 'beer_slurper' ); ?></em>
			</p>
		<?php endif; ?>

		<?php if ( isset( $_GET['beer-slurper-oauth-error'] ) ) : ?>
			<div class="beer-slurper-error" style="margin-top: 10px;">
				<?php _e( 'OAuth connection failed. Please try again.', 'beer_slurper' ); ?>
				<br><small><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['beer-slurper-oauth-error'] ) ) ); ?></small>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Renders the Sync Status section content.
 *
 * Displays sync status information including configured user, sync state,
 * last sync time, errors, next scheduled sync, and statistics (total beers,
 * pictures, and breweries). Also provides a "Sync Now" button for manual sync.
 *
 * @uses _e()
 * @uses esc_html()
 * @uses get_option()
 * @uses date_i18n()
 * @uses number_format_i18n()
 * @uses printf()
 * @uses __()
 * @uses wp_nonce_field()
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
	$local_checkins = \Kraft\Beer_Slurper\Sync_Status\get_total_checkins();
	$untappd_checkins = \Kraft\Beer_Slurper\Sync_Status\get_untappd_total_checkins();

	// Check for active import or backfill operations.
	$import_progress = \Kraft\Beer_Slurper\Import\get_import_progress();
	$backfill_pct = ( $untappd_checkins > 0 ) ? min( 100, round( ( $local_checkins / $untappd_checkins ) * 100 ) ) : 0;

	// Check for pending import batches to determine if import is truly active.
	$pending_import_batches = 0;
	if ( $import_progress && function_exists( 'as_get_scheduled_actions' ) ) {
		$pending = as_get_scheduled_actions( array(
			'hook'   => 'beer_slurper_process_import_batch',
			'status' => \ActionScheduler_Store::STATUS_PENDING,
			'group'  => 'beer-slurper',
		), 'ids' );
		$pending_import_batches = count( $pending );
	}

	?>
	<style>
		.beer-slurper-sync-status { margin-top: 10px; }
		.beer-slurper-sync-status dl { margin: 0; }
		.beer-slurper-sync-status dt { font-weight: 600; margin-top: 10px; }
		.beer-slurper-sync-status dd { margin-left: 0; margin-bottom: 5px; }
		.beer-slurper-error { color: #d63638; background: #fcf0f1; border-left: 4px solid #d63638; padding: 10px; margin: 10px 0; }
		.beer-slurper-warning { color: #996800; background: #fcf9e8; border-left: 4px solid #dba617; padding: 10px; margin: 10px 0; }
		.beer-slurper-success { color: #00a32a; }
		.beer-slurper-stats-table { border-collapse: collapse; margin: 10px 0; }
		.beer-slurper-stats-table th,
		.beer-slurper-stats-table td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #c3c4c7; }
		.beer-slurper-stats-table th { background: #f0f0f1; }
		#beer-slurper-sync-now { margin-top: 15px; }
		#beer-slurper-sync-message { margin-left: 10px; display: inline-block; }
	</style>

	<div class="beer-slurper-sync-status">
		<?php // Active operation banners - Import takes priority over backfill display. ?>
		<?php if ( $import_progress && $import_progress['total'] > 0 ) : ?>
			<?php
			// Use actual DB count if higher than tracked (handles tracking sync issues).
			$tracked_processed = $import_progress['processed'] ?? 0;
			$display_processed = max( $tracked_processed, $local_checkins );

			// Complete if no pending batches AND (tracked OR actual count) meets total.
			$is_import_complete = 0 === $pending_import_batches && (
				$tracked_processed >= $import_progress['total'] ||
				$local_checkins >= $import_progress['total']
			);
			?>
			<div class="notice <?php echo $is_import_complete ? 'notice-success' : 'notice-info'; ?>" id="beer-slurper-import-banner">
				<p id="beer-slurper-import-status">
					<?php
					if ( $is_import_complete ) {
						printf(
							/* translators: %s: total checkins imported */
							__( 'Import complete. %s checkins imported.', 'beer_slurper' ),
							number_format_i18n( $local_checkins )
						);
					} else {
						printf(
							/* translators: 1: processed count, 2: total count, 3: pending batches */
							__( 'Import in progress: %1$s of %2$s checkins processed. %3$d batches remaining.', 'beer_slurper' ),
							number_format_i18n( $display_processed ),
							number_format_i18n( $import_progress['total'] ),
							$pending_import_batches
						);
					}
					?>
					<?php if ( $is_import_complete ) : ?>
						<button type="button" class="button button-link" id="beer-slurper-dismiss-import" style="margin-left: 10px;">
							<?php _e( 'Dismiss', 'beer_slurper' ); ?>
						</button>
					<?php endif; ?>
				</p>
			</div>
		<?php elseif ( $is_backfilling ) : ?>
			<div class="notice notice-info" id="beer-slurper-backfill-banner">
				<p id="beer-slurper-backfill-status">
					<?php
					if ( $untappd_checkins > 0 ) {
						printf(
							/* translators: 1: local count, 2: untappd total */
							__( 'Backfilling history: %1$s of %2$s checkins imported from Untappd.', 'beer_slurper' ),
							number_format_i18n( $local_checkins ),
							number_format_i18n( $untappd_checkins )
						);
					} else {
						printf(
							/* translators: %s: local checkin count */
							__( 'Backfilling history: %s checkins imported so far.', 'beer_slurper' ),
							number_format_i18n( $local_checkins )
						);
					}
					?>
				</p>
			</div>
		<?php endif; ?>

		<dl>
			<dt><?php _e( 'Untappd User', 'beer_slurper' ); ?></dt>
			<dd>
				<?php if ( $user ) : ?>
					<strong><?php echo esc_html( $user ); ?></strong>
				<?php else : ?>
					<em><?php _e( 'Not configured', 'beer_slurper' ); ?></em>
				<?php endif; ?>
			</dd>

			<dt><?php _e( 'Status', 'beer_slurper' ); ?></dt>
			<dd>
				<?php if ( ! $user ) : ?>
					<em><?php _e( 'No user configured', 'beer_slurper' ); ?></em>
				<?php elseif ( $import_progress && $import_progress['total'] > 0 && ! $is_import_complete ) : ?>
					<span style="color: #996800;"><?php _e( 'Import running...', 'beer_slurper' ); ?></span>
				<?php elseif ( $is_backfilling ) : ?>
					<span style="color: #2271b1;"><?php _e( 'Backfilling...', 'beer_slurper' ); ?></span>
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
					$formatted_date = wp_date( $date_format . ' ' . $time_format, $last_sync );
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
					$formatted_next = wp_date( $date_format . ' ' . $time_format, $next_sync );
					?>
					<?php echo esc_html( $formatted_next ); ?>
				<?php elseif ( $user ) : ?>
					<div class="beer-slurper-warning">
						<?php _e( 'Sync not scheduled. Click "Sync Now" to restore.', 'beer_slurper' ); ?>
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
				<tr>
					<td><?php _e( 'Total Checkins', 'beer_slurper' ); ?></td>
					<td>
						<?php
						echo number_format_i18n( $local_checkins );
						if ( $untappd_checkins > 0 ) {
							printf( ' / %s', number_format_i18n( $untappd_checkins ) );
						}
						?>
					</td>
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

	<script>
	jQuery(document).ready(function($) {
		// Dismiss import banner.
		$('#beer-slurper-dismiss-import').on('click', function() {
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'beer_slurper_clear_import_progress',
					nonce: $('#beer_slurper_sync_nonce').val()
				},
				success: function() {
					$('#beer-slurper-import-banner').slideUp();
				}
			});
		});

		// Poll for import/backfill progress updates.
		var pollInterval = null;
		var hasBanner = $('#beer-slurper-import-banner').length || $('#beer-slurper-backfill-banner').length;

		if (hasBanner) {
			pollInterval = setInterval(function() {
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'beer_slurper_import_progress',
						nonce: $('#beer_slurper_sync_nonce').val()
					},
					success: function(response) {
						if (response.success && response.data.active) {
							var pct = response.data.total > 0 ? Math.round((response.data.processed / response.data.total) * 100) : 0;
							$('#beer-slurper-import-fill').css('width', pct + '%');
							$('#beer-slurper-import-status').text(
								'<?php echo esc_js( __( 'Processed', 'beer_slurper' ) ); ?> ' + response.data.processed +
								' <?php echo esc_js( __( 'of', 'beer_slurper' ) ); ?> ' + response.data.total +
								' <?php echo esc_js( __( 'checkins', 'beer_slurper' ) ); ?> (' +
								response.data.imported + ' <?php echo esc_js( __( 'imported', 'beer_slurper' ) ); ?>, ' +
								response.data.skipped + ' <?php echo esc_js( __( 'skipped', 'beer_slurper' ) ); ?>)'
							);
						} else if (response.success && response.data.complete) {
							$('#beer-slurper-import-banner').removeClass('import backfill').addClass('complete');
							$('#beer-slurper-import-banner strong').text('<?php echo esc_js( __( 'Import Complete', 'beer_slurper' ) ); ?>');
							$('#beer-slurper-import-fill').css('width', '100%');
							if (!$('#beer-slurper-dismiss-import').length) {
								$('#beer-slurper-import-banner').append(
									'<button type="button" class="button dismiss-btn" id="beer-slurper-dismiss-import"><?php echo esc_js( __( 'Dismiss', 'beer_slurper' ) ); ?></button>'
								);
							}
							clearInterval(pollInterval);
						}
					}
				});
			}, 5000);
		}
	});
	</script>
	<?php
}

/**
 * Renders the API Rate Limit settings section.
 *
 * Displays current API budget usage, pending Action Scheduler jobs,
 * and scheduled action counts so the admin can see at a glance how
 * the rate limit conductor is managing API calls.
 *
 * @return void
 */
function rate_limit_section_callback() {
	$budget    = \Kraft\Beer_Slurper\Queue\API_BUDGET_PER_HOUR;
	$remaining = \Kraft\Beer_Slurper\Queue\get_remaining_budget();
	$used      = $budget - $remaining;
	$pct_used  = $budget > 0 ? round( ( $used / $budget ) * 100 ) : 0;

	// Count pending Action Scheduler jobs by hook.
	$pending_counts = array();
	if ( function_exists( 'as_get_scheduled_actions' ) ) {
		$hooks = array(
			'bs_process_checkin'              => __( 'Checkin imports', 'beer_slurper' ),
			'bs_prime_queue_page'             => __( 'Prime queue pages', 'beer_slurper' ),
			'bs_backfill_toast'               => __( 'Toast backfills', 'beer_slurper' ),
			'bs_maintenance_stats'            => __( 'Stats refresh', 'beer_slurper' ),
			'bs_maintenance_brewery_backfill' => __( 'Brewery backfills', 'beer_slurper' ),
			'bs_maintenance_venue_backfill'   => __( 'Venue backfills', 'beer_slurper' ),
			'bs_maintenance_badge_backfill'   => __( 'Badge backfills', 'beer_slurper' ),
		);

		foreach ( $hooks as $hook => $label ) {
			$actions = as_get_scheduled_actions( array(
				'hook'     => $hook,
				'status'   => \ActionScheduler_Store::STATUS_PENDING,
				'group'    => \Kraft\Beer_Slurper\Queue\AS_GROUP,
				'per_page' => 0,
			), 'ids' );

			$count = count( $actions );
			if ( $count > 0 ) {
				$pending_counts[ $label ] = $count;
			}
		}
	}

	$total_pending = array_sum( $pending_counts );

	// Determine bar color based on usage.
	if ( $pct_used >= 90 ) {
		$bar_color = '#d63638';
	} elseif ( $pct_used >= 70 ) {
		$bar_color = '#dba617';
	} else {
		$bar_color = '#00a32a';
	}

	?>
	<style>
		.beer-slurper-rate-limit { margin-top: 10px; }
		.beer-slurper-rate-limit dl { margin: 0; }
		.beer-slurper-rate-limit dt { font-weight: 600; margin-top: 10px; }
		.beer-slurper-rate-limit dd { margin-left: 0; margin-bottom: 5px; }
		.beer-slurper-budget-bar {
			width: 300px;
			height: 20px;
			background: #f0f0f1;
			border: 1px solid #c3c4c7;
			border-radius: 3px;
			overflow: hidden;
			display: inline-block;
			vertical-align: middle;
		}
		.beer-slurper-budget-bar-fill {
			height: 100%;
			transition: width 0.3s ease;
		}
		.beer-slurper-budget-text {
			display: inline-block;
			margin-left: 8px;
			vertical-align: middle;
		}
		.beer-slurper-pending-list { margin: 5px 0 0 0; padding: 0; list-style: none; }
		.beer-slurper-pending-list li {
			display: inline-block;
			background: #f0f0f1;
			border: 1px solid #c3c4c7;
			border-radius: 3px;
			padding: 2px 8px;
			margin: 2px 4px 2px 0;
			font-size: 13px;
		}
		.beer-slurper-pending-list li strong { font-variant-numeric: tabular-nums; }
	</style>

	<div class="beer-slurper-rate-limit">
		<dl>
			<dt><?php _e( 'Hourly Budget', 'beer_slurper' ); ?></dt>
			<dd>
				<div class="beer-slurper-budget-bar">
					<div class="beer-slurper-budget-bar-fill" style="width: <?php echo esc_attr( $pct_used ); ?>%; background-color: <?php echo esc_attr( $bar_color ); ?>;"></div>
				</div>
				<span class="beer-slurper-budget-text">
					<?php
					printf(
						/* translators: 1: used calls, 2: total budget, 3: remaining calls */
						__( '%1$s / %2$s used (%3$s remaining)', 'beer_slurper' ),
						'<strong>' . number_format_i18n( $used ) . '</strong>',
						number_format_i18n( $budget ),
						'<strong>' . number_format_i18n( $remaining ) . '</strong>'
					);
					?>
				</span>
			</dd>

			<dt><?php _e( 'Pending Jobs', 'beer_slurper' ); ?></dt>
			<dd>
				<?php if ( $total_pending > 0 ) : ?>
					<?php
					printf(
						/* translators: %s: total pending job count */
						__( '%s queued actions:', 'beer_slurper' ),
						'<strong>' . number_format_i18n( $total_pending ) . '</strong>'
					);
					?>
					<ul class="beer-slurper-pending-list">
						<?php foreach ( $pending_counts as $label => $count ) : ?>
							<li><?php echo esc_html( $label ); ?>: <strong><?php echo number_format_i18n( $count ); ?></strong></li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<em><?php _e( 'No pending jobs', 'beer_slurper' ); ?></em>
				<?php endif; ?>
			</dd>

			<dt><?php _e( 'Untappd Limit', 'beer_slurper' ); ?></dt>
			<dd>
				<?php
				printf(
					/* translators: 1: actual limit, 2: reserved count, 3: usable budget */
					__( '%1$s calls/hour (Untappd allows 100; %2$s reserved for overhead, %3$s usable)', 'beer_slurper' ),
					number_format_i18n( $budget ),
					number_format_i18n( 100 - $budget ),
					number_format_i18n( $budget )
				);
				?>
			</dd>
		</dl>
	</div>
	<?php
}

/**
 * Enqueues admin JavaScript and CSS for the settings page.
 *
 * Only loads assets on the Beer Slurper settings page. Localizes the script
 * with AJAX URL, nonce, and translatable strings.
 *
 * @param string $hook The current admin page hook.
 *
 * @uses wp_enqueue_script()
 * @uses wp_localize_script()
 * @uses admin_url()
 * @uses wp_create_nonce()
 * @uses __()
 *
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
 * Handles the AJAX request for the Sync Now button.
 *
 * Verifies the nonce, checks user capabilities, triggers the import,
 * and returns a JSON response indicating success or failure.
 *
 * @uses check_ajax_referer()
 * @uses current_user_can()
 * @uses wp_send_json_error()
 * @uses wp_send_json_success()
 * @uses __()
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

	// Restore scheduled actions if they're missing.
	\Kraft\Beer_Slurper\Queue\init_scheduled_actions( $user );

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

/**
 * Renders the Data Source settings section.
 *
 * Explains the different data source options and their tradeoffs.
 *
 * @return void
 */
function data_source_section_callback() {
	?>
	<p class="description">
		<?php _e( 'Choose how Beer Slurper fetches data from Untappd.', 'beer_slurper' ); ?>
	</p>
	<?php
}

/**
 * Renders the Data Source Mode setting field.
 *
 * @return void
 */
function setting_data_source() {
	$current = get_option( 'beer_slurper_data_source', 'api' );

	// Allow filter to enable hybrid mode (hidden by default).
	$show_hybrid = apply_filters( 'beer_slurper_show_hybrid_mode', false );

	// If currently set to hybrid but hybrid is hidden, treat as scraper for display.
	$display_current = $current;
	if ( 'hybrid' === $current && ! $show_hybrid ) {
		$display_current = 'scraper';
	}

	$options = array(
		'api'     => array(
			'label'       => __( 'Untappd API', 'beer_slurper' ),
			'description' => __( 'Full data including badges, companions, and detailed metadata. Requires API credentials.', 'beer_slurper' ),
		),
		'scraper' => array(
			'label'       => __( 'RSS Feed / Scraper', 'beer_slurper' ),
			'description' => __( 'Works without API credentials using your RSS feed. Limited to recent checkins (~25) and basic data.', 'beer_slurper' ),
		),
	);

	// Add hybrid option if enabled via filter.
	if ( $show_hybrid ) {
		$options = array_slice( $options, 0, 1, true )
			+ array(
				'hybrid' => array(
					'label'       => __( 'API + Scraper Fallback', 'beer_slurper' ),
					'description' => __( 'Uses API when available, falls back to scraping if API fails.', 'beer_slurper' ),
				),
			)
			+ array_slice( $options, 1, null, true );
	}

	echo '<fieldset id="beer-slurper-data-source-options">';

	foreach ( $options as $value => $option ) {
		$checked = checked( $display_current, $value, false );

		printf(
			'<label style="display: block; margin-bottom: 10px;">
				<input type="radio" name="beer_slurper_data_source" value="%s"%s class="beer-slurper-data-source-radio" />
				<strong>%s</strong>
				<br /><span class="description" style="margin-left: 24px;">%s</span>
			</label>',
			esc_attr( $value ),
			$checked,
			esc_html( $option['label'] ),
			esc_html( $option['description'] )
		);
	}

	echo '</fieldset>';
}

/**
 * Renders the RSS Feed URL setting field.
 *
 * @return void
 */
function setting_rss_url() {
	$rss_url = get_option( 'beer_slurper_rss_url', '' );
	$is_valid = ! empty( $rss_url ) && \Kraft\Beer_Slurper\Scraper\is_valid_rss_url( $rss_url );

	?>
	<input
		type="url"
		id="beer-slurper-rss-url"
		name="beer_slurper_rss_url"
		value="<?php echo esc_attr( $rss_url ); ?>"
		class="regular-text"
		placeholder="https://untappd.com/rss/user/USERNAME?key=YOUR_KEY"
	/>

	<?php if ( $rss_url && $is_valid ) : ?>
		<span style="color: #00a32a; margin-left: 8px;">✓ <?php _e( 'Valid RSS URL', 'beer_slurper' ); ?></span>
	<?php elseif ( $rss_url && ! $is_valid ) : ?>
		<span style="color: #d63638; margin-left: 8px;">✗ <?php _e( 'Invalid RSS URL format', 'beer_slurper' ); ?></span>
	<?php endif; ?>

	<p class="description" style="margin-top: 8px;">
		<?php
		printf(
			/* translators: %s: Link to Untappd settings */
			__( 'Find your personal RSS feed URL at %s under "RSS Private Feed URL". It includes a unique key for your account.', 'beer_slurper' ),
			'<a href="https://untappd.com/account/settings" target="_blank">untappd.com/account/settings</a>'
		);
		?>
	</p>
	<p class="description">
		<strong><?php _e( 'Why RSS?', 'beer_slurper' ); ?></strong>
		<?php _e( 'RSS is an official Untappd feature for accessing your checkins. Using RSS instead of scraping web pages is more reliable and respectful to Untappd\'s servers.', 'beer_slurper' ); ?>
	</p>
	<?php
}

/**
 * Renders the Untappd Username setting field for scraper/RSS mode.
 *
 * This field is required when using scraper or hybrid mode without OAuth.
 * If OAuth is connected, the username is automatically set from the API.
 *
 * @return void
 */
function setting_scraper_username() {
	$username      = get_option( 'beer-slurper-user', '' );
	$is_connected  = \Kraft\Beer_Slurper\OAuth\is_connected();
	$data_source   = get_option( 'beer_slurper_data_source', 'api' );
	$is_scraper    = in_array( $data_source, array( 'scraper', 'hybrid' ), true );

	?>
	<input
		type="text"
		id="beer-slurper-scraper-user"
		name="beer-slurper-user"
		value="<?php echo esc_attr( $username ); ?>"
		class="regular-text"
		placeholder="your_untappd_username"
		<?php echo $is_connected ? 'readonly' : ''; ?>
	/>

	<?php if ( $is_connected ) : ?>
		<span class="description" style="margin-left: 8px;">
			<?php _e( '(Set automatically via OAuth)', 'beer_slurper' ); ?>
		</span>
	<?php elseif ( $username ) : ?>
		<span style="color: #00a32a; margin-left: 8px;">&#10003;</span>
	<?php endif; ?>

	<p class="description" style="margin-top: 8px;">
		<?php _e( 'Your Untappd username. Required for RSS/scraper mode when not using OAuth.', 'beer_slurper' ); ?>
	</p>

	<?php if ( ! $username && $is_scraper && ! $is_connected ) : ?>
		<p class="description" style="color: #d63638;">
			<strong><?php _e( 'Username required:', 'beer_slurper' ); ?></strong>
			<?php _e( 'Please enter your Untappd username to enable syncing.', 'beer_slurper' ); ?>
		</p>
	<?php endif; ?>
	<?php
}

/**
 * Handles username option being updated.
 *
 * Triggers initial sync and sets up scheduled actions when the username
 * is changed (for non-OAuth users using scraper/RSS mode).
 *
 * @param mixed $old_value The old option value.
 * @param mixed $new_value The new option value.
 *
 * @return void
 */
function on_username_updated( $old_value, $new_value ) {
	// Only trigger if username actually changed and is not empty.
	if ( empty( $new_value ) || $old_value === $new_value ) {
		return;
	}

	// Don't trigger if OAuth is connected (OAuth handles its own setup).
	if ( \Kraft\Beer_Slurper\OAuth\is_connected() ) {
		return;
	}

	$username = sanitize_user( $new_value );

	// Set up scheduled actions for this user.
	\Kraft\Beer_Slurper\Queue\init_scheduled_actions( $username );

	// Start the import process.
	\bs_start_import( $username );
}

/**
 * Handles username option being added for the first time.
 *
 * @param string $option The option name.
 * @param mixed  $value  The option value.
 *
 * @return void
 */
function on_username_added( $option, $value ) {
	if ( empty( $value ) ) {
		return;
	}

	// Don't trigger if OAuth is connected.
	if ( \Kraft\Beer_Slurper\OAuth\is_connected() ) {
		return;
	}

	$username = sanitize_user( $value );

	// Set up scheduled actions for this user.
	\Kraft\Beer_Slurper\Queue\init_scheduled_actions( $username );

	// Start the import process.
	\bs_start_import( $username );
}

/**
 * Renders the Import section for uploading Untappd export files.
 *
 * @return void
 */
function import_section_callback() {
	?>
	<style>
		.beer-slurper-import { margin-top: 10px; }
		.beer-slurper-import-dropzone {
			border: 2px dashed #c3c4c7;
			border-radius: 4px;
			padding: 30px;
			text-align: center;
			background: #f9f9f9;
			margin-bottom: 15px;
			transition: all 0.2s ease;
		}
		.beer-slurper-import-dropzone.dragover {
			border-color: #2271b1;
			background: #f0f6fc;
		}
		.beer-slurper-import-dropzone input[type="file"] {
			display: none;
		}
		.beer-slurper-import-dropzone label {
			cursor: pointer;
			color: #2271b1;
			text-decoration: underline;
		}
		.beer-slurper-import-progress {
			display: none;
			margin-top: 15px;
		}
		.beer-slurper-import-progress .progress-bar {
			width: 100%;
			height: 20px;
			background: #f0f0f1;
			border-radius: 3px;
			overflow: hidden;
		}
		.beer-slurper-import-progress .progress-bar-fill {
			height: 100%;
			background: #2271b1;
			width: 0%;
			transition: width 0.3s ease;
		}
		.beer-slurper-import-results {
			margin-top: 15px;
			padding: 15px;
			border-radius: 4px;
			display: none;
		}
		.beer-slurper-import-results.success {
			background: #d1e7dd;
			border: 1px solid #a3cfbb;
		}
		.beer-slurper-import-results.error {
			background: #f8d7da;
			border: 1px solid #f5c2c7;
		}
	</style>

	<div class="beer-slurper-import">
		<p class="description">
			<?php
			printf(
				/* translators: %s: Link to Untappd settings */
				__( 'Import your checkin history from an Untappd data export. You can request your data from %s (Untappd Insider) or via a GDPR data request.', 'beer_slurper' ),
				'<a href="https://untappd.com/user" target="_blank">untappd.com/user → Account Settings</a>'
			);
			?>
		</p>

		<div class="beer-slurper-import-dropzone" id="beer-slurper-dropzone">
			<p>
				<?php _e( 'Drag and drop your Untappd export file here, or', 'beer_slurper' ); ?>
				<label for="beer-slurper-import-file"><?php _e( 'browse to select a file', 'beer_slurper' ); ?></label>
			</p>
			<p class="description"><?php _e( 'Supported formats: CSV, JSON', 'beer_slurper' ); ?></p>
			<input type="file" id="beer-slurper-import-file" accept=".csv,.json" />
		</div>

		<div class="beer-slurper-import-progress" id="beer-slurper-import-progress">
			<p><?php _e( 'Importing...', 'beer_slurper' ); ?> <span id="beer-slurper-import-status"></span></p>
			<div class="progress-bar">
				<div class="progress-bar-fill" id="beer-slurper-progress-fill"></div>
			</div>
		</div>

		<div class="beer-slurper-import-results" id="beer-slurper-import-results">
			<p id="beer-slurper-import-message"></p>
			<div id="beer-slurper-import-errors" style="margin-top: 10px; font-size: 12px;"></div>
		</div>

		<?php wp_nonce_field( 'beer_slurper_import', 'beer_slurper_import_nonce' ); ?>
	</div>

	<script>
	if ( typeof ajaxurl === 'undefined' ) {
		var ajaxurl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
	}
	jQuery(document).ready(function($) {
		var dropzone = $('#beer-slurper-dropzone');
		var fileInput = $('#beer-slurper-import-file');
		var progress = $('#beer-slurper-import-progress');
		var results = $('#beer-slurper-import-results');
		var progressFill = $('#beer-slurper-progress-fill');
		var statusText = $('#beer-slurper-import-status');
		var messageEl = $('#beer-slurper-import-message');
		var errorsEl = $('#beer-slurper-import-errors');

		// Drag and drop handling
		dropzone.on('dragover dragenter', function(e) {
			e.preventDefault();
			e.stopPropagation();
			$(this).addClass('dragover');
		});

		dropzone.on('dragleave dragend drop', function(e) {
			e.preventDefault();
			e.stopPropagation();
			$(this).removeClass('dragover');
		});

		dropzone.on('drop', function(e) {
			var files = e.originalEvent.dataTransfer.files;
			if (files.length) {
				handleFile(files[0]);
			}
		});

		fileInput.on('change', function() {
			if (this.files.length) {
				handleFile(this.files[0]);
			}
		});

		function handleFile(file) {
			if (typeof ajaxurl === 'undefined') {
				console.error('Beer Slurper: ajaxurl not defined');
				results.removeClass('success').addClass('error').show();
				messageEl.text('<?php echo esc_js( __('Configuration error. Please reload the page.', 'beer_slurper' ) ); ?>');
				return;
			}
			var formData = new FormData();
			formData.append('action', 'beer_slurper_import');
			formData.append('nonce', $('#beer_slurper_import_nonce').val());
			formData.append('import_file', file);

			progress.show();
			results.hide();
			progressFill.css('width', '10%');
			statusText.text('<?php echo esc_js( __( 'Uploading...', 'beer_slurper' ) ); ?>');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				xhr: function() {
					var xhr = new window.XMLHttpRequest();
					xhr.upload.addEventListener('progress', function(e) {
						if (e.lengthComputable) {
							var pct = Math.round((e.loaded / e.total) * 50);
							progressFill.css('width', pct + '%');
						}
					});
					return xhr;
				},
				success: function(response) {
					progressFill.css('width', '100%');

					if (response.success) {
						results.removeClass('error').addClass('success').show();
						messageEl.text(response.data.message);

						if (response.data.errors && response.data.errors.length) {
							var warningsList = $('<ul style="margin: 5px 0 0 20px;"></ul>');
							response.data.errors.forEach(function(e) {
								$('<li></li>').text(e).appendTo(warningsList);
							});
							errorsEl.empty().append($('<strong></strong>').text('<?php echo esc_js( __( 'Warnings:', 'beer_slurper' ) ); ?>')).append(warningsList);
						} else {
							errorsEl.html('');
						}

						// Start polling if items were queued for background processing
						if (response.data.queued && response.data.queued > 0) {
							$(document).trigger('beer_slurper_import_queued');
						}
					} else {
						results.removeClass('success').addClass('error').show();
						messageEl.text(response.data.message || '<?php echo esc_js( __( 'Import failed.', 'beer_slurper' ) ); ?>');
						errorsEl.html('');
					}

					progress.hide();
				},
				error: function() {
					progress.hide();
					results.removeClass('success').addClass('error').show();
					messageEl.text('<?php echo esc_js( __( 'An error occurred during import.', 'beer_slurper' ) ); ?>');
				}
			});
		}

		// Background import progress polling
		var bgProgress = $('#beer-slurper-bg-progress');
		var bgStatus = $('#beer-slurper-bg-status');
		var bgFill = $('#beer-slurper-bg-fill');
		var dismissBtn = $('#beer-slurper-dismiss-progress');
		var pollInterval = null;

		function pollImportProgress() {
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'beer_slurper_import_progress',
					nonce: $('#beer_slurper_import_nonce').val()
				},
				success: function(response) {
					if (!response.success || !response.data.active) {
						// No active import or complete
						if (response.data && response.data.complete) {
							bgProgress.addClass('complete');
							bgStatus.html(
								'<?php echo esc_js( __( 'Import complete:', 'beer_slurper' ) ); ?> ' +
								response.data.imported + ' <?php echo esc_js( __( 'imported', 'beer_slurper' ) ); ?>, ' +
								response.data.skipped + ' <?php echo esc_js( __( 'skipped', 'beer_slurper' ) ); ?>'
							);
							bgFill.css('width', '100%');
							dismissBtn.show();
						}
						if (pollInterval) {
							clearInterval(pollInterval);
							pollInterval = null;
						}
						return;
					}

					// Update progress display
					var pct = response.data.total > 0 ? Math.round((response.data.processed / response.data.total) * 100) : 0;
					bgFill.css('width', pct + '%');
					bgStatus.html(
						'<?php echo esc_js( __( 'Processed', 'beer_slurper' ) ); ?> ' + response.data.processed +
						' <?php echo esc_js( __( 'of', 'beer_slurper' ) ); ?> ' + response.data.total +
						' <?php echo esc_js( __( 'checkins', 'beer_slurper' ) ); ?> (' +
						response.data.imported + ' <?php echo esc_js( __( 'imported', 'beer_slurper' ) ); ?>, ' +
						response.data.skipped + ' <?php echo esc_js( __( 'skipped', 'beer_slurper' ) ); ?>)' +
						(response.data.pending_batches > 0 ? ' — ' + response.data.pending_batches + ' <?php echo esc_js( __( 'batches queued', 'beer_slurper' ) ); ?>' : '')
					);
				}
			});
		}

		// Start polling if there's an active import
		if (bgProgress.length) {
			pollImportProgress(); // Immediate check
			pollInterval = setInterval(pollImportProgress, 5000); // Poll every 5 seconds
		}

		// Dismiss button handler
		dismissBtn.on('click', function() {
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'beer_slurper_clear_import_progress',
					nonce: $('#beer_slurper_import_nonce').val()
				},
				success: function() {
					bgProgress.slideUp();
				}
			});
		});

		// Start polling after queuing a new import
		function startProgressPolling() {
			if (bgProgress.length === 0) {
				// Create the progress element if it doesn't exist
				var progressHtml = '<div class="beer-slurper-bg-progress" id="beer-slurper-bg-progress">' +
					'<strong><?php echo esc_js( __( 'Import in Progress', 'beer_slurper' ) ); ?></strong>' +
					'<p id="beer-slurper-bg-status"><?php echo esc_js( __( 'Starting...', 'beer_slurper' ) ); ?></p>' +
					'<div class="progress-bar"><div class="progress-bar-fill" id="beer-slurper-bg-fill" style="width: 0%;"></div></div>' +
					'<button type="button" class="button dismiss-btn" id="beer-slurper-dismiss-progress" style="display: none;"><?php echo esc_js( __( 'Dismiss', 'beer_slurper' ) ); ?></button>' +
					'</div>';
				$('.beer-slurper-import .description').first().before(progressHtml);
				bgProgress = $('#beer-slurper-bg-progress');
				bgStatus = $('#beer-slurper-bg-status');
				bgFill = $('#beer-slurper-bg-fill');
				dismissBtn = $('#beer-slurper-dismiss-progress');
				dismissBtn.on('click', function() {
					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'beer_slurper_clear_import_progress',
							nonce: $('#beer_slurper_import_nonce').val()
						},
						success: function() {
							bgProgress.slideUp();
						}
					});
				});
			}
			bgProgress.removeClass('complete').show();
			dismissBtn.hide();
			if (!pollInterval) {
				pollInterval = setInterval(pollImportProgress, 5000);
			}
			pollImportProgress();
		}

		// Hook into successful import queue response
		$(document).on('beer_slurper_import_queued', startProgressPolling);
	});
	</script>
	<?php
}
