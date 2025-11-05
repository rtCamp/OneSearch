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
use WP_Post;

/**
 * Class Algolia_Index
 */
class Algolia_Index {

	use Singleton;

	/**
	 * Constructor.
	 */
	final protected function __construct() {}

	/**
	 * Index the post types into Algolia.
	 *
	 * @param string[] $site_indexable_entities Post types to index (e.g. ['post','page']).
	 *
	 * @return true|\WP_Error True on success, WP_Error if index not available.
	 */
	public function index( $site_indexable_entities ) {

		$index = Algolia::get_instance()->get_index();

		if ( is_wp_error( $index ) ) {
			return $index;
		}

		// If nothing to index, clear the remote index and exit.
		if ( empty( $site_indexable_entities ) ) {
			$index->clearObjects()->wait();
			return true;
		}

		$settings = $this->get_algolia_settings();

		$index->setSettings( $settings );

		// Batched indexing across pages.
		$batch_size  = 100;
		$first_batch = true;

		// Use generator pattern for memory-efficient batch processing.
		foreach ( $this->fetch_post_batches( $site_indexable_entities, $batch_size ) as $records ) {
			if ( empty( $records ) ) {
				// If it's the first batch, that means the site has no posts and should be reset.
				if ( true === $first_batch ) {
					$index->replaceAllObjects( [] )->wait();
				}
				break;
			}

			// The first batch replaces all objects and subsequent batches add to the index.
			if ( $first_batch ) {
				$index->replaceAllObjects( $records )->wait();
				$first_batch = false;
			} else {
				$index->saveObjects( $records )->wait();
			}

			// Free memory after processing batch.
			unset( $records );
		}

		return true;
	}

	/**
	 * Retrieves the Algolia index settings.
	 *
	 * @return array<string,mixed> The final settings for the Algolia index.
	 */
	public function get_algolia_settings(): array {
		// Default index configuration.
		$default_settings = [
			'attributeForDistinct'  => 'parent_post_id',
			'distinct'              => 1,
			'searchableAttributes'  => [ 'title', 'clean_content', 'excerpt', 'author_display_name' ],
			'attributesForFaceting' => [
				'filterOnly(parent_post_id)',
				'filterOnly(site_url)',
				'filterOnly(type)',
			],
		];

		/**
		 * Modify Algolia index settings.
		 *
		 * @param array<string,mixed> $settings Default settings.
		 */
		return apply_filters( 'onesearch_algolia_index_settings', $default_settings );
	}

	/**
	 * Fetch posts for indexing.
	 *
	 * @param string[] $site_indexable_entities Post types.
	 * @param int      $page                    Page number (1-based).
	 * @param int      $posts_per_page          Batch size (-1 for all).
	 *
	 * @return \WP_Post[] List of posts.
	 */
	private function get_indexable_posts( $site_indexable_entities, $page = 1, $posts_per_page = -1 ) {

		$statuses = $this->compute_post_statuses_for_types( $site_indexable_entities );

		$args = [
			'post_type'              => $site_indexable_entities,
			'post_status'            => $statuses,
			'posts_per_page'         => $posts_per_page,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => true,
		];

		// Enable pagination when batching.
		if ( $posts_per_page > 0 ) {
			$args['paged'] = $page;
		}

		$query = new \WP_Query( $args );
		/** @var \WP_Post[] $posts */
		$posts = $query->get_posts();
		return $posts;
	}

