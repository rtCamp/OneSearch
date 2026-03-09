/**
 * External dependencies
 */
import { createRoot } from 'react-dom/client';

/**
 * Internal dependencies
 */
import OnboardingScreen from './page';

// Render to the target element.
const target = document.getElementById( 'onesearch-site-selection-modal' );
if ( target ) {
	const root = createRoot( target );
	root.render( <OnboardingScreen /> );
}
