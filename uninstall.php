<?php
/**
 * This will be executed when the plugin is uninstalled via the WordPress admin.
 *
 * @package OneSearch
 */

declare( strict_types=1 );

namespace OneSearch;

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
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
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.switch_to_blog_switch_to_blog
		if ( ! switch_to_blog( (int) $site_id ) ) {
			continue;
		}

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
	delete_option( 'onesearch_site_type' );
	delete_option( 'onesearch_shared_sites' );
	delete_option( 'onesearch_indexable_entities' );
	delete_option( 'onesearch_algolia_credentials' );
	delete_option( 'onesearch_sites_search_settings' );

	// Brand site options.
	delete_option( 'onesearch_parent_site_url' );
	delete_option( 'onesearch_consumer_api_key' );
	delete_transient( 'onesearch_site_search_settings_cache' );
	delete_transient( 'onesearch_algolia_credentials_cache' );
	delete_transient( 'onesearch_shared_sites_cache' );
}

/**
 * Cleans up entries from the Algolia index, or the index itself if governing site.
 */
function cleanup_algolia_index(): void {
	// Load required classes.
	if ( ! load_dependencies() ) {
		return;
	}

	$indexer = new \OneSearch\Modules\Search\Index();
	$indexer->delete_index();
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
	return class_exists( '\OneSearch\Autoloader' ) && \OneSearch\Autoloader::autoload();
}

// Run the uninstaller.
multisite_uninstall();
