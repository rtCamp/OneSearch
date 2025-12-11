/**
 * WordPress dependencies
 */
import { useState, useEffect } from 'react';
import {
	Button,
	CardBody,
	Card,
	CardHeader,
	TextControl,
	__experimentalGrid as Grid,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import type { NoticeType } from '@/admin/settings/page';

interface AlgoliaCredentials {
	app_id: string;
	write_key: string;
	admin_key?: string;
}

const EMPTY_CREDENTIALS: AlgoliaCredentials = {
	app_id: '',
	write_key: '',
	admin_key: '',
};

const AlgoliaSettings = (
	{ setNotice } :
	{
		setNotice: ( notice: NoticeType ) => void;
	},
) => {
	const [ algoliaCreds, setAlgoliaCreds ] = useState< AlgoliaCredentials >( EMPTY_CREDENTIALS );
	const [ initial, setInitial ] = useState<AlgoliaCredentials | null>(
		null,
	);
	const [ saving, setSaving ] = useState( false );

	useEffect( () => {
		apiFetch<{ onesearch_algolia_credentials: AlgoliaCredentials }>( {
			path: '/wp/v2/settings',
		} )
			.then( ( settings ) => {
				if ( settings?.onesearch_algolia_credentials ) {
					const creds = settings.onesearch_algolia_credentials;
					setAlgoliaCreds( {
						app_id: creds.app_id,
						write_key: creds.write_key,
						admin_key: creds.admin_key || '',
					} );
					setInitial( {
						app_id: ( creds.app_id ).trim(),
						write_key: ( creds.write_key ).trim(),
						admin_key: ( creds.admin_key || '' ).trim(),
					} );
				} else {
					// No credentials saved yet, set initial as empty
					setInitial( {
						app_id: '',
						write_key: '',
						admin_key: '',
					} );
				}
			} )
			.catch( () => {
				setNotice( {
					type: 'error',
					message: __( 'Error fetching Algolia credentials.', 'onesearch' ),
				} );
			} );
	}, [ setNotice ] );

	const hasChanges =
		!! initial &&
		( algoliaCreds.app_id.trim() !== initial.app_id ||
			algoliaCreds.write_key.trim() !== initial.write_key ||
			algoliaCreds.admin_key?.trim() !== initial.admin_key );

	// Validate that required fields are filled
	const isValid = !! ( algoliaCreds &&
		algoliaCreds.app_id.trim() !== '' &&
		algoliaCreds.write_key.trim() !== '' );

	const onSave = async () => {
		setSaving( true );
		apiFetch<{ onesearch_algolia_credentials: AlgoliaCredentials }>( {
			path: '/wp/v2/settings',
			method: 'POST',
			data: {
				onesearch_algolia_credentials: {
					app_id: algoliaCreds.app_id.trim(),
					write_key: algoliaCreds.write_key.trim(),
					admin_key: algoliaCreds?.admin_key?.trim() || '',
				},
			},
		} )
			.then( ( settings ) => {
				if ( settings?.onesearch_algolia_credentials ) {
					const creds = settings.onesearch_algolia_credentials;
					setAlgoliaCreds( {
						app_id: creds.app_id,
						write_key: creds.write_key,
						admin_key: creds.admin_key || '',
					} );
					setInitial( {
						app_id: ( creds.app_id ).trim(),
						write_key: ( creds.write_key ).trim(),
						admin_key: ( creds.admin_key || '' ).trim(),
					} );
					setNotice( {
						type: 'success',
						message: __( 'Algolia credentials saved successfully.', 'onesearch' ),
					} );
				}
			} )
			.catch( () => {
				setNotice( {
					type: 'error',
					message: __(
						'Error saving Algolia credentials. Please try again later.',
						'onesearch',
					),
				} );
			} )
			.finally( () => {
				setSaving( false );
			} );
	};

	return (
		<Card className="onesearch-card" style={ { marginTop: '30px' } }>
			<CardHeader>
				<h2>{ __( 'Algolia Credentials', 'onesearch' ) }</h2>
				<Button
					variant="primary"
					onClick={ onSave }
					disabled={ ! hasChanges || saving || ! initial || ! isValid }
					isBusy={ saving }
				>
					{ __( 'Save Credentials', 'onesearch' ) }
				</Button>
			</CardHeader>
			<CardBody>
				<Grid columns={ 2 }>
					<TextControl
						label={ __( 'Application ID*', 'onesearch' ) }
						placeholder={ __( 'Enter your Algolia Application ID', 'onesearch' ) }
						help={ __(
							"It's used to identify your application when using Algolia API.",
							'onesearch',
						) }
						value={ algoliaCreds.app_id }
						onChange={ ( value ) =>
							setAlgoliaCreds( ( prev ) => ( {
								...prev,
								app_id: value,
							} ) )
						}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<TextControl
						label={ __( 'Write API Key*', 'onesearch' ) }
						placeholder={ __( 'Enter your Algolia Write API Key', 'onesearch' ) }
						help={ __(
							"This key is usable for write operations and it's also able to list the indices you've got access to.",
							'onesearch',
						) }
						type="password"
						value={ algoliaCreds.write_key }
						onChange={ ( value ) =>
							setAlgoliaCreds( ( prev ) => ( {
								...prev,
								write_key: value,
							} ) )
						}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				</Grid>
			</CardBody>
		</Card>
	);
};

export default AlgoliaSettings;
