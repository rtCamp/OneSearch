/**
 * WordPress dependencies
 */
import { expect, test } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'plugin activation', () => {
	test.beforeEach( async ( { requestUtils } ) => {
		// Ensure environment is clean before test starts.
		await requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/settings',
			data: { onesearch_site_type: '' },
		} );
	} );

	test( 'should activate and deactivate the plugin', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( '/plugins.php' );

		const pluginRow = page.locator(
			'tr[data-plugin="onesearch/onesearch.php"]'
		);
		await expect( pluginRow ).toBeVisible();

		// If the plugin is already activated, deactivate it first for testing the activation flow.
		if (
			await pluginRow
				.locator( 'a', { hasText: 'Deactivate' } )
				.isVisible()
		) {
			const deactivateLink = pluginRow.locator( 'a', {
				hasText: 'Deactivate',
			} );

			await Promise.all( [
				page.waitForNavigation(),
				deactivateLink.click(),
			] );
			await expect(
				pluginRow.locator( 'a', { hasText: 'Activate' } )
			).toBeVisible();
		}

		const activateLink = pluginRow.locator( 'a', { hasText: 'Activate' } );
		await Promise.all( [ page.waitForNavigation(), activateLink.click() ] );

		const modal = page.locator( '#onesearch-site-selection-modal' );
		await expect( modal ).toBeVisible();

		// Select Governing Site in the onboarding modal via SelectControl
		await modal.locator( 'select' ).selectOption( 'governing-site' );
		await modal
			.locator( 'button', { hasText: 'Select Current Site Type' } )
			.click();

		// Verify we are redirected to settings page
		await expect(
			page.locator( 'h1', { hasText: 'Settings' } )
		).toBeVisible();

		// Cleanup: deactivate
		await admin.visitAdminPage( '/plugins.php' );
		const deactivateLink = pluginRow.locator( 'a', {
			hasText: 'Deactivate',
		} );

		await Promise.all( [
			page.waitForNavigation(),
			deactivateLink.click(),
		] );
		await expect(
			pluginRow.locator( 'a', { hasText: 'Activate' } )
		).toBeVisible();
	} );
} );
