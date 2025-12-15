<?php
/**
 * Handles cross-site requests for governing brand data.
 *
 * Powered by the Governing_Data_Controller REST endpoint.
 *
 * @package OneSearch\Modules\Rest
 */

namespace OneSearch\Modules\Rest;

use OneSearch\Modules\Settings\Settings;

/**
 * Class - Governing_Data_Handler
 */
class Governing_Data_Handler {
	/**
	 * The transient key used by the consumer sites to cache brand configuration.
	 */
	public const TRANSIENT_KEY = 'onesearch_brand_config_cache';

	/**
	 * Retrieve consolidated brand site configuration with transient caching.
	 *
	 * This method consolidates multiple configuration requests into a single endpoint call.
	 *
	 * @return array{
	 *  algolia_credentials: array{app_id: string, write_key: string},
	 *  search_settings: array{algolia_enabled: bool, searchable_sites: string[]},
	 *  indexable_entities: string[],
	 *  available_sites: string[],
	 * }|\WP_Error
	 */
	public static function get_brand_config(): array|\WP_Error {
		// Only call on brand sites.
		if ( ! Settings::is_consumer_site() ) {
			return new \WP_Error(
				'onesearch_unauthorized_site',
				__( 'The requesting site is not a shared brand site.', 'onesearch' ),
			);
		}

		// Return cached value when available.
		$cached = get_transient( self::TRANSIENT_KEY );
		if ( false !== $cached && is_array( $cached ) ) {
			/** @var array{algolia_credentials: array{app_id: string, write_key: string}, search_settings: array{algolia_enabled: bool, searchable_sites: array<string>}, indexable_entities: array<string>, available_sites: array<string>} $cached */
			return $cached;
		}

		// If no parent is configured, return an error.
		$parent_url = Settings::get_parent_site_url();
		if ( empty( $parent_url ) ) {
			return new \WP_Error(
				'onesearch_no_parent',
				__( 'No governing site is configured.', 'onesearch' ),
				[ 'status' => 500 ]
			);
		}

		// Child authenticating to the governing site.
		$our_public_key = Settings::get_api_key();
		if ( empty( $our_public_key ) ) {
			return new \WP_Error(
				'onesearch_no_key',
				__( 'No API key is configured.', 'onesearch' ),
				[ 'status' => 500 ]
			);
		}

		$endpoint = sprintf(
			'%s/wp-json/%s/brand-config',
			untrailingslashit( $parent_url ),
			Abstract_REST_Controller::NAMESPACE,
		);

		$response = wp_safe_remote_get(
			$endpoint,
			[
				'headers' => [
					'Accept'            => 'application/json',
					'Content-Type'      => 'application/json',
					'X-OneSearch-Token' => $our_public_key,
				],
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

		// Validate and structure the response.
		$algolia_creds = is_array( $response_data['algolia_credentials'] ?? null )
			? $response_data['algolia_credentials']
			: [];

		$search_settings = is_array( $response_data['search_settings'] ?? null )
			? $response_data['search_settings']
			: [];

		$indexable_entities = is_array( $response_data['indexable_entities'] ?? null )
			? $response_data['indexable_entities']
			: [];

		$available_sites = is_array( $response_data['available_sites'] ?? null )
			? $response_data['available_sites']
			: [];

		$config = [
			'algolia_credentials' => [
				'app_id'    => is_string( $algolia_creds['app_id'] ?? null ) ? sanitize_text_field( $algolia_creds['app_id'] ) : '',
				'write_key' => is_string( $algolia_creds['write_key'] ?? null ) ? sanitize_text_field( $algolia_creds['write_key'] ) : '',
			],
			'search_settings'     => [
				'algolia_enabled'  => ! empty( $search_settings['algolia_enabled'] ),
				'searchable_sites' => is_array( $search_settings['searchable_sites'] ?? null )
					? array_values( array_filter( array_map( 'sanitize_text_field', $search_settings['searchable_sites'] ), 'is_string' ) )
					: [],
			],
			'indexable_entities'  => array_values( array_filter( array_map( 'sanitize_text_field', $indexable_entities ), 'is_string' ) ),
			'available_sites'     => array_values( array_filter( array_map( 'sanitize_text_field', $available_sites ), 'is_string' ) ),
		];

		// Cache for 1 week.
		set_transient( self::TRANSIENT_KEY, $config, WEEK_IN_SECONDS );

		return $config;
	}

	/**
	 * Clear the cached brand configuration.
	 *
	 * @param ?string $site_url Optional site URL to clear cache for a specific site. If null, clears cache for all shared sites.
	 */
	public static function clear_brand_config_cache( ?string $site_url = null ): void {
		if ( ! Settings::is_governing_site() ) {
			delete_transient( self::TRANSIENT_KEY );
			return;
		}

		$shared_sites = Settings::get_shared_sites();

		// If a specific site URL is provided, We'll just target that one.
		if ( ! empty( $site_url ) && isset( $shared_sites[ $site_url ] ) ) {
			$shared_sites = [ $shared_sites[ $site_url ] ];
		}

		foreach ( $shared_sites as $site_data ) {
			if ( empty( $site_data['url'] ) || empty( $site_data['api_key'] ) ) {
				continue;
			}

			// Clear cache on each shared site.
			$endpoint = sprintf(
				'%s/wp-json/%s/brand-config',
				untrailingslashit( $site_data['url'] ),
				Abstract_REST_Controller::NAMESPACE,
			);

			wp_safe_remote_post(
				$endpoint,
				[
					'headers'  => [
						'Accept'            => 'application/json',
						'Content-Type'      => 'application/json',
						'X-OneSearch-Token' => $site_data['api_key'],
					],
					// Don't wait to see if the cache flush was successful.
					'blocking' => false,
				]
			);
		}
	}
}
