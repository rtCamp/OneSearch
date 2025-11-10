<?php
/**
 * Create a secret key for OneSearch site communication.
 *
 * @package OneSearch
 */

namespace Onesearch\Inc\Plugin_Configs;

use Onesearch\Contracts\Interfaces\Registrable;

/**
 * Class Secret_Key
 */
class Secret_Key implements Registrable {

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'admin_init', [ $this, 'generate_secret_key' ] );
	}

	/**
	 * Generate a secret key for the site.
	 *
	 * @return void
	 */
	public function generate_secret_key(): void {
		$secret_key = get_option( 'onesearch_child_site_public_key' );
		if ( ! empty( $secret_key ) ) {
			return;
		}

		$secret_key = wp_generate_password( 128, false, false );
		// Store the secret key in the database.
		update_option( 'onesearch_child_site_public_key', $secret_key );
	}

	/**
	 * Get the secret key.
	 *
	 * @return \WP_REST_Response| \WP_Error
	 */
	public static function get_secret_key(): \WP_REST_Response|\WP_Error {
		$secret_key = get_option( 'onesearch_child_site_public_key' );
		if ( empty( $secret_key ) ) {
			self::regenerate_secret_key();
			$secret_key = get_option( 'onesearch_child_site_public_key' );
		}
		return rest_ensure_response(
			[
				'secret_key' => $secret_key,
			]
		);
	}

	/**
	 * Regenerate the secret key.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function regenerate_secret_key(): \WP_REST_Response|\WP_Error {
		$regenerated_key = wp_generate_password( 128, false, false );
		// Update the option with the new key.
		update_option( 'onesearch_child_site_public_key', $regenerated_key );

		return rest_ensure_response(
			[
				'message'    => __( 'Secret key regenerated successfully.', 'onesearch' ),
				'secret_key' => $regenerated_key,
			]
		);
	}
}
