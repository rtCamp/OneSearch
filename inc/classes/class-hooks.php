<?php
/**
 * Add actions and filters for Onesearch plugin.
 *
 * @todo colocate with the modules that use them.
 *
 * @package Onesearch
 */

namespace Onesearch\Inc;

use Onesearch\Contracts\Interfaces\Registrable;

/**
 * Class Hooks initializes the actions and filters.
 */
class Hooks implements Registrable {

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		// create global variable called onesearch_sites which has info like site name, site url, site id, etc.
		add_action( 'init', [ $this, 'create_global_onesearch_sites' ], -1 );

		add_action( 'init', [ $this, 'load_onesearch_text_domain' ] );
	}

	/**
	 * Load onesearch text domain.
	 *
	 * @todo Remove before release on wordpress.org
	 */
	public function load_onesearch_text_domain(): void {
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound
		load_plugin_textdomain( 'onesearch', false, ONESEARCH_DIR . 'languages/' );
	}

	/**
	 * Create global variable onesearch_sites with site info.
	 *
	 * @return void
	 */
	public function create_global_onesearch_sites(): void {
		$sites = get_option( 'onesearch_shared_sites', [] );

		if ( empty( $sites ) || ! is_array( $sites ) ) {
			return;
		}

		$onesearch_sites = [];
		foreach ( $sites as $site ) {
			$onesearch_sites[ $site['siteUrl'] ] = [
				'siteName'  => $site['siteName'],
				'siteUrl'   => $site['siteUrl'],
				'publicKey' => $site['publicKey'],
			];
		}

		// Set it in GLOBALS.
		$GLOBALS['onesearch_sites'] = $onesearch_sites;
	}
}
