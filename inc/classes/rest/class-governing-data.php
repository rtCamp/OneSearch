<?php
/**
 * Governing data accessors.
 *
 * Provides cached getters for Algolia credentials, the list of searchable sites,
 * and brand-site search settings. Also exposes helpers to clear related caches.
 *
 * @package OneSearch
 */

namespace Onesearch\Inc\REST;

use Onesearch\Inc\Traits\Singleton;

/**
 * Governing Data class.
 */
class Governing_Data {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	private const NAMESPACE = 'onesearch/v1';

	/**
	 * Use Singleton trait.
	 */
	use Singleton;

	/**
	 * Constructor.
	 */
	protected function __construct() {}

	/**
	 * Retrieve Algolia credentials with transient caching.
	 *
	 * Falls back to local options when a governing (parent) site is not configured
	 * or when authentication details are unavailable.
	 *
	 * @return array{
	 *   app_id?: string,
	 *   write_key?: string,
	 *   admin_key?: string
	 * }
	 */
	public static function get_algolia_credentials(): array {
		// Return cached value when available.
		$cached = get_transient( 'onesearch_algolia_creds_cache' );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		// If no parent is configured, use local storage.
		$parent_url = get_option( 'onesearch_parent_site_url', '' );
		if ( empty( $parent_url ) ) {
			return get_local_algolia_credentials();
		}

		// Child authenticating to the governing site.
		$our_public_key = get_option( 'onesearch_child_site_public_key', '' );
		if ( empty( $our_public_key ) ) {
			return get_local_algolia_credentials();
		}

		$endpoint = trailingslashit( $parent_url ) . 'wp-json/' . self::NAMESPACE . '/algolia-credentials';

		$response = wp_remote_get(
			$endpoint,
			[
				'headers' => [
					'Accept'                    => 'application/json',
					'X-OneSearch-Plugins-Token' => $our_public_key,
				],
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return get_local_algolia_credentials();
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return get_local_algolia_credentials();
		}

		set_transient( 'onesearch_algolia_creds_cache', $data );

		return $data;
	}

	/**
	 * Retrieve the list of searchable site URLs with transient caching.
	 *
	 * Falls back to local option when parent configuration or authentication is missing.
	 *
	 * @return array<int, string> List of site URLs.
	 */
	public static function get_searchable_sites(): array {
		// Return cached value when available.
		$cached = get_transient( 'onesearch_searchable_sites_cache' );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		// If no parent is configured, use local storage.
		$parent_url = get_option( 'onesearch_parent_site_url', '' );
		if ( empty( $parent_url ) ) {
			return get_option( 'onesearch_searchable_sites', [] );
		}

		// Child authenticating to the governing site.
		$our_public_key = get_option( 'onesearch_child_site_public_key', '' );
		if ( empty( $our_public_key ) ) {
			return get_option( 'onesearch_searchable_sites', [] );
		}

		$endpoint = trailingslashit( $parent_url ) . 'wp-json/' . self::NAMESPACE . '/searchable-sites';

		$response = wp_remote_get(
			$endpoint,
			[
				'headers' => [
					'Accept'                    => 'application/json',
					'X-OneSearch-Plugins-Token' => $our_public_key,
				],
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return get_option( 'onesearch_searchable_sites', [] );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || ! isset( $data['searchable_sites'] ) ) {
			return get_option( 'onesearch_searchable_sites', [] );
		}

		$sites = $data['searchable_sites'];
		set_transient( 'onesearch_searchable_sites_cache', $sites );

		return $sites;
	}

	/**
	 * Retrieve brand-site search settings with transient caching.
	 *
	 * Structure example:
	 * [
	 *   'algolia_enabled'  => bool,
	 *   'searchable_sites' => string[],
	 * ]
	 *
	 * @return array<string, mixed> Search configuration.
	 */
	public static function get_search_settings(): array {
		// Return cached value when available.
		$cached = get_transient( 'onesearch_search_settings_cache' );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		// If no parent is configured, return a disabled default.
		$parent_url = get_option( 'onesearch_parent_site_url', '' );
		if ( empty( $parent_url ) ) {
			return [
				'algolia_enabled'  => false,
				'searchable_sites' => [],
			];
		}

		// Child authenticating to the governing site.
		$our_public_key = get_option( 'onesearch_child_site_public_key', '' );
		if ( empty( $our_public_key ) ) {
			return [
				'algolia_enabled'  => false,
				'searchable_sites' => [],
			];
		}

		$endpoint = trailingslashit( $parent_url ) . 'wp-json/' . self::NAMESPACE . '/search-settings';

		$response = wp_remote_get(
			$endpoint,
			[
				'headers' => [
					'Accept'                    => 'application/json',
					'X-OneSearch-Plugins-Token' => $our_public_key,
				],
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return [
				'algolia_enabled'  => false,
				'searchable_sites' => [],
			];
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || ! isset( $data['config'] ) ) {
			return [
				'algolia_enabled'  => false,
				'searchable_sites' => [],
			];
		}

		$config = $data['config'];
		set_transient( 'onesearch_search_settings_cache', $config, HOUR_IN_SECONDS );

		return $config;
	}

	/**
	 * Clear the cached brand-site search settings.
	 *
	 * @return void
	 */
	public static function clear_search_settings_cache(): void {
		delete_transient( 'onesearch_search_settings_cache' );
	}

	/**
	 * Clear the cached Algolia credentials.
	 *
	 * @return void
	 */
	public static function clear_credentials_cache(): void {
		delete_transient( 'onesearch_algolia_creds_cache' );
	}

	/**
	 * Clear the cached list of searchable sites.
	 *
	 * @return void
	 */
	public static function clear_searchable_sites_cache(): void {
		delete_transient( 'onesearch_searchable_sites_cache' );
	}
}
