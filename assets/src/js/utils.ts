/**
 * Helper function to validate if a string is a well-formed URL.
 *
 * @param {string} str - The string to validate as a URL.
 *
 * @return {boolean} True if the string is a valid URL, false otherwise.
 */
const isURL = ( str : string ) : boolean => {
	try {
		new URL( str );
		return true;
	} catch {
		return false;
	}
};

/**
 * Validates if a given string is a valid URL.
 *
 * @param {string} url - The URL string to validate.
 *
 * @return {boolean} True if the URL is valid, false otherwise.
 */
export const isValidUrl = ( url:string ):boolean => {
	try {
		const parsedUrl = new URL( url );
		return isURL( parsedUrl.href );
	} catch ( e ) {
		return false;
	}
};

/**
 * Ensures that a URL string ends with a trailing slash.
 *
 * @param {string} url - The URL string to normalize.
 * @return {string} The normalized URL with a trailing slash.
 */
export const withTrailingSlash = ( url : string ) : string => {
	if ( ! url ) {
		return '';
	}
	return url.endsWith( '/' ) ? url : `${ url }/`;
};

/**
 * WordPress REST API base URL for OneSearch plugin requests.
 *
 * @constant
 * @type {string}
 */
export const API_NAMESPACE = window.OneSearchSettings.restUrl + window.OneSearchSettings.restNamespace;

/**
 * The API key used for authenticating requests to the OneSearch REST API.
 */
export const API_KEY = window.OneSearchSettings.api_key;

/**
 * WordPress REST API nonce for authenticated requests.
 *
 * @constant
 * @type {string}
 */
export const NONCE = window.OneSearchSettings.nonce as string;

/**
 * REST namespace used by the OneSearch plugin.
 *
 * @constant
 * @type {string}
 */
export const REST_NAMESPACE = window.OneSearchSettings.restNamespace;

/**
 * Current site’s base URL.
 *
 * @constant
 * @type {string}
 */
export const CURRENT_SITE_URL = window.OneSearchSettings.currentSiteUrl;

