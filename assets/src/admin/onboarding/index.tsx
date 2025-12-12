/**
 * WordPress dependencies
 */
import { createRoot } from 'react-dom/client';

/**
 * Internal dependencies
 */
import OnboardingScreen, { type SiteType } from './page';

interface OneSearchOnboarding {
	nonce: string;
	site_type: SiteType | '';
	setup_url: string;
}

declare global {
	interface Window {
		OneSearchOnboarding: OneSearchOnboarding;
	}
}

// Render to the target element.
const target = document.getElementById( 'onesearch-site-selection-modal' );
if ( target ) {
	const root = createRoot( target );
	root.render( <OnboardingScreen /> );
}
