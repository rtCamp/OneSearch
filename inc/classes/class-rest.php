<?php
/**
 * Register All OneSearch related REST API endpoints.
 *
 * @package OneSearch
 */

namespace Onesearch\Inc;

use Onesearch\Contracts\Interfaces\Registrable;
use Onesearch\Inc\REST\Basic_Options;
use Onesearch\Inc\REST\Governing_Data;
/**
 * Class REST
 */
class REST implements Registrable {

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		Basic_Options::instance();
		Governing_Data::instance();

		// fix cors headers for REST API requests.
		add_filter( 'rest_pre_serve_request', [ $this, 'add_cors_headers' ], PHP_INT_MAX - 20, 1 );
	}

	/**
	 * Add CORS headers to REST API responses.
	 *
	 * @param bool $served Whether the request has been served.
	 * @return bool
	 */
	public function add_cors_headers( $served ): bool {
		header( 'Access-Control-Allow-Headers: X-OneSearch-Plugins-Token, X-OneSearch-Requesting-Origin, Content-Type, Authorization', false );
		return $served;
	}
}
