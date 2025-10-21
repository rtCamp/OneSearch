<?php
/**
 * Assets class.
 *
 * @package onesearch
 */

namespace Onesearch\Inc;

use Onesearch\Inc\Traits\Singleton;

/**
 * Class Assets
 */
class Assets {

	use Singleton;

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	private const NAMESPACE = 'onesearch/v1';

	/**
	 * Construct method.
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * To setup action/filter.
	 *
	 * @return void
	 */
	protected function setup_hooks() {

		/**
		 * Action
		 */
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
	}

	/**
	 * To enqueue scripts and styles.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {

		wp_register_script(
			'onesearch-script',
			ONESEARCH_URL . '/build/js/main.js',
			[],
			filemtime( ONESEARCH_PATH . '/build/js/main.js' ),
			true
		);

		wp_register_style(
			'onesearch-style',
			ONESEARCH_URL . '/build/css/main.css',
			[],
			filemtime( ONESEARCH_PATH . '/build/css/main.css' )
		);

		wp_enqueue_script( 'onesearch-script' );
		wp_enqueue_style( 'onesearch-style' );
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
			$this->register_script(
				'onesearch-settings-script',
				'js/setup.js',
			);

			wp_localize_script(
				'onesearch-settings-script',
				'OneSearchSettings',
				[
					'nonce'          => wp_create_nonce( 'wp_rest' ),
					'restUrl'        => esc_url( home_url( '/wp-json/' ) ),
					'restNamespace'  => self::NAMESPACE,
					'publicKey'      => get_option( 'onesearch_child_site_public_key', '' ),
					'restNonce'      => wp_create_nonce( 'wp_rest' ),
					'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
					'setupUrl'       => admin_url( 'admin.php?page=onesearch-settings' ),
					'currentSiteUrl' => esc_url( home_url( '/' ) ),
					'sharedSites'    => get_option( 'onesearch_shared_sites', [] ),
				]
			);

			wp_enqueue_script( 'onesearch-settings-script' );
		}

		if ( strpos( $hook_suffix, 'onesearch' ) !== false ) {
			$this->register_script(
				'onesearch-config-script',
				'js/settings.js',
			);

			wp_localize_script(
				'onesearch-config-script',
				'OneSearchSettings',
				[
					'nonce'             => wp_create_nonce( 'wp_rest' ),
					'restUrl'           => esc_url( home_url( '/wp-json/' ) ),
					'restNamespace'     => self::NAMESPACE,
					'publicKey'         => get_option( 'onesearch_child_site_public_key', '' ),
					'restNonce'         => wp_create_nonce( 'wp_rest' ),
					'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
					'setupUrl'          => admin_url( 'admin.php?page=onesearch' ),
					'currentSiteUrl'    => esc_url( home_url( '/' ) ),
					'sharedSites'       => get_option( 'onesearch_shared_sites', [] ),
					'indexableEntities' => get_option( 'onesearch_indexable_entities', [] ),
				]
			);

			wp_enqueue_script( 'onesearch-config-script' );
		}

		if ( strpos( $hook_suffix, 'plugins' ) !== false ) {
			$this->register_script(
				'onesearch-settings-script',
				'js/plugin.js',
			);

			wp_localize_script(
				'onesearch-settings-script',
				'OneSearchSettings',
				[
					'nonce'         => wp_create_nonce( 'wp_rest' ),
					'restUrl'       => esc_url( home_url( '/wp-json/' ) ),
					'restNamespace' => self::NAMESPACE,
					'apiKey'        => get_option( 'onesearch_child_site_public_key', 'default_api_key' ),
					'restNonce'     => wp_create_nonce( 'wp_rest' ),
					'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
					'setupUrl'      => admin_url( 'admin.php?page=onesearch-settings' ),
				]
			);

			wp_enqueue_script( 'onesearch-settings-script' );
		}

		$this->register_style( 'onesearch-admin-style', 'css/admin.css' );

		wp_enqueue_style( 'onesearch-admin-style' );
	}

	/**
	 * Register a CSS stylesheet.
	 *
	 * @param string           $handle Name of the stylesheet. Should be unique.
	 * @param string|bool      $file   Style file, path of the script relative to the build/ directory.
	 * @param array            $deps   Optional. An array of registered stylesheet handles this stylesheet depends on. Default empty array.
	 * @param string|bool|null $ver    Optional. String specifying script version number, if not set, filetime will be used as version number.
	 * @param string           $media  Optional. The media for which this stylesheet has been defined.
	 *                                 Default 'all'. Accepts media types like 'all', 'print' and 'screen', or media queries like
	 *                                 '(orientation: portrait)' and '(max-width: 640px)'.
	 *
	 * @return bool Whether the style has been registered. True on success, false on failure.
	 */
	public function register_style( $handle, $file, $deps = [], $ver = false, $media = 'all' ) {

		$file_path = sprintf( '%s/%s', ONESEARCH_PATH . '/build', $file );

		if ( ! \file_exists( $file_path ) ) {
			return false;
		}

		$src     = sprintf( ONESEARCH_URL . '/build/%s', $file );
		$version = $this->get_file_version( $file, $ver );

		return wp_register_style( $handle, $src, $deps, $version, $media );
	}

