<?php
/**
 * Onesearch custom functions.
 *
 * @package onesearch
 */

/**
 * Generate cache key.
 *
 * @param string|array $unique base on that cache key will generate.
 *
 * @return string Cache key.
 */
function onesearch_get_cache_key( $unique = '' ) {

	$cache_key = 'onesearch_cache_';

	if ( is_array( $unique ) ) {
		ksort( $unique );
		$unique = wp_json_encode( $unique );
	}

	$md5 = md5( $unique );
	return $cache_key . $md5;
}

/**
 * To get cached version of result of WP_Query
 *
 * @param array $args Args of WP_Query.
 *
 * @return array List of posts.
 */
function onesearch_get_cached_posts( $args ) {

	if ( empty( $args ) || ! is_array( $args ) ) {
		return [];
	}

	$args['suppress_filters'] = false;

	$expires_in = MINUTE_IN_SECONDS * 15;

	$cache_key = onesearch_get_cache_key( $args );

	$cache  = new \Onesearch\Inc\Cache( $cache_key );
	$result = $cache->expires_in( $expires_in )->updates_with( 'get_posts', [ $args ] )->get();

	return ! empty( $result ) && is_array( $result ) ? $result : [];
}

/**
 * Return template content.
 *
 * @param string $slug Template path.
 * @param array  $vars Variables to be used in the template.
 *
 * @return string Template markup.
 */
function onesearch_get_template_content( $slug, $vars = [] ) {

	ob_start();

	get_template_part( $slug, null, $vars );

	return ob_get_clean();
}

/**
 * Get plugin template.
 *
 * @param string $template  Name or path of the template within /templates folder without php extension.
 * @param array  $variables pass an array of variables you want to use in template.
 * @param bool   $echo      Whether to echo out the template content or not.
 *
 * @return string|void Template markup.
 */
function onesearch_template( $template, $variables = [], $echo = false ) {

	$template_file = sprintf( '%1$s/templates/%2$s.php', ONESEARCH_PATH, $template );

	if ( ! file_exists( $template_file ) ) {
		return '';
	}

	if ( ! empty( $variables ) && is_array( $variables ) ) {
		extract( $variables, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Used as an exception as there is no better alternative.
	}

	ob_start();

	include $template_file; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable

	$markup = ob_get_clean();

	if ( ! $echo ) {
		return $markup;
	}

	echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output escaped already in template.
}

/**
 * Get data file content from '/data' directory.
 *
 * @param  string $slug file Data file name without '.php' extention.
 * @param  array  $default   Default value to return if file not found.
 *
 * @return mixed Data file content.
 */
function onesearch_get_data( $slug, $default = [] ) {

	$data_file = sprintf( ONESEARCH_PATH . '/inc/data/%s.php', $slug );

	if ( file_exists( $data_file ) ) {
		return require $data_file;
	}

	return $default;
}

/**
 * Check whether current environment is production?
 *
 * Environment type support has been added in WordPress 5.5 and also support added to VIP-Go platform environments.
 *
 * @see https://make.wordpress.org/core/2020/07/24/new-wp_get_environment_type-function-in-wordpress-5-5/
 * @see https://lobby.vip.wordpress.com/2020/08/20/environment-type-support/
 *
 * @return bool Return true if it's production else return false.
 */
function onesearch_is_production() {

	return 'production' === wp_get_environment_type();
}

/**
 * Determine if the current User Agent matches the passed $kind
 *
 * @param string $kind                 Category of mobile device to check for.
 *                                     Either: any, dumb, smart.
 * @param bool   $return_matched_agent Boolean indicating if the UA should be returned.
 *
 * @return bool|string Boolean indicating if current UA matches $kind. If
 *                     $return_matched_agent is true, returns the UA string
 */
function onesearch_is_mobile( $kind = 'any', $return_matched_agent = false ) {

	if ( function_exists( 'jetpack_is_mobile' ) ) {
		return jetpack_is_mobile( $kind, $return_matched_agent );
	}

	return false;
}

/**
 * Validate API key.
 *
 * @return bool
 */
function onesearch_validate_api_key(): bool {
	// check if the request is from same site.
	if ( 'governing-site' === get_option( 'onesearch_site_type', '' ) ) {
		return (bool) current_user_can( 'manage_options' );
	}

	// check X-onesearch-Plugins-Token header.
	if ( isset( $_SERVER['HTTP_X_ONESEARCH_PLUGINS_TOKEN'] ) && ! empty( $_SERVER['HTTP_X_ONESEARCH_PLUGINS_TOKEN'] ) ) {
		$token = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_ONESEARCH_PLUGINS_TOKEN'] ) );
		// Get the public key from options.
		$public_key = get_option( 'onesearch_child_site_public_key', '' );
		if ( hash_equals( $token, $public_key ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Delete the Algolia index for a given site URL.
 *
 * @param string $site_url Absolute site URL used to derive the index name.
 *
 * @return string          Result message indicating success or the error encountered.
 */
function delete_site_algolia_index( string $site_url ): string {
	$sanitized_url = (string) esc_url_raw( $site_url );

	if ( empty( $sanitized_url ) ) {
		return __( 'Invalid site URL.', 'onesearch' );
	}

	try {
		$algolia = \Onesearch\Inc\Algolia\Algolia::get_instance();
		$client  = $algolia->get_client();

		if ( is_wp_error( $client ) ) {
			return sprintf(
				/* translators: %s: error message */
				__( 'Algolia client error: %s', 'onesearch' ),
				$client->get_error_message()
			);
		}

		$index_name = $algolia->get_algolia_index_name_from_url( $sanitized_url );
		$index      = $client->initIndex( $index_name );

		// Delete the index.
		$index->delete()->wait();

		return sprintf(
			/* translators: %s: index name */
			__( 'Algolia index deleted: %s', 'onesearch' ),
			$index_name
		);
	} catch ( \Throwable $e ) {
		return sprintf(
			/* translators: %s: error message */
			__( 'Error deleting Algolia index: %s', 'onesearch' ),
			$e->getMessage()
		);
	}
}

/**
 * Normalize a URL by trimming and adding a trailing slash.
 *
 * @param string $url The URL to normalize.
 *
 * @return string Normalized URL with trailing slash.
 */
function norm_url( string $url ): string {
	return trailingslashit( trim( (string) $url ) );
}

/**
 * Get locally stored Algolia credentials.
 *
 * @return array{app_id?: string, write_key?: string, admin_key?: string}
 */
function get_local_algolia_credentials(): array {
	return (array) get_option( 'onesearch_algolia_credentials', [] );
}

/**
 * Allow if current user is admin, otherwise require a valid token header.
 *
 * @param \WP_REST_Request $request    WP_Request.
 * @param string           $option_key Option that stores the expected token.
 * @param string           $header_key HTTP header to read the token from.
 *
 * @return true|\WP_Error
 */
function verify_admin_and_token( \WP_REST_Request $request, string $option_key = 'onesearch_child_site_public_key', string $header_key = 'X-OneSearch-Plugins-Token' ) {
	$incoming_key = (string) ( $request->get_header( $header_key ) ?? '' );
	$expected_key = (string) get_option( $option_key, '' );

	$is_admin = current_user_can( 'manage_options' );

	if ( ! $is_admin ) {
		if ( empty( $incoming_key ) || $incoming_key !== $expected_key ) {
			return new \WP_Error(
				'invalid_api_key',
				__( 'Invalid or missing API key.', 'onesearch' ),
				[ 'status' => 403 ]
			);
		}
	}

	return true;
}
