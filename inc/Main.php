<?php
/**
 * The main plugin file.
 *
 * @package OneSearch
 */

declare( strict_types = 1 );

namespace OneSearch;

use OneSearch\Contracts\Traits\Singleton;

/**
 * Class - Main
 */
final class Main {
	use Singleton;

	/**
	 * Registrable classes are entrypoints that "hook" into WordPress.
	 * They should implement the Registrable interface.
	 *
	 * @var class-string<\OneSearch\Contracts\Interfaces\Registrable>[]
	 */
	private const REGISTRABLE_CLASSES = [
		Modules\Core\Assets::class,
		Modules\Core\Rest::class,
		Modules\Settings\Admin::class,
		Modules\Settings\Settings::class,
		Modules\Search\Admin::class,
		Modules\Search\Settings::class,
		Modules\Rest\Basic_Options_Controller::class,

		// @todo finish migrating legacy endpoints.
		Inc\REST\Basic_Options::class,
		Inc\Algolia\Algolia_Search::class,
	];

	/**
	 * @todo Singletons are an antipattern.
	 */
	private const SINGLETON_CLASSES = [
		Inc\Algolia\Algolia_Index::class,
		Inc\Algolia\Algolia_Index_By_Post::class,
	];

	/**
	 * {@inheritDoc}
	 */
	public static function instance(): self {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
			self::$instance->setup();
		}

		return self::$instance;
	}

	/**
	 * Setup the plugin.
	 */
	private function setup(): void {
		// Ensure pretty permalinks are enabled.
		if ( ! $this->has_pretty_permalinks() ) {
			return;
		}

		// Load the plugin classes.
		$this->load();

		// Do other stuff here like dep-checking, telemetry, etc.
	}

	/**
	 * Returns whether pretty permalinks are enabled.
	 *
	 * Will also render an admin notice if not enabled.
	 */
	private function has_pretty_permalinks(): bool {
		if ( ! empty( get_option( 'permalink_structure' ) ) ) {
			return true;
		}

		foreach ( [
			'admin_notices',
			'network_admin_notices',
		] as $hook ) {
			add_action(
				$hook,
				static function () {
					wp_admin_notice(
						sprintf(
						/* translators: 1: Plugin name */
							__( 'OneSearch: The plugin requires pretty permalinks to be enabled. Please go to <a href="%s">Permalink Settings</a> and enable an option other than <code>Plain</code>.', 'onesearch' ),
							admin_url( 'options-permalink.php' ),
						),
						[
							'type'        => 'error',
							'dismissible' => false,
						]
					);
				}
			);
		}

		return false;
	}

	/**
	 * Load the plugin classes.
	 */
	private function load(): void {
		// Loop through all the classes, instantiate them, and register any hooks.
		$instances = [];
		foreach ( self::REGISTRABLE_CLASSES as $class_name ) {
			/**
			 * If it's a singleton, we can use the instance method. Otherwise we instantiate it directly.
			 *
			 * @todo reduce use of singletons where possible.
			 */
			$instances[ $class_name ] = new $class_name();
		}

		/**
		 * @todo remove this when we're no longer dealing with singletons.
		 */
		foreach ( self::SINGLETON_CLASSES as $class_name ) {
			$instances[ $class_name ] = $class_name::instance();
		}

		foreach ( $instances as $instance ) {
			// Hooks should be registered outside of the constructor.
			if ( $instance instanceof Contracts\Interfaces\Registrable ) {
				$instance->register_hooks();
			}

			// Do other generalizable stuff here.
		}
	}
}
