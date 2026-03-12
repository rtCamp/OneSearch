/**
 * WordPress dependencies
 */
import {
	expect,
	test,
	RequestUtils,
} from '@wordpress/e2e-test-utils-playwright';

test.describe( 'plugin activation', () => {
	test.beforeEach( async ( { requestUtils } ) => {
		// Ensure environment is clean before test starts for Governing Site
		await requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/settings',
			data: { onesearch_site_type: '' },
		} );
	} );

	test( 'should activate the child site and add to governing site', async ( {
		page,
		admin,
		browser,
	} ) => {
		// Use the default page/admin fixtures for Governing Site (port 8889) to ensure auth and storage states are correct.
		const pageGoverning = page;

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

		// Reset Child Site environment via Playwright RequestUtils
		const childRequestUtils = new RequestUtils( contextChild.request, {
			baseURL: 'http://localhost:8890',
		} );
		await childRequestUtils.setupRest();
		await childRequestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/settings',
			data: { onesearch_site_type: '' },
		} );

		// Step 1: Ensure Governing Site is setup
		await admin.visitAdminPage( '/plugins.php' );

		const governingPluginRow = pageGoverning.locator(
			'tr[data-plugin="onesearch/onesearch.php"]'
		);
		await expect( governingPluginRow ).toBeVisible();

		// Deactivate first if already active to ensure clean state
		if (
			await governingPluginRow
				.locator( 'a', { hasText: 'Deactivate' } )
				.isVisible()
		) {
			const deactivateLink = governingPluginRow.locator( 'a', {
				hasText: 'Deactivate',
			} );

			await Promise.all( [
				pageGoverning.waitForNavigation(),
				deactivateLink.click(),
			] );
			await expect(
				governingPluginRow.locator( 'a', { hasText: 'Activate' } )
			).toBeVisible();
		}

		const activateLink = governingPluginRow.locator( 'a', {
			hasText: 'Activate',
		} );

		await Promise.all( [
			pageGoverning.waitForNavigation(),
			activateLink.click(),
		] );

		const modal = pageGoverning.locator(
			'#onesearch-site-selection-modal'
		);
		await expect( modal ).toBeVisible();
		await modal.locator( 'select' ).selectOption( 'governing-site' );
		await modal
			.locator( 'button', { hasText: 'Select Current Site Type' } )
			.click();

		await expect(
			pageGoverning.locator( 'h1', { hasText: 'Settings' } )
		).toBeVisible();

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
				pageChild.waitForNavigation(),
				deactivateLink.click(),
			] );
			await expect(
				childPluginRow.locator( 'a', { hasText: 'Activate' } )
			).toBeVisible();
		}

		const childActivateLink = childPluginRow.locator( 'a', {
			hasText: 'Activate',
		} );

		await Promise.all( [
			pageChild.waitForNavigation(),
			childActivateLink.click(),
		] );

		const childModal = pageChild.locator(
			'#onesearch-site-selection-modal'
		);
		await expect( childModal ).toBeVisible();

		await childModal.locator( 'select' ).selectOption( 'brand-site' );
		await childModal
			.locator( 'button', { hasText: 'Select Current Site Type' } )
			.click();

		await expect(
			pageChild.locator( 'h1', { hasText: 'Settings' } )
		).toBeVisible();

		// Step 3: Get API Key from Child Site
		const apiKeyInput = pageChild.getByLabel( 'API Key' );
		await expect( apiKeyInput ).toBeVisible();
		const apiKey = await apiKeyInput.inputValue();
		expect( apiKey.length ).toBeGreaterThan( 0 );

		// Step 4: Add Child Site to Governing Site
		await pageGoverning.goto(
			'/wp-admin/admin.php?page=onesearch-settings'
		);

		await expect(
			pageGoverning.locator( 'h1', { hasText: 'Settings' } )
		).toBeVisible();

		const addSiteButton = pageGoverning.locator( 'button', {
			hasText: 'Add Brand Site',
		} );
		await addSiteButton.click();

		const addSiteModal = pageGoverning.locator(
			'.components-modal__content'
		);
		await expect( addSiteModal ).toBeVisible();

		// Use better selectors by finding labels using Playwright's native getByLabel
		await addSiteModal.getByLabel( 'Site Name*' ).fill( 'Child Site' );
		await addSiteModal
			.getByLabel( 'Site URL*' )
			.fill( 'http://localhost:8890' );
		await addSiteModal.getByLabel( 'API Key*' ).fill( apiKey );

		await addSiteModal.locator( 'button', { hasText: 'Add Site' } ).click();

		// Verify it was added
		const siteList = pageGoverning.locator( 'table.components-table' );
		await expect(
			siteList.locator( 'td', { hasText: 'Child Site' } )
		).toBeVisible();

		// Cleanup: contexts
		await contextChild.close();
	} );
} );
