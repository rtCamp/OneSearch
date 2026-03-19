<?php
/**
 * Test the Admin module.
 *
 * @package OneSearch\Tests\Unit\inc\Modules\Settings
 */

declare( strict_types = 1 );

namespace OneSearch\Tests\Unit\inc\Modules\Settings;

use OneSearch\Modules\Settings\Admin;
use OneSearch\Modules\Settings\Settings;
use OneSearch\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Class - AdminTest
 */
#[CoversClass( Admin::class )]
class AdminTest extends TestCase {
	/**
	 * Setup.
	 */
	public function setUp(): void {
		parent::setUp();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	/**
	 * Test admin menu generation using WordPress actions.
	 */
	public function test_admin_menu_generation(): void {
		// Initialize the admin module. It calls register_hooks, which hooks admin_menu.
		$admin = new Admin();
		$admin->register_hooks();

		// Trigger the WordPress action that builds the menus.
		do_action( 'admin_menu' );

		global $menu, $submenu;

		// Assert main menu exists.
		$found_main = false;
		foreach ( $menu as $item ) {
			if ( Admin::MENU_SLUG === $item[2] ) {
				$found_main = true;
				break;
			}
		}
		$this->assertTrue( $found_main, 'Main menu slug was not added' );

		// Assert submenu exists.
		$this->assertArrayHasKey( Admin::MENU_SLUG, $submenu );
		$found_sub = false;
		foreach ( // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$submenu[ Admin::MENU_SLUG ] as $item ) {
			if ( Admin::SCREEN_ID === $item[2] ) {
				$found_sub = true;
				break;
			}
		}
		$this->assertTrue( $found_sub, 'Submenu slug was not added' );
	}

	/**
	 * Test remove_default_submenu integration.
	 */
	public function test_remove_default_submenu_integration(): void {
		$admin = new Admin();
		$admin->register_hooks();

		// Case 1: Governing site with shared sites - default submenu should stay.
		update_option( Settings::OPTION_SITE_TYPE, 'governing-site' );
		Settings::set_shared_sites(
			[
				[
					'id'      => '123',
					'name'    => 'site',
					'url'     => 'https://example.com',
					'api_key' => '123',
				],
			]
		);

		// The remove_default_submenu runs at priority 999
		// It only checks if it should remove. To ensure it doesn't remove we need the menu to exist.
		// Since we didn't add it in this test (do_action admin_menu normally does, but we have to register hooks first)
		// we already called register_hooks above. So it should add the submenu then not remove it.
		global $submenu;
		$submenu[ Admin::MENU_SLUG ][] = [ 'OneSearch', 'manage_options', Admin::MENU_SLUG ];

		do_action( 'admin_menu' );

		$found_default = false;
		if ( isset( $submenu[ Admin::MENU_SLUG ] ) ) {
			foreach ( // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$submenu[ Admin::MENU_SLUG ] as $item ) {
				if ( Admin::MENU_SLUG === $item[2] ) {
					$found_default = true;
					break;
				}
			}
		}
		$this->assertTrue( $found_default, 'Default submenu was incorrectly removed' );

		// Reset submenu for next test.
		$submenu = [];

		// Case 2: Consumer site - default submenu should be removed.
		update_option( Settings::OPTION_SITE_TYPE, 'brand-site' );
		update_option( Settings::OPTION_GOVERNING_SHARED_SITES, [] );

		$submenu[ Admin::MENU_SLUG ] = [
			[ 'OneSearch', 'manage_options', Admin::MENU_SLUG, 'OneSearch' ],
		];

		do_action( 'admin_menu' );

		$found_default = false;
		if ( isset( $submenu[ Admin::MENU_SLUG ] ) ) {
			foreach ( // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$submenu[ Admin::MENU_SLUG ] as $item ) {
				if ( Admin::MENU_SLUG === $item[2] ) {
					$found_default = true;
					break;
				}
			}
		}
		$this->assertFalse( $found_default, 'Default submenu was not removed for brand site' );
	}

	/**
	 * Test screen_callback renders expected content.
	 */
	public function test_screen_callback_renders(): void {
		$admin = new Admin();

		ob_start();
		$admin->screen_callback();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<div id="onesearch-settings-page"></div>', $output );
		$this->assertStringContainsString( 'Settings', $output );
	}

	/**
	 * Test script enqueuing during admin_enqueue_scripts action.
	 */
	public function test_enqueue_scripts_integration(): void {
		$admin = new Admin();
		$admin->register_hooks();

		wp_dequeue_script( \OneSearch\Modules\Core\Assets::SETTINGS_SCRIPT_HANDLE );

		// Simulate being on an unrelated page.
		$GLOBALS['current_screen'] = \WP_Screen::get( 'tools' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		do_action( 'admin_enqueue_scripts', 'tools.php' );
		$this->assertFalse( wp_script_is( \OneSearch\Modules\Core\Assets::SETTINGS_SCRIPT_HANDLE, 'enqueued' ) );

		// Simulate being on the OneSearch settings page.
		// Because there's no actual build file, wp_enqueue_script won't register,.
		// but we can check if it tries to enqueue it.
		$GLOBALS['current_screen'] = \WP_Screen::get( 'toplevel_page_' . Admin::SCREEN_ID ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		wp_register_script( \OneSearch\Modules\Core\Assets::SETTINGS_SCRIPT_HANDLE, 'dummy.js' );

		do_action( 'admin_enqueue_scripts', 'toplevel_page_' . Admin::SCREEN_ID );
		$this->assertTrue( wp_script_is( \OneSearch\Modules\Core\Assets::SETTINGS_SCRIPT_HANDLE, 'enqueued' ) );
		$GLOBALS['current_screen'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Test action links injected on plugins page.
	 */
	public function test_add_action_links(): void {
		$admin = new Admin();

		$links = [ '<a href="test">Original</a>' ];

		// The hook is dynamic based on plugin basename, call the method directly to check logic.
		$modified = $admin->add_action_links( $links );

		$this->assertCount( 2, $modified );
		$this->assertStringContainsString( 'Original', $modified[0] );
		$this->assertStringContainsString( Admin::SCREEN_ID, $modified[1] );
		$this->assertStringContainsString( 'Settings', $modified[1] );
	}

	/**
	 * Test body class additions
	 */
	public function test_body_classes_integration(): void {
		$admin = new Admin();

		set_current_screen( 'front' );

		update_option( Settings::OPTION_SITE_TYPE, 'brand-site' );
		Settings::set_shared_sites(
			[
				[
					'id'      => '123',
					'name'    => 'site',
					'url'     => 'https://example.com',
					'api_key' => '123',
				],
			]
		);

		$this->assertEquals( 'test-class', $admin->add_body_classes( 'test-class' ) );

		$screen = \WP_Screen::get( 'plugins' );
		set_current_screen( $screen );

		update_option( Settings::OPTION_SITE_TYPE, '' );
		update_option( Settings::OPTION_GOVERNING_SHARED_SITES, [] );

		// Call method directly to avoid apply_filters triggering WP core debug notices
		$classes = $admin->add_body_classes( 'test-class' );
		$this->assertStringContainsString( 'onesearch-site-selection-modal', $classes );
		$this->assertStringContainsString( 'onesearch-missing-brand-sites', $classes );

		set_current_screen( 'front' );
	}
}
