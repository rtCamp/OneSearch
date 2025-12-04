/**
 * WordPress dependencies
 */
import {
	Card,
	CardHeader,
	CardBody,
	Button,
	__experimentalText as Text, // eslint-disable-line @wordpress/no-unsafe-wp-apis
} from '@wordpress/components';

/**
 * External dependencies
 */
import { useState, useEffect, useCallback } from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import MultiSelectChips from './MultiSelectChips';

/**
 * Internal dependencies
 */
import { API_NAMESPACE, NONCE } from '../js/utils';

const SiteIndexableEntities = ( {
	sites,
	allPostTypes,
	currentSiteUrl,
	setNotice,
	onEntitiesSaved,
} ) => {
	const [ selectedEntities, setSelectedEntities ] = useState( [] );
	const [ savedEntities, setSavedEntities ] = useState( {} );
	const [ saving, setSaving ] = useState( false );
	const [ reindexing, setReindexing ] = useState( false );

	const controlsDisabled = saving || reindexing;

	const normalizeEntities = ( map = {} ) => {
		const results = {};
		Object.keys( map || {} )
			.sort()
			.forEach( ( site ) => {
				const arr = Array.isArray( map[ site ] ) ? map[ site ] : [];
				const clean = Array.from( new Set( arr.map( String ) ) ).sort();
				results[ site ] = clean;
			} );

		return results;
	};

	const isEmptySavedEntities = () => {
		if ( ! savedEntities || typeof savedEntities !== 'object' ) {
			return true;
		}

		const keys = Object.keys( savedEntities );
		if ( keys.length === 0 ) {
			return true;
		}

		return keys.every( ( key ) => {
			const value = savedEntities[ key ];
			return ! Array.isArray( value ) || value.length === 0;
		} );
	};

	const getIndexableEntities = useCallback( async () => {
		try {
			const response = await fetch( `${ API_NAMESPACE }/indexable-entities`, {
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
			} );

			const data = await response.json();
			const incoming = data.indexableEntities.entities;
			setSelectedEntities( incoming );
			setSavedEntities( normalizeEntities( incoming ) );
		} catch {
			setNotice( {
				type: 'error',
				message: __( 'Error fetching indexable entities.', 'onesearch' ),
			} );
		}
	}, [] );

	useEffect( () => {
		getIndexableEntities();
	}, [ getIndexableEntities ] );

	const handleSelectedEntitiesChange = ( selected, url ) => {
		if ( controlsDisabled ) {
			return;
		}
		setSelectedEntities( ( prev ) => ( {
			...prev,
			[ url ]: selected,
		} ) );
	};

	const handleSelectedEntitiesSave = async ( entities ) => {
		try {
			setSaving( true );
			const response = await fetch( `${ API_NAMESPACE }/indexable-entities`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': NONCE,
				},
				body: JSON.stringify( { entities } ),
			} );

			if ( ! response.ok ) {
				throw new Error( __( 'Network response was not ok.', 'onesearch' ) );
			}

			const data = await response.json();

			if ( data.success ) {
				setSavedEntities( normalizeEntities( entities ) );
				onEntitiesSaved?.();
				// Re-index selected entities.
				await handleReIndex();
			} else if ( data.data?.status === 500 ) {
				setNotice( {
					message: __( 'Internal server error.', 'onesearch' ),
					type: 'error',
				} );
			} else {
				setNotice( {
					message: data.message,
					type: 'error',
				} );
			}
		} catch ( error ) {
			setNotice( {
				message: error.message,
				type: 'error',
			} );
		} finally {
			setSaving( false );
		}
	};

	const handleReIndex = async () => {
		try {
			setReindexing( true );
			const response = await fetch( `${ API_NAMESPACE }/re-index`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': NONCE,
				},
			} );

			const data = await response.json();
			if ( data.success ) {
				setNotice( {
					message: data.message,
					type: 'success',
				} );
			} else if ( data.data?.status === 500 ) {
				setNotice( {
					message: __( 'Internal server error.', 'onesearch' ),
					type: 'error',
				} );
			} else {
				setNotice( {
					message: data.message,
					type: 'error',
				} );
			}
		} catch ( error ) {
			setNotice( {
				message: error.message,
				type: 'error',
			} );
		} finally {
			setReindexing( false );
		}
	};

	const isDirty = JSON.stringify( normalizeEntities( selectedEntities ) ) !== JSON.stringify( savedEntities );

	return (
		<Card className="onesearch-entities-card">
			<CardHeader className="onesearch-entities-card-group">
				<h2 className="onesearch-title">
					{ __( 'Select Entities to Index', 'onesearch' ) }
				</h2>
				<div className="onesearch-entities-inner-card">
					<div className="onesearch-entities-controls">
						<Button
							variant="secondary"
							onClick={ handleReIndex }
							isBusy={ reindexing }
							disabled={ reindexing || isEmptySavedEntities() }
							className="onesearch-btn-reindex"
						>
							{ reindexing ? __( 'Re-indexing…', 'onesearch' ) : __( 'Re-index', 'onesearch' ) }
						</Button>
						<Button
							variant="primary"
							onClick={ () => handleSelectedEntitiesSave( selectedEntities ) }
							disabled={ ! isDirty || saving }
							isBusy={ saving }
							className="onesearch-btn-save-entities"
						>
							{ saving ? __( 'Saving…', 'onesearch' ) : __( 'Save Changes', 'onesearch' ) }
						</Button>
					</div>
					<p className="onesearch-entities-info">
						{ __( 'Saving changes will automatically re-index the data.', 'onesearch' ) }
					</p>
				</div>
			</CardHeader>

			<CardBody className="onesearch-entities-body">
				{ /* Governing Site */ }
				<div className="onesearch-entity-site">
					<div className="onesearch-entity-site-header">
						<h3 className="onesearch-entity-site-name">
							{ __( 'Governing Site', 'onesearch' ) }
						</h3>
						<p className="onesearch-entity-site-url">
							{ currentSiteUrl }
						</p>
					</div>
					<div className="onesearch-entity-selector">
						<MultiSelectChips
							placeholder={ __( 'Select entities…', 'onesearch' ) }
							options={ allPostTypes?.[ currentSiteUrl ] || [] }
							value={ selectedEntities?.[ currentSiteUrl ] || [] }
							onChange={ ( next ) => handleSelectedEntitiesChange( next, currentSiteUrl ) }
							valueField="slug"
							labelField="label"
							disabled={ controlsDisabled }
						/>
					</div>
				</div>

				{ /* Brand Sites */ }
				{ sites?.map( ( site, index ) => (
					<div key={ index } className="onesearch-entity-site onesearch-entity-brand">
						<div className="onesearch-entity-site-header">
							<h3 className="onesearch-entity-site-name">
								{ site.name }
							</h3>
							<p className="onesearch-entity-site-url">
								{ site.url }
							</p>
						</div>
						{
							! allPostTypes?.[ site?.url ] ? (
								<Text variant="muted">
									{ __( 'No entities to select. Please check site configuration', 'onesearch' ) }
								</Text>
							) : (
								<div className="onesearch-entity-selector">
									<MultiSelectChips
										placeholder={ __( 'Select entities…', 'onesearch' ) }
										options={ allPostTypes?.[ site?.url ] || [] }
										value={ selectedEntities?.[ site?.url ] || [] }
										onChange={ ( next ) => handleSelectedEntitiesChange( next, site?.url ) }
										valueField="slug"
										labelField="label"
										disabled={ controlsDisabled }
									/>
								</div>
							)
						}
					</div>
				) ) }
			</CardBody>
		</Card>
	);
};

export default SiteIndexableEntities;
