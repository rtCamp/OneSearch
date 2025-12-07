/**
 * WordPress dependencies
 */
import {
	Button,
	CardBody,
	Card,
	CardHeader,
	TextControl,
	__experimentalGrid as Grid, // eslint-disable-line @wordpress/no-unsafe-wp-apis
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { API_NAMESPACE, NONCE } from '../js/utils';

/**
 * External dependencies
 */
import { useState, useEffect } from 'react';

const AlgoliaSettings = ( { setNotice } ) => {
	const [ appId, setAppId ] = useState( '' );
	const [ writeKey, setWriteKey ] = useState( '' );
	const [ adminKey, setAdminKey ] = useState( '' );
	const [ initial, setInitial ] = useState( null );
	const [ saving, setSaving ] = useState( false );

	useEffect( () => {
		( async () => {
			try {
				const res = await fetch( `${ API_NAMESPACE }/algolia-credentials`, {
					headers: { 'X-WP-Nonce': NONCE },
				} );
				const data = await res.json();
				if ( res.ok ) {
					setAppId( data.app_id || '' );
					setWriteKey( data.write_key || '' );
					setAdminKey( data.admin_key || '' );
					setInitial( {
						appId: ( data.app_id || '' ).trim(),
						writeKey: ( data.write_key || '' ).trim(),
						adminKey: ( data.admin_key || '' ).trim(),
					} );
				} else {
					throw new Error( data?.message || `HTTP ${ res.status }` );
				}
			} catch ( e ) {
				setNotice( { type: 'error', message: __( 'Failed to load Algolia credentials.', 'onesearch' ) } );
			}
		} )();
	}, [] );

	const currentNormalized = {
		appId: appId.trim(),
		writeKey: writeKey.trim(),
		adminKey: adminKey.trim(),
	};
	const hasChanges =
	!! initial &&
	( initial.appId !== currentNormalized.appId ||
		initial.writeKey !== currentNormalized.writeKey ||
		initial.adminKey !== currentNormalized.adminKey
	);

	const onSave = async () => {
		try {
			setSaving( true );
			const res = await fetch( `${ API_NAMESPACE }/algolia-credentials`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': NONCE,
				},
				body: JSON.stringify( {
					app_id: appId.trim(),
					write_key: writeKey.trim(),
					admin_key: adminKey.trim(),
				} ),
			} );

			const data = await res.json();

			if ( ! res.ok ) {
				throw new Error( data?.message || `HTTP ${ res.status }` );
			}

			setNotice( { type: 'success', message: __( 'Credentials saved.', 'onesearch' ) } );
			setInitial( { ...currentNormalized } );
			window.location.reload();
		} catch ( e ) {
			setNotice( { type: 'error', message: e.message || __( 'Failed to save credentials.', 'onesearch' ) } );
		} finally {
			setSaving( false );
		}
	};

	return (
		<Card className="onesearch-card" style={ { marginTop: '30px' } }>
			<CardHeader>
				<h2>{ __( 'Algolia Credentials', 'onesearch' ) }</h2>
				<Button
					isPrimary
					onClick={ onSave }
					disabled={ ! hasChanges || saving || ! initial }
					isBusy={ saving }
				>
					{ __( 'Save Credentials', 'onesearch' ) }
				</Button>
			</CardHeader>
			<CardBody>
				<>
					<Grid columns={ 2 }>
						<TextControl
							label={ __( 'Application ID*', 'onesearch' ) }
							placeholder={ __( 'Enter your Algolia Application ID', 'onesearch' ) }
							help={ __( 'It\'s used to identify your application when using Algolia API.', 'onesearch' ) }
							value={ appId }
							onChange={ setAppId }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
						<TextControl
							label={ __( 'Write API Key*', 'onesearch' ) }
							placeholder={ __( 'Enter your Algolia Write API Key', 'onesearch' ) }
							help={ __( 'This key is usable for write operations and it\'s also able to list the indices you\'ve got access to.', 'onesearch' ) }
							type="password"
							value={ writeKey }
							onChange={ setWriteKey }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
					</Grid>
				</>
			</CardBody>
		</Card>
	);
};

export default AlgoliaSettings;
