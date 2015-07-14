<?php
namespace Kraft\Beer_Slurper\Temp;

/* Temporary functions to prop up parts of the plugin until everything is fleshed out. */

/**
 * Sets up the settings that will eventually be added.
 *
 **/
function default_settings() {

	if ( defined( 'UNTAPPD_KEY' ) && defined( 'UNTAPPD_SECRET' ) ) {
		add_filter( 'pre_option_beer-slurper-key',    function() { return UNTAPPD_KEY; } );
		add_filter( 'pre_option_beer-slurper-secret', function() { return UNTAPPD_SECRET; } );
	}
}

add_action( 'beer_slurper_init', '\Kraft\Beer_Slurper\Temp\default_settings' );

define( 'UNTAPPD_DEV_MODE', false ); // true to kill Untappd API calls and use the sample responses in /dev/
