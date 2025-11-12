<?php
/**
 * Basic REST routes for OneSearch.
 *
 * Exposes administration and utility endpoints for configuring sites,
 * credentials, search settings, and indexing.
 *
 * @package OneSearch
 */

namespace Onesearch\Inc\REST;

use Algolia\AlgoliaSearch\SearchClient;
use Onesearch\Contracts\Traits\Singleton;
use Onesearch\Inc\Algolia\Algolia;
use Onesearch\Inc\Algolia\Algolia_Index;
use Onesearch\Inc\Algolia\Algolia_Index_By_Post;
use Onesearch\Modules\Rest\Governing_Data;
use Onesearch\Modules\Settings\Settings;
use Onesearch\Utils;
use WP_REST_Server;

/**
 * Class Basic_Options
 *
 * Registers REST routes and provides handlers for OneSearch settings and actions.
 */
class Basic_Options {

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
	 * Initialize and register hooks.
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function setup_hooks(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {

		// Site type: get / set.
		register_rest_route(
			self::NAMESPACE,
			'/site-type',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_site_type' ],
					'permission_callback' => static function () {
						return current_user_can( 'manage_options' );
					},
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'set_site_type' ],
					'permission_callback' => static function () {
						return current_user_can( 'manage_options' );
					},
					'args'                => [
						'site_type' => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
			]
		);

		// Secret key: get / regenerate.
		register_rest_route(
			self::NAMESPACE,
			'/secret-key',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => static function () {
						$api_key = Settings::get_api_key();

						return new \WP_REST_Response(
							[
								'success'    => ! empty( $api_key ),
								'secret_key' => $api_key,
							]
						);
					},
					'permission_callback' => static function () {
						return current_user_can( 'manage_options' );
					},
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => static function () {
						$api_key = Settings::regenerate_api_key();

						return new \WP_REST_Response(
							[
								'success'    => true,
								'message'    => __( 'Secret key regenerated successfully.', 'onesearch' ),
								'secret_key' => $api_key,
							]
						);
					},
					'permission_callback' => static function () {
						return current_user_can( 'manage_options' );
					},
				],
			]
		);

		// Shared sites (name, URL, public key): get / set.
		register_rest_route(
			self::NAMESPACE,
			'/shared-sites',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_shared_sites' ],
					'permission_callback' => static function () {
						return current_user_can( 'manage_options' );
					},
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'set_shared_sites' ],
					'permission_callback' => static function () {
						return current_user_can( 'manage_options' );
					},
					'args'                => [
						'sites_data' => [
							'required'          => true,
							'type'              => 'array',
							'sanitize_callback' => static function ( $value ) {
								return is_array( $value );
							},
						],
					],
				],
			]
		);

		// Indexable entities (per site URL): get / set.
		register_rest_route(
			self::NAMESPACE,
			'/indexable-entities',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_indexable_entities' ],
					'permission_callback' => '__return_true',
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'set_indexable_entities' ],
					'permission_callback' => static function () {
						return current_user_can( 'manage_options' );
					},
					'args'                => [
						'entities' => [
							'required'          => true,
							'type'              => 'array',
							'sanitize_callback' => static function ( $value ) {
								return is_array( $value );
							},
						],
					],
				],
			]
		);

		// Health check for the site.
		register_rest_route(
			self::NAMESPACE,
			'/health-check',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'health_check' ],
				'permission_callback' => [ $this, 'validate_api_key' ],
			]
		);

		// Re-index current site (and children for governing sites).
		register_rest_route(
			self::NAMESPACE,
			'/re-index',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 're_index' ],
				'permission_callback' => [ $this, 'permission_admin_or_token' ],
			]
		);

		// Public post types (local and, for governing, aggregated from children).
		register_rest_route(
			self::NAMESPACE,
			'/all-post-types',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_all_post_types' ],
				'permission_callback' => [ $this, 'permission_admin_or_token' ],
			]
		);

		// Algolia credentials: get / set.
		register_rest_route(
			self::NAMESPACE,
			'/algolia-credentials',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_algolia_credentials' ],
					'permission_callback' => '__return_true',
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'save_algolia_credentials' ],
					'permission_callback' => [ $this, 'permission_admin_or_token' ],
				],
			]
		);

		// Searchable sites (for child): list the sites the child may search.
		register_rest_route(
			self::NAMESPACE,
			'/searchable-sites',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_searchable_sites_for_child' ],
					'permission_callback' => '__return_true',
				],
			]
		);

		// Cache busting endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/bust-creds-cache',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'bust_creds_cache' ],
				'permission_callback' => [ $this, 'permission_admin_or_token' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/bust-sites-cache',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'bust_sites_cache' ],
				'permission_callback' => [ $this, 'permission_admin_or_token' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/bust-search-settings-cache',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'bust_search_settings_cache' ],
				'permission_callback' => [ $this, 'permission_admin_or_token' ],
			]
		);

		// Set and delete governing site URL on child.
		register_rest_route(
			self::NAMESPACE,
			'/governing-url',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'set_governing_url' ],
					'permission_callback' => [ $this, 'permission_admin_or_token' ],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_governing_url' ],
					'permission_callback' => [ $this, 'permission_admin_or_token' ],
				],
			]
		);

		// Governing: read/update search settings for all brand sites.
		register_rest_route(
			self::NAMESPACE,
			'/sites-search-settings',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_sites_search_settings' ],
					'permission_callback' => static function () {
						return current_user_can( 'manage_options' );
					},
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'update_sites_search_settings' ],
					'permission_callback' => static function () {
						return current_user_can( 'manage_options' );
					},
					'args'                => [
						'settings' => [
							'required' => true,
							'type'     => 'object',
						],
					],
				],
			]
		);

		// Brand: cached search settings for the requesting brand site.
		register_rest_route(
			self::NAMESPACE,
			'/search-settings',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_search_settings_for_brand' ],
				'permission_callback' => '__return_true',
			]
		);

		// Delete a brand site from the governing site's list.
		register_rest_route(
			self::NAMESPACE,
			'/delete-site',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'delete_site' ],
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'args'                => [
					'site_index' => [
						'required' => true,
						'type'     => 'integer',
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/governing-site-info',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_parent_url' ],
					'permission_callback' => static function () {
						return current_user_can( 'manage_options' );
					},
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_governing_url' ],
					'permission_callback' => static function () {
						return current_user_can( 'manage_options' );
					},
				],
			]
		);

		/**
		 * Governing: REST endpoint to receive brand requests.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/reindex-post',
			[
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'reindex_post' ],
					'permission_callback' => '__return_true',
				],
			]
		);
	}

	/**
	 * Reindex a post from a child (brand) site.
	 *
	 * @param \WP_REST_Request $request The incoming REST API request.
	 *
	 * @return \WP_REST_Response|\WP_Error The response with indexing result or error.
	 */
	public function reindex_post( \WP_REST_Request $request ) {
		if ( ! Settings::is_governing_site() ) {
			return new \WP_Error( 'forbidden', __( 'Only governing site can reindex posts.', 'onesearch' ), [ 'status' => 403 ] );
		}

		$incoming_key = (string) ( $request->get_header( 'X-OneSearch-Plugins-Token' ) ?? '' );

		if ( empty( $incoming_key ) || ! Algolia_Index_By_Post::instance()->is_valid_child_token( $incoming_key ) ) {
			return new \WP_Error( 'invalid_api_key', __( 'Invalid or missing API key.', 'onesearch' ), [ 'status' => 403 ] );
		}

		$body        = json_decode( (string) $request->get_body(), true ) ?: [];
		$records     = (array) ( $body['records'] ?? [] );
		$site_url    = Utils::normalize_url( sanitize_url( (string) ( $body['site_url'] ?? '' ) ) );
		$post_id     = absint( ( $body['post_id'] ?? 0 ) );
		$post_type   = sanitize_text_field( wp_unslash( (string) ( $body['post_type'] ?? '' ) ) );
		$post_status = sanitize_text_field( wp_unslash( (string) ( $body['post_status'] ?? '' ) ) );

		if ( empty( $site_url ) || empty( $post_id ) ) {
			return new \WP_Error( 'bad_request', __( 'Missing site_url or post_id.', 'onesearch' ), [ 'status' => 400 ] );
		}

		$result = Algolia_Index_By_Post::instance()->governing_handle_change( $site_url, $post_id, $post_type, $post_status, $records );

		return rest_ensure_response( $result );
	}

	/**
	 * Return the saved governing site URL, or empty.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_parent_url(): \WP_REST_Response {
		$url = Settings::get_parent_site_url();

		return rest_ensure_response(
			[
				'success'         => true,
				'parent_site_url' => $url,
			]
		);
	}

	/**
	 * Delete a site from the shared list and perform cleanup steps.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_site( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {

		$site_index = (int) $request->get_param( 'site_index' );

		$sites_data = array_values( Settings::get_shared_sites() );

		if ( ! isset( $sites_data[ $site_index ] ) ) {
			return new \WP_Error(
				'site_not_found',
				__( 'Site not found.', 'onesearch' ),
				[ 'status' => 404 ]
			);
		}

		$site_to_delete = $sites_data[ $site_index ];
		$site_url       = empty( $site_to_delete['siteUrl'] ) ? '' : trailingslashit( $site_to_delete['siteUrl'] );
		$site_key       = $site_to_delete['publicKey'] ?? '';

		if ( empty( $site_url ) || empty( $site_key ) ) {
			return new \WP_Error(
				'invalid_site_data',
				__( 'Invalid site data.', 'onesearch' ),
				[ 'status' => 400 ]
			);
		}

		$deletion_results = [];

		// 1) Bust caches on the site being deleted.
		$cache_results                  = $this->bust_site_all_caches( $site_url, $site_key );
		$deletion_results['cache_bust'] = $cache_results;

		// 2) Delete governing site url option on the child.
		$parent_url_delete_result              = $this->delete_site_governing_url_remote( $site_url, $site_key );
		$deletion_results['parent_url_option'] = $parent_url_delete_result;

		// 3) Delete Algolia index for this site.
		$algolia_result                       = $this->delete_site_from_index( $site_url );
		$deletion_results['algolia_deletion'] = $algolia_result;

		// 4) Remove site from option.
		unset( $sites_data[ $site_index ] );
		$sites_data = array_values( $sites_data );

		// 5) Reuse set_shared_sites logic by mocking a request with updated payload.
		$mock_request = new \WP_REST_Request();
		$mock_request->set_body( wp_json_encode( [ 'sites_data' => $sites_data ] ) ?: '' );

		$shared_sites_result = $this->set_shared_sites( $mock_request );
		if ( is_wp_error( $shared_sites_result ) ) {
			return $shared_sites_result;
		}

		$shared_sites_data                       = $shared_sites_result->get_data();
		$deletion_results['shared_sites_update'] = $shared_sites_data['push_results'] ?? [];

		// 6) Remove site from indexable entities if exists.
		$governing_entities = $this->get_governing_entities_map();

		if ( ! array_key_exists( $site_url, $governing_entities ) ) {
			$deletion_results['governing_entities_update'] = __( 'Skipped: no governing-entity data for this site.', 'onesearch' );
		} else {
			unset( $governing_entities[ $site_url ] );

			$mock_request = new \WP_REST_Request();
			$mock_request->set_body( wp_json_encode( [ 'entities' => $governing_entities ] ) ?: '' );

			$governing_entities_result = $this->set_indexable_entities( $mock_request );
			if ( is_wp_error( $governing_entities_result ) ) {
				return $governing_entities_result;
			}

			$governing_entities_data                       = $governing_entities_result->get_data();
			$deletion_results['governing_entities_update'] = $governing_entities_data['message'] ?? [];
		}

		return rest_ensure_response(
			[
				'success'          => true,
				'message'          => __( 'Site deleted successfully.', 'onesearch' ),
				'deleted_site'     => $site_to_delete,
				'remaining_sites'  => count( $sites_data ),
				'deletion_results' => $deletion_results,
			]
		);
	}

	/**
	 * Delete the governing site URL from brand site.
	 *
	 * @param string $site_url Target site URL.
	 * @param string $site_key Target site public key.
	 *
	 * @return string The response of the API call.
	 */
	public function delete_site_governing_url_remote( string $site_url, string $site_key ): string {
		$endpoint = trailingslashit( $site_url ) . 'wp-json/' . self::NAMESPACE . '/governing-url';

		$response = wp_remote_request(
			$endpoint,
			[
				'method'  => 'DELETE',
				'headers' => [
					'Accept'                    => 'application/json',
					'Content-Type'              => 'application/json',
					'X-OneSearch-Plugins-Token' => $site_key,
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return sprintf(
				/* translators: %s: error message */
				__( 'Error: %s', 'onesearch' ),
				$response->get_error_message()
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 === $code ) {
			return __( 'Success', 'onesearch' );
		}

		return sprintf(
			/* translators: %d: status code */
			__( 'Failed: HTTP %d', 'onesearch' ),
			$code
		);
	}

	/**
	 * Bust all relevant caches on a specific brand site.
	 *
	 * @param string $site_url Target site URL.
	 * @param string $site_key Target site public key.
	 *
	 * @return array<string, string> Results of each cache-busting attempt.
	 */
	private function bust_site_all_caches( string $site_url, string $site_key ): array {
		$sanitized_url = trailingslashit( (string) esc_url_raw( $site_url ) );
		$site_key      = (string) trim( $site_key );

		$endpoints = [
			'bust-creds-cache',
			'bust-sites-cache',
			'bust-search-settings-cache',
		];

		$results = [];

		foreach ( $endpoints as $endpoint ) {
			$bust_endpoint = trailingslashit( $sanitized_url ) . 'wp-json/' . self::NAMESPACE . '/' . $endpoint;

			$response = wp_remote_post(
				$bust_endpoint,
				[
					'headers' => [
						'Accept'                    => 'application/json',
						'Content-Type'              => 'application/json',
						'X-OneSearch-Plugins-Token' => $site_key,
					],
					'body'    => wp_json_encode( [] ) ?: '',
				]
			);

			if ( is_wp_error( $response ) ) {
				$results[ $endpoint ] = sprintf(
					/* translators: %s: error message */
					__( 'Error: %s', 'onesearch' ),
					$response->get_error_message()
				);
			} elseif ( 200 === wp_remote_retrieve_response_code( $response ) ) {
				$results[ $endpoint ] = __( 'Success', 'onesearch' );
			} else {
				$results[ $endpoint ] = sprintf(
					/* translators: %d: HTTP status code */
					__( 'Failed: HTTP %d', 'onesearch' ),
					wp_remote_retrieve_response_code( $response )
				);
			}
		}

		return $results;
	}

	/**
	 * Clear the cached search settings configuration.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function bust_search_settings_cache(): \WP_REST_Response|\WP_Error {

		Governing_Data::clear_search_settings_cache();

		return rest_ensure_response(
			[
				'success' => true,
				'message' => __( 'Search settings cache cleared successfully.', 'onesearch' ),
			]
		);
	}

	/**
	 * Return the saved sites-search settings (governing).
	 *
	 * @return \WP_REST_Response
	 */
	public function get_sites_search_settings() {
		$settings = Settings::get_search_settings();
		return rest_ensure_response(
			[
				'success'  => true,
				'settings' => $settings,
			]
		);
	}

	/**
	 * Update sites-search settings (governing) and bust caches on brand sites.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function update_sites_search_settings( \WP_REST_Request $request ) {
		$settings = $request->get_param( 'settings' );

		update_option( Settings::OPTION_GOVERNING_SEARCH_SETTINGS, $settings );

		// Notify each brand site to refresh its cached search settings.
		$this->bust_search_settings_cache_on_all_sites();

		return rest_ensure_response(
			[
				'success' => true,
				'message' => __( 'Search settings updated successfully.', 'onesearch' ),
			]
		);
	}

	/**
	 * Return search settings for the requesting brand site.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_search_settings_for_brand( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$incoming_key = (string) ( $request->get_header( 'X-OneSearch-Plugins-Token' ) ?? '' );
		$is_admin     = current_user_can( 'manage_options' );

		// Allow either admin or a valid child-site token.
		if ( ! $is_admin ) {
			if ( empty( $incoming_key ) || ! $this->is_valid_child_site_key( $incoming_key ) ) {
				return new \WP_Error(
					'invalid_api_key',
					__( 'Invalid or missing API key.', 'onesearch' ),
					[ 'status' => 403 ]
				);
			}
		}

		$requesting_site_url = $this->get_site_url_from_key( $incoming_key );
		if ( empty( $requesting_site_url ) ) {
			return new \WP_Error( 'invalid_site', __( 'Could not identify requesting site.', 'onesearch' ), [ 'status' => 400 ] );
		}

		$all_settings = Settings::get_search_settings();
		$site_config  = $all_settings[ $requesting_site_url ] ?? [
			'algolia_enabled'  => false,
			'searchable_sites' => [],
		];

		return rest_ensure_response(
			[
				'success' => true,
				'config'  => $site_config,
			]
		);
	}

	/**
	 * Ask all brand sites to clear their cached search settings.
	 *
	 * @return void
	 */
	private function bust_search_settings_cache_on_all_sites() {
		$shared_sites = Settings::get_shared_sites();

		foreach ( $shared_sites as $site ) {
			if ( empty( $site['siteUrl'] ) || empty( $site['publicKey'] ) ) {
				continue;
			}

			$endpoint = trailingslashit( $site['siteUrl'] ) . 'wp-json/' . self::NAMESPACE . '/bust-search-settings-cache';

			wp_remote_post(
				$endpoint,
				[
					'headers' => [
						'X-OneSearch-Plugins-Token' => $site['publicKey'],
					],
				]
			);
		}
	}

	/**
	 * Resolve a site URL from a stored public key.
	 *
	 * @param string $public_key Site public key.
	 *
	 * @return string Site URL (trailed) or empty string.
	 */
	private function get_site_url_from_key( $public_key ) {
		$shared_sites = Settings::get_shared_sites();

		foreach ( $shared_sites as $site ) {
			if ( isset( $site['publicKey'] ) && $site['publicKey'] === $public_key ) {
				return trailingslashit( $site['siteUrl'] );
			}
		}

		return '';
	}

	/**
	 * Set the governing (parent) site URL on a brand site.
	 *
	 * @param \WP_REST_Request $request Request object with parent_site_url.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function set_governing_url( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {

		$body       = json_decode( $request->get_body(), true );
		$parent_url = $body['parent_site_url'] ?? '';

		if ( empty( $parent_url ) || ! filter_var( $parent_url, FILTER_VALIDATE_URL ) ) {
			return new \WP_Error( 'invalid_url', __( 'Valid parent site URL is required.', 'onesearch' ), [ 'status' => 400 ] );
		}

		Settings::set_parent_site_url( esc_url_raw( $parent_url ) );

		return rest_ensure_response(
			[
				'success' => true,
				'message' => __( 'Parent site URL set successfully.', 'onesearch' ),
			]
		);
	}

	/**
	 * Delete the governing site URL from current site.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_governing_url(): \WP_REST_Response|\WP_Error {
		delete_option( Settings::OPTION_CONSUMER_PARENT_SITE_URL );

		// Also delete search setting to prevent brand site taking data from other sites.
		Governing_Data::clear_search_settings_cache();

		return rest_ensure_response(
			[
				'success' => true,
				'message' => __( 'Parent site URL removed.', 'onesearch' ),
			]
		);
	}

	/**
	 * Clear the cached Algolia credentials.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function bust_creds_cache(): \WP_REST_Response|\WP_Error {

		Governing_Data::clear_credentials_cache();

		return rest_ensure_response(
			[
				'success' => true,
				'message' => __( 'Cache cleared successfully.', 'onesearch' ),
			]
		);
	}

	/**
	 * Clear the cached searchable sites data.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function bust_sites_cache(): \WP_REST_Response|\WP_Error {

		Governing_Data::clear_searchable_sites_cache();

		return rest_ensure_response(
			[
				'success' => true,
				'message' => __( 'Cache cleared successfully.', 'onesearch' ),
			]
		);
	}

	/**
	 * Return the list of URLs the requesting brand site can search.
	 *
	 * @param \WP_REST_Request $request Request object (validates token for non-admin).
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_searchable_sites_for_child( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {

		$incoming_key = (string) ( $request->get_header( 'X-OneSearch-Plugins-Token' ) ?? '' );
		$is_admin     = current_user_can( 'manage_options' );

		if ( ! $is_admin ) {
			if ( empty( $incoming_key ) || ! $this->is_valid_child_site_key( $incoming_key ) ) {
				return new \WP_Error(
					'invalid_api_key',
					__( 'Invalid or missing API key.', 'onesearch' ),
					[ 'status' => 403 ]
				);
			}
		}

		$shared_sites    = Settings::get_shared_sites();
		$searchable_urls = [];

		foreach ( $shared_sites as $site ) {
			if ( ! isset( $site['siteUrl'] ) ) {
				continue;
			}

			$searchable_urls[] = (string) $site['siteUrl'];
		}

		$searchable_urls[] = trailingslashit( get_site_url() );

		return rest_ensure_response(
			[
				'success'          => true,
				'searchable_sites' => array_unique( $searchable_urls ),
			]
		);
	}

	/**
	 * Return saved Algolia credentials to authorized callers.
	 *
	 * @param \WP_REST_Request $request Request object (validates token for non-admin).
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_algolia_credentials( \WP_REST_Request $request ) {
		$incoming_key = (string) ( $request->get_header( 'X-OneSearch-Plugins-Token' ) ?? '' );
		$is_admin     = current_user_can( 'manage_options' );

		if ( ! $is_admin ) {
			if ( empty( $incoming_key ) || ! $this->is_valid_child_site_key( $incoming_key ) ) {
				return new \WP_Error(
					'invalid_api_key',
					__( 'Invalid or missing API key.', 'onesearch' ),
					[ 'status' => 403 ]
				);
			}
		}

		$creds = Settings::get_algolia_credentials();

		return new \WP_REST_Response(
			[
				'success'   => true,
				'app_id'    => $creds['app_id'] ?? '',
				'write_key' => $creds['write_key'] ?? '',
				'admin_key' => $creds['admin_key'] ?? '',
			]
		);
	}

	/**
	 * Validate that a provided key matches a known child site.
	 *
	 * @param string $key Candidate public key.
	 *
	 * @return bool
	 */
	private function is_valid_child_site_key( string $key ): bool {
		$shared_sites = Settings::get_shared_sites();

		foreach ( $shared_sites as $site ) {
			$site_key = isset( $site['publicKey'] ) ? (string) $site['publicKey'] : '';
			if ( ! empty( $site_key ) && $site_key === $key ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Save Algolia credentials (admin or exact token required).
	 *
	 * @param \WP_REST_Request $request Request object with app_id/write_key/admin_key.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save_algolia_credentials( \WP_REST_Request $request ) {

		$body = json_decode( (string) $request->get_body(), true ) ?: [];

		$app_id    = isset( $body['app_id'] ) ? sanitize_text_field( wp_unslash( $body['app_id'] ) ) : '';
		$write_key = isset( $body['write_key'] ) ? sanitize_text_field( wp_unslash( $body['write_key'] ) ) : '';
		$admin_key = isset( $body['admin_key'] ) ? sanitize_text_field( wp_unslash( $body['admin_key'] ) ) : '';

		if ( empty( $app_id ) ) {
			return new \WP_Error( 'missing_app_id', __( 'Application ID is required.', 'onesearch' ), [ 'status' => 400 ] );
		}

		if ( empty( $write_key ) && empty( $admin_key ) ) {
			return new \WP_Error( 'missing_keys', __( 'Provide a Write API Key.', 'onesearch' ), [ 'status' => 400 ] );
		}

		$key_validation_result = $this->check_valid_api_key( $app_id, $write_key );

		if ( is_wp_error( $key_validation_result ) ) {
			return $key_validation_result;
		}

		$new = [
			'app_id'    => $app_id,
			'write_key' => $write_key,
			'admin_key' => $admin_key,
		];

		$success = Settings::set_algolia_credentials( $new );

		if ( false === $success ) {
			return new \WP_Error( 'update_failed', __( 'Failed to update Algolia credentials.', 'onesearch' ), [ 'status' => 500 ] );
		}

		// If governing: instruct all child sites to clear their credentials cache.
		$bust_results = [];

		if ( Settings::is_governing_site() ) {
			$child_sites = Settings::get_shared_sites();

			if ( ! empty( $child_sites ) ) {
				foreach ( $child_sites as $child ) {
					$raw_url = isset( $child['siteUrl'] ) ? (string) $child['siteUrl'] : '';
					$url     = rtrim( $raw_url, '/' );
					$key     = isset( $child['publicKey'] ) ? (string) $child['publicKey'] : '';

					if ( empty( $url ) || empty( $key ) ) {
						$bust_results[ $url ?: '(missing)' ] = __( 'Missing URL or key.', 'onesearch' );
						continue;
					}

					$bust_endpoint = trailingslashit( $url ) . 'wp-json/' . self::NAMESPACE . '/bust-creds-cache';

					$bust_response = wp_remote_post(
						$bust_endpoint,
						[
							'headers' => [
								'Accept'       => 'application/json',
								'Content-Type' => 'application/json',
								'X-OneSearch-Plugins-Token' => $key,
							],
							'body'    => wp_json_encode( [] ) ?: '',
						]
					);

					if ( is_wp_error( $bust_response ) ) {
						$bust_results[ $url ] = sprintf(
							/* translators: %s: error message */
							__( 'Error: %s', 'onesearch' ),
							$bust_response->get_error_message()
						);
					} else {
						$code                 = (int) wp_remote_retrieve_response_code( $bust_response );
						$bust_results[ $url ] = 200 === $code
						? __( 'Cache cleared.', 'onesearch' )
						: sprintf(
							/* translators: %d: HTTP status code */
							__( 'Failed: HTTP %d', 'onesearch' ),
							$code
						);
					}
				}
			}
		}

		$response_data = [
			'success' => true,
			'message' => __( 'Algolia credentials saved.', 'onesearch' ),
		];

		if ( ! empty( $bust_results ) ) {
			$response_data['cache_bust_results'] = $bust_results;
		}

		return rest_ensure_response( $response_data );
	}

	/**
	 * Check if the provided API key has write permissions.
	 *
	 * @param string $app_id  Algolia Application ID.
	 * @param string $api_key API key to validate.
	 *
	 * @return true|\WP_Error True if valid write key, WP_Error otherwise.
	 */
	public function check_valid_api_key( $app_id, $api_key ) {

		if ( empty( $app_id ) || empty( $api_key ) ) {
			return new \WP_Error(
				'missing_credentials',
				__( 'Application ID and API key are required.', 'onesearch' ),
				[ 'status' => 400 ]
			);
		}

		try {
			// Create Algolia client with provided credentials.
			$client = SearchClient::create( $app_id, $api_key );

			// Try to get API key information to check permissions (ACL).
			$key_info = $client->getApiKey( $api_key );

			// Check if key has required write permissions.
			$acl = $key_info['acl'] ?? [];

			// Required permissions for write operations.
			$required_permissions = [ 'addObject', 'deleteObject' ];

			// Check if the key has write permissions.
			$has_write_permissions = false;
			foreach ( $required_permissions as $permission ) {
				if ( in_array( $permission, $acl, true ) ) {
					$has_write_permissions = true;
					break;
				}
			}

			// Admin keys have all permissions, so check for that too.
			if ( ! $has_write_permissions && in_array( 'editSettings', $acl, true ) ) {
				$has_write_permissions = true; // Admin key.
			}

			if ( ! $has_write_permissions ) {
				return new \WP_Error(
					'insufficient_permissions',
					__( 'The provided API key does not have write permissions. Please provide a Write API Key.', 'onesearch' ),
					[ 'status' => 403 ]
				);
			}

			// Additional validation: try to perform a simple write operation.
			$test_index_name = 'onesearch_validation_test_' . time();
			$test_index      = $client->initIndex( $test_index_name );

			// Try to add a test object.
			$test_object = [
				'objectID' => 'test_validation',
				'test'     => 'validation',
			];

			$test_index->saveObject( $test_object )->wait();

			// Clean up: delete the test object and index.
			$test_index->deleteObject( 'test_validation' )->wait();
			$test_index->delete()->wait();

			return true;
		} catch ( \Throwable $e ) {
			$error_message = $e->getMessage();

			if ( strpos( $error_message, 'Invalid Application-Id or API key' ) !== false ) {
				return new \WP_Error(
					'invalid_credentials',
					__( 'Invalid Application ID or API key. Please check your credentials.', 'onesearch' ),
					[ 'status' => 401 ]
				);
			}

			if ( strpos( $error_message, 'operation not allowed' ) !== false ||
				strpos( $error_message, 'write operation' ) !== false ) {
				return new \WP_Error(
					'read_only_key',
					__( 'The provided API key appears to be a Search-Only key. Please provide a Write API Key instead.', 'onesearch' ),
					[ 'status' => 403 ]
				);
			}

			return new \WP_Error(
				'validation_failed',
				/* translators: %s: error message */
				sprintf( __( 'API key validation failed: %s', 'onesearch' ), $error_message ),
				[ 'status' => 400 ]
			);
		}
	}

	/**
	 * Build payload of public post types for this site.
	 *
	 * @return array{
	 *  slug: string,
	 *  label: string,
	 *  restBase: string,
	 * }[]
	 */
	private function get_public_post_types_payload(): array {
		$objects = get_post_types( [ 'public' => true ], 'objects' );
		$payload = [];

		foreach ( $objects as $slug => $obj ) {
			$payload[] = [
				'slug'     => $slug,
				'label'    => isset( $obj->labels->name ) ? (string) $obj->labels->name : $slug,
				'restBase' => ! empty( $obj->rest_base ) ? (string) $obj->rest_base : $slug,
			];
		}

		return $payload;
	}

	/**
	 * Identify the current site.
	 *
	 * @return array{
	 *   site_url: string,
	 *   site_name: string,
	 * }
	 */
	private function get_local_site_identity(): array {
		return [
			'site_url'  => trailingslashit( get_site_url() ),
			'site_name' => (string) get_bloginfo( 'name' ),
		];
	}

	/**
	 * Return public post types for the current site (and children if governing).
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_all_post_types(): \WP_REST_Response|\WP_Error {

		$current_identity = $this->get_local_site_identity();
		$sites            = [
			$current_identity['site_url'] => array_merge(
				$current_identity,
				[ 'post_types' => $this->get_public_post_types_payload() ]
			),
		];

		$errors = [];

		if ( Settings::is_governing_site() ) {
			$child_sites = Settings::get_shared_sites();

			foreach ( $child_sites as $child ) {
				$child_site_url = isset( $child['siteUrl'] ) ? rtrim( (string) $child['siteUrl'], '/' ) : '';
				$child_site_key = isset( $child['publicKey'] ) ? (string) $child['publicKey'] : '';

				if ( empty( $child_site_url ) || empty( $child_site_key ) ) {
					$errors[] = [
						'site_url' => $child_site_url ?: '(missing)',
						'message'  => __( 'Missing siteUrl or publicKey.', 'onesearch' ),
					];
					continue;
				}

				$endpoint = trailingslashit( $child_site_url ) . 'wp-json/' . self::NAMESPACE . '/all-post-types';

				$response = wp_remote_get(
					$endpoint,
					[
						'headers' => [
							'Accept'                    => 'application/json',
							'X-OneSearch-Plugins-Token' => $child_site_key,
						],
					]
				);

				if ( is_wp_error( $response ) ) {
					$errors[] = [
						'site_url' => $child_site_url,
						'message'  => $response->get_error_message(),
					];
					continue;
				}

				$code = (int) wp_remote_retrieve_response_code( $response );

				if ( 200 !== $code ) {
					$errors[] = [
						'site_url' => $child_site_url,
						'message'  => sprintf(
							/* translators: %s: status code */
							__( 'HTTP: %s', 'onesearch' ),
							$code
						),
					];
					continue;
				}

				$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
				if ( ! is_array( $body ) || ! isset( $body['sites'] ) || ! is_array( $body['sites'] ) ) {
					$errors[] = [
						'site_url' => $child_site_url,
						'message'  => __( 'Malformed JSON response.', 'onesearch' ),
					];
					continue;
				}

				foreach ( $body['sites'] as $url => $site_payload ) {
					if ( ! is_array( $site_payload ) ) {
						continue;
					}

					$sites[ trailingslashit( (string) $url ) ] = $site_payload;
				}
			}
		}

		return rest_ensure_response(
			[
				'success' => true,
				'sites'   => $sites,
				'errors'  => $errors,
			]
		);
	}

	/**
	 * Check that a value is a list of strings.
	 *
	 * @param mixed $val Candidate value.
	 *
	 * @return bool
	 */
	private function is_string_list( $val ): bool {
		if ( ! is_array( $val ) ) {
			return false;
		}
		foreach ( $val as $v ) {
			if ( ! is_string( $v ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Governing: read the full entities map from option storage.
	 *
	 * @return array<string, string[]>
	 */
	private function get_governing_entities_map(): array {
		$opt = Settings::get_indexable_entities();
		$map = isset( $opt['entities'] ) && is_array( $opt['entities'] ) ? $opt['entities'] : [];

		$out = [];
		foreach ( $map as $url => $list ) {
			$key         = Utils::normalize_url( (string) $url );
			$out[ $key ] = $this->is_string_list( $list ) ? array_values( array_unique( array_map( 'strval', $list ) ) ) : [];
		}
		return $out;
	}

	/**
	 * Re-index current site, and for governing sites, trigger re-index on children.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function re_index(): \WP_REST_Response|\WP_Error {
		$current_site_url = Utils::normalize_url( get_site_url() );
		$results          = [];

		if ( Settings::is_governing_site() ) {
			// Index self using governing entities map.
			$entities_map = $this->get_governing_entities_map();
			$my_entities  = isset( $entities_map[ $current_site_url ] ) ? $entities_map[ $current_site_url ] : [];

			$indexed = Algolia_Index::instance()->index( $my_entities );

			$results[ $current_site_url ] = is_wp_error( $indexed )
				? [
					'status'  => 'error',
					'message' => $indexed->get_error_message() ?: __( 'Re-index failed.', 'onesearch' ),
				]
				: [
					'status'  => 'ok',
					'message' => __( 'Re-indexed successfully.', 'onesearch' ),
				];

			// Trigger re-index on each child site (child fetches its own entities).
			$child_sites = Settings::get_shared_sites();

			if ( ! empty( $child_sites ) ) {
				foreach ( $child_sites as $child ) {
					$raw_url = isset( $child['siteUrl'] ) ? (string) $child['siteUrl'] : '';
					$url     = Utils::normalize_url( $raw_url );
					$key     = isset( $child['publicKey'] ) ? (string) $child['publicKey'] : '';

					if ( empty( $url ) || empty( $key ) ) {
						$results[ $url ?: '(missing)' ] = [
							'status'  => 'error',
							'message' => __( 'Missing siteUrl or publicKey for child.', 'onesearch' ),
						];
						continue;
					}

					$endpoint = trailingslashit( $url ) . 'wp-json/' . self::NAMESPACE . '/re-index';

					$response_obj = wp_remote_post(
						$endpoint,
						[
							'headers' => [
								'Accept'       => 'application/json',
								'Content-Type' => 'application/json',
								'X-OneSearch-Plugins-Token' => $key,
							],
							'body'    => wp_json_encode( [] ) ?: '',
							'timeout' => 999, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
						]
					);

					if ( is_wp_error( $response_obj ) ) {
						$results[ $url ] = [
							'status'  => 'error',
							'message' => $response_obj->get_error_message() ?: __( 'Request failed.', 'onesearch' ),
						];
						continue;
					}

					$code = (int) wp_remote_retrieve_response_code( $response_obj );
					$body = (string) wp_remote_retrieve_body( $response_obj );
					$json = json_decode( $body, true );

					if ( 200 !== $code ) {
						$pretty = '';
						if ( is_array( $json ) && isset( $json['message'] ) && is_string( $json['message'] ) ) {
							$pretty = trim( $json['message'] );
						} else {
							$strip  = trim( wp_strip_all_tags( $body ) );
							$pretty = '' !== $strip ? $strip : __( 'Request failed.', 'onesearch' );
						}

						$results[ $url ] = [
							'status'  => 'error',
							'message' => $pretty,
						];
						continue;
					}

					if ( ! is_array( $json ) ) {
						$results[ $url ] = [
							'status'  => 'error',
							'message' => __( 'Invalid response from child site.', 'onesearch' ),
						];
						continue;
					}

					$ok  = (bool) ( $json['success'] ?? false );
					$msg = (string) ( $json['message'] ?? '' );

					$results[ $url ] = [
						'status'  => $ok ? 'ok' : 'error',
						'message' => $msg ?: ( $ok ? __( 'Re-indexed successfully.', 'onesearch' ) : __( 'Re-index failed.', 'onesearch' ) ),
					];
				}
			}
		} else {
			// Brand site: pull entities from parent, then index locally.
			$my_entities = $this->get_child_indexable_entities();

			if ( is_wp_error( $my_entities ) ) {
				return $my_entities;
			}

			try {
				$indexed = Algolia_Index::instance()->index( $my_entities );

				if ( is_wp_error( $indexed ) ) {
					return new \WP_Error( 'reindex_failed', __( 'Re-indexing failed.', 'onesearch' ), [ 'status' => 500 ] );
				}
			} catch ( \Throwable $e ) {
				return new \WP_Error( 'reindex_failed', $e->getMessage(), [ 'status' => 500 ] );
			}

			$results[ $current_site_url ] = [
				'status'  => 'ok',
				'message' => __( 'Re-indexed successfully.', 'onesearch' ),
			];
		}

		$had_errors = (bool) array_filter(
			$results,
			static fn( $r ) => 'error' === $r['status']
		);

		$lines = [];
		foreach ( $results as $site => $r ) {
			$site_label = $site ?: __( '(unknown site)', 'onesearch' );
			$msg        = is_string( $r['message'] ?? '' ) ? $r['message'] : wp_json_encode( $r['message'] );
			$lines[]    = sprintf( '%s: %s', $site_label, $msg ?: ( 'ok' === $r['status'] ? __( 'Re-indexed successfully.', 'onesearch' ) : __( 'Error', 'onesearch' ) ) );
		}

		$final_message = $had_errors
		? implode( "\n", $lines )
		: __( 'Re-indexing completed successfully.', 'onesearch' );

		return rest_ensure_response(
			[
				'success' => ! $had_errors,
				'message' => $final_message,
				'results' => $results,
			]
		);
	}

	/**
	 * Retrieve the indexable entities for this brand site from the parent.
	 *
	 * @return string[]|\WP_Error List of post type slugs or WP_Error on failure.
	 */
	private function get_child_indexable_entities(): array|\WP_Error {
		$parent_url = Settings::get_parent_site_url();

		if ( empty( $parent_url ) ) {
			return new \WP_Error( 'no_parent_url', __( 'Parent site URL not configured.', 'onesearch' ), [ 'status' => 400 ] );
		}

		$current_site_url = Utils::normalize_url( get_site_url() );

		$endpoint = trailingslashit( $parent_url ) . 'wp-json/' . self::NAMESPACE . '/indexable-entities';

		$response = wp_remote_get(
			$endpoint,
			[
				'headers' => [
					'Accept' => 'application/json',
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'parent_fetch_failed', $response->get_error_message(), [ 'status' => 500 ] );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new \WP_Error(
				'parent_fetch_failed',
				sprintf(
					// translators: %d is the status code of the REST Response.
					__( 'Parent returned HTTP %d', 'onesearch' ),
					$code
				),
				[ 'status' => 500 ]
			);
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) || ! isset( $data['indexableEntities']['entities'] ) ) {
			return new \WP_Error( 'invalid_parent_data', __( 'Invalid data from parent site.', 'onesearch' ), [ 'status' => 500 ] );
		}

		$entities_map = $data['indexableEntities']['entities'];

		$my_entities = isset( $entities_map[ $current_site_url ] ) ? $entities_map[ $current_site_url ] : [];

		return is_array( $my_entities ) ? $my_entities : [];
	}

	/**
	 * Health check endpoint.
	 *
	 * @return \WP_REST_Response
	 */
	public function health_check(): \WP_REST_Response|\WP_Error {
		$requesting_origin = '';
		if ( isset( $_SERVER['HTTP_X_ONESEARCH_REQUESTING_ORIGIN'] ) && ! empty( $_SERVER['HTTP_X_ONESEARCH_REQUESTING_ORIGIN'] ) ) {
			$requesting_origin = trailingslashit( esc_url_raw( wp_unslash( $_SERVER['HTTP_X_ONESEARCH_REQUESTING_ORIGIN'] ) ) );
		}

		$existing_parent_url = Settings::get_parent_site_url();
		if ( ! empty( $existing_parent_url ) ) {
			$governing_site_url = trailingslashit( esc_url_raw( $existing_parent_url ) );

			if ( ! empty( $requesting_origin ) && $governing_site_url !== $requesting_origin ) {
				return rest_ensure_response(
					[
						'success'         => false,
						'code'            => 'already_connected',
						'message'         => sprintf(
							/* translators: %s: governing site url */
							__( 'This site is already connected to a governing site at: %s', 'onesearch' ),
							$governing_site_url
						),
						'parent_site_url' => $governing_site_url,
					]
				);
			}
		}

		return rest_ensure_response(
			[
				'success' => true,
				'message' => __( 'Health check passed successfully.', 'onesearch' ),
			]
		);
	}

	/**
	 * Get the stored indexable entities map (governing only).
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_indexable_entities(): \WP_REST_Response|\WP_Error {
		if ( ! Settings::is_governing_site() ) {
			return new \WP_Error( 'not_governing_site', __( 'Only governing sites can provide indexable entities.', 'onesearch' ), [ 'status' => 403 ] );
		}

		$indexable_entities = Settings::get_indexable_entities();

		return rest_ensure_response(
			[
				'success'           => true,
				'indexableEntities' => $indexable_entities,
			]
		);
	}

	/**
	 * Save the indexable entities map (governing only).
	 *
	 * @param \WP_REST_Request $request Request object with JSON body.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function set_indexable_entities( \WP_REST_Request $request ) {

		$indexable_entities = json_decode( $request->get_body(), true );

		if ( ! is_array( $indexable_entities ) ) {
			return new \WP_Error( 'invalid_data', __( 'Failed saving settings. Please try again', 'onesearch' ), [ 'status' => 400 ] );
		}

		update_option( Settings::OPTION_GOVERNING_INDEXABLE_SITES, $indexable_entities );

		return rest_ensure_response(
			[
				'success'           => true,
				'message'           => __( 'Data saved successfully.', 'onesearch' ),
				'indexableEntities' => $indexable_entities,
			]
		);
	}

	/**
	 * Return the stored site type.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_site_type(): \WP_REST_Response|\WP_Error {
		return rest_ensure_response(
			[
				'site_type' => Settings::get_site_type() ?? '',
			]
		);
	}

	/**
	 * Set the site type.
	 *
	 * @param \WP_REST_Request $request Request object with site_type param.
	 *
	 * @return \WP_REST_Response
	 */
	public function set_site_type( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {

		$site_type = sanitize_text_field( wp_unslash( $request->get_param( 'site_type' ) ) );

		update_option( Settings::OPTION_SITE_TYPE, $site_type );

		$updated_site_type = Settings::get_site_type();

		if ( $updated_site_type !== $site_type ) {
			return new \WP_Error(
				'update_failed',
				\sprintf(
					/* translators: %s: site type */
					__( 'Failed to update site type to: %s', 'onesearch' ),
					$site_type
				),
				[ 'status' => 500 ]
			);
		}

		return rest_ensure_response(
			[
				'site_type' => $updated_site_type,
			]
		);
	}

	/**
	 * Return the saved list of shared sites.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_shared_sites(): \WP_REST_Response|\WP_Error {
		$shared_sites = Settings::get_shared_sites();
		return rest_ensure_response(
			[
				'success'      => true,
				'shared_sites' => array_values( $shared_sites ),
			]
		);
	}

	/**
	 * Save the list of shared sites and notify children.
	 *
	 * @param \WP_REST_Request $request Request object with JSON body (sites_data).
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function set_shared_sites( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {

		$body         = $request->get_body();
		$decoded_body = json_decode( $body, true );
		$sites_data   = $decoded_body['sites_data'] ?? [];

		// Reject duplicate URLs.
		$urls = [];

		foreach ( $sites_data as $site ) {
			if ( isset( $site['siteUrl'] ) && in_array( $site['siteUrl'], $urls, true ) ) {
				return new \WP_Error( 'duplicate_site_url', __( 'Brand Site already exists.', 'onesearch' ), [ 'status' => 400 ] );
			}
			$urls[] = $site['siteUrl'] ?? '';
		}

		$response = Settings::set_shared_sites( $sites_data );

		if ( false === $response ) {
			return new \WP_Error( 'update_failed', __( 'Failed saving settings. Please try again', 'onesearch' ), [ 'status' => 500 ] );
		}

		$parent_url   = get_site_url();
		$push_results = [];

		// Notify each child: set parent URL and bust searchable-sites cache.
		foreach ( $sites_data as $child ) {
			$raw_url = isset( $child['siteUrl'] ) ? (string) $child['siteUrl'] : '';
			$raw_key = isset( $child['publicKey'] ) ? (string) $child['publicKey'] : '';

			$url = trailingslashit( esc_url_raw( $raw_url ) );
			$key = sanitize_text_field( $raw_key );

			if ( empty( $url ) || empty( $key ) ) {
				$push_results[ $url ?: '(missing)' ] = __( 'Missing URL or key', 'onesearch' );
				continue;
			}

			// 1) Send parent URL to child site.
			$parent_url_endpoint = $url . 'wp-json/' . self::NAMESPACE . '/governing-url';

			$parent_response = wp_remote_post(
				$parent_url_endpoint,
				[
					'headers' => [
						'Accept'                    => 'application/json',
						'Content-Type'              => 'application/json',
						'X-OneSearch-Plugins-Token' => $key,
					],
					'body'    => wp_json_encode(
						[
							'parent_site_url' => $parent_url,
						]
					) ?: '',
				]
			);

			// 2) Bust searchable-sites cache on child.
			$bust_endpoint = $url . 'wp-json/' . self::NAMESPACE . '/bust-sites-cache';

			$bust_response = wp_remote_post(
				$bust_endpoint,
				[
					'headers' => [
						'Accept'                    => 'application/json',
						'Content-Type'              => 'application/json',
						'X-OneSearch-Plugins-Token' => $key,
					],
					'body'    => wp_json_encode( [] ) ?: '',
				]
			);

			$parent_ok = ! is_wp_error( $parent_response ) && 200 === wp_remote_retrieve_response_code( $parent_response );
			$bust_ok   = ! is_wp_error( $bust_response ) && 200 === wp_remote_retrieve_response_code( $bust_response );

			if ( $parent_ok && $bust_ok ) {
				$push_results[ $url ] = __( 'Parent URL set & cache cleared', 'onesearch' );
			} elseif ( $parent_ok ) {
				$push_results[ $url ] = __( 'Parent URL set, cache clear failed', 'onesearch' );
			} elseif ( $bust_ok ) {
				$push_results[ $url ] = __( 'Cache cleared, parent URL failed', 'onesearch' );
			} else {
				$error_msg = '';
				if ( is_wp_error( $parent_response ) ) {
					$error_msg .= sprintf(
						// translators: %d is error message.
						__( 'Parent: %d', 'onesearch' ),
						$parent_response->get_error_message()
					);
				}
				if ( is_wp_error( $bust_response ) ) {
					$error_msg .= sprintf(
						// translators: %d is error message.
						__( 'Cache: %d', 'onesearch' ),
						$bust_response->get_error_message(),
					);
				}
				$push_results[ $url ] = sprintf(
					// translators: %d is error message.
					__( 'Error: %d', 'onesearch' ),
					$error_msg
				);
			}
		}

		return rest_ensure_response(
			[
				'success'      => true,
				'sites_data'   => $sites_data,
				'push_results' => $push_results,
			]
		);
	}

	/**
	 * Permission callback function to authenticate via admin permission and token.
	 *
	 * @param \WP_REST_Request $request The incoming REST request.
	 *
	 * @return \WP_Error|true True if allowed, or WP_Error if not.
	 */
	public function permission_admin_or_token( \WP_REST_Request $request ) {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		$incoming_key = (string) $request->get_header( 'X-OneSearch-Plugins-Token' );
		$expected_key = Settings::get_api_key();

		if ( empty( $incoming_key ) || ! hash_equals( $incoming_key, $expected_key ) ) {
			return new \WP_Error(
				'invalid_api_key',
				__( 'Invalid or missing API key.', 'onesearch' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Validates the API key.
	 *
	 * @used-by health_check REST endpoint.
	 */
	public function validate_api_key(): bool {
		// Check if the request is from the governing site.
		if ( Settings::is_governing_site() ) {
			return (bool) current_user_can( 'manage_options' );
		}

		// Check X-onesearch-Plugins-Token header.
		if ( ! empty( $_SERVER['HTTP_X_ONESEARCH_PLUGINS_TOKEN'] ) ) {
			$token = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_ONESEARCH_PLUGINS_TOKEN'] ) );

			if ( ! empty( $token ) && hash_equals( $token, Settings::get_api_key() ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Delete the Algolia results associated with a given site URL.
	 *
	 * @param string $site_url Absolute site URL to delete from index.
	 *
	 * @return string Result message indicating success or the error encountered.
	 */
	private function delete_site_from_index( string $site_url ): string {

		try {
			$index = Algolia::instance()->get_index();

			if ( is_wp_error( $index ) ) {
				return sprintf(
					/* translators: %s: error message */
					__( 'Algolia client error: %s', 'onesearch' ),
					$index->get_error_message()
				);
			}

			$settings = Algolia_Index::instance()->get_algolia_settings();

			$index->setSettings( $settings )->wait();

			$index->deleteBy(
				[
					'filters' => sprintf( 'site_url:"%s"', Utils::normalize_url( $site_url ) ),
				]
			)->wait();

			return sprintf(
				/* translators: %s: index name */
				__( 'Algolia entries deleted for site: %s', 'onesearch' ),
				$index->getIndexName()
			);
		} catch ( \Throwable $e ) {
			return sprintf(
				/* translators: %s: error message */
				__( 'Error deleting Algolia index: %s', 'onesearch' ),
				$e->getMessage()
			);
		}
	}
}
