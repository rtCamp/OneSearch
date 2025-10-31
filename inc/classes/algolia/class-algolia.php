<?php
/**
 * Algolia service wrapper.
 *
 * @package onesearch
 */

namespace Onesearch\Inc\Algolia;

use Algolia\AlgoliaSearch\SearchClient;
use Onesearch\Inc\REST\Governing_Data;
use Onesearch\Inc\Traits\Singleton;
use Onesearch\Utils;

/**
 * Class Algolia
 */
class Algolia {

	use Singleton;

	/**
	 * Constructor.
	 */
	final protected function __construct() {}

	/**
	 * Resolve Algolia credentials.
	 *
	 * When site type is "brand-site", credentials are fetched from the
	 * governing site, otherwise from local options.
	 *
	 * @return array {
	 *   @type string $app_id     Algolia App ID.
	 *   @type string $write_key  Algolia Write API key.
	 *   @type string $admin_key  Algolia Admin API key (if stored).
	 * }
	 */
	private function get_creds(): array {

		$site_type = (string) get_option( 'onesearch_site_type', '' );

		if ( 'brand-site' === $site_type ) {
			return Governing_Data::get_algolia_credentials();
		}

		$creds = Utils::get_local_algolia_credentials();

		return [
			'app_id'    => (string) ( $creds['app_id'] ?? '' ),
			'write_key' => (string) ( $creds['write_key'] ?? '' ),
			'admin_key' => (string) ( $creds['admin_key'] ?? '' ),
		];
	}

	/**
	 * Get the index object for the current site.
	 *
	 * @return object|\WP_Error Algolia index instance or WP_Error on failure.
	 */
	public function get_index() {

		$client = $this->get_client();

		if ( is_wp_error( $client ) ) {
			return $client;
		}

		$index_name = $this->get_algolia_index_name_from_url( get_site_url() );

		return $client->initIndex( $index_name );
	}

	/**
	 * Create an Algolia client using stored credentials.
	 *
	 * @return \Algolia\AlgoliaSearch\SearchClient|\WP_Error SearchClient on success, WP_Error if creds are missing.
	 */
	public function get_client() {
		$creds = $this->get_creds();

		$app_id        = $creds['app_id'];
		$write_api_key = $creds['write_key'];

		if ( '' === $app_id || '' === $write_api_key ) {
			return new \WP_Error(
				'algolia_credentials_missing',
				__( 'Algolia admin credentials missing.', 'onesearch' )
			);
		}

		return SearchClient::create( $app_id, $write_api_key );
	}

	/**
	 * Build index name from a site URL.
	 *
	 * @param string $site_url Optional site URL. Defaults to current site.
	 *
	 * @return string Index name (e.g., onesearch_example_com_wp_posts).
	 */
	public function get_algolia_index_name_from_url( $site_url = '' ) {

		if ( empty( $site_url ) ) {
			$site_url = get_site_url();
		}

		$parsed_url = wp_parse_url( $site_url );
		$site_name  = isset( $parsed_url['host'] ) ? $parsed_url['host'] : '';

		$site_name = str_replace( '.', '_', $site_name );

		return sprintf( 'onesearch_%s_wp_posts', sanitize_title( $site_name ) );
	}

	/**
	 * Return Algolia index instances for selected child/remote sites.
	 *
	 * Behavior:
	 * - On governing site: reads local selection for current site URL.
	 * - On brand site: intersects local selection with server-provided availability.
	 *
	 * @return array|\WP_Error Array of index instances, empty array when none, or WP_Error on failure.
	 */
	public function get_child_sites_indices() {

		$site_type = (string) get_option( 'onesearch_site_type', '' );

		if ( 'governing-site' === $site_type ) {
			// Parent: use local data.
			$search_config  = get_option( 'onesearch_sites_search_settings', [] );
			$selected_sites = $search_config[ trailingslashit( get_site_url() ) ] ?? [];
			$child_sites    = $selected_sites['searchable_sites'] ?? [];
		} else {
			// Brand: intersect local selection with governing-available sites.
			$available_sites = Governing_Data::get_searchable_sites();

			if ( empty( $available_sites ) ) {
				return [];
			}

			$selected_sites = Governing_Data::get_search_settings();
			$selected_sites = $selected_sites['searchable_sites'] ?? [];

			if ( ! empty( $selected_sites ) ) {
				$valid_selected_sites = array_intersect( $selected_sites, $available_sites );
				$child_sites          = $valid_selected_sites;
			} else {
				$child_sites = [];
			}
		}

		$client = $this->get_client();

		if ( is_wp_error( $client ) ) {
			return $client;
		}

		$child_index_names = [];

		foreach ( $child_sites as $site ) {
			try {
				$child_index_names[] = $client->initIndex( $this->get_algolia_index_name_from_url( $site ) );
			} catch ( \Throwable $e ) {
				continue;
			}
		}

		return $child_index_names;
	}
}
