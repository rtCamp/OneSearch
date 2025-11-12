/* global OneSearchSettings */
/**
 * WordPress dependencies
 */
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	ToggleControl,
	Spinner,
	Notice,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * External dependencies
 */
import { useState, useEffect } from 'react';

/**
 * Internal dependencies
 */
import { API_NAMESPACE, NONCE } from '../js/utils';

const SiteSearchSettings = ( { indexableEntities, setNotice, allPostTypes } ) => {
	const [ searchSettings, setSearchSettings ] = useState( {} );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ localNotice, setLocalNotice ] = useState( null );
	const [ reloadKey, setReloadKey ] = useState( 0 );

	// Get all sites from sharedSites and governing site
	const sharedSites = OneSearchSettings?.sharedSites || [];
	const currentSiteUrl = OneSearchSettings?.currentSiteUrl || '';
	const [ initialSettings, setInitialSettings ] = useState( {} );

	const trailingslashit = ( url ) => {
		return typeof url === 'string' && url.endsWith( '/' ) ? url : `${ url }/`;
	};

	const brandSites = sharedSites
		.filter( ( site ) => {
			const url = trailingslashit( site.siteUrl );
			const types = allPostTypes?.[ url ];
			return Array.isArray( types ) ? types.length > 0 : false;
		} )
		.map( ( site ) => ( {
			siteName: site.siteName,
			siteUrl: site.siteUrl,
			isGoverning: false,
		} ) );

	// Combine shared sites and governing site
	const allSites = [
		// Governing site
		{
			siteName: __( 'Governing Site', 'onesearch' ),
			siteUrl: currentSiteUrl,
			isGoverning: true,
		},
		// Brand sites from shared sites
		...brandSites,
	];

	//  Check if site has indexable entities.
	const siteHasEntities = ( siteUrl ) => {
		const normalizedUrl = trailingslashit( siteUrl );
		const entities = indexableEntities[ normalizedUrl ] || [];
		return Array.isArray( entities ) && entities.length > 0;
	};

	// Auto-save search setting when entities are removed.
	useEffect( () => {
		if ( ! indexableEntities || Object.keys( searchSettings ).length === 0 ) {
			return;
		}

		let hasChanges = false;
		const updatedSettings = { ...searchSettings };

		Object.keys( searchSettings ).forEach( ( siteUrl ) => {
			const currentSetting = searchSettings[ siteUrl ];
			const hasEntities = siteHasEntities( siteUrl );

			if ( currentSetting?.algolia_enabled && ! hasEntities ) {
				updatedSettings[ siteUrl ] = {
					...currentSetting,
					algolia_enabled: false,
					searchable_sites: [],
				};
				hasChanges = true;
			}
		} );

		if ( hasChanges ) {
			setSearchSettings( updatedSettings );

			const autoSave = async () => {
				try {
					await fetch( `${ API_NAMESPACE }/sites-search-settings`, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': NONCE,
						},
						body: JSON.stringify( { settings: updatedSettings } ),
					} );

					setInitialSettings( updatedSettings );

					setNotice( {
						type: 'success',
						message: __(
							'Sites without indexable entities have been automatically disabled and saved.',
							'onesearch',
						),
					} );

					// To trigger re-rendering of search configuration component.
					setReloadKey( ( k ) => k + 1 );
				} catch ( err ) {
					setNotice( {
						type: 'error',
						message: __( 'Failed to auto-save disabled sites.', 'onesearch' ),
					} );
				}
			};

			autoSave();
		}
	}, [ indexableEntities ] );

	// Load existing search settings
	useEffect( () => {
		const loadSettings = async () => {
			try {
				const res = await fetch( `${ API_NAMESPACE }/sites-search-settings`, {
					headers: { 'X-WP-Nonce': NONCE },
				} );

				if ( res.ok ) {
					const data = await res.json();
					if ( data?.success ) {
						setSearchSettings( data.settings );
						setInitialSettings( data.settings );
					}
				}
			} catch ( err ) {
				setNotice( {
					type: 'error',
					message: __( 'Failed to load search settings.', 'onesearch' ),
				} );
			} finally {
				setLoading( false );
				setLocalNotice( null );
			}
		};

		loadSettings();
	}, [ reloadKey ] );

	// Toggle Algolia for a site
	const handleSiteToggle = ( siteUrl, enabled ) => {
		if ( enabled && ! siteHasEntities( siteUrl ) ) {
			setLocalNotice( {
				type: 'warning',
				message: __(
					'This site cannot use Algolia search because no content types have been selected for indexing. Please configure indexable entities first, then enable Algolia search.',
					'onesearch',
				),
			} );
			return;
		}

		setSearchSettings( ( prev ) => ( {
			...prev,
			[ siteUrl ]: {
				...( prev[ siteUrl ] || {} ),
				algolia_enabled: enabled,
				searchable_sites: enabled ? [ siteUrl ] : [],
			},
		} ) );
	};

	// Toggle searchable sites for a site
	const handleSearchableSiteToggle = (
		parentSiteUrl,
		targetSiteUrl,
		checked,
	) => {
		const isSelf =
			trailingslashit( targetSiteUrl ) === trailingslashit( parentSiteUrl );

		if ( isSelf && ! checked ) {
			setLocalNotice( {
				type: 'warning',
				message: __(
					'The current site cannot be excluded from search results. It will always be included when Algolia search is enabled.',
					'onesearch',
				),
			} );
			return;
		}

		setSearchSettings( ( prev ) => {
			const currentSearchables = prev[ parentSiteUrl ]?.searchable_sites || [];
			const newSearchables = checked
				? [ ...currentSearchables, targetSiteUrl ]
				: currentSearchables.filter( ( url ) => url !== targetSiteUrl );

			return {
				...prev,
				[ parentSiteUrl ]: {
					...( prev[ parentSiteUrl ] || {} ),
					searchable_sites: newSearchables,
				},
			};
		} );
	};

	// Handling bulk toggles.
	const handleBulkToggle = ( enable ) => {
		const newSettings = {};
		let skippedCount = 0;

		allSites.forEach( ( site ) => {
			const siteUrl = trailingslashit( site.siteUrl );

			// Only enable sites that have entities.
			const canEnable = enable ? siteHasEntities( siteUrl ) : true;

			if ( enable && ! canEnable ) {
				skippedCount++;
			}

			// Preserve previous searchable_sites when enabling.
			const prev = searchSettings[ siteUrl ] || {};
			const prevSites = Array.isArray( prev?.searchable_sites )
			// Keep only targets that still have entities.
				? prev.searchable_sites.filter( ( targetUrl ) =>
					siteHasEntities( targetUrl ),
				)
				: [];

			let sitesToReturn = [];

			if ( enable && canEnable ) {
				sitesToReturn = prevSites.length > 0 ? prevSites : [ siteUrl ];
			}

			newSettings[ siteUrl ] = {
				algolia_enabled: canEnable ? enable : false,
				searchable_sites: sitesToReturn,
			};
		} );

		setSearchSettings( newSettings );

		// Show notice if some sites were skipped
		if ( enable && skippedCount > 0 ) {
			setLocalNotice( {
				type: 'warning',
				message: __(
					'Some sites were skipped because they have no content types selected for indexing. Please configure indexable entities for these sites first.',
					'onesearch',
				),
			} );
		}
	};

	// Save the settings.
	const handleSave = async () => {
		setSaving( true );
		try {
			const res = await fetch( `${ API_NAMESPACE }/sites-search-settings`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': NONCE,
				},
				body: JSON.stringify( { settings: searchSettings } ),
			} );

			const data = await res.json();
			if ( data.success ) {
				setNotice( {
					type: 'success',
					message: __( 'Search settings saved successfully.', 'onesearch' ),
				} );
			} else if ( data.data?.status === 500 ) {
				setNotice( {
					message: __( 'Internal server error.', 'onesearch' ),
					type: 'error',
				} );
			} else {
				throw new Error( data.message || 'Save failed' );
			}
		} catch ( err ) {
			setNotice( {
				type: 'error',
				message:
					err.message || __( 'Failed to save search settings.', 'onesearch' ),
			} );
		} finally {
			setSaving( false );
			setReloadKey( ( k ) => k + 1 );
		}
	};

	const isDirty =
		JSON.stringify( searchSettings ) !== JSON.stringify( initialSettings );

	if ( loading ) {
		return <Spinner />;
	}

	return (
		<Card className="onesearch-card">
			<CardHeader>
				<h2 className="onesearch-title">
					{ __( 'Site Search Configuration', 'onesearch' ) }
				</h2>
				<div className="onesearch-controls">
					<Button
						variant="secondary"
						onClick={ () => handleBulkToggle( true ) }
						disabled={ saving }
						className="onesearch-btn-enable-all"
					>
						{ __( 'Enable All', 'onesearch' ) }
					</Button>
					<Button
						variant="secondary"
						onClick={ () => handleBulkToggle( false ) }
						disabled={ saving }
						className="onesearch-btn-disable-all"
					>
						{ __( 'Disable All', 'onesearch' ) }
					</Button>
					<Button
						variant="primary"
						onClick={ handleSave }
						disabled={ saving || ! isDirty }
						isBusy={ saving }
						className="onesearch-btn-save"
					>
						{ saving
							? __( 'Saving…', 'onesearch' )
							: __( 'Save Settings', 'onesearch' ) }
					</Button>
				</div>
			</CardHeader>
			<CardBody className="onesearch-body">
				{ /* Notice for warnings */ }
				{ localNotice && (
					<Notice
						status={ localNotice.type }
						isDismissible={ true }
						onRemove={ () => setLocalNotice( null ) }
						className="onesearch-notice"
					>
						{ localNotice.message }
					</Notice>
				) }

				{ allSites.length === 0 ? (
					<p className="onesearch-no-sites">
						{ __( 'No sites configured yet.', 'onesearch' ) }
					</p>
				) : (
					allSites.map( ( site ) => {
						const siteUrl = trailingslashit( site.siteUrl );
						const siteSettings = searchSettings[ siteUrl ] || {
							algolia_enabled: false,
							searchable_sites: [],
						};

						const hasEntities = siteHasEntities( siteUrl );

						return (
							<div
								key={ siteUrl }
								className={ `onesearch-site-card ${
									site.isGoverning ? 'onesearch-site-governing' : ''
								} ${ ! hasEntities ? 'onesearch-site-no-entities' : '' }` }
							>
								{ /* Site Header */ }
								<div className="onesearch-site-header">
									<div className="onesearch-site-info">
										<h3 className="onesearch-site-name">{ site.siteName }</h3>
										<p className="onesearch-entity-site-url">{ siteUrl }</p>
										<p className="onesearch-site-status">
											{ siteSettings.algolia_enabled
												? __( 'Algolia search enabled', 'onesearch' )
												: __( 'Using default WordPress search', 'onesearch' ) }
										</p>
										{ ! hasEntities && (
											<p className="onesearch-site-warning">
												{ __(
													'Please select entities for indexing to enable Algolia search',
													'onesearch',
												) }
											</p>
										) }
									</div>

									<div className="onesearch-site-toggle">
										<ToggleControl
											checked={ siteSettings.algolia_enabled }
											disabled={ ! hasEntities }
											onChange={ ( enabled ) => handleSiteToggle( siteUrl, enabled ) }
											__nextHasNoMarginBottom
										/>
									</div>
								</div>

								{ /* Searchable Sites Selection */ }
								{ siteSettings.algolia_enabled && (
									<div className="onesearch-searchable-sites">
										<h4 className="onesearch-searchable-title">
											{ __( 'Search from:', 'onesearch' ) }
										</h4>

										{ /* Sort sites with current site first */ }
										{ allSites
											.slice()
											.filter( ( singleSite ) => {
												const siteURL = trailingslashit( singleSite.siteUrl );
												const ents = indexableEntities[ siteURL ] || [];
												return Array.isArray( ents ) && ents.length > 0;
											} )
											.sort( ( a, b ) => {
												const aUrl = trailingslashit( a.siteUrl );
												const bUrl = trailingslashit( b.siteUrl );
												const currentUrl = trailingslashit( siteUrl );

												// Put current site first.
												if ( aUrl === currentUrl && bUrl !== currentUrl ) {
													return -1;
												}
												if ( bUrl === currentUrl && aUrl !== currentUrl ) {
													return 1;
												}

												// Sort others alphabetically.
												return a.siteName.localeCompare( b.siteName );
											} )
											.map( ( targetSite ) => {
												const targetSiteUrl = trailingslashit(
													targetSite.siteUrl,
												);
												const isChecked =
													siteSettings.searchable_sites.includes( targetSiteUrl );
												const isSelf = targetSiteUrl === siteUrl;

												return (
													<div
														key={ targetSiteUrl }
														className={ `onesearch-searchable-item ${
															isSelf ? 'onesearch-current-site' : ''
														}` }
													>
														<ToggleControl
															label={
																<div className="onesearch-searchable-label">
																	<div className="onesearch-searchable-name">
																		{ targetSite.siteName }
																	</div>
																	<div className="onesearch-searchable-url">
																		{ targetSiteUrl }
																		{ isSelf && (
																			<span className="onesearch-current-indicator">
																				{ __(
																					'(Current Site - Always Included)',
																					'onesearch',
																				) }
																			</span>
																		) }
																	</div>
																</div>
															}
															checked={ isChecked || isSelf }
															disabled={ isSelf }
															onChange={ ( checked ) =>
																handleSearchableSiteToggle(
																	siteUrl,
																	targetSiteUrl,
																	checked,
																)
															}
															__nextHasNoMarginBottom
														/>
													</div>
												);
											} ) }
									</div>
								) }
							</div>
						);
					} )
				) }
			</CardBody>
		</Card>
	);
};

export default SiteSearchSettings;
