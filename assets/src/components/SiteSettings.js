/**
 * WordPress dependencies
 */
import { useEffect, useState, useCallback } from '@wordpress/element';
import { TextareaControl, Button, Card, Notice, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { API_NAMESPACE, NONCE } from '../js/utils';

const SiteSettings = () => {
	const [ publicKey, setPublicKey ] = useState( '' );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	const fetchPublicKey = useCallback( async () => {
		try {
			setIsLoading( true );
			const response = await fetch( API_NAMESPACE + '/secret-key', {
				method: 'GET',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': NONCE,
				},
			} );
			if ( ! response.ok ) {
				throw new Error( 'Network response was not ok' );
			}
			const data = await response.json();
			setPublicKey( data?.secret_key || '' );
		} catch ( error ) {
			setNotice( {
				type: 'error',
				message: __( 'Failed to fetch API key. Please try again later.', 'onesearch' ),
			} );
		} finally {
			setIsLoading( false );
		}
	}, [] );

	// Regenerate Public Key.
	const regeneratePublicKey = useCallback( async () => {
		try {
			const response = await fetch( API_NAMESPACE + '/secret-key', {
				method: 'POST',
				headers: {
					'X-WP-Nonce': NONCE,
				},
			} );
			if ( ! response.ok ) {
				throw new Error( 'Network response was not ok' );
			}
			const data = await response.json();
			if ( data?.secret_key ) {
				setPublicKey( data.secret_key );
				setNotice( {
					type: 'warning',
					message: __( 'API key regenerated successfully. Please update your old key with this newly generated key to make sure plugin works properly.', 'onesearch' ),
				} );
			} else {
				setNotice( {
					type: 'error',
					message: __( 'Failed to regenerate API key. Please try again later.', 'onesearch' ),
				} );
			}
		} catch ( error ) {
			setNotice( {
				type: 'error',
				message: __( 'Error regenerating API key. Please try again later.', 'onesearch' ),
			} );
		}
	}, [] );

	useEffect( () => {
		fetchPublicKey();
	}, [ fetchPublicKey ] );

	if ( isLoading ) {
		return <Spinner />;
	}

	return (
		<Card className="onesearch-brand-site-settings">
			{ notice && (
				<Notice
					status={ notice.type }
					isDismissible={ true }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }
			<div className="onesearch-public-key-card">
				<TextareaControl
					label={ __( 'API Key', 'onesearch' ) }
					value={ publicKey }
					disabled={ true }
					help={ __( 'This key is used for secure communication with the Governing site.', 'onesearch' ) }
					__nextHasNoMarginBottom
				/>
			</div>
			{ /* Copy to clipboard button */ }
			<Button
				isPrimary
				onClick={ () => {
					navigator?.clipboard?.writeText( publicKey )
						.then( () => {
							setNotice( {
								type: 'success',
								message: __( 'API key copied to clipboard.', 'onesearch' ),
							} );
						} )
						.catch( ( error ) => {
							setNotice( {
								type: 'error',
								message: sprintf(
									/** translators: %s is error message */
									__( 'Failed to copy API key. Please try again. %s', 'onesearch' ),
									error,
								),
							} );
						} );
				} }
			>
				{ __( 'Copy API Key', 'onesearch' ) }
			</Button>
			{ /* Regenerate key button */ }
			<Button
				isSecondary
				onClick={ regeneratePublicKey }
				className="onesearch-regenerate-key-button"
			>
				{ __( 'Regenerate API Key', 'onesearch' ) }
			</Button>
		</Card>
	);
};

export default SiteSettings;
