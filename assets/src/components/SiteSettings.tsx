/**
 * WordPress dependencies
 */
import { useEffect, useState, useCallback } from 'react';
import {
	TextareaControl,
	Button,
	Card,
	Notice,
	Spinner,
	CardHeader,
	CardBody,
	TextControl,
	Modal,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { NoticeType } from '@/admin/settings/page';

const API_NAMESPACE = window.OneSearchSettings.restUrl + 'onesearch/v1';
const NONCE = window.OneSearchSettings.nonce as string;
const API_KEY = window.OneSearchSettings.api_key;

const SiteSettings = () => {
	const [ apiKey, setApiKey ] = useState< string >( '' );
	const [ isLoading, setIsLoading ] = useState< boolean >( false );
	const [ notice, setNotice ] = useState< NoticeType | null >( null );
	const [ governingSite, setGoverningSite ] = useState< string >( '' );
	const [ showDisconectionModal, setShowDisconectionModal ] = useState< boolean >( false );

	const fetchApiKey = useCallback( async () => {
		setIsLoading( true );
		try {
			const response = await fetch( API_NAMESPACE + '/secret-key', {
				method: 'GET',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': NONCE,
					'X-OneSearch-Token': API_KEY,
				},
			} );
			if ( ! response.ok ) {
				throw new Error( 'Network response was not ok' );
			}
			const data = await response.json();
			setApiKey( data?.secret_key || '' );
		} catch ( error ) {
			setNotice( {
				type: 'error',
				message: __( 'Failed to fetch api key. Please try again later.', 'onesearch' ),
			} );
		} finally {
			setIsLoading( false );
		}
	}, [] );

	// regenerate api key using REST endpoint.
	const regenerateApiKey = useCallback( async () => {
		try {
			const response = await fetch( API_NAMESPACE + '/secret-key', {
				method: 'POST',
				headers: {
					'X-WP-Nonce': NONCE,
					'X-OneSearch-Token': API_KEY,
				},
			} );
			if ( ! response.ok ) {
				throw new Error( 'Network response was not ok' );
			}
			const data = await response.json();
			if ( data?.secret_key ) {
				setApiKey( data.secret_key );
				setNotice( {
					type: 'warning',
					message: __( 'API key regenerated successfully. Please update your old key with this newly generated key to make sure plugin works properly.', 'onesearch' ),
				} );
			} else {
				setNotice( {
					type: 'error',
					message: __( 'Failed to regenerate api key. Please try again later.', 'onesearch' ),
				} );
			}
		} catch ( error ) {
			setNotice( {
				type: 'error',
				message: __( 'Error regenerating api key. Please try again later.', 'onesearch' ),
			} );
		}
	}, [] );

	const fetchCurrentGoverningSite = useCallback( async () => {
		setIsLoading( true );
		try {
			const response = await fetch(
				`${ API_NAMESPACE }/governing-site?${ new Date().getTime() }`,
				{
					method: 'GET',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': NONCE,
						'X-OneSearch-Token': apiKey,
					},
				},
			);
			if ( ! response.ok ) {
				throw new Error( 'Network response was not ok' );
			}
			const data = await response.json();
			setGoverningSite( data?.governing_site_url || '' );
		} catch ( error ) {
			setNotice( {
				type: 'error',
				message: __( 'Failed to fetch governing site. Please try again later.', 'onesearch' ),
			},
			);
		} finally {
			setIsLoading( false );
		}
	}, [ apiKey ] );

	const deleteGoverningSiteConnection = useCallback( async () => {
		try {
			const response = await fetch(
				`${ API_NAMESPACE }/governing-site`,
				{
					method: 'DELETE',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': NONCE,
						'X-OneSearch-Token': apiKey,
					},
				},
			);
			if ( ! response.ok ) {
				throw new Error( 'Network response was not ok' );
			}
			setGoverningSite( '' );
			setNotice( {
				type: 'success',
				message: __( 'Governing site disconnected successfully.', 'onesearch' ),
			} );
		} catch ( error ) {
			setNotice( {
				type: 'error',
				message: __( 'Failed to disconnect governing site. Please try again later.', 'onesearch' ),
			} );
		} finally {
			setShowDisconectionModal( false );
		}
	}, [ apiKey ] );

	const handleDisconnectGoverningSite = useCallback( async () => {
		setShowDisconectionModal( true );
	}, [] );

	useEffect( () => {
		fetchApiKey();
		fetchCurrentGoverningSite();
	}, [] ); // eslint-disable-line react-hooks/exhaustive-deps

	if ( isLoading ) {
		return <Spinner />;
	}

	return (
		<>

			{ notice && (
				<Notice
					status={ notice.type }
					isDismissible={ true }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }

			<Card className="brand-site-settings"
				style={ { marginTop: '30px' } }
			>
				<CardHeader>
					<h2>{ __( 'API Key', 'onesearch' ) }</h2>
					<div>
						{ /* Copy to clipboard button */ }
						<Button
							variant="primary"
							onClick={ () => {
								navigator?.clipboard?.writeText( apiKey )
									.then( () => {
										setNotice( {
											type: 'success',
											message: __( 'API key copied to clipboard.', 'onesearch' ),
										} );
									} )
									.catch( ( error ) => {
										setNotice( {
											type: 'error',
											message: __( 'Failed to copy api key. Please try again.', 'onesearch' ) + ' ' + error,
										} );
									} );
							} }
						>
							{ __( 'Copy API Key', 'onesearch' ) }
						</Button>
						{ /* Regenerate key button */ }
						<Button
							variant="secondary"
							onClick={ regenerateApiKey }
							style={ { marginLeft: '10px' } }
						>
							{ __( 'Regenerate API Key', 'onesearch' ) }
						</Button>
					</div>
				</CardHeader>
				<CardBody>
					<div>
						<TextareaControl
							value={ apiKey }
							disabled={ true }
							help={ __( 'This key is used for secure communication with the Governing site.', 'onesearch' ) }
							__nextHasNoMarginBottom
							onChange={ () => {} } // to avoid ts warning
						/>
					</div>
				</CardBody>

			</Card>
			<Card className="governing-site-connection"
				style={ { marginTop: '30px' } }
			>
				<CardHeader>
					<h2>{ __( 'Governing Site Connection', 'onesearch' ) }</h2>
					<Button
						variant="secondary"
						isDestructive
						onClick={ handleDisconnectGoverningSite }
						disabled={ governingSite.trim().length === 0 || isLoading }
					>
						{ __( 'Disconnect Governing Site', 'onesearch' ) }
					</Button>
				</CardHeader>
				<CardBody>
					<TextControl
						label={ __( 'Governing Site URL', 'onesearch' ) }
						value={ governingSite }
						disabled={ true }
						help={ __( 'This is the URL of the Governing site this Brand site is connected to.', 'onesearch' ) }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						onChange={ () => {} } // to avoid ts warning
					/>
				</CardBody>
			</Card>

			{ showDisconectionModal && (
				<Modal
					title={ __( 'Disconnect Governing Site', 'onesearch' ) }
					onRequestClose={ () => setShowDisconectionModal( false ) }
					shouldCloseOnClickOutside={ true }
				>
					<p>{ __( 'Are you sure you want to disconnect from the governing site? This action cannot be undone.', 'onesearch' ) }</p>
					<div style={ { display: 'flex', justifyContent: 'flex-end', marginTop: '20px', gap: '16px' } }>
						<Button
							variant="secondary"
							onClick={ () => setShowDisconectionModal( false ) }
						>
							{ __( 'Cancel', 'onesearch' ) }
						</Button>
						<Button
							variant="primary"
							isDestructive
							onClick={ deleteGoverningSiteConnection }
						>
							{ __( 'Disconnect', 'onesearch' ) }
						</Button>
					</div>
				</Modal>
			) }
		</>
	);
};

export default SiteSettings;
