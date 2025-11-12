<?php
/**
 * Registers the Admin menu and settings screen.
 *
 * @package Onesearch
 */

declare(strict_types = 1);

namespace Onesearch\Modules\Settings;

use Onesearch\Contracts\Interfaces\Registrable;
use Onesearch\Modules\Core\Assets;

/**
 * Class - Admin
 */
final class Admin implements Registrable {
	/**
	 * The menu slug for the admin menu.
	 *
	 * @todo replace with a cross-plugin menu.
	 */
	public const MENU_SLUG = 'onesearch';

	/**
	 * The screen ID for the settings page.
	 */
	public const SCREEN_ID = 'onesearch-settings';

	/**
	 * Path to the SVG logo for the menu.
	 *
	 * @todo Replace with actual logo.
	 * @var string
	 */
	private const SVG_LOGO_PATH = '';

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_menu', [ $this, 'remove_default_submenu' ], 999 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'admin_footer', [ $this, 'inject_site_selection_modal' ] );

		add_filter( 'plugin_action_links_' . ONESEARCH_PLUGIN_BASENAME, [ $this, 'add_action_links' ], 2 );
		add_filter( 'admin_body_class', [ $this, 'add_body_classes' ] );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu(): void {
		add_menu_page(
			__( 'OneSearch', 'onesearch' ),
			__( 'OneSearch', 'onesearch' ),
			'manage_options',
			self::MENU_SLUG,
			// Redirect to the submenu that shares the slug.
			null,
			self::SVG_LOGO_PATH,
			2
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'onesearch' ),
			__( 'Settings', 'onesearch' ),
			'manage_options',
			self::SCREEN_ID,
			[ $this, 'sites_screen_callback' ],
			3
		);

		// Register the "Indices and Search" submenu only for governing sites with Algolia credentials.
		if ( ! Settings::is_governing_site() || empty( Settings::get_algolia_credentials() ) ) {
			return;
		}

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Indices and Search', 'onesearch' ),
			__( 'Indices and Search', 'onesearch' ),
			'manage_options',
			self::MENU_SLUG, // Reuse the main menu slug.
			[ $this, 'search_screen_callback' ],
			1, // Put this submenu at the top.
		);
	}

	/**
	 * Remove the default submenu added by WordPress.
	 */
	public function remove_default_submenu(): void {
		remove_submenu_page( self::MENU_SLUG, self::MENU_SLUG );
	}

	/**
	 * Inject site selection modal into the admin footer.
	 */
	public function inject_site_selection_modal(): void {
		$current_screen = get_current_screen();
		if ( ! $current_screen || 'plugins' !== $current_screen->base ) {
			return;
		}

		// Bail if the site type is already set.
		if ( ! empty( Settings::get_site_type() ) ) {
			return;
		}

		?>
		<div class="wrap">
			<div id="onesearch-site-selection-modal" class="onesearch-modal"></div>
		</div>
		<?php
	}

	/**
	 * Add action links to the settings on the plugins page.
	 *
	 * @param string[] $links Existing links.
	 *
	 * @return string[]
	 */
	public function add_action_links( $links ): array {
		// Defense against other plugins.
		if ( ! is_array( $links ) ) {
			_doing_it_wrong( __METHOD__, esc_html__( 'Expected an array.', 'onesearch' ), 'n.e.x.t' );

			$links = [];
		}

		$links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( sprintf( 'admin.php?page=%s', self::SCREEN_ID ) ) ),
			__( 'Settings', 'onesearch' )
		);

		return $links;
	}

	/**
	 * Admin page content callback for settings screen.
	 */
	public function sites_screen_callback(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Settings', 'onesearch' ); ?></h1>
			<div id="onesearch-settings"></div>
		</div>
		<?php
	}

	/**
	 * Admin page content callback for search screen.
	 */
	public function search_screen_callback(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Configuration', 'onesearch' ); ?></h1>
			<div id="onesearch-config"></div>
		</div>
		<?php
	}

	/**
	 * Add body classes for the admin area.
	 *
	 * @param string $classes Existing body classes.
	 */
	public function add_body_classes( $classes ): string {
		$current_screen = get_current_screen();

		if ( ! $current_screen ) {
			return $classes;
		}

		// Cast to string in case it's null.
		$classes = $this->add_body_class_for_modal( (string) $classes, $current_screen );
		$classes = $this->add_body_class_for_missing_sites( (string) $classes, $current_screen );

		return $classes;
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( string $hook ): void {
		if ( 'plugins.php' === $hook || str_contains( $hook, 'plugins' ) || str_contains( $hook, 'onesearch' ) ) {

			// Enqueue the onboarding modal.
			$this->enqueue_onboarding_scripts();
		}

		// @todo Move other scripts from Assets to here.
	}

	/**
	 * Enqueue scripts and styles for the onboarding screen.
	 */
	private function enqueue_onboarding_scripts(): void {
		// Bail if the site type is already set.
		if ( ! empty( Settings::get_site_type() ) ) {
			return;
		}

		wp_localize_script(
			Assets::ONBOARDING_SCRIPT_HANDLE,
			'OneSearchPluginGlobal',
			[
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'setup_url' => admin_url( sprintf( 'admin.php?page=%s', self::SCREEN_ID ) ),
				'site_type' => Settings::get_site_type(), // @todo We can probably remove this.
			]
		);

		wp_enqueue_script( Assets::ONBOARDING_SCRIPT_HANDLE );
		wp_enqueue_style( Assets::ONBOARDING_SCRIPT_HANDLE );
	}

	/**
	 * Add body class if the modal is going to be shown.
	 *
	 * @param string     $classes        Existing body classes.
	 * @param \WP_Screen $current_screen Current screen object.
	 */
	private function add_body_class_for_modal( string $classes, \WP_Screen $current_screen ): string {
		if ( 'plugins' !== $current_screen->base ) {
			return $classes;
		}

		// Bail if the site type is already set.
		if ( ! empty( Settings::get_site_type() ) ) {
			return $classes;
		}

		// Add onesearch-site-selection-modal class to body.
		$classes .= ' onesearch-site-selection-modal ';
		return $classes;
	}

	/**
	 * Add body class for missing sites.
	 *
	 * @param string     $classes Existing body classes.
	 * @param \WP_Screen $current_screen Current screen object.
	 */
	private function add_body_class_for_missing_sites( string $classes, \WP_Screen $current_screen ): string {
		// Bail if the shared sites are already set.
		$shared_sites = Settings::get_shared_sites();
		if ( ! empty( $shared_sites ) ) {
			return $classes;
		}

		$classes .= ' onesearch-missing-brand-sites ';
		return $classes;
	}
}
