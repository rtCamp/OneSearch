<?php
/**
 * Create OneSearch settings page.
 *
 * @package OneSearch
 */

namespace Onesearch\Inc;

use Onesearch\Contracts\Interfaces\Registrable;
use Onesearch\Inc\Settings\Shared_Sites;

/**
 * Class Settings
 */
class Settings implements Registrable {

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		Shared_Sites::instance();
	}
}
