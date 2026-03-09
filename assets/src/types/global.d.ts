/**
 * Global type declarations for OneSearch.
 *
 * These types describe the window globals injected by WordPress PHP code.
 */

export type SiteType = 'governing-site' | 'brand-site' | '';

export interface OneSearchSharedSite {
	name: string;
	url: string;
	api_key?: string;
}

export interface OneSearchSettings {
	restUrl: string;
	restNonce: string;
	api_key: string;
	settingsLink: string;
	siteType: SiteType;
	sharedSites?: OneSearchSharedSite[];

	// @todo legacy - to be removed later
	restNamespace?: string;
	nonce?: string;
	currentSiteUrl?: string;
}

export interface OneSearchOnboarding {
	nonce: string;
	site_type: SiteType | '';
	setup_url: string;
}

declare global {
	interface Window {
		OneSearchSettings: OneSearchSettings;
		OneSearchOnboarding: OneSearchOnboarding;
	}
}
