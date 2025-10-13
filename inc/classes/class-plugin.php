<?php
/**
 * Plugin manifest class.
 *
 * @package onesearch
 */

namespace Onesearch\Inc;

use Onesearch\Inc\Algolia\Algolia_Index;
use Onesearch\Inc\Algolia\Algolia_Index_By_Post;
use Onesearch\Inc\Algolia\Algolia_Search;
use Onesearch\Inc\Plugin_Configs\Secret_Key;
use Onesearch\Inc\Traits\Singleton;

/**
 * Class Plugin
 */
class Plugin {

	use Singleton;

	/**
	 * Construct method.
	 */
	protected function __construct() {

		Assets::get_instance();
		Hooks::get_instance();
		Settings::get_instance();
		REST::get_instance();
		Algolia_Index::get_instance();
		Algolia_Index_By_Post::get_instance();
		Algolia_Search::get_instance();
		Secret_Key::get_instance();
	}
}
