/**
 * WordPress dependencies
 */
import { useState, useRef } from '@wordpress/element';
import {
	Modal,
	TextControl,
	TextareaControl,
	Button,
	Notice,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
/**
 * Internal dependencies
 */
import { isValidUrl, REST_NAMESPACE, withTrailingSlash } from '../js/utils';

const SiteModal = ( { formData, setFormData, onSubmit, onClose, editing, currentSiteUrl = '' } ) => {
	const [ errors, setErrors ] = useState( {
		siteName: '',
		siteUrl: '',
		publicKey: '',
		message: '',
	} );
	const [ showNotice, setShowNotice ] = useState( false );
	const [ isProcessing, setIsProcessing ] = useState( false ); // New state for processing

	// Snapshot initial values once (when modal mounts)
	const initialRef = useRef( formData );

	const isDirty = ! editing ||
		formData.siteName !== initialRef.current.siteName ||
		formData.siteUrl !== initialRef.current.siteUrl ||
		formData.publicKey !== initialRef.current.publicKey;

	const handleSubmit = async () => {
		// Validate inputs
		let siteUrlError = '';
		if ( ! formData.siteUrl.trim() ) {
			siteUrlError = __( 'Site URL is required.', 'onesearch' );
		} else if ( ! isValidUrl( formData.siteUrl ) ) {
			siteUrlError = __( 'Enter a valid URL (must start with http or https).', 'onesearch' );
		}

		// Guarantee a trailing slash in the payload
		formData.siteUrl = withTrailingSlash( formData.siteUrl );

		const newErrors = {
			siteName: ! formData.siteName.trim() ? __( 'Site Name is required.', 'onesearch' ) : '',
			siteUrl: siteUrlError,
			publicKey: ! formData.publicKey.trim() ? __( 'API Key is required.', 'onesearch' ) : '',
			message: '',
		};

		// Make sure site name is under 20 characters.
		if ( formData.siteName.length > 20 ) {
			newErrors.siteName = __( 'Site Name must be under 20 characters.', 'onesearch' );
		}

		setErrors( newErrors );
		const hasErrors = Object.values( newErrors ).some( ( err ) => err );

		if ( hasErrors ) {
			setShowNotice( true );
			return;
		}

		// Start processing
		setIsProcessing( true );
		setShowNotice( false );

		try {
			// Perform health-check
			const healthCheck = await fetch(
				`${ withTrailingSlash( formData.siteUrl ) }wp-json/${ REST_NAMESPACE }/health-check`,
				{
					method: 'GET',
					headers: {
						'Content-Type': 'application/json',
						'X-OneSearch-Plugins-Token': formData.publicKey,
						'X-OneSearch-Requesting-Origin': currentSiteUrl,
					},
				},
			);

			const healthCheckData = await healthCheck.json();

			if ( healthCheckData?.code === 'already_connected' ) {
				setErrors( {
					...newErrors,
					message:
						healthCheckData?.message ||
						__( 'This site is already connected to a governing site.', 'onesearch' ),
				} );
				setShowNotice( true );
				setIsProcessing( false );
				return;
			}

			if ( ! healthCheckData.success ) {
				setErrors( {
					...newErrors,
					message: __( 'Health check failed. Please ensure the site is accessible and the API key is correct.', 'onesearch' ),
				} );
				setShowNotice( true );
				setIsProcessing( false );
				return;
			}

			setShowNotice( false );
			const submitResponse = await onSubmit();

			if ( ! submitResponse.ok ) {
				const errorData = await submitResponse.json();
				setErrors( {
					...newErrors,
					message: errorData.message || __( 'An error occurred while saving the site. Please try again.', 'onesearch' ),
				} );
				setShowNotice( true );
			}
			if ( submitResponse?.data?.status === 400 ) {
				setErrors( {
					...newErrors,
					message: submitResponse?.message || __( 'An error occurred while saving the site. Please try again.', 'onesearch' ),
				} );
				setShowNotice( true );
			}
		} catch ( error ) {
			setErrors( {
				...newErrors,
				message: __( 'An unexpected error occurred. Please try again.', 'onesearch' ),
			} );
			setShowNotice( true );
			setIsProcessing( false );
			return;
		}

		setIsProcessing( false );
	};

	return (
		<Modal
			title={ editing ? __( 'Edit Brand Site', 'onesearch' ) : __( 'Add Brand Site', 'onesearch' ) }
			onRequestClose={ onClose }
			size="medium"
		>
			{ showNotice && (
				<Notice
					status="error"
					isDismissible={ true }
					onRemove={ () => setShowNotice( false ) }
				>
					{ errors.message || errors.siteName || errors.siteUrl || errors.publicKey }
				</Notice>
			) }

			<TextControl
				label={ __( 'Site Name*', 'onesearch' ) }
				value={ formData.siteName }
				onChange={ ( value ) => setFormData( { ...formData, siteName: value } ) }
				error={ errors.siteName }
				help={ __( 'This is the name of the site that will be registered.', 'onesearch' ) }
				className="onesearch-site-modal-text"
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>
			<TextControl
				label={ __( 'Site URL*', 'onesearch' ) }
				value={ formData.siteUrl }
				onChange={ ( value ) => setFormData( { ...formData, siteUrl: value } ) }
				error={ errors.siteUrl }
				help={ __( 'It must start with http or https, like: https://rtcamp.com/', 'onesearch' ) }
				className="onesearch-site-modal-text"
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>
			<TextareaControl
				label={ __( 'API Key*', 'onesearch' ) }
				value={ formData.publicKey }
				onChange={ ( value ) => setFormData( { ...formData, publicKey: value } ) }
				error={ errors.publicKey }
				help={ __( 'This is the API key that will be used to authenticate the site for onesearch.', 'onesearch' ) }
				className="onesearch-site-modal-text"
				__nextHasNoMarginBottom
			/>

			<Button
				isPrimary
				onClick={ handleSubmit }
				className={ isProcessing ? 'is-busy' : '' }
				disabled={ isProcessing || ! formData.siteName || ! formData.siteUrl || ! formData.publicKey || ! isDirty }
			>
				{ (
					editing ? __( 'Update Site', 'onesearch' ) : __( 'Add Site', 'onesearch' )
				) }
			</Button>
		</Modal>
	);
};

export default SiteModal;
