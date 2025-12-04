/**
 * WordPress dependencies
 */
import { useState, useEffect, createRoot } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Snackbar } from '@wordpress/components';
/**
 * Internal dependencies
 */
import SiteTable from '../../components/SiteTable';
import SiteModal from '../../components/SiteModal';
import SiteSettings from '../../components/SiteSettings';
import AlgoliaSettings from '../../components/AlgoliaSettings';
import GoverningSiteConnection from '../../components/GoverningSiteConnection';
import { API_NAMESPACE, NONCE } from '../../js/utils';

/**
 * Settings page component for OneSearch plugin.
 *
 * @return {JSX.Element} Rendered component.
 */

const OneSearchSettingsPage = () => {
	const [ siteType, setSiteType ] = useState( '' );
	const [ showModal, setShowModal ] = useState( false );
	const [ editingIndex, setEditingIndex ] = useState( null );
	const [ sites, setSites ] = useState( [] );
	const [ formData, setFormData ] = useState( {
		name: '',
		url: '',
		api_key: '',
	} );
	const [ notice, setNotice ] = useState( {
		type: 'success',
		message: '',
	} );

	useEffect( () => {
		const token = ( NONCE );

		const fetchData = async () => {
			try {
				const [ siteTypeRes, sitesRes ] = await Promise.all( [
					fetch( `${ API_NAMESPACE }/site-type`, {
						headers: {
							'Content-Type': 'application/json',
							'X-WP-NONCE': token,
						},
					} ),
					fetch( `${ API_NAMESPACE }/shared-sites`, {
						headers: {
							'Content-Type': 'application/json',
							'X-WP-NONCE': token,
						},
					} ),
				] );

				const siteTypeData = await siteTypeRes.json();
				const sitesData = await sitesRes.json();

				if ( siteTypeData?.site_type ) {
					setSiteType( siteTypeData.site_type );
				}
				if ( Array.isArray( sitesData?.shared_sites ) ) {
					setSites( sitesData?.shared_sites );
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

	useEffect( () => {
		if ( siteType === 'governing-site' && sites.length > 0 ) {
			document.body.classList.remove( 'onesearch-missing-brand-sites' );
		}
	}, [ sites, siteType ] );

	const handleFormSubmit = async () => {
		const updated =
			editingIndex !== null
				? sites.map( ( item, i ) => ( i === editingIndex ? formData : item ) )
				: [ ...sites, formData ];

		const token = NONCE;
		try {
			const response = await fetch( `${ API_NAMESPACE }/shared-sites`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-NONCE': token,
				},
				body: JSON.stringify( { sites_data: updated } ),
			} );
			if ( ! response.ok ) {
				console.error( 'Error saving Brand site:', response.statusText ); // eslint-disable-line no-console
				return response;
			}

			if ( sites.length === 0 ) {
				window.location.reload();
			}

			setSites( updated );
			setNotice( {
				type: 'success',
				message: __( 'Brand Site saved successfully.', 'onesearch' ),
			} );
		} catch {
			setNotice( {
				type: 'error',
				message: __(
					'Error saving Brand site. Please try again later.',
					'onesearch',
				),
			} );
		}

		setFormData( { name: '', url: '', api_key: '' } );
		setShowModal( false );
		setEditingIndex( null );
	};

	const handleDelete = async ( index ) => {
		const token = NONCE;

		try {
			const response = await fetch( `${ API_NAMESPACE }/delete-site`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-NONCE': token,
				},
				body: JSON.stringify( { site_index: index } ),
			} );

			const data = await response.json();

			if ( ! response.ok || ! data.success ) {
				setNotice( {
					type: 'error',
					message:
						data.message ||
						__( 'Failed to delete Brand site. Please try again.', 'onesearch' ),
				} );
				return;
			}

			// Update UI
			const updated = sites.filter( ( _, i ) => i !== index );
			setSites( updated );
			setNotice( {
				type: 'success',
				message: __( 'Brand Site deleted successfully.', 'onesearch' ),
			} );

			if ( updated.length === 0 ) {
				window.location.reload();
			} else {
				document.body.classList.remove( 'onesearch-missing-brand-sites' );
			}
		} catch ( error ) {
			setNotice( {
				type: 'error',
				message: __(
					'Error deleting Brand site. Please try again later.',
					'onesearch',
				),
			} );
		}
	};

	return (
		<>
			<>
				{ notice?.message?.length > 0 && (
					<Snackbar
						status={ notice?.type ?? 'success' }
						isDismissible={ true }
						onRemove={ () => setNotice( null ) }
						className={
							notice?.type === 'error'
								? 'onesearch-error-notice'
								: 'onesearch-success-notice'
						}
					>
						{ notice?.message }
					</Snackbar>
				) }
			</>

			{ siteType === 'brand-site' && <SiteSettings /> }

			{ siteType === 'governing-site' && (
				<SiteTable
					sites={ sites }
					onEdit={ setEditingIndex }
					onDelete={ handleDelete }
					setFormData={ setFormData }
					setShowModal={ setShowModal }
				/>
			) }

			{ siteType === 'governing-site' && (
				<AlgoliaSettings setNotice={ setNotice } />
			) }

			{ siteType === 'brand-site' && (
				<GoverningSiteConnection
					setNotice={ setNotice }
				/>
			) }

			{ showModal && (
				<SiteModal
					formData={ formData }
					setFormData={ setFormData }
					onSubmit={ handleFormSubmit }
					onClose={ () => {
						setShowModal( false );
						setEditingIndex( null );
						setFormData( { name: '', url: '', api_key: '' } );
					} }
					editing={ editingIndex !== null }
				/>
			) }
		</>
	);
};

// Render to Gutenberg admin page with ID: onesearch-settings
const target = document.getElementById( 'onesearch-settings' );
if ( target ) {
	const root = createRoot( target );
	root.render( <OneSearchSettingsPage /> );
}
