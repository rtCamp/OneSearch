/* global OneSearchSettings */
const isURL = ( str ) => {
	const pattern = new RegExp(
		'^(https?:\\/\\/)?' +
			'(([a-z\\d]([a-z\\d-]*[a-z\\d])*):([a-z\\d-]*[a-z\\d])*@)?' +
			'((([a-z\\d]([a-z\\d-]*[a-z\\d])*)\\.?)+[a-z]{2,}|' +
			'((\\d{1,3}\\.){3}\\d{1,3}))' +
			'(\\:\\d+)?(\\/[-a-z\\d%_.~+]*)*' +
			'(\\?[;&a-z\\d%_.~+=-]*)?' +
			'(\\#[-a-z\\d_]*)?$', 'i',
	);
	return pattern.test( str );
};

const isValidUrl = ( url ) => {
	try {
		const parsedUrl = new URL( url );
		return isURL( parsedUrl.href );
	} catch ( e ) {
		return false;
	}
};

const API_NAMESPACE = OneSearchSettings.restUrl + OneSearchSettings.restNamespace;
const NONCE = OneSearchSettings.restNonce;
const REST_NAMESPACE = OneSearchSettings.restNamespace;
const CURRENT_SITE_URL = OneSearchSettings.currentSiteUrl;
const SETUP_URL = OneSearchSettings.setupUrl;

export {
	isURL,
	isValidUrl,
	API_NAMESPACE,
	NONCE,
	REST_NAMESPACE,
	CURRENT_SITE_URL,
	SETUP_URL,
};
