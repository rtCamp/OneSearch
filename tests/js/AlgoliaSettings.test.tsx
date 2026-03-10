/**
 * External dependencies
 */
import { fireEvent, render, screen, waitFor } from '@testing-library/react';

/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

import AlgoliaSettings from '@/components/AlgoliaSettings';

const mockedApiFetch = apiFetch as jest.MockedFunction< typeof apiFetch >;

describe( 'AlgoliaSettings', () => {
	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'loads saved credentials and keeps save disabled until values change', async () => {
		const setNotice = jest.fn();

		mockedApiFetch.mockImplementation( ( params ) => {
			if (
				params &&
				typeof params === 'object' &&
				( params as Record< string, unknown > )[ 'path' ] ===
					'/onesearch/v1/algolia-credentials'
			) {
				return Promise.resolve( {
					app_id: '  app-id  ',
					write_key: '  write-key  ',
				} );
			}
			return Promise.resolve( {} );
		} );

		render( <AlgoliaSettings setNotice={ setNotice } /> );

		const appIdInput = await screen.findByRole( 'textbox', {
			name: /Application ID/i,
		} );
		expect( appIdInput ).toHaveValue( 'app-id' );

		expect( screen.getByLabelText( 'Write API Key*' ) ).toHaveValue(
			'write-key'
		);
		expect(
			screen.getByRole( 'button', { name: /Save Credentials/i } )
		).toBeDisabled();
	} );

	it( 'shows an error notice when loading credentials fails', async () => {
		const setNotice = jest.fn();

		mockedApiFetch.mockImplementation( () =>
			Promise.reject( new Error( 'failed' ) )
		);

		render( <AlgoliaSettings setNotice={ setNotice } /> );

		await waitFor( () => {
			expect( setNotice ).toHaveBeenCalledWith(
				expect.objectContaining( {
					type: 'error',
					message: expect.stringMatching(
						/Error fetching Algolia credentials/i
					),
				} )
			);
		} );
	} );

	it( 'saves changed credentials and reports success', async () => {
		const setNotice = jest.fn();

		mockedApiFetch.mockImplementation( ( params ) => {
			if (
				params &&
				typeof params === 'object' &&
				( params as Record< string, unknown > )[ 'method' ] === 'POST'
			) {
				return Promise.resolve( { success: true } );
			}
			if (
				params &&
				typeof params === 'object' &&
				( params as Record< string, unknown > )[ 'path' ] ===
					'/onesearch/v1/algolia-credentials'
			) {
				return Promise.resolve( {
					app_id: 'app-id',
					write_key: 'write-key',
				} );
			}
			return Promise.resolve( {} );
		} );

		render( <AlgoliaSettings setNotice={ setNotice } /> );

		const appIdInput = await screen.findByRole( 'textbox', {
			name: /Application ID/i,
		} );
		fireEvent.change( appIdInput, { target: { value: 'updated-app' } } );
		fireEvent.click(
			screen.getByRole( 'button', { name: /Save Credentials/i } )
		);

		await waitFor( () => {
			expect( mockedApiFetch ).toHaveBeenLastCalledWith(
				expect.objectContaining( {
					path: '/onesearch/v1/algolia-credentials',
					method: 'POST',
					data: expect.objectContaining( {
						app_id: 'updated-app',
						write_key: 'write-key',
					} ),
				} )
			);
		} );

		expect( setNotice ).toHaveBeenCalledWith(
			expect.objectContaining( {
				type: 'success',
				message: expect.stringMatching(
					/Algolia credentials saved successfully/i
				),
			} )
		);
	} );

	it( 'reports an error when saving fails', async () => {
		const setNotice = jest.fn();

		mockedApiFetch.mockImplementation( ( params ) => {
			if (
				params &&
				typeof params === 'object' &&
				( params as Record< string, unknown > )[ 'method' ] === 'POST'
			) {
				return Promise.reject( new Error( 'save failed' ) );
			}
			if (
				params &&
				typeof params === 'object' &&
				( params as Record< string, unknown > )[ 'path' ] ===
					'/onesearch/v1/algolia-credentials'
			) {
				return Promise.resolve( {
					app_id: 'app-id',
					write_key: 'write-key',
				} );
			}
			return Promise.resolve( {} );
		} );

		render( <AlgoliaSettings setNotice={ setNotice } /> );

		// Use getByLabelText for password field (not a textbox role)
		const writeKeyInput = await screen.findByLabelText( /Write API Key/i );
		fireEvent.change( writeKeyInput, { target: { value: 'new-key' } } );
		fireEvent.click(
			screen.getByRole( 'button', { name: /Save Credentials/i } )
		);

		await waitFor( () => {
			expect( setNotice ).toHaveBeenCalledWith(
				expect.objectContaining( {
					type: 'error',
					message: expect.stringMatching(
						/Error saving Algolia credentials/i
					),
				} )
			);
		} );
	} );
} );
