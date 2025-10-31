<?php
/**
 * This file is to create admin page.
 *
 * @package OneSearch
 */

namespace Onesearch\Inc\Settings;

use Onesearch\Inc\Traits\Singleton;
use Onesearch\Utils;

/**
 * Class Shared_Sites
 */
class Shared_Sites {

	/**
	 * Use Singleton trait.
	 */
	use Singleton;

	/**
	 * Protected class constructor
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Setup WordPress hooks
	 */
	public function setup_hooks(): void {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
	}

	/**
	 * Add admin menu under media
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {

		add_menu_page(
			__( 'OneSearch', 'onesearch' ),
			__( 'OneSearch', 'onesearch' ),
			'manage_options',
			'onesearch',
			'__return_null',
			'',
			2
		);

		$site_type     = (string) get_option( 'onesearch_site_type', '' );
		$algolia_creds = Utils::get_local_algolia_credentials();

		if ( 'governing-site' === $site_type ) {
			add_submenu_page(
				'onesearch',
				__( 'Indices and Search', 'onesearch' ),
				__( 'Indices and Search', 'onesearch' ),
				'manage_options',
				'onesearch',
				[ $this, 'render_onesearch_page' ]
			);
		}

		add_submenu_page(
			'onesearch',
			__( 'Settings', 'onesearch' ),
			__( 'Settings', 'onesearch' ),
			'manage_options',
			'onesearch-settings',
			[ $this, 'render_onesearch_settings_page' ]
		);

		if ( 'governing-site' === $site_type && ! empty( $algolia_creds ) ) {
			return;
		}

		remove_submenu_page( 'onesearch', 'onesearch' );
	}

	/**
	 * Render admin page
	 *
	 * @return void
	 */
	public function render_onesearch_page(): void {
		// Check if the user has permission to manage options.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'onesearch' ) );
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Configuration', 'onesearch' ); ?></h1>
			<div id="onesearch-config"></div>
		</div>
		<?php
	}

	/**
	 * Render admin page
	 *
	 * @return void
	 */
	public function render_onesearch_settings_page(): void {
		// Check if the user has permission to manage options.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'onesearch' ) );
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Settings', 'onesearch' ); ?></h1>
			<div id="onesearch-settings"></div>
		</div>
		<?php
	}
}
