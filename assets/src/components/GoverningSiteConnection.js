/**
 * WordPress dependencies
 */
import { useEffect, useState, useCallback } from '@wordpress/element';
import { Card, Button, TextControl, Modal, CardHeader, CardBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { API_NAMESPACE, NONCE } from '../js/utils';

const GoverningSiteConnection = ( { setNotice } ) => {
	const [ governingSiteURL, setGoverningSiteURL ] = useState( '' );
	const [ isBusy, setIsBusy ] = useState( false );
	const [ showDisconnectionModal, setShowDisconnectionModal ] = useState( false );

	const fetchParentUrl = useCallback( async () => {
		try {
			const res = await fetch( API_NAMESPACE + '/governing-site-info', {
				method: 'GET',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': NONCE,
				},
			} );
			if ( ! res.ok ) {
				throw new Error( __( 'Network response was not ok', 'onesearch' ) );
			}
			const data = await res.json();
			setGoverningSiteURL( data?.parent_site_url || '' );
		} catch ( error ) {
			setNotice( {
				type: 'error',
				message: __( 'Failed to fetch governing site URL. Please try again later.', 'onesearch' ),
			} );
		}
	}, [] );

	const disconnectParentUrl = useCallback( async () => {
		try {
			setIsBusy( true );
			const res = await fetch( API_NAMESPACE + '/governing-site-info', {
				method: 'DELETE',
				headers: {
					'X-WP-Nonce': NONCE,
				},
			} );
			if ( ! res.ok ) {
				throw new Error( __( 'Network response was not ok', 'onesearch' ) );
			}
			setGoverningSiteURL( '' );
			setNotice( {
				type: 'success',
				message: __( 'Disconnected from governing site.', 'onesearch' ),
			} );
		} catch ( error ) {
			setNotice( {
				type: 'error',
				message: __( 'Failed to disconnect. Please try again.', 'onesearch' ),
			} );
		} finally {
			setIsBusy( false );
			setShowDisconnectionModal( false );
		}
	}, [] );

	useEffect( () => {
		fetchParentUrl();
	}, [ fetchParentUrl ] );

	return (
		<>
			<Card className="onesearch-card">
				<CardHeader>
					<h2>{ __( 'Governing Site Connection', 'onesearch' ) }</h2>
					<Button
						variant="secondary"
						isDestructive
						disabled={ isBusy || governingSiteURL.trim().length === 0 }
						onClick={ () => setShowDisconnectionModal( true ) }
					>
						{ isBusy ? __( 'Disconnecting…', 'onesearch' ) : __( 'Disconnect Governing Site', 'onesearch' ) }
					</Button>
				</CardHeader>
				<CardBody>
					<TextControl
						label={ __( 'Governing Site URL', 'onesearch' ) }
						value={ governingSiteURL }
						disabled
						help={ __( 'This is the URL of the governing site this brand site is connected to.', 'onesearch' ) }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</CardBody>
			</Card>

			{ showDisconnectionModal && (
				<Modal
					title={ __( 'Disconnect Governing Site', 'onesearch' ) }
					onRequestClose={ () => setShowDisconnectionModal( false ) }
					shouldCloseOnClickOutside
				>
					<p>{ __( 'Are you sure you want to disconnect from the governing site? This action cannot be undone.', 'onesearch' ) }</p>
					<div className="onesearch-governing-site-card">
						<Button variant="secondary" onClick={ () => setShowDisconnectionModal( false ) }>
							{ __( 'Cancel', 'onesearch' ) }
						</Button>
						<Button
							variant="primary"
							isDestructive
							isBusy={ isBusy }
							onClick={ disconnectParentUrl }
						>
							{ __( 'Disconnect', 'onesearch' ) }
						</Button>
					</div>
				</Modal>
			) }
		</>
	);
};

export default GoverningSiteConnection;
