<?php
/**
 * GistPress
 *
 * @package   GistPress
 * @author    Brady Vercher <brady@blazersix.com>
 * @author    Gary Jones <gary@garyjones.co.uk>
 * @copyright Copyright (c) 2013, Blazer Six, Inc.
 * @license   GPL-2.0+
 *
 * @wordpress-plugin
 * Plugin Name:       GistPress
 * Plugin URI:        https://github.com/bradyvercher/gistpress
 * Description:       Gist oEmbed and shortcode support with caching.
 * Version:           3.0.0
 * Author:            Blazer Six
 * Author URI:        http://www.blazersix.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gistpress
 * Domain Path:       /languages
 * GitHub Plugin URI: https://github.com/bradyvercher/gistpress
 * GitHub Branch:     master
 */

// Instantiate main plugin class.
if ( ! class_exists( 'GistPress' ) ) {
	require( plugin_dir_path( __FILE__ ) . 'includes/class-gistpress.php' );
}
$GLOBALS['gistpress'] = new GistPress;

// Instantiate logging class.
if ( ! class_exists( 'GistPress_Log' ) ) {
	require( plugin_dir_path( __FILE__ ) . 'includes/class-gistpress-log.php' );
}
$GLOBALS['gistpress_logger'] = new GistPress_Log;

/**
 * Support localization for plugin.
 *
 * @see http://www.geertdedeckere.be/article/loading-wordpress-language-files-the-right-way
 *
 * @since 1.1.0
 */
function gistpress_i18n() {
	$domain = 'gistpress';
	$locale = apply_filters( 'plugin_locale', get_locale(), $domain );
	load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . $domain . '/' . $domain . '-' . $locale . '.mo' );
	load_plugin_textdomain( $domain, FALSE, basename( dirname( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'gistpress_i18n' );

/**
 * Set plugin logger class and initialise plugin.
 *
 * If you want a different logger class, then unhook this function, and hook
 * in your own which does what you need.
 *
 * @since 1.1.0
 */
function gistpress_init() {
	global $gistpress, $gistpress_logger;
	$gistpress->set_logger( $gistpress_logger );
	$gistpress->run();
}
add_action( 'init', 'gistpress_init' );

/**
 * Add instance of our debug bar panel to Debug Bar plugin if not in the admin.
 *
 * @since 1.1.0
 *
 * @param array $panels
 *
 * @return array Debug Bar panels.
 */
function gistpress_add_debug_bar_panel( array $panels ) {
	global $gistpress_logger;

	if ( ! is_admin() ) {
		if ( ! class_exists( 'GistPress_Debug_Bar_Panel' ) ) {
			require( plugin_dir_path( __FILE__ ) . 'includes/class-gistpress-debug-bar-panel.php' );
		}

		$panels[] = new GistPress_Debug_Bar_Panel( $gistpress_logger );
	}

	return $panels;
}
add_filter( 'debug_bar_panels', 'gistpress_add_debug_bar_panel' );
