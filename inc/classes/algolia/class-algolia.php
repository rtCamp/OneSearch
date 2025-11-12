<?php
/**
 * Algolia service wrapper.
 *
 * @package onesearch
 */

namespace OneSearch\Inc\Algolia;

use Algolia\AlgoliaSearch\SearchClient;
use OneSearch\Contracts\Traits\Singleton;
use OneSearch\Modules\Rest\Governing_Data;
use OneSearch\Modules\Settings\Settings;

/**
 * Class Algolia
 */
class Algolia {

	use Singleton;

	/**
	 * Resolve Algolia credentials.
	 *
	 * When site type is "brand-site", credentials are fetched from the
	 * governing site, otherwise from local options.
	 *
	 * @return array{
	 *   app_id: string,
	 *   write_key: string,
	 *   admin_key: string
	 * }
	 */
	private function get_creds(): array {
		$creds = Settings::is_consumer_site()
			? Governing_Data::get_algolia_credentials()
			: Settings::get_algolia_credentials();

		if ( is_wp_error( $creds ) ) {
			return [
				'app_id'    => '',
				'write_key' => '',
				'admin_key' => '',
			];
		}

		return [
			'app_id'    => (string) ( $creds['app_id'] ?? '' ),
			'write_key' => (string) ( $creds['write_key'] ?? '' ),
			'admin_key' => (string) ( $creds['admin_key'] ?? '' ),
		];
	}

	/**
	 * Get the index object for the current site.
	 */
	public function get_index(): \Algolia\AlgoliaSearch\SearchIndex|\WP_Error {
		$index_name = $this->get_index_name();

		if ( null === $index_name ) {
			return new \WP_Error(
				'algolia_index_name_invalid',
				__( 'Algolia index name could not be determined due to invalid site URL.', 'onesearch' )
			);
		}

		$client = $this->get_client();

		if ( is_wp_error( $client ) ) {
			return $client;
		}

		return $client->initIndex( $index_name );
	}

	/**
	 * Create an Algolia client using stored credentials.
	 */
	public function get_client(): \Algolia\AlgoliaSearch\SearchClient|\WP_Error {
		$creds = $this->get_creds();

		if ( empty( $creds['app_id'] ) || empty( $creds['write_key'] ) ) {
			return new \WP_Error(
				'algolia_credentials_missing',
				__( 'Algolia admin credentials missing.', 'onesearch' )
			);
		}

		return SearchClient::create( $creds['app_id'], $creds['write_key'] );
	}

	/**
	 * Get the algolia index name.
	 *
	 * @return ?string Index name (e.g., onesearch_example_com_wp_posts).
	 *                 Null if site URL is invalid.
	 */
	public function get_index_name(): ?string {
		$site_url = Settings::is_governing_site()
			? get_site_url()
			: Settings::get_parent_site_url();

		if ( empty( $site_url ) ) {
			return null;
		}

		$parsed_url = wp_parse_url( $site_url );
		$site_name  = ! empty( $parsed_url['host'] ) ? $parsed_url['host'] : null;

		if ( null === $site_name ) {
			return null;
		}

		$site_name = str_replace( '.', '_', $site_name );

		return sprintf( 'onesearch_%s_wp_posts', sanitize_title( $site_name ) );
	}
}
