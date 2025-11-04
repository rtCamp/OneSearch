<?php
/**
 * Algolia Search integration.
 *
 * @package onesearch
 */

namespace Onesearch\Inc\Algolia;

use Onesearch\Inc\REST\Governing_Data;
use Onesearch\Inc\Traits\Singleton;
use WP_Post;
use stdClass;

/**
 * Class Algolia_Search
 */
class Algolia_Search {


	use Singleton;

	/**
	 * Constructor.
	 */
	final protected function __construct() {
		$site_type = (string) get_option( 'onesearch_site_type', '' );

		// Select search config based on site role.
		if ( 'brand-site' === $site_type ) {
			$search_config = Governing_Data::get_search_settings();
		} else {
			$all_settings  = get_option( 'onesearch_sites_search_settings', [] );
			$search_config = $all_settings[ trailingslashit( get_site_url() ) ] ?? [];
		}

		// Do not hook the hooks if disabled or no sites are selected.
		if ( ! is_array( $search_config ) || empty( $search_config ) || empty( $search_config['algolia_enabled'] ) || empty( $search_config['searchable_sites'] ) ) {
			return;
		}

		$this->setup_hooks();
	}

	/**
	 * Register filters used to map Algolia data to WordPress expectations.
	 *
	 * @return void
	 */
	protected function setup_hooks() {
		add_filter( 'posts_pre_query', [ $this, 'get_algolia_results' ], 10, 2 );
		// Map permalinks and author/category/tag data for remote posts.
		add_filter( 'page_link', [ $this, 'get_post_type_permalink' ], 10, 2 );
		add_filter( 'post_link', [ $this, 'get_post_type_permalink' ], 10, 2 );
		add_filter( 'post_type_link', [ $this, 'get_post_type_permalink' ], 10, 2 );
		add_filter( 'page_type_link', [ $this, 'get_post_type_permalink' ], 10, 2 );
		add_filter( 'attachment_link', [ $this, 'get_post_type_permalink' ], 10, 2 );

		add_filter( 'get_the_author_display_name', [ $this, 'get_post_author' ], 10, 2 );
		add_filter( 'author_link', [ $this, 'get_post_author_link' ], 10, 3 );
		add_filter( 'get_avatar_url', [ $this, 'get_post_author_avatar' ], 10, 3 );

		// Term and taxonomy link handling for remote objects.
		add_filter( 'category_link', [ $this, 'get_post_category_link' ], 10, 2 );
		add_filter( 'term_link', [ $this, 'get_term_link' ], 10, 3 );
		add_filter( 'category_link', [ $this, 'get_category_link' ], 10, 2 );
		add_filter( 'tag_link', [ $this, 'get_tag_link' ], 10, 2 );

		add_filter( 'get_the_terms', [ $this, 'get_post_terms' ], 10, 3 );
		add_filter( 'wp_get_post_terms', [ $this, 'get_post_terms' ], 10, 3 );
	}

	/**
	 * Resolve term link for remote posts using stored taxonomy metadata.
	 *
	 * @param string     $term_link Default term link.
	 * @param int|string $term Term ID or slug (as provided by WP).
	 * @param string     $taxonomy  Taxonomy name.
	 *
	 * @return string
	 */
	public function get_term_link( $term_link, $term, $taxonomy ) {
		global $post;

		if ( isset( $post->ID ) && $post->ID < 0 && isset( $post->onesearch_remote_taxonomies ) ) {
			foreach ( $post->onesearch_remote_taxonomies as $tax_data ) {
				if (
					$tax_data['taxonomy'] === $taxonomy &&
					( $tax_data['term_id'] === (int) $term || $tax_data['slug'] === $term )
				) {
					return $tax_data['term_link'] ?? $term_link;
				}
			}
		}

		return $term_link;
	}

	/**
	 * Category link helper for remote posts.
	 *
	 * @param string $cat_link    Default link.
	 * @param int    $category_id Category ID.
	 *
	 * @return string
	 */
	public function get_category_link( $cat_link, $category_id ) {
		return $this->get_term_link( $cat_link, $category_id, 'category' );
	}

	/**
	 * Tag link helper for remote posts.
	 *
	 * @param string $tag_link Default link.
	 * @param int    $tag_id   Tag ID.
	 *
	 * @return string
	 */
	public function get_tag_link( $tag_link, $tag_id ) {
		return $this->get_term_link( $tag_link, $tag_id, 'post_tag' );
	}

