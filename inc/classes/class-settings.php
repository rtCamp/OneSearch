<?php
/**
 * Create OneSearch settings page.
 *
 * @package OneSearch
 */

namespace Onesearch\Inc;

use Onesearch\Inc\Settings\Shared_Sites;
use Onesearch\Inc\Traits\Singleton;

/**
 * Class Settings
 */
class Settings {

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
		Shared_Sites::get_instance();
	}
}
