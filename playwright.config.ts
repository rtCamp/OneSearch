/**
 * External dependencies
 */
import { defineConfig, type PlaywrightTestConfig } from '@playwright/test';
import path from 'path';

// Ensure WP artifacts (and storage-state) are written into tests/_output
process.env[ 'WP_ARTIFACTS_PATH' ] = path.join(
	process.cwd(),
	'tests/_output'
);
// Ensure STORAGE_STATE_PATH points into tests/_output as well
process.env[ 'STORAGE_STATE_PATH' ] = path.join(
	process.env[ 'WP_ARTIFACTS_PATH' ],
	'storage-states',
	'admin.json'
);

const baseConfig =
	require( '@wordpress/scripts/config/playwright.config.js' ) as PlaywrightTestConfig; // eslint-disable-line @typescript-eslint/no-var-requires

const config = defineConfig( {
	...baseConfig,
	testDir: './tests/e2e',
	outputDir: './tests/_output',
} );

export default config;
