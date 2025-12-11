<?php
/**
 * Algolia service wrapper.
 *
 * @package OneSearch\Modules\Search
 */

declare(strict_types = 1);

namespace OneSearch\Modules\Search;

use Algolia\AlgoliaSearch\SearchClient;
use OneSearch\Contracts\Traits\Singleton;
use OneSearch\Modules\Rest\Abstract_REST_Controller;
use OneSearch\Modules\Search\Settings as Search_Settings;
use OneSearch\Modules\Settings\Settings;

/**
 * Class - Algolia
 */
final class Algolia {
	use Singleton;

	/**
	 * The instance of the index.
	 *
	 * @var ?\Algolia\AlgoliaSearch\SearchIndex
	 */
	private ?\Algolia\AlgoliaSearch\SearchIndex $index;

	/**
	 * Get the index object for the current site.
	 */
	public function get_index(): \Algolia\AlgoliaSearch\SearchIndex|\WP_Error {
		if ( $this->index instanceof \Algolia\AlgoliaSearch\SearchIndex ) {
			return $this->index;
		}

		$index_name = $this->get_index_name();

		if ( empty( $index_name ) ) {
			return new \WP_Error(
				'algolia_index_name_invalid',
				__( 'Algolia index name could not be determined.', 'onesearch' )
			);
		}

		$client = $this->get_client();

		if ( is_wp_error( $client ) ) {
			return $client;
		}

		$index       = $client->initIndex( $index_name );
		$this->index = $index;

		return $index;
	}

	/**
	 * Create an Algolia client using stored credentials.
	 */
	private function get_client(): \Algolia\AlgoliaSearch\SearchClient|\WP_Error {
		$creds = $this->get_algolia_credentials();

		if ( is_wp_error( $creds ) ) {
			return $creds;
		}

		if ( empty( $creds['app_id'] ) || empty( $creds['write_key'] ) ) {
			return new \WP_Error(
				'algolia_credentials_missing',
				__( 'Algolia admin credentials missing.', 'onesearch' )
			);
		}

		return SearchClient::create( $creds['app_id'], $creds['write_key'] );
	}

	/**
	 * Gets the index name.
	 *
	 * Returns empty string if the index name could not be determined.
	 */
	private function get_index_name(): string {
		$site_url = Settings::is_governing_site()
			? get_site_url()
			: Settings::get_parent_site_url();

		if ( empty( $site_url ) ) {
			return '';
		}

		$parsed_url = wp_parse_url( $site_url );
		$site_name  = ! empty( $parsed_url['host'] ) ? $parsed_url['host'] : null;

		if ( null === $site_name ) {
			return '';
		}

		$site_name = str_replace( '.', '_', $site_name );

		return sprintf( 'onesearch_%s_wp_posts', sanitize_title( $site_name ) );
	}

	/**
	 * Get algolia credentials.
	 *
	 * If on a child site, the credentials are fetched from the governing site.
	 *
	 * @return array{
	 *   app_id: ?string,
	 *   write_key: ?string,
	 * }|\WP_Error
	 */
	private function get_algolia_credentials(): array|\WP_Error {
		if ( Settings::is_governing_site() ) {
			return Search_Settings::get_algolia_credentials();
		}

		// If no parent is configured, return an error.
		$parent_url     = Settings::get_parent_site_url();
		$our_public_key = Settings::get_api_key();
		if ( empty( $parent_url ) || empty( $our_public_key ) ) {
			return new \WP_Error(
				'algolia_credentials_unavailable',
				__( 'Algolia credentials are unavailable because no governing site is configured.', 'onesearch' )
			);
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
					'Accept'            => 'application/json',
					'Content-Type'      => 'application/json',
					'X-OneSearch-Token' => $our_public_key,
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

		return [
			'app_id'    => isset( $response_data['app_id'] ) ? sanitize_text_field( $response_data['app_id'] ) : null,
			'write_key' => isset( $response_data['write_key'] ) ? sanitize_text_field( $response_data['write_key'] ) : null,
		];
	}
}
