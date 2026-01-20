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

}

/**
 * Register the settings and whatnot.
 *
 * @return voice
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