	/**
	 * Convert posts to Algolia records.
	 *
	 * @param \WP_Post[] $posts Posts to convert.
	 *
	 * @return array<array<string,mixed>> List of records.
	 */
	public function get_indexable_records_from_post( array $posts ) {
		$site_url  = trailingslashit( get_site_url() );
		$site_key  = sanitize_key( $site_url );
		$site_name = get_bloginfo( 'name' );

		$records = [];

		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$base_record = [
				'objectID'               => $site_key . '_' . $post->ID,
				'title'                  => $post->post_title,
				'excerpt'                => get_the_excerpt( $post ),
				'content'                => $post->post_content,
				'clean_content'          => $this->get_clean_content( $post->post_content ),
				'name'                   => $post->post_name,
				'type'                   => $post->post_type,
				'permalink'              => get_permalink( $post->ID ),
				'taxonomies'             => $this->get_all_taxonomies( $post ),
				'date'                   => $post->post_date,
				'modified'               => $post->post_modified,
				'thumbnail'              => get_the_post_thumbnail_url( $post->ID ),
				'site_url'               => $site_url,
				'site_name'              => $site_name,
				'site_key'               => $site_key,
				'post_id'                => $post->ID,
				'postDate'               => $post->post_date,
				'postDateGmt'            => $post->post_date_gmt,
				'author_ID'              => $post->post_author,
				'author_login'           => get_the_author_meta( 'user_login', $post->post_author ),
				'author_nicename'        => get_the_author_meta( 'user_nicename', $post->post_author ),
				'author_email'           => get_the_author_meta( 'user_email', $post->post_author ),
				'author_registered'      => get_the_author_meta( 'user_registered', $post->post_author ),
				'author_display_name'    => get_the_author_meta( 'display_name', $post->post_author ),
				'author_first_name'      => get_the_author_meta( 'first_name', $post->post_author ),
				'author_last_name'       => get_the_author_meta( 'last_name', $post->post_author ),
				'author_description'     => get_the_author_meta( 'description', $post->post_author ),
				'author_avatar'          => get_avatar_url( $post->post_author ),
				'author_posts_url'       => get_author_posts_url( $post->post_author ),
				'parent_post_id'         => $site_key . '_' . $post->ID, // Used for Algolia distinct/grouping.
				'is_chunked'             => false,
				'onesearch_chunk_index'  => 0,
				'onesearch_total_chunks' => 1,
			];

			/**
			 * Allow modification of the record payload.
			 *
			 * @param array    $base_record Record being indexed.
			 * @param \WP_Post $post        Source post.
			 */
			$filtered_record = apply_filters( 'onesearch_algolia_index_data', $base_record, $post );

			// Segment records that exceed the size threshold.
			$chunks = $this->maybe_chunk_record( $filtered_record );

			// Add chunks to records array.
			$records = array_merge( $records, $chunks );

			// Free memory after processing each post.
			unset( $base_record, $filtered_record, $chunks );
		}

