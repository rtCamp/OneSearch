/* global OneSearchSettings */

/**
 * Checks whether a given string is a valid URL pattern using URL.
 *
 * @function
 * @param {string} str - The string to validate.
 * @return {boolean} True if the string is a correct URL, false otherwise.
 */
const isURL = ( str ) => {
	try {
		const url = new URL( str );
		return [ 'http:', 'https:' ].includes( url.protocol );
	} catch {
		return false;
	}
};

/**
 * Validates whether the provided string is a syntactically valid URL.
 *
 * @function
 * @param {string} url - The URL string to validate.
 * @return {boolean} True if the URL is valid and conforms to a proper format, false otherwise.
 */
export const isValidUrl = ( url ) => {
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
 * @function
 * @param {string} url - The URL string to normalize.
 * @return {string} The normalized URL with a trailing slash.
 */
export const withTrailingSlash = ( url ) => {
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
export const API_NAMESPACE = OneSearchSettings.restUrl + OneSearchSettings.restNamespace;

/**
 * WordPress REST API nonce for authenticated requests.
 *
 * @constant
 * @type {string}
 */
export const NONCE = OneSearchSettings.nonce;

/**
 * REST namespace used by the OneSearch plugin.
 *
 * @constant
 * @type {string}
 */
export const REST_NAMESPACE = OneSearchSettings.restNamespace;

/**
 * Current site’s base URL.
 *
 * @constant
 * @type {string}
 */
export const CURRENT_SITE_URL = OneSearchSettings.currentSiteUrl;

