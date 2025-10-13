<?php
/**
 * This will be executed when the plugin is uninstalled.
 *
 * @package Onesearch
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'ONESEARCH_PATH' ) ) {
	define( 'ONESEARCH_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
}

if ( ! function_exists( 'onesearch_deactivate' ) ) {

	/**
	 * Function to deactivate the plugin and clean up options.
	 */
	function onesearch_deactivate() {

		// Delete Algolia indices before clearing options.
		onesearch_cleanup_algolia_indices();

		// Governing site options.
		delete_option( 'onesearch_child_site_public_key' );
		delete_option( 'onesearch_site_type' );
		delete_option( 'onesearch_shared_sites' );
		delete_option( 'onesearch_indexable_entities' );
		delete_option( 'onesearch_algolia_credentials' );
		delete_option( 'onesearch_sites_search_settings' );
		delete_transient( 'onesearch_site_type_transient' );

		// Brand site options.
		delete_option( 'onesearch_parent_site_url' );
		delete_transient( 'onesearch_search_settings_cache' );
		delete_transient( 'onesearch_algolia_creds_cache' );
		delete_transient( 'onesearch_searchable_sites_cache' );
	}

	/**
	 * Clean up Algolia indices before plugin removal.
	 *
	 * @return void
	 */
	function onesearch_cleanup_algolia_indices() {

		// Load required classes.
		if ( ! onesearch_load_dependencies() ) {
			return;
		}

		$site_type = (string) get_option( 'onesearch_site_type', '' );

		try {
			if ( 'governing-site' === $site_type ) {
				onesearch_delete_governing_site_indices();
			} else {
				// For brand sites or unknown types, delete current site only.
				onesearch_delete_current_site_index();
			}
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Do nothing.
		}
	}

	/**
	 * Load required plugin dependencies.
	 *
	 * @return bool True if dependencies loaded successfully.
	 */
	function onesearch_load_dependencies() {
		// Try to find and load the plugin's autoloader.
		$autoloader_path = __DIR__ . '/inc/Autoloader.php';
		if ( file_exists( $autoloader_path ) ) {
			require_once $autoloader_path;
		}

		// If the autoloader succeeded we have what we need.
		if ( class_exists( '\Onesearch\Autoloader' ) && \Onesearch\Autoloader::autoload() ) {
			return true;
		}

		// Try to find and load the plugin classes.
		$class_files = [
			WP_PLUGIN_DIR . '/onesearch/inc/traits/trait-singleton.php',
			WP_PLUGIN_DIR . '/onesearch/inc/classes/rest/class-governing-data.php',
			WP_PLUGIN_DIR . '/onesearch/inc/classes/algolia/class-algolia.php',
		];

		foreach ( $class_files as $class_file ) {
			if ( ! file_exists( $class_file ) ) {
				continue;
			}

			require_once $class_file;
		}

		// Check if required classes are available.
		return class_exists( '\Algolia\AlgoliaSearch\SearchClient' ) &&
			class_exists( '\Onesearch\Inc\REST\Governing_Data' ) &&
			class_exists( '\Onesearch\Inc\Algolia\Algolia' );
	}

	/**
	 * Delete indices for governing site and all its brand sites.
	 *
	 * @return void
	 */
	function onesearch_delete_governing_site_indices() {

		$algolia_instance = \Onesearch\Inc\Algolia\Algolia::get_instance();
		$client           = $algolia_instance->get_client();

		if ( is_wp_error( $client ) ) {
			return;
		}

		$indices_to_delete = [];

		// Add current site index.
		$current_site_index  = $algolia_instance->get_algolia_index_name_from_url( get_site_url() );
		$indices_to_delete[] = $current_site_index;

		// Add all brand site indices.
		$shared_sites = get_option( 'onesearch_shared_sites', [] );
		if ( is_array( $shared_sites ) ) {
			foreach ( $shared_sites as $site ) {
				$site_url = $site['siteUrl'] ?? '';
				if ( empty( $site_url ) ) {
					continue;
				}

				$index_name          = $algolia_instance->get_algolia_index_name_from_url( $site_url );
				$indices_to_delete[] = $index_name;
			}
		}

		// Delete all indices.
		foreach ( $indices_to_delete as $index_name ) {
			onesearch_safe_delete_index( $client, $index_name );
		}
	}

	/**
	 * Delete current site index only.
	 *
	 * @return void
	 */
	function onesearch_delete_current_site_index() {

		$algolia_instance = \Onesearch\Inc\Algolia\Algolia::get_instance();
		$client           = $algolia_instance->get_client();

		if ( is_wp_error( $client ) ) {
			return;
		}

		$current_site_index = $algolia_instance->get_algolia_index_name_from_url( get_site_url() );
		onesearch_safe_delete_index( $client, $current_site_index );
	}

	/**
	 * Safely delete an Algolia index with error handling.
	 *
	 * @param \Algolia\AlgoliaSearch\SearchClient $client Algolia client.
	 * @param string                              $index_name Index name to delete.
	 * @return void
	 */
	function onesearch_safe_delete_index( $client, $index_name ) {

		if ( empty( $index_name ) ) {
			return;
		}

		try {
			$index = $client->initIndex( $index_name );
				$index->getSettings();
				$index->delete()->wait();
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// do nothing.
		}
	}
}

/**
 * Uninstall the plugin and clean up options.
 */
onesearch_deactivate();
