<?php
/**
 * Algolia Index
 *
 * Builds and updates the Algolia index from WordPress posts.
 *
 * @package onesearch
 */

namespace Onesearch\Inc\Algolia;

use Onesearch\Inc\Traits\Singleton;

/**
 * Class Algolia_Index_By_Post
 */
class Algolia_Index_By_Post {

	use Singleton;

	private const NAMESPACE = 'onesearch/v1';

	/**
	 * Constructor.
	 */
	final protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Sets up hooks.
	 */
	public function setup_hooks() {
		add_action( 'transition_post_status', [ $this, 'on_transition' ], 10, 3 );
	}

	/**
	 * Triggered when a post's status changes (e.g., publish, update, trash, etc.)
	 *
	 * @param string $new_status The new post status.
	 * @param string $old_status The previous post status.
	 *
	 * @param object $post The post object.
	 */
	public function on_transition( $new_status, $old_status, $post ) {
		if ( ! $post || empty( $post->ID ) ) {
			return;
		}

		$site_type = (string) get_option( 'onesearch_site_type', '' );
		$post_id   = (int) $post->ID;

		if ( 'governing-site' === $site_type ) {
			$records = Algolia_Index::get_instance()->get_indexable_records_from_post( [ $post ] );

			$this->governing_handle_change(
				trailingslashit( get_site_url() ),
				$post_id,
				(string) $post->post_type,
				(string) $new_status,
				$records,
			);

			return;
		}

		$this->brand_send_to_governing(
			$post_id,
			(string) $post->post_type,
			(string) $new_status
		);
	}

	/**
	 * Handles changes for governing sites when a post's status changes.
	 *
	 * @param string $site_url    The site URL.
	 * @param int    $post_id     The ID of the post.
	 * @param string $post_type   The type of post.
	 * @param string $post_status The new post status.
	 * @param array  $records     The records to index.
	 *
	 * @return array Status of the operation.
	 */
	public function governing_handle_change( string $site_url, int $post_id, string $post_type, string $post_status, array $records ): array {
		$site_url = norm_url( $site_url );

		$selected_types = $this->get_selected_entities_for_site( $site_url );
		$type_selected  = in_array( $post_type, $selected_types, true );

		if ( empty( $type_selected ) || ! $type_selected ) {
			return [
				'ok'     => true,
				'action' => 'skip',
			];
		}

		$allowed_statuses = Algolia_Index::get_instance()->compute_post_statuses_for_types( $selected_types );
		$status_allowed   = in_array( $post_status, $allowed_statuses, true );

		$client = Algolia::get_instance()->get_client();

		if ( is_wp_error( $client ) ) {
			return [
				'ok'      => false,
				'action'  => 'error',
				'message' => $client->get_error_message(),
			];
		}

		$index_name = Algolia::get_instance()->get_algolia_index_name_from_url( $site_url );
		$index      = $client->initIndex( $index_name );

		$this->ensure_index_ready( $index );

		try {
			$index->deleteBy( [ 'filters' => "parent_post_id:{$post_id}" ] )->wait();
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Do nothing.
		}

		if ( ! $status_allowed || empty( $status_allowed ) ) {
			return [
				'ok'      => true,
				'action'  => 'deleted',
				'message' => __( 'Post deleted', 'onesearch' ),
			];
		}

		try {
			$index->saveObjects( $records )->wait();
			return [
				'ok'     => true,
				'action' => 'upserted',
				'count'  => count( $records ),
			];
		} catch ( \Throwable $e ) {
			return [
				'ok'      => false,
				'action'  => 'error',
				'message' => $e->getMessage(),
			];
		}
	}

	/**
	 * Sends data to the governing site (e.g., parent site).
	 *
	 * @param int    $post_id      The ID of the post.
	 * @param string $post_type    The type of post.
	 * @param string $status       The post's status.
	 * @param bool   $force_delete Whether to force the deletion of the post.
	 */
	public function brand_send_to_governing( int $post_id, string $post_type, string $status, bool $force_delete = false ) {
		$site_url   = trailingslashit( get_site_url() );
		$public_key = (string) get_option( 'onesearch_child_site_public_key', '' );
		$parent_url = (string) get_option( 'onesearch_parent_site_url', '' );

		if ( empty( $public_key ) || empty( $parent_url ) ) {
			return;
		}

		$records = [];
		if ( ! $force_delete ) {
			$post = get_post( $post_id );
			if ( $post ) {
				$records = Algolia_Index::get_instance()->get_indexable_records_from_post( [ $post ] );
			}
		}

		$payload = [
			'site_url'    => $site_url,
			'post_id'     => $post_id,
			'post_type'   => $post_type,
			'post_status' => $status,
			'records'     => $records,
		];

		wp_remote_post(
			trailingslashit( $parent_url ) . 'wp-json/' . self::NAMESPACE . '/reindex-post',
			[
				'headers' => [
					'Accept'                    => 'application/json',
					'Content-Type'              => 'application/json',
					'X-OneSearch-Plugins-Token' => $public_key,
				],
				'body'    => wp_json_encode( $payload ),
				'timeout' => 999, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
			]
		);
	}

	/**
	 * Ensures that the Algolia index is ready.
	 *
	 * @param object $index The Algolia index object.
	 */
	public function ensure_index_ready( $index ): void {
		$settings = Algolia_Index::get_instance()->get_algolia_settings( $index );
		try {
			$index->setSettings( $settings )->wait();
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// do nothing.
		}
	}

	/**
	 * Retrieves selected entities for a specific site.
	 *
	 * @param string $site_url The site URL.
	 *
	 * @return array List of selected entity types for the site.
	 */
	public function get_selected_entities_for_site( string $site_url ): array {
		$indexable_entities = get_option( 'onesearch_indexable_entities', [] );

		$map = is_array( $indexable_entities ) ? ( $indexable_entities['entities'] ?? [] ) : [];

		if ( ! is_array( $map ) ) {
			return [];
		}

		$site_url_norm = norm_url( $site_url );

		if ( isset( $map[ $site_url_norm ] ) && is_array( $map[ $site_url_norm ] ) ) {
			return array_values( array_unique( array_map( 'strval', $map[ $site_url_norm ] ) ) );
		}

		return [];
	}

	/**
	 * Checks if the token sent by the child site is valid.
	 *
	 * @param string $incoming The token sent by the child site.
	 *
	 * @return bool True if the token is valid, false otherwise.
	 */
	public function is_valid_child_token( string $incoming ): bool {
		$child_sites = get_option( 'onesearch_shared_sites', [] );

		if ( ! is_array( $child_sites ) ) {
			return false;
		}

		foreach ( $child_sites as $child ) {
			$key = isset( $child['publicKey'] ) ? (string) $child['publicKey'] : '';
			if ( $key && hash_equals( $key, $incoming ) ) {
				return true;
			}
		}

		return false;
	}
}
