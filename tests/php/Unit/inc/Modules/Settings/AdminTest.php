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
	 * Test add_admin_menu.
	 */
	public function test_add_admin_menu(): void {
		$admin = new Admin();

		// Set current user to admin so menu functions work.
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$admin->add_admin_menu();

		global $menu;
		$found = false;
		foreach ( $menu as $item ) {
			if ( Admin::MENU_SLUG === $item[2] ) {
				$found = true;
				break;
			}
		}

		$this->assertTrue( $found );
	}

	/**
	 * Test add_submenu.
	 */
	public function test_add_submenu(): void {
		$admin = new Admin();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$admin->add_admin_menu();
		$admin->add_submenu();

		global $submenu; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$this->assertArrayHasKey( Admin::MENU_SLUG, $submenu );

		$found = false;
		foreach ( // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$submenu[ Admin::MENU_SLUG ] as $item ) {
			if ( Admin::SCREEN_ID === $item[2] ) {
				$found = true;
				break;
			}
		}

		$this->assertTrue( $found );
	}

	/**
	 * Test remove_default_submenu
	 */
	public function test_remove_default_submenu(): void {
		$admin = new Admin();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		// 1. Test when governing site and has shared sites (should NOT remove).
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

		$admin->add_admin_menu();

		// Mock a submenu item with same slug.
		global $submenu; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$submenu[ Admin::MENU_SLUG ][] = [ 'OneSearch', 'manage_options', Admin::MENU_SLUG ];

		$admin->remove_default_submenu();

		$found = false;
		foreach ( // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$submenu[ Admin::MENU_SLUG ] as $item ) {
			if ( Admin::MENU_SLUG === $item[2] ) {
				$found = true;
				break;
			}
		}
		$this->assertTrue( $found, 'Submenu should not be removed' );

		// 2. Test when not governing site (should remove).
		update_option( Settings::OPTION_SITE_TYPE, 'brand-site' );

		// WordPress removes the submenu page by mutating the global directly.
		// It only works if the slug matches the menu slug correctly.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$submenu[ Admin::MENU_SLUG ] = [
			[ 'OneSearch', 'manage_options', Admin::MENU_SLUG, 'OneSearch' ],
		];

		$admin->remove_default_submenu();

		$found = false;
		if ( isset( // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$submenu[ Admin::MENU_SLUG ]
		) ) {
			foreach ( // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$submenu[ Admin::MENU_SLUG ] as $item ) {
				if ( Admin::MENU_SLUG === $item[2] ) {
					$found = true;
					break;
				}
			}
		}
		$this->assertFalse( $found, 'Submenu should be removed' );
	}

	/**
	 * Test screen_callback.
	 */
	public function test_screen_callback(): void {
		$admin = new Admin();

		ob_start();
		$admin->screen_callback();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<div id="onesearch-settings-page"></div>', $output );
		$this->assertStringContainsString( 'Settings', $output );
	}

	/**
	 * Test enqueue_scripts
	 */
	public function test_enqueue_scripts(): void {
		$admin = new Admin();

		wp_dequeue_script( \OneSearch\Modules\Core\Assets::SETTINGS_SCRIPT_HANDLE );
		wp_dequeue_script( \OneSearch\Modules\Core\Assets::ONBOARDING_SCRIPT_HANDLE );

		// Unrelated hook.
		$admin->enqueue_scripts( 'tools.php' );
		$this->assertFalse( wp_script_is( \OneSearch\Modules\Core\Assets::SETTINGS_SCRIPT_HANDLE, 'enqueued' ) );
	}

	/**
	 * Test add_action_links.
	 */
	public function test_add_action_links(): void {
		$admin = new Admin();

		$links          = [ '<a href="something">Link</a>' ];
		$modified_links = $admin->add_action_links( $links );

		$this->assertCount( 2, $modified_links );
		$this->assertStringContainsString( 'Settings', $modified_links[1] );
		$this->assertStringContainsString( Admin::SCREEN_ID, $modified_links[1] );

		// Test doing it wrong when wrong type.
		$this->setExpectedIncorrectUsage( 'OneSearch\Modules\Settings\Admin::add_action_links' );
		$modified_links_wrong = $admin->add_action_links( 'string' );

		$this->assertCount( 1, $modified_links_wrong );
		$this->assertStringContainsString( 'Settings', $modified_links_wrong[0] );
	}
}
