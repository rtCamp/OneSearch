<?php
/**
 * Watches for object changes to reindex in Algolia.
 *
 * @package OneSearch\Modules\Search
 */

declare(strict_types = 1);

namespace OneSearch\Modules\Search;

use OneSearch\Contracts\Interfaces\Registrable;
use OneSearch\Modules\Rest\Abstract_REST_Controller;
use OneSearch\Modules\Search\Settings as Search_Settings;
use OneSearch\Modules\Settings\Settings;
use OneSearch\Utils;

/**
 * Class - Watcher
 */
final class Watcher implements Registrable {

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'transition_post_status', [ $this, 'on_post_transition' ], 10, 3 );
	}

	/**
	 * Triggered when a post's status changes (e.g., publish, update, trash, etc.)
	 *
	 * @param string   $new_status The new post status.
	 * @param string   $old_status The previous post status.
	 * @param \WP_Post $post       The post object.
	 */
	public function on_post_transition( $new_status, $old_status, $post ): void {
		if ( ! $post instanceof \WP_Post || ! $this->is_post_type_indexable( (string) $post->post_type ) ) {
			return;
		}

		// First delete the old post.
		// @see Post_Record::prepare_record_object_name .
		$site_post_id   = sprintf( '%s_%d', Utils::normalize_url( get_site_url() ), (int) $post->ID );
		$indexer        = new Index();
		$delete_success = $indexer->delete_by(
			[
				'filters' => sprintf( 'site_post_id:"%s"', $site_post_id ),
			]
		);

		if ( is_wp_error( $delete_success ) ) {
			return;
		}

		// Check if the new status is allowed before reindexing.
		if ( ! in_array( $new_status, Post_Record::get_allowed_statuses( [ $post->post_type ] ), true ) ) {
			return;
		}

		$records = ( new Post_Record() )->to_records( $post );

		$indexer->save_records( $records );
	}

	/**
	 * Checks whether the post type is indexable.
	 *
	 * @param string $post_type The post type.
	 */
	private function is_post_type_indexable( string $post_type ): bool {
		// @todo Need the child site entities.
		$indexable_entities = $this->get_indexable_entities();
		if ( is_wp_error( $indexable_entities ) ) {
			return false;
		}

		$types_by_site = $indexable_entities['entities'] ?? [];

		$site_url = Utils::normalize_url( get_site_url() );
		if ( empty( $types_by_site[ $site_url ] ) ) {
			return false;
		}

		return in_array( $post_type, $types_by_site[ $site_url ], true );
	}

	/**
	 * Gets the allowed post types.
	 *
	 * Uses the indexable entities settings on governing site, or fetches from governing site if on child.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	private function get_indexable_entities(): array|\WP_Error {
		if ( Settings::is_governing_site() ) {
			return Search_Settings::get_indexable_entities();
		}

		// If no parent is configured, return an error.
		$parent_url     = Settings::get_parent_site_url();
		$our_public_key = Settings::get_api_key();
		if ( empty( $parent_url ) || empty( $our_public_key ) ) {
			return new \WP_Error(
				'onesearch_indexable_entities_unavailable',
				__( 'Algolia credentials are unavailable because no governing site is configured.', 'onesearch' )
			);
		}

		$endpoint = sprintf(
			'%s/wp-json/%s/indexable-entities',
			untrailingslashit( $parent_url ),
			Abstract_REST_Controller::NAMESPACE,
		);

		$response = wp_safe_remote_get(
			$endpoint,
			[
				'headers'    => [
					'Accept'            => 'application/json',
					'Content-Type'      => 'application/json',
					'X-OneSearch-Token' => $our_public_key,
				],
				'user-agent' => sprintf( 'OneSearch/%s', ONESEARCH_VERSION ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		if ( 200 !== $code ) {
			return new \WP_Error(
				'onesearch_rest_failed_to_connect',
				__( 'Failed to connect to the governing site.', 'onesearch' ),
				[
					'status' => $code,
					'body'   => $body,
				]
			);
		}

		$response_data = json_decode( $body, true );
		if ( null === $response_data || ! is_array( $response_data ) ) {
			return new \WP_Error(
				'onesearch_rest_invalid_response',
				__( 'Received invalid response from the governing site.', 'onesearch' )
			);
		}

		return $response_data['indexableEntities'] ?? [];
	}
}
