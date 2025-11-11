<?php
/**
 * Registers plugin assets.
 *
 * @package onesearch
 */

declare( strict_types = 1 );

namespace Onesearch\Modules\Core;

use Onesearch\Contracts\Interfaces\Registrable;
use Onesearch\Modules\Settings\Settings;

/**
 * Class Assets
 */
final class Assets implements Registrable {
	/**
	 * The relative path to the built assets directory.
	 * No preceding or trailing slashes.
	 */
	private const ASSETS_DIR = 'build';

	/**
	 * Prefix for all asset handles.
	 */
	private const PREFIX = 'onesearch-';

	/**
	 * Asset handles
	 */
	public const ADMIN_STYLES_HANDLE      = self::PREFIX . 'admin';
	public const ONBOARDING_SCRIPT_HANDLE = self::PREFIX . 'onboarding';
	public const SETTINGS_SCRIPT_HANDLE   = self::PREFIX . 'settings';
	public const SETUP_SCRIPT_HANDLE      = self::PREFIX . 'setup';

	/**
	 * Plugin directory path.
	 *
	 * @var string
	 */
	private string $plugin_dir;

	/**
	 * Plugin URL.
	 *
	 * @var string
	 */
	private string $plugin_url;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->plugin_dir = (string) ONESEARCH_DIR;
		$this->plugin_url = (string) ONESEARCH_URL;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Assets are only registered globally. Enqueuing is handled in specific contexts that need them.
	 */
	public function register_hooks(): void {
		// Assets are always registered. They can be enqueued later as needed.
		add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'register_assets' ] );

		// Add defer attribute to certain plugin bundles to improve admin load performance.
		add_filter( 'script_loader_tag', [ $this, 'defer_scripts' ], 10, 2 );

		// Enqueue the assets
		// @todo colocate these.
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
	}

	/**
	 * Register all scripts and styles.
	 */
	public function register_assets(): void {
		$this->register_script(
			self::SETUP_SCRIPT_HANDLE,
			'setup',
		);

		$this->register_script(
			self::SETTINGS_SCRIPT_HANDLE,
			'settings',
		);

		$this->register_script(
			self::ONBOARDING_SCRIPT_HANDLE,
			'onboarding',
		);
		$this->register_style(
			self::ONBOARDING_SCRIPT_HANDLE,
			'onboarding',
			[ 'wp-components' ],
		);

		$this->register_style(
			self::ADMIN_STYLES_HANDLE,
			'admin',
			[ 'wp-components' ],
		);
	}

	/**
	 * Add defer attribute to certain plugin bundle scripts to improve loading performance.
	 *
	 * @param string $tag    The script tag.
	 * @param string $handle The script handle.
	 * @return string Modified script tag.
	 */
	public function defer_scripts( string $tag, string $handle ): string {
		$defer_handles = [
			self::SETTINGS_SCRIPT_HANDLE,
			self::SETUP_SCRIPT_HANDLE,
			self::ONBOARDING_SCRIPT_HANDLE,
		];

		// Bail if we don't need to defer.
		if ( ! in_array( $handle, $defer_handles, true ) || false !== strpos( $tag, ' defer' ) ) {
			return $tag;
		}

		return str_replace( ' src', ' defer src', $tag );
	}

	/**
	 * To enqueue scripts and styles. in admin.
	 *
	 * @param string $hook_suffix Admin page name.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts( $hook_suffix ) {
		if ( strpos( $hook_suffix, 'onesearch-settings' ) !== false ) {
			$this->localize_script( self::SETUP_SCRIPT_HANDLE, 'OneSearchSettings' );
			wp_enqueue_script( self::SETUP_SCRIPT_HANDLE );
		}

		if ( strpos( $hook_suffix, 'onesearch' ) !== false ) {
			$this->localize_script( self::SETTINGS_SCRIPT_HANDLE, 'OneSearchSettings' );

			wp_enqueue_script( self::SETTINGS_SCRIPT_HANDLE );
		}

		// @todo Only enqueue on OneSearch admin pages.
		wp_enqueue_style( self::ADMIN_STYLES_HANDLE );
	}

	/**
	 * Localize the provided script.
	 *
	 * @todo this should be singular and unified, and then we don't need a helper.
	 *
	 * @param string $handle Name of the script.
	 * @param string $object_name Name of the JavaScript object.
	 */
	public function localize_script( string $handle, string $object_name ): void {
		$localized_args = [
			'nonce'             => wp_create_nonce( 'wp_rest' ),
			'restUrl'           => esc_url( home_url( '/wp-json/' ) ),
			'restNamespace'     => 'onesearch/v1',
			'publicKey'         => Settings::get_api_key(),
			'setupUrl'          => admin_url( 'admin.php?page=onesearch-settings' ),
			'currentSiteUrl'    => esc_url( home_url( '/' ) ),
			'sharedSites'       => array_values( Settings::get_shared_sites() ),
			'indexableEntities' => Settings::get_indexable_entities(),
		];

		wp_localize_script( $handle, $object_name, $localized_args );
	}

	/**
	 * Register a script.
	 *
	 * @param string   $handle    Name of the script. Should be unique.
	 * @param string   $filename  Path of the script relative to js directory.
	 *                            excluding the .js extension.
	 * @param string[] $deps      Optional. An array of registered script handles this script depends on. If not set, the dependencies will be inherited from the asset file.
	 * @param ?string  $ver       Optional. String specifying script version number, if not set, the version will be inherited from the asset file.
	 * @param bool     $in_footer Optional. Whether to enqueue the script before </body> instead of in the <head>.
	 */
	private function register_script( string $handle, string $filename, array $deps = [], $ver = null, bool $in_footer = true ): bool {
		$asset_file = sprintf( '%s/js/%s.asset.php', $this->plugin_dir . untrailingslashit( self::ASSETS_DIR ), $filename );

		// Bail if the asset file does not exist. Log error and optionally show admin notice.
		if ( ! file_exists( $asset_file ) ) {
			return false;
		}

		// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- The file is checked for existence above.
		$asset = require_once $asset_file;

		$version   = $ver ?? ( $asset['version'] ?? filemtime( $asset_file ) );
		$asset_src = sprintf( '%s/js/%s.js', $this->plugin_url . untrailingslashit( self::ASSETS_DIR ), $filename );

		return wp_register_script(
			$handle,
			$asset_src,
			$deps ?: $asset['dependencies'],
			$version ?: false,
			$in_footer
		);
	}

	/**
	 * Register a CSS stylesheet
	 *
	 * @param string   $handle    Name of the stylesheet. Should be unique.
	 * @param string   $filename  Path of the stylesheet relative to the css directory,
	 *                            excluding the .css extension.
	 * @param string[] $deps      Optional. An array of registered stylesheet handles this stylesheet depends on. Default empty array.
	 * @param ?string  $ver       Optional. String specifying style version number, if not set, the version will be inherited from the asset file.
	 *
	 * @param string   $media     Optional. The media for which this stylesheet has been defined.
	 *                            Default 'all'. Accepts media types like 'all', 'print' and 'screen', or media queries like
	 *                            '(orientation: portrait)' and '(max-width: 640px)'.
	 */
	private function register_style( string $handle, string $filename, array $deps = [], $ver = null, string $media = 'all' ): bool {
		// CSS doesnt have a PHP assets file so we infer from the file itself.
		$asset_file = sprintf( '%s/css/%s.css', $this->plugin_dir . untrailingslashit( self::ASSETS_DIR ), $filename );

		// Bail if the asset file does not exist.
		if ( ! file_exists( $asset_file ) ) {
			return false;
		}

		$version   = $ver ?? (string) filemtime( $asset_file );
		$asset_src = sprintf( '%s/css/%s.css', $this->plugin_url . untrailingslashit( self::ASSETS_DIR ), $filename );

		// Register as a style.
		return wp_register_style(
			$handle,
			$asset_src,
			$deps,
			$version ?: false,
			$media
		);
	}
}
