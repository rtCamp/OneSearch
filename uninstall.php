<?php
/**
 * This will be executed when the plugin is uninstalled via the WordPress admin.
 *
 * @package Onesearch
 */

declare( strict_types=1 );

namespace Onesearch;

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'ONESEARCH_DIR' ) ) {
	define( 'ONESEARCH_DIR', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
}

/**
 * Multisite loop for uninstalling from all sites.
 */
function multisite_uninstall(): void {
	if ( ! is_multisite() ) {
		uninstall();
		return;
	}

	// Get all site IDs.
	$site_ids = get_sites(
		[
			'fields' => 'ids',
			'number' => 0,
		]
	) ?: [];

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.switch_to_blog_switch_to_blog
		uninstall();
		restore_current_blog();
	}
}

/**
 * The uninstall function.
 */
function uninstall(): void {
	cleanup_algolia_index();

	// Wait until the end to delete options and transients.
	delete_plugin_data();
}

/**
 * Deletes options, transients, etc.
 */
function delete_plugin_data(): void {

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
 * Cleans up entries from the Algolia index, or the index itself if governing site.
 *
 * @throws \RuntimeException Doesn't throw as it is caught by the function itself.
 */
function cleanup_algolia_index(): void {
	// Load required classes.
	if ( ! load_dependencies() ) {
		return;
	}

	try {
		$algolia_index = \Onesearch\Inc\Algolia\Algolia::instance()->get_index();
		if ( is_wp_error( $algolia_index ) ) {
			throw new \RuntimeException( $algolia_index->get_error_message() );
		}

		// For governing sites, we can delete the entire index.
		if ( 'governing-site' === (string) get_option( 'onesearch_site_type', '' ) ) {
			$algolia_index->getSettings();
			$algolia_index->delete()->wait();
			return;
		}

		// For single sites, just delete the site's records.
		$algolia_index->deleteBy(
			[
				// Shims `Utils::normalize_url()` to avoid the dependency.
				'filters' => sprintf( 'site_url:"%s"', trailingslashit( trim( get_site_url() ) ) ),
			]
		)->wait();
	} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		// Do nothing.
	}
}

/**
 * Load required plugin dependencies using the autoloader.
 *
 * @return bool True if dependencies loaded successfully.
 */
function load_dependencies(): bool {
	// Try to find and load the plugin's autoloader.
	$autoloader_path = __DIR__ . '/inc/Autoloader.php';
	if ( ! file_exists( $autoloader_path ) ) {
		return false;
	}

	require_once $autoloader_path; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable

	// If the autoloader succeeded we have what we need.
	return class_exists( '\Onesearch\Autoloader' ) && \Onesearch\Autoloader::autoload();
}

// Run the uninstaller.
multisite_uninstall();
