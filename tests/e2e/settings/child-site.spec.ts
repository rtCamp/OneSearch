/**
 * WordPress dependencies
 */
import { expect, test } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'plugin activation', () => {
	test( 'should activate the child site and add to governing site', async ( {
		browser,
	} ) => {
		// Context 1: Governing Site
		const contextGoverning = await browser.newContext( {
			baseURL: 'http://localhost:8889',
			storageState: 'tests/_output/e2e/storage-states/admin.json',
		} );
		const pageGoverning = await contextGoverning.newPage();

		// Context 2: Brand/Child Site
		const contextChild = await browser.newContext( {
			baseURL: 'http://localhost:8890',
		} );
		const pageChild = await contextChild.newPage();

		// Log into child site
		await pageChild.goto( '/wp-login.php' );
		await pageChild.locator( '#user_login' ).fill( 'admin' );
		await pageChild.locator( '#user_pass' ).fill( 'password' );
		await pageChild.locator( '#wp-submit' ).click();

		// Wait for login to complete
		await expect( pageChild.locator( '#wpadminbar' ) ).toBeVisible();

		// Step 1: Ensure Governing Site is setup
		await pageGoverning.goto( '/wp-admin/plugins.php' );
		const governingPluginRow = pageGoverning.locator(
			'tr[data-plugin="onesearch/onesearch.php"]'
		);
		await expect( governingPluginRow ).toBeVisible();

		if (
			await governingPluginRow
				.locator( 'a', { hasText: 'Activate' } )
				.isVisible()
		) {
			const activateLink = governingPluginRow.locator( 'a', {
				hasText: 'Activate',
			} );
			await Promise.all( [
				pageGoverning.waitForURL( /onesearch-settings/ ),
				activateLink.click(),
			] );

			const modal = pageGoverning.locator(
				'#onesearch-site-selection-modal'
			);
			await expect( modal ).toBeVisible();
			await modal
				.locator( 'button', { hasText: 'Governing Site' } )
				.click();
			await expect( modal ).toBeHidden();
		}

		// Step 2: Setup Child Site
		await pageChild.goto( '/wp-admin/plugins.php' );
		const childPluginRow = pageChild.locator(
			'tr[data-plugin="onesearch/onesearch.php"]'
		);
		await expect( childPluginRow ).toBeVisible();

		// Deactivate first if already active to ensure a clean state
		if (
			await childPluginRow
				.locator( 'a', { hasText: 'Deactivate' } )
				.isVisible()
		) {
			const deactivateLink = childPluginRow.locator( 'a', {
				hasText: 'Deactivate',
			} );
			await Promise.all( [
				pageChild.waitForURL( /plugins.php/ ),
				deactivateLink.click(),
			] );
		}

		const childActivateLink = childPluginRow.locator( 'a', {
			hasText: 'Activate',
		} );
		await Promise.all( [
			pageChild.waitForURL( /onesearch-settings/ ),
			childActivateLink.click(),
		] );

		const childModal = pageChild.locator(
			'#onesearch-site-selection-modal'
		);
		await expect( childModal ).toBeVisible();

		await childModal.locator( 'button', { hasText: 'Brand Site' } ).click();
		await expect( childModal ).toBeHidden();

		// Step 3: Get API Key from Child Site
		const apiKeyInput = pageChild.locator( 'textarea' );
		await expect( apiKeyInput ).toBeVisible();
		const apiKey = await apiKeyInput.inputValue();
		expect( apiKey.length ).toBeGreaterThan( 0 );

		// Step 4: Add Child Site to Governing Site
		await pageGoverning.goto(
			'/wp-admin/admin.php?page=onesearch-settings'
		);

		await expect(
			pageGoverning.locator( 'h1', { hasText: 'OneSearch' } )
		).toBeVisible();

		const addSiteButton = pageGoverning.locator( 'button', {
			hasText: 'Add Brand Site',
		} );
		await addSiteButton.click();

		const addSiteModal = pageGoverning.locator(
			'.components-modal__content'
		);
		await expect( addSiteModal ).toBeVisible();

		// Use better selectors by finding labels
		await addSiteModal
			.locator( 'label:has-text("Site Name*")' )
			.locator( '..//input' )
			.fill( 'Child Site' );
		await addSiteModal
			.locator( 'label:has-text("Site URL*")' )
			.locator( '..//input' )
			.fill( 'http://localhost:8890' );
		await addSiteModal
			.locator( 'label:has-text("API Key*")' )
			.locator( '..//textarea' )
			.fill( apiKey );

		await addSiteModal.locator( 'button', { hasText: 'Add Site' } ).click();

		// Verify it was added
		const siteList = pageGoverning.locator( 'table.components-table' );
		await expect(
			siteList.locator( 'td', { hasText: 'Child Site' } )
		).toBeVisible();

		// Cleanup: contexts
		await contextGoverning.close();
		await contextChild.close();
	} );
} );
