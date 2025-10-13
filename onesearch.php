<?php
/**
 * Plugin Name:       OneSearch
 * Description:       This plugin allows you to run multi-index, multi-site searches seamlessly, without duplicate or missing results.
 * Author:            rtCamp
 * Plugin URI:        https://rtcamp.com
 * Author URI:        https://rtcamp.com
 * License:           GPL2
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       onesearch
 * Domain Path:       /languages
 * Version:           1.0
 * Requires PHP:      8.0
 * Requires at least: 6.8
 * Tested up to:      6.8.2
 *
 * @package Onesearch
 */

declare (strict_types = 1);

namespace Onesearch;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit();

/**
 * Define the plugin constants.
 */
function constants(): void {
	/**
	 * Version of the plugin.
	 */
	define( 'ONESEARCH_VERSION', '1.0' );

	/**
	 * Root path to the plugin directory.
	 */
	define( 'ONESEARCH_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

	/**
	 * Root URL to the plugin directory.
	 */
	define( 'ONESEARCH_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );

	/**
	 * The plugin basename.
	 */
	define( 'ONESEARCH_PLUGIN_LOADER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
	define( 'ONESEARCH_PLUGIN_LOADER_SLUG', 'onesearch' );
}

constants();


// If autoloader failed, we cannot proceed.
require_once __DIR__ . '/inc/Autoloader.php';
if ( ! \Onesearch\Autoloader::autoload() ) {
	return;
}

// Load the plugin.
if ( class_exists( '\Onesearch\Inc\Plugin' ) ) {
	\Onesearch\Inc\Plugin::get_instance();
}
