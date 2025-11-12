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
		Modules\Settings\Admin::class,
		Modules\Settings\Settings::class,
		Modules\Rest\Rest::class,
	];

	/**
	 * @todo Singletons are an antipattern.
	 */
	private const SINGLETON_CLASSES = [
		Inc\Algolia\Algolia_Index::class,
		Inc\Algolia\Algolia_Index_By_Post::class,
		Inc\Algolia\Algolia_Search::class,
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
		// Load the plugin classes.
		$this->load();

		// Do other stuff here like dep-checking, telemetry, etc.
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
