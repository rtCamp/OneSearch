import { createRoot } from 'react-dom/client';
import OnboardingScreen, { type SiteType } from './page';

interface OneSearchPluginGlobal {
	nonce: string;
	site_type: SiteType | '';
	setup_url: string;
}

declare global {
	interface Window {
		OneSearchPluginGlobal: OneSearchPluginGlobal;
	}
}

// Render to the target element.
const target = document.getElementById( 'onesearch-site-selection-modal' );
if ( target ) {
	const root = createRoot( target );
	root.render( <OnboardingScreen /> );
}
