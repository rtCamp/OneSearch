import {
	Modal,
	TextControl,
	TextareaControl,
	Button,
	Notice,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useState, useMemo } from 'react';
import { isValidUrl, withTrailingSlash } from '../js/utils';

/**
 * Site Modal component for adding/editing a site.
 *
 * @param {Object}   props              - Component properties.
 * @param {Object}   props.formData     - Current form data.
 * @param {Function} props.setFormData  - Function to update form data.
 * @param {Function} props.onSubmit     - Function to call on form submission.
 * @param {Function} props.onClose      - Function to call on modal close.
 * @param {boolean}  props.editing      - Whether the modal is in editing mode.
 * @param {Object}   props.originalData - Original data for comparison when editing.
 * @return {JSX.Element} Rendered component.
 */
const SiteModal = ( { formData, setFormData, onSubmit, onClose, editing, originalData = {} } ) => {
	const [ errors, setErrors ] = useState( {
		name: '',
		url: '',
		api_key: '',
		message: '',
	} );
	const [ showNotice, setShowNotice ] = useState( false );
	const [ isProcessing, setIsProcessing ] = useState( false );

	const handleSubmit = async () => {
		// Validate inputs
		let siteUrlError = '';
		if ( ! formData.url.trim() ) {
			siteUrlError = __( 'Site URL is required.', 'onesearch' );
		} else if ( ! isValidUrl( formData.url ) ) {
			siteUrlError = __( 'Enter a valid URL (must start with http or https).', 'onesearch' );
		}

		// Guarantee a trailing slash in the payload
		formData.url = withTrailingSlash( formData.url );

		const newErrors = {
			name: ! formData.name.trim() ? __( 'Site Name is required.', 'onesearch' ) : '',
			url: siteUrlError,
			api_key: ! formData.api_key.trim() ? __( 'API Key is required.', 'onesearch' ) : '',
			message: '',
		};

		// Make sure site name is under 20 characters.
		if ( formData.name.length > 20 ) {
			newErrors.name = __( 'Site Name must be under 20 characters.', 'onesearch' );
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
				`${ formData.url }/wp-json/onesearch/v1/health-check`,
				{
					method: 'GET',
					headers: {
						'Content-Type': 'application/json',
						'X-OneSearch-Token': formData.api_key,
					},
				},
			);

			const healthCheckData = await healthCheck.json();

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

	const hasChanges = useMemo( () => {
		if ( ! editing ) {
			return true;
		} // Always allow submission for new sites

		return (
			formData?.name !== originalData?.name ||
			formData?.url !== originalData?.url ||
			formData?.api_key !== originalData?.api_key ||
			formData?.logo !== originalData?.logo
		);
	}, [ editing, formData, originalData ] );

	// Button should be disabled if:
	// 1. Currently processing, OR
	// 2. Required fields are empty, OR
	// 3. In editing mode and no changes have been made
	const isButtonDisabled = isProcessing ||
		! formData.name ||
		! formData.url ||
		! formData.api_key ||
		( editing && ! hasChanges );

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
					{ errors.message || errors.name || errors.url || errors.api_key }
				</Notice>
			) }

			<TextControl
				label={ __( 'Site Name*', 'onesearch' ) }
				value={ formData.name }
				onChange={ ( value ) => setFormData( { ...formData, name: value } ) }
				error={ errors.name }
				help={ __( 'This is the name of the site that will be registered.', 'onesearch' ) }
				className="onesearch-site-modal-text"
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>
			<TextControl
				label={ __( 'Site URL*', 'onesearch' ) }
				value={ formData.url }
				onChange={ ( value ) => setFormData( { ...formData, url: value } ) }
				error={ errors.url }
				help={ __( 'It must start with http or https, like: https://rtcamp.com/', 'onesearch' ) }
				className="onesearch-site-modal-text"
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>
			<TextareaControl
				label={ __( 'API Key*', 'onesearch' ) }
				value={ formData.api_key }
				onChange={ ( value ) => setFormData( { ...formData, api_key: value } ) }
				error={ errors.api_key }
				help={ __( 'This is the API key that will be used to authenticate the site for onesearch.', 'onesearch' ) }
				className="onesearch-site-modal-text"
				__nextHasNoMarginBottom
			/>

			<Button
				variant="primary"
				onClick={ handleSubmit }
				className={ isProcessing ? 'is-busy' : '' }
				disabled={ isButtonDisabled }
				style={ { marginTop: '12px' } }
			>
				{ (
					editing ? __( 'Update Site', 'onesearch' ) : __( 'Add Site', 'onesearch' )
				) }
			</Button>
		</Modal>
	);
};

export default SiteModal;