		return $records;
	}

	/**
	 * Convert a post to its rendered version without markup
	 *
	 * @param string $post_content Post content (string).
	 *
	 * @return string
	 */
	private function get_clean_content( string $post_content ): string {
		// Render Gutenberg blocks (convert block markup to HTML).
		$parsed_post_content = do_blocks( $post_content );

		// Define allowed HTML elements and attributes useful for search context.
		$allowed_tags = [
			'a'      => [
				'href'  => true,
				'title' => true,
			],
			'img'    => [
				'src' => true,
				'alt' => true,
			],
			'strong' => [],
			'em'     => [],
			'p'      => [],
			'ul'     => [],
			'ol'     => [],
			'li'     => [],
			'h1'     => [],
			'h2'     => [],
			'h3'     => [],
			'h4'     => [],
			'h5'     => [],
			'h6'     => [],
		];

		// Sanitize the rendered HTML but keep useful structure and metadata.
		$clean_content = wp_kses( $parsed_post_content, $allowed_tags );

		// Remove excessive blank lines and normalize whitespace.
		$clean_content = preg_replace( '/\s*\n\s*/', "\n", $clean_content ); // collapse blank lines.
		$clean_content = preg_replace( '/\n{2,}/', "\n", $clean_content );   // limit to single newlines.
		$clean_content = trim( (string) $clean_content );

		return $clean_content;
	}

	/**
	 * Collect taxonomy terms for a post.
	 *
	 * @param \WP_Post $post Post object.
	 *
	 * @return array Array of term data.
	 */
	private function get_all_taxonomies( $post ) {
		$taxonomies    = get_object_taxonomies( $post->post_type );
		$taxonomy_data = [];

		foreach ( $taxonomies as $taxonomy ) {
			$terms = wp_get_post_terms(
				$post->ID,
				$taxonomy,
				[ 'fields' => 'all' ]
			);

			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				$taxonomy_data[] = [
					'taxonomy'    => $taxonomy,
					'term_id'     => $term->term_id,
					'name'        => $term->name,
					'slug'        => $term->slug,
					'description' => $term->description,
					'parent'      => $term->parent,
					'count'       => $term->count,
					'term_link'   => get_term_link( $term ),
				];
			}
		}

		return $taxonomy_data;
	}

	/**
	 * Compute which post statuses to include for the given post types.
	 *
	 * @param string[] $post_types Post types.
	 *
	 * @return string[] Statuses to query.
	 */
	public function compute_post_statuses_for_types( $post_types ) {
		$statuses = [ 'publish' ];

		// Media uses inherit when attached as a ststus.
		if ( in_array( 'attachment', $post_types, true ) ) {
			$statuses[] = 'inherit';
		}

		/**
		 * Filter the statuses used for indexing.
		 *
		 * @param string[] $statuses   Statuses.
		 * @param string[] $post_types Post types.
		 */
		return apply_filters( 'onesearch_indexable_post_statuses', $statuses, $post_types );
	}

	/**
	 * Split a record into multiple parts if it exceeds a size threshold.
	 *
	 * Handles UTF-8 sanitization and returns records ready for Algolia indexing.
	 *
	 * @param array $record Record to evaluate and possibly split.
	 *
	 * @return array<array<string,mixed>> Array of one or more records.
	 */
	private function maybe_chunk_record( $record ): array {
		// Sanitize UTF-8 and measure size in one operation.
		$json_encoded = wp_json_encode( $record, JSON_INVALID_UTF8_SUBSTITUTE );
		$json_size    = strlen( $json_encoded ?: '' );
		$record       = json_decode( $json_encoded, true );
		if ( ! is_array( $record ) ) {
			return [];
		}

		// Size threshold: 9KB to constraint payload size.
		if ( $json_size <= 9000 ) {
			return [ $record ];
		}

		// Separate the base fields from the content.
		$content     = $record['content'];
		$base_record = $record;
		unset( $base_record['content'] );

		$base_size       = strlen( wp_json_encode( $base_record ) ?: '' );
		$available_space = 8000 - $base_size; // Per-chunk allowed size (left size).

		if ( $available_space <= 0 ) {
			return [];
		}

		$chunks          = $this->smart_chunk_content( $content, $available_space );
		$chunked_records = [];

		foreach ( $chunks as $index => $chunk_content ) {
			$chunk_record                           = $base_record;
			$chunk_record['objectID']               = $record['parent_post_id'] . '_chunk_' . $index;
			$chunk_record['content']                = $chunk_content;
			$chunk_record['is_chunked']             = true;
			$chunk_record['onesearch_chunk_index']  = $index;
			$chunk_record['onesearch_total_chunks'] = count( $chunks );

			$chunked_records[] = $chunk_record;
		}

		return $chunked_records;
	}

	/**
	 * Content segmentation in paragraphs.
	 *
	 * @param string $content  Raw content.
	 * @param int    $max_size Maximum bytes per chunk.
	 *
	 * @return string[] Content chunks.
	 */
	private function smart_chunk_content( $content, $max_size ) {

		$available_size = (int) $max_size - 100;

		if ( $available_size <= 0 ) {
			return [ mb_substr( (string) $content, 0, 1000 ) ];
		}

		return str_split( (string) $content, $available_size );
	}

	/**
	 * Generator that yields batches of records for indexing.
	 *
	 * This method uses a generator pattern to process posts in batches
	 * while maintaining memory efficiency.
	 *
	 * @param string[] $site_indexable_entities Post types to index.
	 * @param int      $batch_size              Number of posts per batch.
	 *
	 * @return \Generator<array<string,mixed>[]> Yields arrays of indexable records.
	 */
	private function fetch_post_batches( $site_indexable_entities, $batch_size ) {
		$page = 1;

		while ( true ) {
			$posts = $this->get_indexable_posts( $site_indexable_entities, $page, $batch_size );

			if ( empty( $posts ) ) {
				break;
			}

			$records = $this->get_indexable_records_from_post( $posts );

			yield $records;

			unset( $posts, $records );

			++$page;
		}
	}
}
