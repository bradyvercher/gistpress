<?php
/**
 * Plugin Name: Blazer Six Gist oEmbed
 * Plugin URI: https://github.com/bradyvercher/wp-blazer-six-gist-oembed
 * Description: Gist oEmbed and shortcode support with caching.
 * Version: 1.1.0
 * Author: Blazer Six, Inc.
 * Author URI: http://www.blazersix.com/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package BlazerSix\GistoEmbed
 * @author Brady Vercher <brady@blazersix.com>
 * @author Gary Jones <gary@garyjones.co.uk>
 * @copyright Copyright (c) 2012, Blazer Six, Inc.
 * @license GPL-2.0+
 */

// Instantiate main plugin class
if ( ! class_exists( 'Blazer_Six_Gist_oEmbed' ) ) {
	require( plugin_dir_path( __FILE__ ) . 'class-blazer-six-gist-oembed.php' );
}
$gist_oembed = new Blazer_Six_Gist_oEmbed;

// Instantiate logging class
if ( ! class_exists( 'Blazer_Six_Gist_oEmbed_Log' ) ) {
	require( plugin_dir_path( __FILE__ ) . 'class-blazer-six-gist-oembed-log.php' );
}
$gist_oembed_logger = new Blazer_Six_Gist_oEmbed_Log;

add_action( 'init', 'blazer_six_gist_oembed_localization' );
/**
 * Support localization for plugin.
 *
 * @see http://www.geertdedeckere.be/article/loading-wordpress-language-files-the-right-way
 *
 * @since 1.1.0
 */
function blazer_six_gist_oembed_localization() {
	$domain = 'blazersix-gist-oembed';
	// The "plugin_locale" filter is also used in load_plugin_textdomain()
	$locale = apply_filters( 'plugin_locale', get_locale(), $domain );
	load_textdomain( $domain, WP_LANG_DIR . '/my-plugin/' . $domain . '-' . $locale . '.mo' );
	load_plugin_textdomain( $domain, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

add_action( 'init', 'blazer_six_gist_oembed_init' );
/**
 * Set plugin logger class and initialise plugin.
 *
 * If you want a different logger class, then unhook this function, and hook
 * in your own which does what you need.
 *
 * @since 1.1.0
 */
function blazer_six_gist_oembed_init() {
	global $gist_oembed, $gist_oembed_logger;
	$gist_oembed->set_logger( $gist_oembed_logger );
	$gist_oembed->run();
}

/** @todo Can the following two functions be made static methods of the debug
 * bar class, since there is already an inherent dependency? */

// Late priority to give Debug Bar plugin chance to initialise
add_action( 'plugins_loaded', 'blazer_six_gist_oembed_add_debug_bar_panel_support', 15 );
/**
 * Add optional support for Debug Bar plugin, if enabled
 *
 * @return null Return early if Debug Bar plugin not enabled.
 */
function blazer_six_gist_oembed_add_debug_bar_panel_support() {
	if ( ! class_exists( 'Debug_Bar' ) || is_admin() || ! is_admin_bar_showing() ) {
		return;
	}

	add_filter( 'debug_bar_panels', 'blazer_six_gist_oembed_add_debug_bar_panel' );
}

/**
 * Add instance of our debug bar panel to Debug Bar plugin.
 *
 * @param array $panels
 *
 * @return Blazer_Six_Gist_oEmbed_Debug_Bar_Panel
 */
function blazer_six_gist_oembed_add_debug_bar_panel( array $panels ) {
	global $gist_oembed_logger;
//	wp_die('panel being added');
	if ( ! class_exists( 'Blazer_Six_Gist_oEmbed_Debug_Bar_Panel' ) ) {
		require( plugin_dir_path( __FILE__ ) . 'class-blazer-six-gist-oembed-debug-bar-panel.php' );
	}
	$panels[] = new Blazer_Six_Gist_oEmbed_Debug_Bar_Panel( $gist_oembed_logger );
	return $panels;
}
