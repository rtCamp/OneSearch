<?php
/**
 * Routes for Search-related operations.
 *
 * @package OneSearch
 */

namespace OneSearch\Modules\Rest;

use OneSearch\Modules\Search\Settings as Search_Settings;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class Search_Controller
 */
class Search_Controller extends Abstract_REST_Controller {

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		// Algolia credentials: get / set.
		register_rest_route(
			self::NAMESPACE,
			'/algolia-credentials',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_algolia_credentials' ],
					'permission_callback' => [ $this, 'check_api_permissions' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_algolia_credentials' ],
					'permission_callback' => [ $this, 'check_api_permissions' ],
				],
			]
		);
	}

	/**
	 * Get Algolia credentials from governing site.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_algolia_credentials(): WP_REST_Response {
		$creds = Search_Settings::get_algolia_credentials();

		return new \WP_REST_Response(
			[
				'success'   => true,
				'app_id'    => $creds['app_id'] ?? '',
				'write_key' => $creds['write_key'] ?? '',
			]
		);
	}

	/**
	 * Update Algolia credentials on governing site.
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Request.
	 */
	public function update_algolia_credentials( $request ): \WP_REST_Response|\WP_Error {
		$parameters = $request->get_json_params();

		$app_id    = isset( $parameters['app_id'] ) ? sanitize_text_field( $parameters['app_id'] ) : '';
		$write_key = isset( $parameters['write_key'] ) ? sanitize_text_field( $parameters['write_key'] ) : '';

		if ( empty( $app_id ) || empty( $write_key ) ) {
			return new \WP_Error(
				'onesearch_algolia_credentials_invalid',
				__( 'Both App ID and Write Key are required.', 'onesearch' ),
				[ 'status' => 400 ]
			);
		}

		$is_valid = $this->validate_algolia_key( $app_id, $write_key );
		if ( ! $is_valid ) {
			return new \WP_Error(
				'onesearch_algolia_credentials_invalid',
				__( 'The provided Algolia credentials are invalid or lack necessary permissions.', 'onesearch' ),
				[ 'status' => 400 ]
			);
		}

		$success = Search_Settings::set_algolia_credentials(
			[
				'app_id'    => $app_id,
				'write_key' => $write_key,
			]
		);

		if ( ! $success ) {
			return new \WP_Error(
				'onesearch_algolia_credentials_save_failed',
				__( 'Failed to save Algolia credentials.', 'onesearch' ),
				[ 'status' => 500 ]
			);
		}

		return new \WP_REST_Response(
			[
				'success' => true,
				'message' => __( 'Algolia credentials updated successfully.', 'onesearch' ),
			]
		);
	}

	/**
	 * Validate the Algolia Key before saving.
	 *
	 * @param string $app_id    The Algolia Application ID.
	 * @param string $write_key The Algolia Write Key.
	 */
	private function validate_algolia_key( string $app_id, string $write_key ): bool {
		try {
			$client = \Algolia\AlgoliaSearch\SearchClient::create( $app_id, $write_key );
			// Try to get API key information to check permissions (ACL).
			$key_info = $client->getApiKey( $write_key );

			// Check if key has required write permissions.
			$acl = $key_info['acl'] ?? [];

			// Required permissions for write operations.
			$required_permissions = [ 'addObject', 'deleteObject' ];
			foreach ( $required_permissions as $permission ) {
				if ( ! in_array( $permission, $acl, true ) ) {
					return false;
				}
			}

			return true;
		} catch ( \Throwable $e ) {
			return false;
		}
	}
}
