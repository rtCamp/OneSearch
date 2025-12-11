/**
 * WordPress dependencies
 */
import { createRoot } from 'react-dom/client';

/**
 * Internal dependencies
 */
import SettingsPage from './page';

export type SiteType = 'governing-site' | 'brand-site' | '';

interface OneSearchSettingsType {
	restUrl: string;
	restNonce: string;
	api_key: string;
	settingsLink: string;
	siteType: SiteType;

	// @todo legacy - to be removed later
	restNamespace?: string;
	nonce?: string;
	currentSiteUrl?: string;
}

declare global {
	interface Window {
		OneSearchSettings: OneSearchSettingsType;
	}
}

// Render to Gutenberg admin page with ID: onesearch-settings-page
const target = document.getElementById( 'onesearch-settings-page' );
if ( target ) {
	const root = createRoot( target );
	root.render( <SettingsPage /> );
}
