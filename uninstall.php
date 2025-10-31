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

if ( ! defined( 'ONESEARCH_PATH' ) ) {
	define( 'ONESEARCH_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
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
	cleanup_algolia_indices();

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
 * Clean up Algolia indices before plugin removal.
 *
 * @throws \Exception When Algolia client encounters an error.
 */
function cleanup_algolia_indices(): void {

	// Load required classes.
	if ( ! load_dependencies() ) {
		return;
	}

	try {
		$algolia_instance = \Onesearch\Inc\Algolia\Algolia::get_instance();
		$client           = $algolia_instance->get_client();

		// This will be bubbled to the catch block.
		if ( is_wp_error( $client ) ) {
			throw new \Exception( 'Algolia client error: ' . $client->get_error_message() );
		}

		// Delete the index for the current site.
		$current_site_index = $algolia_instance->get_algolia_index_name_from_url( get_site_url() );
		delete_algolia_index( $client, $current_site_index );

		// If it's a governing site, delete the brand site indices as well.
		$site_type = (string) get_option( 'onesearch_site_type', '' );
		if ( 'governing-site' === $site_type ) {
			delete_shared_site_indices( $client );
		}
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

/**
 * Deletes the indexes for the brand sites associated with the governing site.
 *
 * @param \Algolia\AlgoliaSearch\SearchClient $client Algolia client.
 */
function delete_shared_site_indices( $client ): void {
	$shared_sites = get_option( 'onesearch_shared_sites', [] );

	if ( ! is_array( $shared_sites ) ) {
		return;
	}

	$algolia_instance = \Onesearch\Inc\Algolia\Algolia::get_instance();

	foreach ( $shared_sites as $site ) {
		if ( empty( $site['siteUrl'] ) ) {
			continue;
		}

		$index_name = $algolia_instance->get_algolia_index_name_from_url( $site['siteUrl'] );

		delete_algolia_index( $client, $index_name );
	}
}

/**
 * Deletes an Algolia index.
 *
 * Errors are thrown and caught by the caller.
 *
 * @param \Algolia\AlgoliaSearch\SearchClient $client Algolia client.
 * @param string                              $index_name Index name to delete.
 * @return void
 */
function delete_algolia_index( $client, $index_name ): void {
	if ( empty( $index_name ) ) {
		return;
	}

	$index = $client->initIndex( $index_name );
	$index->getSettings();
	$index->delete()->wait();
}

// Run the uninstaller.
multisite_uninstall();
