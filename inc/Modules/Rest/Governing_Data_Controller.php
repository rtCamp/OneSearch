<?php
/**
 * Routes for Search-related operations.
 *
 * @package OneSearch
 */

namespace OneSearch\Modules\Rest;

use OneSearch\Modules\Search\Settings as Search_Settings;
use OneSearch\Modules\Settings\Settings;
use OneSearch\Utils;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class Governing_Data_Controller
 */
class Governing_Data_Controller extends Abstract_REST_Controller {

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		if ( Settings::is_governing_site() ) {
			register_rest_route(
				self::NAMESPACE,
				'/brand-config',
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_brand_config' ],
					'permission_callback' => [ $this, 'check_api_permissions' ],
				]
			);

			return;
		}

		// Prime the config cache on brand sites.
		register_rest_route(
			self::NAMESPACE,
			'/brand-config',
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_brand_config_cache' ],
				'permission_callback' => [ $this, 'check_governing_site_permissions' ],
			]
		);
	}

	/**
	 * Get consolidated configuration for a brand site.
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Request.
	 */
	public function get_brand_config( $request ): WP_REST_Response|\WP_Error {
		// Get the origin from the request headers and confirm it's a known site.
		$origin   = $request->get_header( 'origin' );
		$origin   = ! empty( $origin ) ? esc_url_raw( wp_unslash( $origin ) ) : '';
		$site_url = Utils::normalize_url( $origin );

		if ( empty( $site_url ) || ! $this->is_allowed_site( $site_url ) ) {
			return new \WP_Error(
				'onesearch_unauthorized_site',
				__( 'The requesting site is not a shared brand site.', 'onesearch' ),
				[ 'status' => 403 ]
			);
		}

		// Get Algolia credentials.
		$creds = Search_Settings::get_algolia_credentials();

		// Get search settings for this specific site.
		$all_search_settings = Search_Settings::get_search_settings();
		$site_search_config  = $all_search_settings[ $site_url ] ?? [
			'algolia_enabled'  => false,
			'searchable_sites' => [],
		];

		// Get indexable entities for this specific site.
		$all_indexable_entities = Search_Settings::get_indexable_entities();
		$entities_map           = isset( $all_indexable_entities['entities'] ) && is_array( $all_indexable_entities['entities'] )
			? $all_indexable_entities['entities']
			: [];
		$site_entities          = $entities_map[ $site_url ] ?? [];

		// Get all available sites (for searchable_sites to be meaningful).
		$shared_sites    = Settings::get_shared_sites();
		$searchable_urls = array_keys( $shared_sites );
		// Add governing site itself.
		$searchable_urls[] = trailingslashit( get_site_url() );

		return rest_ensure_response(
			[
				'success'             => true,
				'algolia_credentials' => [
					'app_id'    => $creds['app_id'] ?? '',
					'write_key' => $creds['write_key'] ?? '',
				],
				'search_settings'     => [
					'algolia_enabled'  => $site_search_config['algolia_enabled'],
					'searchable_sites' => $site_search_config['searchable_sites'],
				],
				'indexable_entities'  => is_array( $site_entities ) ? $site_entities : [],
				'available_sites'     => array_values( array_unique( $searchable_urls ) ),
			]
		);
	}

	/**
	 * Deletes the config cache for the brand site.
	 */
	public function delete_brand_config_cache(): WP_REST_Response {
		delete_transient( Governing_Data_Handler::TRANSIENT_KEY );

		return rest_ensure_response(
			[
				'success' => true,
				'message' => __( 'Brand configuration cache cleared successfully.', 'onesearch' ),
			]
		);
	}

	/**
	 * Check whether the request is from a known brand site.
	 *
	 * @param string $origin The origin URL.
	 */
	private function is_allowed_site( string $origin ): bool {
		$shared_sites = Settings::get_shared_sites();

		return isset( $shared_sites[ $origin ] );
	}
}