	/**
	 * Populate terms for remote posts from stored taxonomy metadata.
	 *
	 * @param array|\WP_Term[]|false $terms    Default terms.
	 * @param int                    $post_id  Post ID (negative for remote).
	 * @param string                 $taxonomy Taxonomy.
	 *
	 * @return array|\WP_Term[]|false
	 */
	public function get_post_terms( $terms, $post_id, $taxonomy ) {
		if ( $post_id < 0 ) {
			$post = get_post( $post_id );

			if ( $post && isset( $post->onesearch_remote_taxonomies ) ) {
				$filtered_terms = [];

				foreach ( $post->onesearch_remote_taxonomies as $tax_data ) {
					if ( $tax_data['taxonomy'] !== $taxonomy ) {
						continue;
					}

					$fake_term                   = new \stdClass();
					$fake_term->term_id          = $tax_data['term_id'];
					$fake_term->name             = $tax_data['name'];
					$fake_term->slug             = $tax_data['slug'];
					$fake_term->term_group       = 0;
					$fake_term->term_taxonomy_id = $tax_data['term_id'];
					$fake_term->taxonomy         = $tax_data['taxonomy'];
					$fake_term->description      = $tax_data['description'];
					$fake_term->parent           = $tax_data['parent'];
					$fake_term->count            = $tax_data['count'];

					$filtered_terms[] = $fake_term;
				}

				return ! empty( $filtered_terms ) ? $filtered_terms : $terms;
			}
		}

		return $terms;
	}

	/**
	 * Category link mapping for remote posts using prefilled link.
	 *
	 * @param string $category_link Default link.
	 * @param int    $category_id   Category ID (negative for remote).
	 *
	 * @return string
	 */
	public function get_post_category_link( $category_link, $category_id ) {
		global $post;

		if ( (int) $category_id < -999 ) {
			return $post->onesearch_remote_post_category_link ?? $category_link;
		}

		return $category_link;
	}

	/**
	 * Author avatar URL mapping for remote posts.
	 *
	 * @param string $avatar_url Default avatar URL.
	 * @param mixed  $author_id  Value passed by the filter for the avatar.
	 * @param array  $args       Arguments array forwarded by the filter (size, scheme, etc.).
	 *
	 * @return string
	 */
	public function get_post_author_avatar( $avatar_url, $author_id, $args ) {
		global $post;

		if ( isset( $post->ID ) && (int) $post->ID < 0 ) {
			return $post->onesearch_remote_post_author_gravatar ?? $avatar_url;
		}

		return $avatar_url;
	}

	/**
	 * Author link mapping for remote posts.
	 *
	 * @param string      $author_link     Default author link.
	 * @param int         $author_id       Author ID (negative for remote).
	 * @param string|null $author_nicename The author's nicename if provided by the filter.
	 *
	 * @return string
	 */
	public function get_post_author_link( $author_link, $author_id, $author_nicename = null ) {
		global $post;

		if ( isset( $post->ID ) && (int) $post->ID < 0 ) {
			return $post->onesearch_remote_post_author_link ?? $author_link;
		}

		return $author_link;
	}

	/**
	 * Author display name mapping for remote posts.
	 *
	 * @param string $author_name Default display name.
	 * @param int    $author_id   Author ID (negative for remote).
	 *
	 * @return string
	 */
	public function get_post_author( $author_name, $author_id ) {
		global $post;

		if ( isset( $post->ID ) && (int) $post->ID < 0 ) {
			return $post->onesearch_remote_post_author_display_name ?? $author_name;
		}

		return $author_name;
	}

	/**
	 * Return the correct permalink for local or remote posts.
	 *
	 * For remote posts we store a negative ID and the original ID separately.
	 * This method locates the remote item and returns its GUID.
	 *
	 * @param string       $permalink Default permalink.
	 * @param int|\WP_Post $post      Post object or ID.
	 *
	 * @return string
	 */
	public function get_post_type_permalink( $permalink, $post ) {

		global $wp_query;

		$post_id = $post instanceof WP_Post ? $post->ID : $post;

		if ( (int) $post_id < 0 ) {
			$original_post_id = absint( $post_id + 1 );
			$all_found_posts  = $wp_query->posts;

			foreach ( $all_found_posts as $post ) {
				// For remote placeholders we set onesearch_original_id.
				if ( absint( $post->onesearch_original_id ) === $original_post_id ) {
					return $post->guid;
				}
			}
		}

		return $permalink;
	}

