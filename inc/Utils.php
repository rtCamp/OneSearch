<?php
/**
 * Shared utilities functions for internal plugin use.
 *
 * Utilities are a domain antipattern - where ever possible, colocate methods with their related classes.
 *
 * @package Onesearch
 */

declare(strict_types = 1);

namespace Onesearch;

use Onesearch\Inc\Algolia\Algolia;

/**
 * Class - Utils
 */
final class Utils {

	/**
	 * Normalize a URL by trimming whitespace and ensuring it ends with a trailing slash.
	 *
	 * @param string $url The URL to normalize.
	 */
	public static function normalize_url( string $url ): string {
		return trailingslashit( trim( $url ) );
	}

	/**
	 * Validates the API key.
	 *
	 * @used-by health_check REST endpoint.
	 */
	public static function validate_api_key(): bool {
		// check if the request is from same site.
		if ( 'governing-site' === get_option( 'onesearch_site_type', '' ) ) {
			return (bool) current_user_can( 'manage_options' );
		}

		// check X-onesearch-Plugins-Token header.
		if ( isset( $_SERVER['HTTP_X_ONESEARCH_PLUGINS_TOKEN'] ) && ! empty( $_SERVER['HTTP_X_ONESEARCH_PLUGINS_TOKEN'] ) ) {
			$token = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_ONESEARCH_PLUGINS_TOKEN'] ) );
			// Get the public key from options.
			$public_key = get_option( 'onesearch_child_site_public_key', '' );
			if ( hash_equals( $token, $public_key ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Delete the Algolia index for a given site URL.
	 *
	 * @param string $site_url Absolute site URL used to derive the index name.
	 *
	 * @return string          Result message indicating success or the error encountered.
	 */
	public static function delete_site_algolia_index( string $site_url ): string {
		$sanitized_url = (string) esc_url_raw( $site_url );

		if ( empty( $sanitized_url ) ) {
			return __( 'Invalid site URL.', 'onesearch' );
		}

		try {
			$algolia = Algolia::get_instance();
			$client  = $algolia->get_client();

			if ( is_wp_error( $client ) ) {
				return sprintf(
					/* translators: %s: error message */
					__( 'Algolia client error: %s', 'onesearch' ),
					$client->get_error_message()
				);
			}

			$index_name = $algolia->get_algolia_index_name_from_url( $sanitized_url );
			$index      = $client->initIndex( $index_name );

			// Delete the index.
			$index->delete()->wait();

			return sprintf(
				/* translators: %s: index name */
				__( 'Algolia index deleted: %s', 'onesearch' ),
				$index_name
			);
		} catch ( \Throwable $e ) {
			return sprintf(
				/* translators: %s: error message */
				__( 'Error deleting Algolia index: %s', 'onesearch' ),
				$e->getMessage()
			);
		}
	}

	/**
	 * Get locally stored Algolia credentials.
	 *
	 * @return array{app_id?: string, write_key?: string, admin_key?: string}
	 */
	public static function get_local_algolia_credentials(): array {
		return (array) get_option( 'onesearch_algolia_credentials', [] );
	}

	/**
	 * Allow if current user is admin, otherwise require a valid token header.
	 *
	 * @param \WP_REST_Request $request    WP_Request.
	 * @param string           $option_key Option that stores the expected token.
	 * @param string           $header_key HTTP header to read the token from.
	 *
	 * @return true|\WP_Error
	 */
	public static function verify_admin_and_token( \WP_REST_Request $request, string $option_key = 'onesearch_child_site_public_key', string $header_key = 'X-OneSearch-Plugins-Token' ) {
		$incoming_key = (string) ( $request->get_header( $header_key ) ?? '' );
		$expected_key = (string) get_option( $option_key, '' );

		$is_admin = current_user_can( 'manage_options' );

		if ( ! $is_admin ) {
			if ( empty( $incoming_key ) || $incoming_key !== $expected_key ) {
				return new \WP_Error(
					'invalid_api_key',
					__( 'Invalid or missing API key.', 'onesearch' ),
					[ 'status' => 403 ]
				);
			}
		}

		return true;
	}
}
