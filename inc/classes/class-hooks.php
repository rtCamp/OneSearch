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

		// add setup page link to plugins page.
		add_filter( 'plugin_action_links_' . ONESEARCH_PLUGIN_LOADER_PLUGIN_BASENAME, [ $this, 'add_setup_page_link' ] );

		// add container for modal for site selection on activation.
		add_action( 'admin_footer', [ $this, 'add_site_selection_modal' ] );

		// add body class for site selection modal.
		add_filter( 'admin_body_class', [ $this, 'add_body_class_for_modal' ] );
		add_filter( 'admin_body_class', [ $this, 'add_body_class_for_missing_sites' ] );
	}

	/**
	 * Load onesearch text domain.
	 *
	 * @todo Remove before release on wordpress.org
	 */
	public function load_onesearch_text_domain(): void {
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound
		load_plugin_textdomain( 'onesearch', false, ONESEARCH_DIR . '/languages/' );
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

	/**
	 * Add setup page link to plugins page.
	 *
	 * @param array $links Existing plugin action links.
	 * @return array Modified plugin action links.
	 */
	public function add_setup_page_link( $links ): array {
		$setup_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=onesearch-settings' ) ),
			__( 'Setup', 'onesearch' )
		);
		array_unshift( $links, $setup_link );
		return $links;
	}

	/**
	 * Add site selection modal to admin footer.
	 *
	 * @return void
	 */
	public function add_site_selection_modal(): void {

		$current_screen = get_current_screen();

		if ( ! $current_screen || 'plugins' !== $current_screen->base ) {
			return;
		}

		// get onesearch_site_type_transient transient to check if site type is set.
		$site_type_transient = get_transient( 'onesearch_site_type_transient' );

		if ( $site_type_transient ) {
			// If site type is already set, do not show the modal.
			return;
		}

		?>
		<div class="wrap">
			<div id="onesearch-site-selection-modal" class="onesearch-modal"></div>
		</div>
		<?php
	}

	/**
	 * Create global variable onesearch_sites with site info.
	 *
	 * @param string $classes Existing body classes.
	 *
	 * @return string
	 */
	public function add_body_class_for_modal( $classes ): string {
		$current_screen = get_current_screen();

		if ( ! $current_screen || 'plugins' !== $current_screen->base ) {
			return $classes;
		}

		// get onesearch_site_type_transient transient to check if site type is set.
		$site_type_transient = get_transient( 'onesearch_site_type_transient' );

		if ( $site_type_transient ) {
			// If site type is already set, do not show the modal.
			return $classes;
		}

		// add onesearch-site-selection-modal class to body.
		$classes .= ' onesearch-site-selection-modal ';
		return $classes;
	}

	/**
	 * Add body class for missing sites.
	 *
	 * @param string $classes Existing body classes.
	 *
	 * @return string
	 */
	public function add_body_class_for_missing_sites( $classes ): string {

		$current_screen = get_current_screen();

		if ( ! $current_screen ) {
			return $classes;
		}

		// get onesearch_shared_sites option.
		$shared_sites = get_option( 'onesearch_shared_sites', [] );

		// if shared_sites is empty or not an array, return the classes.
		if ( empty( $shared_sites ) || ! is_array( $shared_sites ) ) {
			$classes .= ' onesearch-missing-brand-sites ';

			// remove plugin manager submenu.
			remove_submenu_page( 'onesearch', 'onesearch' );

			return $classes;
		}

		return $classes;
	}
}