	/**
	 * Fulfill the main search query with Algolia results.
	 *
	 * @param array|null $posts Posts.
	 * @param \WP_Query  $query Current query.
	 *
	 * @return array|null Array of WP_Post-like objects or original $posts on skip/error.
	 */
	public function get_algolia_results( $posts, $query ) {

		// Only handle: main, search queries with a search term.
		if ( ! $query->is_main_query() || ! $query->is_search() || empty( $query->get( 's' ) ) ) {
			return $posts;
		}

		// Skip WordPress template.
		if ( isset( $query->query['post_type'] ) && 'wp_template' === $query->query['post_type'] ) {
			return $posts;
		}

		$user_search = get_search_query();

		$hits = $this->get_all_searched_record_ids( $user_search, $query );
		if ( is_wp_error( $hits ) ) {
			return $posts;
		}

		// Group all hits by their parent_post_id.
		$grouped = $this->group_hits_by_post( $hits );

		// Set total for pagination.
		[ $page_keys, $total_groups ] = $this->get_paged_group_keys( $grouped, $query );
		$query->found_posts           = $total_groups;

		$keys_to_build = null === $page_keys ? array_keys( $grouped ) : $page_keys;

		$hits_for_page = $this->pick_representative_hits( $grouped, $keys_to_build );

		$reconstruct = apply_filters( 'onesearch_reconstruct_chunked_on_search', true );

		$searched_posts = $this->build_posts_from_grouped_hits( $hits_for_page, $reconstruct );

		$query->post_count        = count( $searched_posts );
		$query->is_algolia_search = true;

		return $searched_posts;
	}

	/**
	 * Build posts for the current page using batch chunk fetches.
	 *
	 * @param array $hits_for_page One representative hit per grouped post.
	 * @param bool  $reconstruct   Whether to reconstruct chunked posts (true = fetch chunks).
	 * @return array               List of WP_Post / WP_Post-like objects.
	 */
	private function build_posts_from_grouped_hits( array $hits_for_page, bool $reconstruct = true ): array {
		$parent_ids = [];

		// Collect all parent IDs we need to fetch (for chunked posts only).
		if ( $reconstruct ) {
			foreach ( $hits_for_page as $hit ) {
				$is_chunked = ! empty( $hit['is_chunked'] );
				$parent_id  = $hit['parent_post_id'] ?? null;

				// Site filtering is already done by Algolia, so site_url is guaranteed to exist.
				if ( ! $is_chunked || ! $parent_id ) {
					continue;
				}

				$parent_ids[] = $parent_id;
			}
		}

		// Fetch chunks in batches using parent_post_id (globally unique with site_key prefix).
		$chunks_by_parent = $reconstruct ? $this->batch_fetch_chunks( $parent_ids ) : [];

		$posts = [];

		foreach ( $hits_for_page as $hit ) {
			$is_chunked = ! empty( $hit['is_chunked'] );

			// If not chunked, or we are not rebuilding full content, just build from this hit.
			if ( ! $is_chunked || ! $reconstruct ) {
				$post_obj = $this->create_post_object( $hit );
				if ( $post_obj ) {
					$posts[] = $post_obj;
				}
				continue;
			}

			$parent_id  = $hit['parent_post_id'] ?? null;
			$all_chunks = $chunks_by_parent[ $parent_id ] ?? [];

			// If we did not get any chunks, fall back to the single hit.
			if ( empty( $all_chunks ) ) {
				$post_obj = $this->create_post_object( $hit );
				if ( $post_obj ) {
					$posts[] = $post_obj;
				}
				continue;
			}

			$post_obj = $this->build_post_with_all_chunks( $all_chunks );
			if ( ! $post_obj ) {
				continue;
			}

			$posts[] = $post_obj;
		}

		return $posts;
	}

