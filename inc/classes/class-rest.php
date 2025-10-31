<?php
/**
 * Register All OneSearch related REST API endpoints.
 *
 * @package OneSearch
 */

namespace Onesearch\Inc;

use Onesearch\Inc\REST\Basic_Options;
use Onesearch\Inc\REST\Governing_Data;
use Onesearch\Inc\Traits\Singleton;

/**
 * Class REST
 */
class REST {

	/**
	 * Use Singleton trait.
	 */
	use Singleton;

	/**
	 * REST constructor.
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Setup hooks.
	 *
	 * @return void
	 */
	public function setup_hooks(): void {
		Basic_Options::get_instance();
		Governing_Data::get_instance();

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
