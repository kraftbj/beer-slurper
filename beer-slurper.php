<?php
/**
 * Plugin Name: Beer Slurper
 * Plugin URI:  https://kraft.im/
 * Description: Slurp data from Untappd into your site!
 * Version:     0.1.0
 * Author:      Brandon Kraft
 * Author URI:  https://kraft.im/
 * License:     GPLv2+
 * Text Domain: beer_slurper
 * Domain Path: /languages
 */

/**
 * Copyright (c) 2015 Brandon Kraft (email : public@brandonkraft.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

/**
 * Built using yo wp-make:plugin
 * Copyright (c) 2015 10up, LLC
 * https://github.com/10up/generator-wp-make
 */

// Useful global constants
define( 'BEER_SLURPER_VERSION', '0.1.0' );
define( 'BEER_SLURPER_URL',     plugin_dir_url( __FILE__ ) );
define( 'BEER_SLURPER_PATH',    dirname( __FILE__ ) . '/' );
define( 'BEER_SLURPER_INC',     BEER_SLURPER_PATH . 'includes/' );

// Include files
require_once BEER_SLURPER_INC . 'functions/core.php';
include_once BEER_SLURPER_INC . 'config.php';

if ( ! defined('UNTAPPD_KEY') ) {
	return ; // config.php not set, so let's just bail out of here now.
}


// Activation/Deactivation
register_activation_hook( __FILE__, '\Kraft\Beer_Slurper\Core\activate' );
register_deactivation_hook( __FILE__, '\Kraft\Beer_Slurper\Core\deactivate' );

// Bootstrap
Kraft\Beer_Slurper\Core\setup();