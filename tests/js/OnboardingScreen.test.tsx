/**
 * External dependencies
 */
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

import OnboardingScreen from '@/admin/onboarding/page';

const mockedApiFetch = apiFetch as jest.MockedFunction< typeof apiFetch >;

describe( 'OnboardingScreen', () => {
	beforeEach( () => {
		mockedApiFetch.mockReset();
		window.OneSearchOnboarding = {
			nonce: 'onboarding-nonce',
			site_type: '',
			setup_url: '',
		};
	} );

	it( 'loads the current site type from settings', async () => {
		// Suppress WordPress component deprecation warnings (outside our control)
		jest.spyOn( console, 'warn' ).mockImplementation( () => {} );

		mockedApiFetch.mockResolvedValueOnce( {
			onesearch_site_type: 'brand-site',
		} );

		render( <OnboardingScreen /> );

		await waitFor( () => {
			expect( screen.getByLabelText( 'Site Type' ) ).toHaveValue(
				'brand-site'
			);
		} );
	} );

	it( 'shows an error notice when loading fails', async () => {
		// Suppress WordPress component deprecation warnings (outside our control)
		jest.spyOn( console, 'warn' ).mockImplementation( () => {} );

		mockedApiFetch.mockRejectedValueOnce( new Error( 'load failed' ) );

		render( <OnboardingScreen /> );

		// WordPress renders notices in both accessibility region and visible content
		expect(
			await screen.findAllByText( 'Error fetching site type.' )
		).toHaveLength( 2 );
	} );

	it( 'saves the chosen site type', async () => {
		// Suppress WordPress component deprecation warnings (outside our control)
		jest.spyOn( console, 'warn' ).mockImplementation( () => {} );

		mockedApiFetch.mockResolvedValueOnce( {} ).mockResolvedValueOnce( {
			onesearch_site_type: 'governing-site',
		} );

		render( <OnboardingScreen /> );

		fireEvent.change( await screen.findByLabelText( 'Site Type' ), {
			target: { value: 'governing-site' },
		} );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Select Current Site Type' } )
		);

		await waitFor( () => {
			expect( mockedApiFetch ).toHaveBeenLastCalledWith( {
				path: '/wp/v2/settings',
				method: 'POST',
				data: { onesearch_site_type: 'governing-site' },
			} );
		} );
	} );

	it( 'shows an error notice when saving fails', async () => {
		// Suppress WordPress component deprecation warnings (outside our control)
		jest.spyOn( console, 'warn' ).mockImplementation( () => {} );

		mockedApiFetch
			.mockResolvedValueOnce( {} )
			.mockRejectedValueOnce( new Error( 'save failed' ) );

		render( <OnboardingScreen /> );

		fireEvent.change( await screen.findByLabelText( 'Site Type' ), {
			target: { value: 'brand-site' },
		} );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Select Current Site Type' } )
		);

		// WordPress renders notices in both accessibility region and visible content
		expect(
			await screen.findAllByText( 'Error setting site type.' )
		).toHaveLength( 2 );
	} );
} );
