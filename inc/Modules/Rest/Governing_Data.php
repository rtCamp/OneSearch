<?php
/**
 * Governing data handler accessors.
 *
 * Provides cached getters for Algolia credentials, the list of searchable sites,
 * and brand-site search settings. Also exposes helpers to clear related caches.
 *
 * @package OneSearch
 */

namespace Onesearch\Modules\Rest;

use Onesearch\Modules\Settings\Settings;

/**
 * Governing Data class.
 */
class Governing_Data {

	/**
	 * Retrieve Algolia credentials with transient caching.
	 *
	 * Falls back to local options when a governing (parent) site is not configured
	 * or when authentication details are unavailable.
	 *
	 * @return array{
	 *   app_id: ?string,
	 *   write_key: ?string,
	 *   admin_key: ?string
	 * }|\WP_Error
	 */
	public static function get_algolia_credentials(): array|\WP_Error {
		// Return cached value when available.
		$cached = get_transient( Settings::OPTION_GOVERNING_ALGOLIA_CREDENTIALS . '_cache' );
		if ( false !== $cached && is_array( $cached ) ) {
			return [
				'app_id'    => $cached['app_id'] ?? null,
				'write_key' => $cached['write_key'] ?? null,
				'admin_key' => $cached['admin_key'] ?? null,
			];
		}
		// If no parent is configured, use local credentials.
		$parent_url = Settings::get_parent_site_url();
		if ( empty( $parent_url ) ) {
			return Settings::get_algolia_credentials();
		}

		// Child authenticating to the governing site.
		$our_public_key = Settings::get_api_key();
		if ( empty( $our_public_key ) ) {
			return Settings::get_algolia_credentials();
		}

		$endpoint = sprintf(
			'%s/wp-json/%s/algolia-credentials',
			untrailingslashit( $parent_url ),
			Abstract_REST_Controller::NAMESPACE,
		);

		$response = wp_safe_remote_get(
			$endpoint,
			[
				'headers'    => [
					'Accept'                    => 'application/json',
					'Content-Type'              => 'application/json',
					'X-OneSearch-Plugins-Token' => $our_public_key,
				],
				'user-agent' => sprintf( 'OneSearch/%s', ONESEARCH_VERSION ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( 200 !== $code ) {
			return new \WP_Error(
				'onesearch_rest_failed_to_connect',
				__( 'Failed to connect to the governing site.', 'onesearch' ),
				[
					'status' => $code,
					'body'   => $body,
				]
			);
		}

		$response_data = json_decode( $body, true );
		if ( null === $response_data || ! is_array( $response_data ) ) {
			return new \WP_Error(
				'onesearch_rest_invalid_response',
				__( 'The governing site returned an invalid response.', 'onesearch' ),
				[ 'status' => 500 ]
			);
		}

		$sanitized = [
			'app_id'    => isset( $response_data['app_id'] ) ? sanitize_text_field( $response_data['app_id'] ) : null,
			'write_key' => isset( $response_data['write_key'] ) ? sanitize_text_field( $response_data['write_key'] ) : null,
			'admin_key' => isset( $response_data['admin_key'] ) ? sanitize_text_field( $response_data['admin_key'] ) : null,
		];

		set_transient( Settings::OPTION_GOVERNING_ALGOLIA_CREDENTIALS . '_cache', $sanitized, WEEK_IN_SECONDS );

		return $sanitized;
	}

	/**
	 * Retrieve the list of searchable site URLs with transient caching.
	 *
	 * Falls back to local option when parent configuration or authentication is missing.
	 *
	 * @return array<int, string>|\WP_Error
	 */
	public static function get_searchable_sites(): array|\WP_Error {
		// Return cached value when available.
		$cached = get_transient( Settings::OPTION_GOVERNING_SHARED_SITES . '_cache' );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		// If no parent is configured, use local storage.
		$parent_url = Settings::get_parent_site_url();
		// Child authenticating to the governing site.
		$our_public_key = Settings::get_api_key();

		if ( empty( $parent_url ) || empty( $our_public_key ) ) {
			$all_sites = Settings::get_shared_sites();

			$site_urls = array_map(
				static fn( $site ) => esc_url_raw( $site['siteUrl'] ) ?: null,
				$all_sites
			);

			return array_values( array_filter( $site_urls ) );
		}

		$endpoint = sprintf(
			'%s/wp-json/%s/searchable-sites',
			untrailingslashit( $parent_url ),
			Abstract_REST_Controller::NAMESPACE,
		);

		$response = wp_safe_remote_get(
			$endpoint,
			[
				'headers'    => [
					'Accept'                    => 'application/json',
					'Content-Type'              => 'application/json',
					'X-OneSearch-Plugins-Token' => $our_public_key,
				],
				'user-agent' => sprintf( 'OneSearch/%s', ONESEARCH_VERSION ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( 200 !== $code ) {
			return new \WP_Error(
				'onesearch_rest_failed_to_connect',
				__( 'Failed to connect to the governing site.', 'onesearch' ),
				[
					'status' => $code,
					'body'   => $body,
				]
			);
		}

		$response_data = json_decode( $body, true );
		if ( null === $response_data || ! is_array( $response_data ) ) {
			return new \WP_Error(
				'onesearch_rest_invalid_response',
				__( 'The governing site returned an invalid response.', 'onesearch' ),
				[ 'status' => 500 ]
			);
		}

		$sites = $response_data['searchable_sites'] ?? null;

		// Don't cache empty results.
		if ( empty( $sites ) || ! is_array( $sites ) ) {
			return [];
		}

		$sanitized = array_map(
			static fn( $site ) => esc_url_raw( $site ),
			$sites
		);

		set_transient( Settings::OPTION_GOVERNING_SHARED_SITES . '_cache', $sanitized, HOUR_IN_SECONDS );

		return $sanitized;
	}

	/**
	 * Retrieve brand-site search settings with transient caching.
	 *
	 * @return array{
	 *  algolia_enabled: bool,
	 *  searchable_sites: string[],
	 * }|\WP_Error
	 */
	public static function get_search_settings(): array|\WP_Error {
		// Return cached value when available.
		$cached = get_transient( Settings::OPTION_GOVERNING_SEARCH_SETTINGS . '_cache' );
		if ( false !== $cached && is_array( $cached ) ) {
			return [
				'algolia_enabled'  => $cached['algolia_enabled'] ?? false,
				'searchable_sites' => $cached['searchable_sites'] ?? [],
			];
		}

		// If no parent is configured, return a disabled default.
		$parent_url = Settings::get_parent_site_url();
		if ( empty( $parent_url ) ) {
			return [
				'algolia_enabled'  => false,
				'searchable_sites' => [],
			];
		}

		// Child authenticating to the governing site.
		$our_public_key = Settings::get_api_key();
		if ( empty( $our_public_key ) ) {
			return [
				'algolia_enabled'  => false,
				'searchable_sites' => [],
			];
		}

		$endpoint = sprintf(
			'%s/wp-json/%s/search-settings',
			untrailingslashit( $parent_url ),
			Abstract_REST_Controller::NAMESPACE,
		);

		$response = wp_safe_remote_get(
			$endpoint,
			[
				'headers'    => [
					'Accept'                    => 'application/json',
					'Content-Type'              => 'application/json',
					'X-OneSearch-Plugins-Token' => $our_public_key,
				],
				'user-agent' => sprintf( 'OneSearch/%s', ONESEARCH_VERSION ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( 200 !== $code ) {
			return new \WP_Error(
				'onesearch_rest_failed_to_connect',
				__( 'Failed to connect to the governing site.', 'onesearch' ),
				[
					'status' => $code,
					'body'   => $body,
				]
			);
		}

		$response_data = json_decode( $body, true );
		if ( null === $response_data || ! is_array( $response_data ) ) {
			return new \WP_Error(
				'onesearch_rest_invalid_response',
				__( 'The governing site returned an invalid response.', 'onesearch' ),
				[ 'status' => 500 ]
			);
		}

		$config = $response_data['config'] ?? null;

		// Don't cache empty results.
		if ( null === $config || ! is_array( $config ) ) {
			return [
				'algolia_enabled'  => false,
				'searchable_sites' => [],
			];
		}

		$sanitized = [
			'algolia_enabled'  => ! empty( $config['algolia_enabled'] ),
			'searchable_sites' => isset( $config['searchable_sites'] ) && is_array( $config['searchable_sites'] )
				? array_map( static fn( $site ) => esc_url_raw( $site ), $config['searchable_sites'] )
				: [],
		];

		set_transient( Settings::OPTION_GOVERNING_SEARCH_SETTINGS . '_cache', $sanitized, HOUR_IN_SECONDS );

		return $sanitized;
	}

	/**
	 * Clear the cached brand-site search settings.
	 */
	public static function clear_search_settings_cache(): void {
		delete_transient( Settings::OPTION_GOVERNING_SEARCH_SETTINGS . '_cache' );
	}

	/**
	 * Clear the cached Algolia credentials.
	 */
	public static function clear_credentials_cache(): void {
		delete_transient( Settings::OPTION_GOVERNING_ALGOLIA_CREDENTIALS . '_cache' );
	}

	/**
	 * Clear the cached list of searchable sites.
	 */
	public static function clear_searchable_sites_cache(): void {
		delete_transient( Settings::OPTION_GOVERNING_SHARED_SITES . '_cache' );
	}
}