	/**
	 * Fetch chunks for many posts in batches.
	 *
	 * @param array<string> $parent_ids Array of parent_post_id values (strings with site_key prefix).
	 * @return array<string, array<array<string, mixed>>> Map of parent_post_id to list of chunk hits.
	 */
	private function batch_fetch_chunks( array $parent_ids ): array {
		$index = Algolia::get_instance()->get_index();
		if ( is_wp_error( $index ) ) {
			return [];
		}

		// Remove duplicates and split into small groups.
		$ids_list = array_values( array_unique( $parent_ids ) );
		$groups   = array_chunk( $ids_list, 20 );

		$collected = [];

		foreach ( $groups as $group_ids ) {
			// Build "parent_post_id:ID1 OR parent_post_id:ID2 ..." filter.
			$parts = [];
			foreach ( $group_ids as $parent_id ) {
				$parts[] = 'parent_post_id:' . $parent_id;
			}
			$filters = implode( ' OR ', $parts );

			try {
				$res = $index->search(
					'',
					[
						'filters'     => $filters,
						'hitsPerPage' => 1000,
						'distinct'    => false,
					]
				);
			} catch ( \Throwable $e ) {
				// Skip this group on error and continue with others.
				continue;
			}

			$hits = isset( $res['hits'] ) ? (array) $res['hits'] : [];
			foreach ( $hits as $hit ) {
				$pid = $hit['parent_post_id'] ?? null;
				if ( ! $pid ) {
					continue;
				}

				$collected[ $pid ][] = $hit;
			}
		}

		return $collected;
	}

	/**
	 * Execute search and return all matching records.
	 *
	 * @param string    $search_query User query string.
	 * @param \WP_Query $wp_query     WP_Query instance.
	 *
	 * @return array|\WP_Error Array of Algolia records or WP_Error.
	 */
	private function get_all_searched_record_ids( $search_query, $wp_query ) {
		$default_params = [
			'attributesToHighlight' => [ 'title', 'content', 'excerpt' ],
			'highlightPreTag'       => '<span class="algolia-highlight">',
			'highlightPostTag'      => '</span>',
			'getRankingInfo'        => true,
			'distinct'              => false,
			'typoTolerance'         => 'min',
			'minWordSizefor1Typo'   => 3,
			'minWordSizefor2Typos'  => 6,
			'ignorePlurals'         => true,
			'removeStopWords'       => true,
			'queryType'             => 'prefixAll',
			'optionalWords'         => [ 'the', 'of', 'guide' ],
		];

		// Restrict by post type when present.
		$post_type = $wp_query->query['post_type'] ?? '';
		if ( ! empty( $post_type ) ) {
			$default_params['filters'] = "type:{$post_type}";
		}

		// Append site_url filters.
		$site_urls = $this->get_searchable_site_urls();
		if ( is_wp_error( $site_urls ) ) {
			return $site_urls;
		}

		$site_url_filters = [];
		foreach ( $site_urls as $site_url ) {
			$escaped_url        = str_replace( ':', '\:', $site_url );
			$site_url_filters[] = "site_url:{$escaped_url}";
		}
		$site_url_filters_str = implode( ' OR ', $site_url_filters );

		$default_params['filters'] = isset( $default_params['filters'] )
			? '(' . $default_params['filters'] . ') AND (' . $site_url_filters_str . ')'
			: $site_url_filters_str;

		/**
		 * Filter Algolia search parameters (facets, filters, etc.).
		 *
		 * @param array $search_params Default search params.
		 * @param \WP_Query $wp_query  Query context.
		 * @param string $search_query Raw search string.
		 */
		$search_params = apply_filters( 'onesearch_algolia_search_params', $default_params, $wp_query, $search_query );

		try {
			$index = Algolia::get_instance()->get_index();
			if ( is_wp_error( $index ) ) {
				return $index;
			}

			return $this->search_index( $index, $search_query, $search_params );
		} catch ( \Throwable $e ) {
			return new \WP_Error(
				'search_failed',
				/* translators: %s: error message */
				sprintf( __( 'Search failed: %s', 'onesearch' ), $e->getMessage() )
			);
		}
	}

	/**
	 * Search the single shared Algolia index.
	 *
	 * @param \Algolia\AlgoliaSearch\SearchIndex $index         The Index to search.
	 * @param string                             $search_query  Query string.
	 * @param array                              $search_params Search parameters.
	 *
	 * @return array List of matching hits.
	 *
	 * @throws \Exception On client errors.
	 */
	private function search_index( $index, $search_query, $search_params ) {
		$response = $index->search( $search_query, $search_params );

		$hits = ! empty( $response['hits'] ) && is_array( $response['hits'] ) ? $response['hits'] : null;

		if ( empty( $hits ) ) {
			return [];
		}

		// Sort hits by Algolia ranking score descending.
		usort(
			$hits,
			function ( $a, $b ) {
				return $this->compute_algolia_score( $b ) <=> $this->compute_algolia_score( $a );
			}
		);

		return $hits;
	}

