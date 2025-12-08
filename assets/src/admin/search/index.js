/**
 * WordPress dependencies
 */
import { useState, useEffect, createRoot } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Snackbar } from '@wordpress/components';
/**
 * Internal dependencies
 */
import SiteModal from '../../components/SiteModal';
import SiteIndexableEntities from '../../components/SiteIndexableEntities';
import SiteSearchSettings from '../../components/SiteSearchSettings';

/**
 * Internal dependencies
 */
import { API_NAMESPACE, NONCE, CURRENT_SITE_URL } from '../../js/utils';

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
	const [ allPostTypes, setAllPostTypes ] = useState( {} );
	const [ indexableEntities, setIndexableEntities ] = useState( {} );

	const fetchEntities = async () => {
		const res = await fetch( `${ API_NAMESPACE }/indexable-entities`, {
			headers: { 'X-WP-Nonce': NONCE },
		} );
		const data = await res.json();
		setIndexableEntities( data.indexableEntities?.entities || {} );
	};

	useEffect( () => {
		fetchEntities();
	}, [] );

	const handleEntitiesSaved = () => {
		fetchEntities();
	};

	// Fetch all post types.
	useEffect( () => {
		const token = NONCE;

		const ensureSlash = ( url ) =>
			typeof url === 'string' && url.endsWith( '/' ) ? url : `${ url }/`;

		const toEntitiesMap = ( data ) => {
			const results = {};
			if ( data && data.sites && typeof data.sites === 'object' ) {
				Object.keys( data.sites ).forEach( ( url ) => {
					const payload = data.sites[ url ] || {};

					// Get the list of the post types.
					const list = Array.isArray( payload.post_types )
						? payload.post_types
						: [];

					// Map out post types for each site.
					results[ ensureSlash( url ) ] = ( list || [] ).map(
						( { slug = '', label, restBase } = {} ) => {
							const s = String( slug );
							return {
								slug: s,
								label: String( label || s ),
								restBase: String( restBase || s ),
							};
						},
					);
				} );
			}

			// Returning the final results.
			return results;
		};

		const fetchAllPostTypes = async () => {
			try {
				const res = await fetch( `${ API_NAMESPACE }/all-post-types`, {
					headers: {
						Accept: 'application/json',
						'X-WP-NONCE': token,
					},
				} );
				if ( ! res.ok ) {
					throw new Error( `HTTP ${ res.status }` );
				}
				const data = await res.json();
				const mapped = toEntitiesMap( data );
				setAllPostTypes( mapped );
			} catch ( e ) {
				setNotice( {
					type: 'error',
					message: __( 'Error fetching post types from sites.', 'onesearch' ),
				} );
			}
		};

		fetchAllPostTypes();
	}, [] );

	useEffect( () => {
		const token = NONCE;

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
		window.location.reload();
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

			{ siteType === 'governing-site' && (
				<SiteIndexableEntities
					sites={ sites }
					allPostTypes={ allPostTypes }
					currentSiteUrl={ CURRENT_SITE_URL }
					setNotice={ setNotice }
					onEntitiesSaved={ handleEntitiesSaved }
				/>
			) }

			{ siteType === 'governing-site' && (
				<SiteSearchSettings
					setNotice={ setNotice }
					indexableEntities={ indexableEntities }
					allPostTypes={ allPostTypes }
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

// Render to Gutenberg admin page with ID: onesearch-config
const target = document.getElementById( 'onesearch-search-settings' );
if ( target ) {
	const root = createRoot( target );
	root.render( <OneSearchSettingsPage /> );
}
