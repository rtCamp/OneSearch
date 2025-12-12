/**
 * WordPress dependencies
 */
import { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { Snackbar } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import SiteTable from '@/components/SiteTable';
import SiteModal from '@/components/SiteModal';
import SiteSettings from '@/components/SiteSettings';
import AlgoliaSettings from '@/components/AlgoliaSettings';
import type { SiteType } from '../onboarding/page';

export interface NoticeType {
	type: 'success' | 'error' | 'warning' | 'info';
	message: string;
}

export interface BrandSite {
	id?: string;
	name: string;
	url: string;
	api_key: string;
}

export const defaultBrandSite: BrandSite = {
	name: '',
	url: '',
	api_key: '',
};

export type EditingIndex = number | null;

const NONCE = window.OneSearchSettings.restNonce;
const siteType = window.OneSearchSettings.siteType as SiteType || '';

/**
 * Create NONCE middleware for apiFetch
 */
apiFetch.use( apiFetch.createNonceMiddleware( NONCE ) );

const SettingsPage = () => {
	const [ showModal, setShowModal ] = useState( false );
	const [ editingIndex, setEditingIndex ] = useState< EditingIndex >( null );
	const [ sites, setSites ] = useState< BrandSite[] >( [] );
	const [ formData, setFormData ] = useState< BrandSite >( defaultBrandSite );
	const [ notice, setNotice ] = useState< NoticeType | null >( null );

	useEffect( () => {
		apiFetch<{ onesearch_shared_sites?: BrandSite[] }>( {
			path: '/wp/v2/settings',
		} )
			.then( ( settings ) => {
				if ( settings?.onesearch_shared_sites ) {
					setSites( settings.onesearch_shared_sites );
				}
			} )
			.catch( () => {
				setNotice( {
					type: 'error',
					message: __( 'Error fetching settings data.', 'onesearch' ),
				} );
			} );
	}, [] ); // Empty dependency array to run only once on mount

	useEffect( () => {
		if ( siteType === 'governing-site' && sites.length > 0 ) {
			document.body.classList.remove( 'onesearch-missing-brand-sites' );
		}
	}, [ sites ] );

	const handleFormSubmit = async () : Promise< boolean > => {
		const updated : BrandSite[] = editingIndex !== null
			? sites.map( ( item, i ) => ( i === editingIndex ? formData : item ) )
			: [ ...sites, formData ];
		return apiFetch<{ onesearch_shared_sites?: BrandSite[] }>( {
			path: '/wp/v2/settings',
			method: 'POST',
			data: { onesearch_shared_sites: updated },
		} ).then( ( settings ) => {
			if ( ! settings?.onesearch_shared_sites ) {
				throw new Error( 'No shared sites in response' );
			}
			const previousLength = sites.length;
			setSites( settings.onesearch_shared_sites );
			if ( ( settings.onesearch_shared_sites.length === 1 && previousLength === 0 ) || ( previousLength === 1 && settings.onesearch_shared_sites.length === 0 ) ) {
				window.location.reload();
			}
			setNotice( {
				type: 'success',
				message: __( 'Brand Site saved successfully.', 'onesearch' ),
			} );
			return true;
		} ).catch( () => {
			setNotice( {
				type: 'error',
				message: __( 'Failed to update shared sites', 'onesearch' ),
			} );
			return false;
		} ).finally( () => {
			setFormData( defaultBrandSite );
			setShowModal( false );
			setEditingIndex( null );
		} );
	};

	const handleDelete = async ( index : number|null ) : Promise<void> => {
		const updated : BrandSite[] = sites.filter( ( _, i ) => i !== index );

		apiFetch<{ onesearch_shared_sites?: BrandSite[] }>( {
			path: '/wp/v2/settings',
			method: 'POST',
			data: { onesearch_shared_sites: updated },
		} ).then( ( settings ) => {
			if ( ! settings?.onesearch_shared_sites ) {
				throw new Error( 'No shared sites in response' );
			}
			const previousLength = sites.length;
			setSites( settings.onesearch_shared_sites );
			if ( ( settings.onesearch_shared_sites.length === 1 && previousLength === 0 ) || ( previousLength === 1 && settings.onesearch_shared_sites.length === 0 ) ) {
				window.location.reload();
			} else {
				document.body.classList.remove( 'onesearch-missing-brand-sites' );
			}
		} ).catch( () => {
			throw new Error( 'Failed to update shared sites' );
		} );
	};

	return (
		<>
			{ !! notice && notice?.message?.length > 0 &&
				<Snackbar
					explicitDismiss={ false }
					onRemove={ () => setNotice( null ) }
					className={ notice?.type === 'error' ? 'onesearch-error-notice' : 'onesearch-success-notice' }
				>
					{ notice?.message }
				</Snackbar>
			}

			{ siteType === 'brand-site' && (
				<SiteSettings />
			) }

			{ siteType === 'governing-site' && (
				<SiteTable sites={ sites } onEdit={ setEditingIndex } onDelete={ handleDelete } setFormData={ setFormData } setShowModal={ setShowModal } />
			) }

			{ siteType === 'governing-site' && (
				<AlgoliaSettings setNotice={ setNotice } />
			) }

			{ showModal && (
				<SiteModal
					formData={ formData }
					setFormData={ setFormData }
					onSubmit={ handleFormSubmit }
					onClose={ () => {
						setShowModal( false );
						setEditingIndex( null );
						setFormData( defaultBrandSite );
					} }
					editing={ editingIndex !== null }
					sites={ sites }
					originalData={ editingIndex !== null ? sites[ editingIndex ] : undefined }
				/>
			) }
		</>
	);
};

export default SettingsPage;
