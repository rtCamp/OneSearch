/**
 * WordPress dependencies
 */
import { useState, useEffect, createRoot } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Card, CardHeader, CardBody, Notice, Button, SelectControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { API_NAMESPACE, NONCE, SETUP_URL } from '../../js/utils';

const SiteTypeSelector = ( { value, setSiteType } ) => (

	<SelectControl
		label={ __( 'Site Type', 'onesearch' ) }
		value={ value }
		help={ __( 'Choose your site\'s primary purpose. This setting cannot be changed later and affects available features and configurations.', 'onesearch' ) }
		onChange={ ( v ) => {
			setSiteType( v );
		} }
		options={ [
			{ label: __( 'Select…', 'onesearch' ), value: '' },
			{ label: __( 'Brand site', 'onesearch' ), value: 'brand-site' },
			{ label: __( 'Governing site', 'onesearch' ), value: 'governing-site' },
		] }
	/>
);

const OneSearchSettingsPage = () => {
	const [ siteType, setSiteType ] = useState( '' );
	const [ notice, setNotice ] = useState( null );
	const [ isSaving, setIsSaving ] = useState( false );

	useEffect( () => {
		const token = ( NONCE );

		const fetchData = async () => {
			try {
				const [ siteTypeRes ] = await Promise.all( [
					fetch( `${ API_NAMESPACE }/site-type`, {
						headers: { 'Content-Type': 'application/json', 'X-WP-NONCE': token },
					} ),
				] );

				const siteTypeData = await siteTypeRes.json();

				if ( siteTypeData?.site_type ) {
					setSiteType( siteTypeData.site_type );
				}
			} catch {
				setNotice( {
					type: 'error',
					message: __( 'Error fetching site type or Brand sites.', 'onesearch' ),
				} );
			}
		};

		fetchData();
	}, [] );

	const handleSiteTypeChange = async ( value ) => {
		setSiteType( value );
		const token = ( NONCE );
		setIsSaving( true );

		try {
			const response = await fetch( `${ API_NAMESPACE }/site-type`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-NONCE': token,
				},
				body: JSON.stringify( { site_type: value } ),
			} );

			if ( ! response.ok ) {
				setNotice( {
					type: 'error',
					message: __( 'Error setting site type.', 'onesearch' ),
				} );
				return;
			}

			const data = await response.json();
			if ( data?.site_type ) {
				setSiteType( data.site_type );

				// redirect user to setup page.
				window.location.href = SETUP_URL;
			}
		} catch {
			setNotice( {
				type: 'error',
				message: __( 'Error setting site type.', 'onesearch' ),
			} );
		} finally {
			setIsSaving( false );
		}
	};

	return (
		<>
			<Card>
				<>
					{ notice?.message?.length > 0 &&
					<Notice
						status={ notice?.type ?? 'success' }
						isDismissible={ true }
						onRemove={ () => setNotice( null ) }
					>
						{ notice?.message }
					</Notice>
					}
				</>
				<CardHeader>
					<h2>{ __( 'OneSearch', 'onesearch' ) }</h2>
				</CardHeader>
				<CardBody>
					<SiteTypeSelector value={ siteType } setSiteType={ setSiteType } />
					<Button
						isPrimary
						onClick={ () => handleSiteTypeChange( siteType ) }
						disabled={ isSaving || ! siteType }
						className={ `onesearch-site-selection-button ${ isSaving ? 'is-busy' : '' }` }
					>
						{ __( 'Select current site type', 'onesearch' ) }
					</Button>
				</CardBody>
			</Card>
		</>
	);
};

// Render to Gutenberg admin page with ID: onesearch-site-selection-modal
const target = document.getElementById( 'onesearch-site-selection-modal' );
if ( target ) {
	const root = createRoot( target );
	root.render( <OneSearchSettingsPage /> );
}
