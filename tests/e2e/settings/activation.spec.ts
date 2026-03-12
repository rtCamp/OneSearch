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

		// If the plugin is already activated, deactivate it first for testing the activation flow.
		// If the modal is covering the screen, force: true will click through it.
		if (
			await pluginRow
				.locator( 'a', { hasText: 'Deactivate' } )
				.isVisible()
		) {
			const deactivateLink = pluginRow.locator( 'a', {
				hasText: 'Deactivate',
			} );
			await Promise.all( [
				page.waitForURL( /plugins.php/ ),
				deactivateLink.click( { force: true } ),
			] );
		}

		const activateLink = pluginRow.locator( 'a', { hasText: 'Activate' } );

		// In WordPress, clicking activate reloads plugins.php
		await Promise.all( [
			page.waitForURL( /plugins.php/ ),
			activateLink.click( { force: true } ),
		] );

		const modal = page.locator( '#onesearch-site-selection-modal' );
		await expect( modal ).toBeVisible();

		// Select Governing Site in the onboarding modal via SelectControl
		await modal.locator( 'select' ).selectOption( 'governing-site' );
		await modal
			.locator( 'button', { hasText: 'Select Current Site Type' } )
			.click();

		// Verify we are redirected to settings page
		await page.waitForURL( /onesearch-settings/ );
		await expect(
			page.locator( 'h1', { hasText: 'Settings' } )
		).toBeVisible();

		// Reset settings option via WP API to ensure clean state for retries
		// We use Playwright's built-in fetch on the authenticated context.
		await page.request.post( '/wp-json/wp/v2/settings', {
			data: { onesearch_site_type: '' },
			headers: {
				'X-WP-Nonce': ( await page.evaluate(
					() =>
						// @ts-ignore
						window.wpApiSettings?.nonce ||
						// @ts-ignore
						window.OneSearchSettings?.nonce ||
						// @ts-ignore
						window.OneSearchOnboarding?.nonce
				) ) as string,
			},
		} );

		// Cleanup: deactivate
		await admin.visitAdminPage( '/plugins.php' );
		const deactivateLink = pluginRow.locator( 'a', {
			hasText: 'Deactivate',
		} );
		await Promise.all( [
			page.waitForURL( /plugins.php/ ),
			deactivateLink.click( { force: true } ),
		] );

		await expect(
			pluginRow.locator( 'a', { hasText: 'Activate' } )
		).toBeVisible();
	} );
} );