	/**
	 * Build a comparable score from Algolia _rankingInfo.
	 *
	 * @param array $hit search hit.
	 *
	 * @return float ranking score
	 */
	private function compute_algolia_score( array $hit ): float {
		$r = $hit['_rankingInfo'] ?? [];

		// If Algolia provides rankingScore, prefer it.
		if ( isset( $r['rankingScore'] ) ) {
			return (float) $r['rankingScore'];
		}

		// Otherwise, derive a reasonable composite. Tune weights to your ranking.
		$nb_typos           = (int) ( $r['nbTypos'] ?? 0 );
		$words              = (int) ( $r['words'] ?? 0 );
		$proximity_distance = (int) ( $r['proximityDistance'] ?? 0 );
		$user_score         = (int) ( $r['userScore'] ?? 0 );
		$geo_distance       = (int) ( $r['geoDistance'] ?? 0 );

		// Higher is better. Penalize typos/proximity/geo distance.
		return ( $user_score * 1_000_000 )
		+ ( $words * 1_000 )
		- ( $nb_typos * 10_000 )
		- $proximity_distance
		- ( $geo_distance / 1000.0 );
	}

	/**
	 * Build a post object from all chunks, joining their content.
	 *
	 * @param array<string,mixed> $all_chunks Array of chunk hits for the same post.
	 * @return \WP_Post|null
	 */
	private function build_post_with_all_chunks( array $all_chunks ) {
		if ( empty( $all_chunks ) ) {
			return null;
		}

		// Sort chunks by their index so the content is in order.
		usort(
			$all_chunks,
			static function ( $a, $b ) {
				$a_idx = isset( $a['onesearch_chunk_index'] ) ? (int) $a['onesearch_chunk_index'] : 0;
				$b_idx = isset( $b['onesearch_chunk_index'] ) ? (int) $b['onesearch_chunk_index'] : 0;
				return $a_idx <=> $b_idx;
			}
		);

		// Join chunk contents.
		$joined = '';
		foreach ( $all_chunks as $chunk ) {
			$joined .= (string) ( $chunk['content'] ?? '' ) . ' ';
		}

		$first_chunk            = $all_chunks[0];
		$first_chunk['content'] = trim( $joined );

		return $this->create_post_object( $first_chunk );
	}

	/**
	 * Create a WP_Post (local) or WP_Post-like placeholder (remote) from hit data.
	 *
	 * @param array $post_data Algolia hit.
	 *
	 * @return \WP_Post|null
	 */
	private function create_post_object( $post_data ) {
		$post_id  = (int) ( $post_data['post_id'] ?? 0 );
		$site_url = trailingslashit( $post_data['site_url'] );

		$algolia_highlights = $this->extract_algolia_highlights( $post_data );

		// Remote post: create placeholder object with prefixed ID.
		if ( trailingslashit( get_site_url() ) !== $site_url ) {
			$custom_post                        = new WP_Post( new stdClass() );
			$custom_post->ID                    = -1 - absint( $post_id );
			$custom_post->onesearch_original_id = $post_id;
			$custom_post->post_title            = $post_data['title'];
			$custom_post->post_excerpt          = $post_data['excerpt'];
			$custom_post->post_content          = $post_data['content'];
			$custom_post->guid                  = $post_data['permalink'];
			$custom_post->post_name             = $post_data['name'];
			$custom_post->post_status           = 'publish';
			$custom_post->post_type             = $post_data['type'];
			$custom_post->filter                = 'raw';
			$custom_post->post_date             = $post_data['postDate'];
			$custom_post->post_modified         = $post_data['postDateGmt'];
			$custom_post->post_author           = -1000 - absint( $post_id );
			$custom_post->onesearch_remote_post_author_display_name = $post_data['author_display_name'];
			$custom_post->onesearch_remote_post_author_link         = $post_data['author_posts_url'];
			$custom_post->onesearch_remote_post_author_gravatar     = $post_data['author_avatar'];
			$custom_post->onesearch_remote_taxonomies               = $post_data['taxonomies'] ?? [];
			$custom_post->onesearch_site_url                        = $post_data['site_url'];
			$custom_post->onesearch_site_name                       = $post_data['site_name'];
			$custom_post->onesearch_algolia_highlights              = $algolia_highlights ?? [];
			return apply_filters( 'onesearch_post_custom_data', $custom_post, $post_data );
		}

		$post = get_post( $post_id );

		if ( $post instanceof WP_Post ) {
			if ( $post_data['is_chunked'] ) {
				$post->post_content = $post_data['content'];
			}

			$post->onesearch_site_url           = $post_data['site_url'];
			$post->onesearch_site_name          = $post_data['site_name'];
			$post->onesearch_algolia_highlights = $algolia_highlights;

			return $post;
		}

		return null;
	}

