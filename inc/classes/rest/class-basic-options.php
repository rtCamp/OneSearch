<?php
/**
 * Basic REST routes for OneSearch.
 *
 * Exposes administration and utility endpoints for configuring sites,
 * credentials, search settings, and indexing.
 *
 * @package OneSearch\Inc\REST
 */

namespace OneSearch\Inc\REST;

use OneSearch\Inc\Algolia\Algolia_Index;
use OneSearch\Inc\Algolia\Algolia_Index_By_Post;
use OneSearch\Modules\Rest\Abstract_REST_Controller;
use OneSearch\Modules\Rest\Governing_Data;
use OneSearch\Modules\Search\Settings as Search_Settings;
use OneSearch\Modules\Settings\Settings;
use OneSearch\Utils;
use WP_REST_Server;

/**
 * Class Basic_Options
 *
 * Registers REST routes and provides handlers for OneSearch settings and actions.
 */
class Basic_Options extends Abstract_REST_Controller {

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {

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

		// Re-index current site (and children for governing sites).
		register_rest_route(
			self::NAMESPACE,
			'/re-index',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 're_index' ],
				'permission_callback' => [ $this, 'check_api_permissions' ],
			]
		);

		// Public post types (local and, for governing, aggregated from children).
		register_rest_route(
			self::NAMESPACE,
			'/all-post-types',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_all_post_types' ],
				'permission_callback' => [ $this, 'check_api_permissions' ],
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
					'permission_callback' => [ $this, 'check_api_permissions' ],
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

		register_rest_route(
			self::NAMESPACE,
			'/bust-search-settings-cache',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'bust_search_settings_cache' ],
				'permission_callback' => [ $this, 'check_api_permissions' ],
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
					'permission_callback' => [ $this, 'check_api_permissions' ],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_governing_url' ],
					'permission_callback' => [ $this, 'check_api_permissions' ],
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

		$incoming_key = (string) ( $request->get_header( 'X-OneSearch-Token' ) ?? '' );

		if ( empty( $incoming_key ) || ! $this->is_valid_child_token( $incoming_key ) ) {
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
					'Accept'            => 'application/json',
					'Content-Type'      => 'application/json',
					'X-OneSearch-Token' => $site_key,
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
		$settings = Search_Settings::get_search_settings();
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

		update_option( Search_Settings::OPTION_GOVERNING_SEARCH_SETTINGS, $settings );

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
		$incoming_key = (string) ( $request->get_header( 'X-OneSearch-Token' ) ?? '' );
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

		$all_settings = Search_Settings::get_search_settings();
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
			if ( empty( $site['url'] ) || empty( $site['api_key'] ) ) {
				continue;
			}

			$endpoint = trailingslashit( $site['url'] ) . 'wp-json/' . self::NAMESPACE . '/bust-search-settings-cache';

			wp_remote_post(
				$endpoint,
				[
					'headers' => [
						'X-OneSearch-Token' => $site['api_key'],
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
			if ( isset( $site['api_key'] ) && $site['api_key'] === $public_key ) {
				return trailingslashit( $site['url'] );
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

		$incoming_key = (string) ( $request->get_header( 'X-OneSearch-Token' ) ?? '' );
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
			if ( ! isset( $site['url'] ) ) {
				continue;
			}

			$searchable_urls[] = (string) $site['url'];
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
	 * Validate that a provided key matches a known child site.
	 *
	 * @param string $key Candidate public key.
	 *
	 * @return bool
	 */
	private function is_valid_child_site_key( string $key ): bool {
		$shared_sites = Settings::get_shared_sites();

		foreach ( $shared_sites as $site ) {
			$site_key = isset( $site['api_key'] ) ? (string) $site['api_key'] : '';
			if ( ! empty( $site_key ) && $site_key === $key ) {
				return true;
			}
		}

		return false;
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
	 * Return public post types for the current site (and children if governing).
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_all_post_types(): \WP_REST_Response|\WP_Error {
		$current_identity = [
			'site_url'  => trailingslashit( get_site_url() ),
			'site_name' => (string) get_bloginfo( 'name' ),
		];

		$sites = [
			$current_identity['site_url'] => array_merge(
				$current_identity,
				[ 'post_types' => $this->get_public_post_types_payload() ]
			),
		];

		$errors = [];

		if ( Settings::is_governing_site() ) {
			$child_sites = Settings::get_shared_sites();

			foreach ( $child_sites as $child ) {
				$child_site_url = isset( $child['url'] ) ? rtrim( (string) $child['url'], '/' ) : '';
				$child_site_key = isset( $child['api_key'] ) ? (string) $child['api_key'] : '';

				if ( empty( $child_site_url ) || empty( $child_site_key ) ) {
					$errors[] = [
						'site_url' => $child_site_url ?: '(missing)',
						'message'  => __( 'Missing url or api_key.', 'onesearch' ),
					];
					continue;
				}

				$endpoint = trailingslashit( $child_site_url ) . 'wp-json/' . self::NAMESPACE . '/all-post-types';

				$response = wp_remote_get(
					$endpoint,
					[
						'headers' => [
							'Accept'            => 'application/json',
							'X-OneSearch-Token' => $child_site_key,
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
		$opt = Search_Settings::get_indexable_entities();
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
					$raw_url = isset( $child['url'] ) ? (string) $child['url'] : '';
					$url     = Utils::normalize_url( $raw_url );
					$key     = isset( $child['api_key'] ) ? (string) $child['api_key'] : '';

					if ( empty( $url ) || empty( $key ) ) {
						$results[ $url ?: '(missing)' ] = [
							'status'  => 'error',
							'message' => __( 'Missing url or api_key for child.', 'onesearch' ),
						];
						continue;
					}

					$endpoint = trailingslashit( $url ) . 'wp-json/' . self::NAMESPACE . '/re-index';

					$response_obj = wp_remote_post(
						$endpoint,
						[
							'headers' => [
								'Accept'            => 'application/json',
								'Content-Type'      => 'application/json',
								'X-OneSearch-Token' => $key,
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
					return new \WP_Error(
						'reindex_failed',
						sprintf(
							// translators: %s: error message.
							__( 'Re-indexing failed: %s.', 'onesearch' ),
							$indexed->get_error_message()
						),
						[ 'status' => 500 ]
					);
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

		$entities_map     = $data['indexableEntities']['entities'];
		$current_site_url = Utils::normalize_url( get_site_url() );

		$my_entities = isset( $entities_map[ $current_site_url ] ) ? $entities_map[ $current_site_url ] : [];

		return is_array( $my_entities ) ? $my_entities : [];
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

		$indexable_entities = Search_Settings::get_indexable_entities();

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

		update_option( Search_Settings::OPTION_GOVERNING_INDEXABLE_SITES, $indexable_entities );

		return rest_ensure_response(
			[
				'success'           => true,
				'message'           => __( 'Data saved successfully.', 'onesearch' ),
				'indexableEntities' => $indexable_entities,
			]
		);
	}

	/**
	 * Checks if the token sent by the child site is valid.
	 *
	 * @param string $incoming The token sent by the child site.
	 *
	 * @return bool True if the token is valid, false otherwise.
	 */
	private function is_valid_child_token( string $incoming ): bool {
		$child_sites = Settings::get_shared_sites();

		foreach ( $child_sites as $child ) {
			$key = isset( $child['api_key'] ) ? (string) $child['api_key'] : '';
			if ( $key && hash_equals( $key, $incoming ) ) {
				return true;
			}
		}

		return false;
	}
}