	/**
	 * Register a new script.
	 *
	 * @param string           $handle    Name of the script. Should be unique.
	 * @param string|bool      $file      Script file, path of the script relative to the build/ directory.
	 * @param array            $deps      Optional. An array of registered script handles this script depends on. Default empty array.
	 * @param string|bool|null $ver       Optional. String specifying script version number, if not set, filetime will be used as version number.
	 * @param bool             $in_footer Optional. Whether to enqueue the script before </body> instead of in the <head>.
	 *                                    Default 'false'.
	 * @return bool Whether the script has been registered. True on success, false on failure.
	 */
	public function register_script( $handle, $file, $deps = [], $ver = false, $in_footer = true ) {

		$file_path = sprintf( '%s/%s', ONESEARCH_PATH . '/build', $file );

		if ( ! \file_exists( $file_path ) ) {
			return false;
		}

		$src        = sprintf( ONESEARCH_URL . '/build/%s', $file );
		$asset_meta = $this->get_asset_meta( $file, $deps );

		// register each dependency styles.
		if ( ! empty( $asset_meta['dependencies'] ) ) {
			foreach ( $asset_meta['dependencies'] as $dependency ) {
				wp_enqueue_style( $dependency );
			}
		}

		return wp_register_script( $handle, $src, $asset_meta['dependencies'], $asset_meta['version'], $in_footer );
	}

	/**
	 * Get asset dependencies and version info from {handle}.asset.php if exists.
	 *
	 * @param string $file File name.
	 * @param array  $deps Script dependencies to merge with.
	 * @param string $ver  Asset version string.
	 *
	 * @return array
	 */
	public function get_asset_meta( $file, $deps = [], $ver = false ) {
		$asset_meta_file = sprintf( '%s/js/%s.asset.php', untrailingslashit( ONESEARCH_PATH . '/build' ), basename( $file, '.' . pathinfo( $file )['extension'] ) );
		$asset_meta      = is_readable( $asset_meta_file )
			? require $asset_meta_file
			: [
				'dependencies' => [],
				'version'      => $this->get_file_version( $file, $ver ),
			];

		$asset_meta['dependencies'] = array_merge( $deps, $asset_meta['dependencies'] );

		return $asset_meta;
	}

	/**
	 * Get file version.
	 *
	 * @param string          $file File path.
	 * @param int|string|bool $ver  File version.
	 *
	 * @return bool|false|int
	 */
	public function get_file_version( $file, $ver = false ) {
		if ( ! empty( $ver ) ) {
			return $ver;
		}

		$file_path = sprintf( '%s/%s', ONESEARCH_PATH . '/build', $file );

		return file_exists( $file_path ) ? filemtime( $file_path ) : false;
	}
}
