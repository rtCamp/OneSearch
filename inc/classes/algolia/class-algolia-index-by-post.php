<?php
/**
 * Algolia Index
 *
 * Builds and updates the Algolia index from WordPress posts.
 *
 * @package OneSearch\Inc\Algolia
 */

namespace OneSearch\Inc\Algolia;

use OneSearch\Contracts\Interfaces\Registrable;
use OneSearch\Contracts\Traits\Singleton;
use OneSearch\Modules\Rest\Abstract_REST_Controller;
use OneSearch\Modules\Search\Settings as Search_Settings;
use OneSearch\Modules\Settings\Settings;
use OneSearch\Utils;

/**
 * Class Algolia_Index_By_Post
 */
class Algolia_Index_By_Post implements Registrable {

	use Singleton;

	private const NAMESPACE = Abstract_REST_Controller::NAMESPACE;

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'transition_post_status', [ $this, 'on_transition' ], 10, 3 );
	}

	/**
	 * Triggered when a post's status changes (e.g., publish, update, trash, etc.)
	 *
	 * @param string   $new_status The new post status.
	 * @param string   $old_status The previous post status.
	 * @param \WP_Post $post       The post object.
	 */
	public function on_transition( $new_status, $old_status, $post ): void {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$post_id = (int) $post->ID;

		if ( Settings::is_governing_site() ) {
			$records = Algolia_Index::instance()->get_indexable_records_from_post( [ $post ] );

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
	 * @param string                      $site_url    The site URL.
	 * @param int                         $post_id     The ID of the post.
	 * @param string                      $post_type   The type of post.
	 * @param string                      $post_status The new post status.
	 * @param array<array<string, mixed>> $records     The records to index.
	 *
	 * @return array{
	 *   ok: bool,
	 *   action: string,
	 *   count?: int,
	 *   message?: string
	 * }
	 */
	public function governing_handle_change( string $site_url, int $post_id, string $post_type, string $post_status, array $records ): array {
		$site_url = Utils::normalize_url( $site_url );

		$selected_types = $this->get_selected_entities_for_site( $site_url );

		// Skip early if the post type is not selected for indexing.
		if ( ! in_array( $post_type, $selected_types, true ) ) {
			return [
				'ok'     => true,
				'action' => 'skip',
			];
		}

		$allowed_statuses = Algolia_Index::instance()->compute_post_statuses_for_types( $selected_types );

		$index = Algolia::instance()->get_index();

		if ( is_wp_error( $index ) ) {
			return [
				'ok'      => false,
				'action'  => 'error',
				'message' => $index->get_error_message(),
			];
		}

		$settings = Algolia_Index::instance()->get_algolia_settings();

		try {
			$index->setSettings( $settings )->wait();
			$index->deleteBy(
				[ 'filters' => sprintf( 'parent_post_id:%s_%d', sanitize_key( $site_url ), $post_id ) ]
			)->wait();
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Do nothing.
		}

		if ( ! in_array( $post_status, $allowed_statuses, true ) ) {
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
	public function brand_send_to_governing( int $post_id, string $post_type, string $status, bool $force_delete = false ): void {
		$site_url   = Utils::normalize_url( get_site_url() );
		$public_key = Settings::get_api_key();
		$parent_url = Settings::get_parent_site_url();

		if ( empty( $public_key ) || empty( $parent_url ) ) {
			return;
		}

		$records = [];
		if ( ! $force_delete ) {
			$post = get_post( $post_id );
			if ( $post ) {
				$records = Algolia_Index::instance()->get_indexable_records_from_post( [ $post ] );
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
					'Accept'            => 'application/json',
					'Content-Type'      => 'application/json',
					'X-OneSearch-Token' => $public_key,
				],
				'body'    => wp_json_encode( $payload ) ?: '',
				'timeout' => 999, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
			]
		);
	}

	/**
	 * Retrieves selected entities for a specific site.
	 *
	 * @param string $site_url The site URL.
	 *
	 * @return string[] List of selected entity types.
	 */
	private function get_selected_entities_for_site( string $site_url ): array {
		$indexable_entities = Search_Settings::get_indexable_entities();

		$map = $indexable_entities['entities'] ?? [];

		if ( ! is_array( $map ) ) {
			return [];
		}

		$site_url_norm = Utils::normalize_url( $site_url );

		if ( isset( $map[ $site_url_norm ] ) && is_array( $map[ $site_url_norm ] ) ) {
			return array_values( array_unique( array_map( 'strval', $map[ $site_url_norm ] ) ) );
		}

		return [];
	}
}
