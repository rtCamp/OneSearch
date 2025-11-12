<?php
/**
 * Handles REST API behavior.
 *
 * @package onesearch
 */

declare( strict_types = 1 );

namespace Onesearch\Modules\Rest;

use Onesearch\Contracts\Interfaces\Registrable;
use Onesearch\Inc\REST\Basic_Options;

/**
 * Class REST
 */
final class Rest implements Registrable {

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		Basic_Options::instance();

		// Exposes the necessary CORS headers for REST API.
		add_filter( 'rest_allowed_cors_headers', [ $this, 'add_cors_headers' ] );
	}

	/**
	 * Add CORS headers to REST API responses.
	 *
	 * @param array<int, string> $headers Existing headers.
	 *
	 * @return array<int, string> Modified headers.
	 */
	public function add_cors_headers( $headers ): array {
		// Skip if the headers are already present.
		if ( in_array( 'X-OneSearch-Plugins-Token', $headers, true ) ) {
			return $headers;
		}

		return array_merge(
			$headers,
			[
				'X-OneSearch-Plugins-Token',
				'X-OneSearch-Requesting-Origin',
			]
		);
	}
}
