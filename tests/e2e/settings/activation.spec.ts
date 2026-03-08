/**
 * WordPress dependencies
 */
import { expect, test } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'plugin activation', () => {
	test( 'should activate and deactivate the plugin', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( '/plugins.php' );

		const pluginRow = page.locator(
			'tr[data-plugin="onesearch/onesearch.php"]'
		);
		await expect( pluginRow ).toBeVisible();

		// Hide the onboarding modal if it appears (blocks all interaction). It's tested separately.
		const modal = page.locator( '#onesearch-site-selection-modal' );
		if ( await modal.isVisible().catch( () => false ) ) {
			await page.evaluate( () => {
				document
					.getElementById( 'onesearch-site-selection-modal' )
					?.remove();
				document.body.classList.remove(
					'onesearch-site-selection-modal'
				);
			} );
		}

		// Deactivate first to ensure we start from inactive state.
		const deactivateLink = pluginRow.locator( 'a', {
			hasText: 'Deactivate',
		} );
		if ( await deactivateLink.isVisible().catch( () => false ) ) {
			await deactivateLink.click();
			await expect(
				pluginRow.locator( 'a', { hasText: 'Activate' } )
			).toBeVisible( { timeout: 10000 } );
		}

		// Test activation from inactive state.
		await pluginRow.locator( 'a', { hasText: 'Activate' } ).click();

		// Verify plugin is active.
		await expect(
			pluginRow.locator( 'a', { hasText: 'Deactivate' } )
		).toBeVisible( { timeout: 10000 } );

		// Test deactivation.
		await pluginRow.locator( 'a', { hasText: 'Deactivate' } ).click();
		await expect(
			pluginRow.locator( 'a', { hasText: 'Activate' } )
		).toBeVisible( { timeout: 10000 } );

		// Test reactivation (no modal since site type is already set).
		await pluginRow.locator( 'a', { hasText: 'Activate' } ).click();
		await expect(
			pluginRow.locator( 'a', { hasText: 'Deactivate' } )
		).toBeVisible( { timeout: 10000 } );
	} );
} );