	/**
	 * Extract highlighting data from Algolia response.
	 *
	 * @param array $algolia_hit Algolia search hit.
	 *
	 * @return array Highlighting data.
	 */
	private function extract_algolia_highlights( $algolia_hit ) {
		$highlights = [];

		$highlight_result = $algolia_hit['_highlightResult'] ?? [];

		foreach ( $highlight_result as $field => $highlight_data ) {
			if ( ! isset( $highlight_data['value'] ) ) {
				continue;
			}

			$highlights[ $field ] = $highlight_data['value'];
		}

		$snippet_result = $algolia_hit['_snippetResult'] ?? [];
		foreach ( $snippet_result as $field => $snippet_data ) {
			if ( ! isset( $snippet_data['value'] ) || ! empty( $highlights[ $field ] ) ) {
				continue;
			}

			$highlights[ $field ] = $snippet_data['value'];
		}

		return $highlights;
	}

	/**
	 * Group Algolia hits by the unique parent_post_id.
	 *
	 * @param array $hits Search results hits.
	 *
	 * @return array<string, array> Grouped hits by parent_post_id.
	 */
	private function group_hits_by_post( array $hits ): array {
		$grouped = [];

		foreach ( $hits as $hit ) {
			if ( ! isset( $hit['parent_post_id'] ) ) {
				continue;
			}

			$grouped[ (string) $hit['parent_post_id'] ][] = $hit;
		}

		return $grouped;
	}

	/**
	 * Compute current page's group keys for pagination.
	 *
	 * @param array     $grouped The group of search hits.
	 * @param \WP_Query $query   WP_Query.
	 *
	 * @return array
	 */
	private function get_paged_group_keys( array $grouped, \WP_Query $query ): array {
		$total_groups = count( $grouped );

		// Determine current page number.
		$paged = (int) $query->get( 'paged' );
		if ( $paged < 1 ) {
			$paged = (int) $query->get( 'page' );
			if ( $paged < 1 ) {
				$paged = 1;
			}
		}

		// Determine page size.
		$ppp = (int) $query->get( 'posts_per_page' );
		if ( ! $ppp ) {
			$ppp = (int) get_option( 'posts_per_page', 10 );
		}

		if ( $ppp < 1 ) {
			return [ null, $total_groups ];
		}

		// Slice group keys to the current page.
		$offset    = ( $paged - 1 ) * $ppp;
		$keys      = array_keys( $grouped );
		$page_keys = array_slice( $keys, $offset, $ppp );

		return [ $page_keys, $total_groups ];
	}

	/**
	 * Choose the representative hit per grouped post for the requested page slice.
	 *
	 * @param array $grouped   The group of search hits.
	 * @param array $page_keys Keys selected for the current page.
	 *
	 * @return array Array of hits (one per post on the page)
	 */
	private function pick_representative_hits( array $grouped, array $page_keys ): array {
		$all_hits = [];

		foreach ( $page_keys as $key ) {
			$all_hits[] = $grouped[ $key ][0];
		}

		return $all_hits;
	}

	/**
	 * Return the list of searchable sites for the current site.
	 *
	 * This is used to determine which sites to include in search queries.
	 *
	 * Behavior:
	 * - On governing site: reads local selection for current site URL.
	 * - On brand site: intersects local selection with server-provided availability.
	 *
	 * @return array|\WP_Error Array of site URLs or WP_Error on failure.
	 */
	private function get_searchable_site_urls() {
		$site_type = (string) get_option( 'onesearch_site_type', '' );

		// Parent: use local data.
		if ( 'governing-site' === $site_type ) {
			$search_config  = get_option( 'onesearch_sites_search_settings', [] );
			$selected_sites = $search_config[ trailingslashit( get_site_url() ) ] ?? [];
			return $selected_sites['searchable_sites'] ?? [];
		}

		// Brand: intersect local selection with governing-available sites.
		$available_sites = Governing_Data::get_searchable_sites();

		if ( empty( $available_sites ) ) {
			return [];
		}

		$selected_sites = Governing_Data::get_search_settings();
		$selected_sites = $selected_sites['searchable_sites'] ?? [];

		if ( empty( $selected_sites ) ) {
			return [];
		}

		return array_intersect( $selected_sites, $available_sites );
	}
}
