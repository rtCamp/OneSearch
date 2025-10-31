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
			$search_config = get_option( 'onesearch_sites_search_settings', [] );
			$search_config = $search_config[ trailingslashit( get_site_url() ) ] ?? [];
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

		// Group all hits by (site_url|parent_post_id).
		$grouped = $this->onesearch_group_hits_by_post( $hits );

		// Set total for pagination.
		[ $page_keys, $total_groups ] = $this->onesearch_paged_group_keys( $grouped, $query );
		$query->found_posts           = $total_groups;

		$keys_to_build = null === $page_keys ? array_keys( $grouped ) : $page_keys;

		$hits_for_page = $this->onesearch_pick_representative_hits( $grouped, $keys_to_build );

		$reconstruct = apply_filters( 'onesearch_reconstruct_chunked_on_search', true );

		$searched_posts = $this->build_posts_from_grouped_hits( $hits_for_page, $reconstruct );

		$query->post_count        = count( $searched_posts );
		$query->is_algolia_search = true;

		return $searched_posts;
	}

	/**
	 * Build posts for the current page using batch chunk fetches per site.
	 *
	 * @param array $hits_for_page One representative hit per grouped post.
	 * @param bool  $reconstruct   Whether to reconstruct chunked posts (true = fetch chunks).
	 * @return array               List of WP_Post / WP_Post-like objects.
	 */
	private function build_posts_from_grouped_hits( array $hits_for_page, bool $reconstruct = true ): array {
		$ids_by_site = [];

		// Collect all parent IDs we need to fetch.
		if ( $reconstruct ) {
			foreach ( $hits_for_page as $hit ) {
				$is_chunked = ! empty( $hit['is_chunked'] );
				$site_url   = isset( $hit['site_url'] ) ? (string) $hit['site_url'] : '';
				$parent_id  = isset( $hit['parent_post_id'] ) ? (int) $hit['parent_post_id'] : 0;

				if ( ! $is_chunked || ! $site_url || ! $parent_id ) {
					continue;
				}

				$ids_by_site[ $site_url ][] = $parent_id;
			}
		}

		// Fetch chunks in batches per site.
		$chunks_by_site = $reconstruct ? $this->batch_fetch_chunks_by_site( $ids_by_site ) : [];

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

			$site_url  = isset( $hit['site_url'] ) ? (string) $hit['site_url'] : '';
			$parent_id = isset( $hit['parent_post_id'] ) ? (int) $hit['parent_post_id'] : 0;

			$all_chunks = isset( $chunks_by_site[ $site_url ][ $parent_id ] )
				? $chunks_by_site[ $site_url ][ $parent_id ]
				: [];

			// If we did not get any chunks, fall back to the single hit.
			if ( empty( $all_chunks ) ) {
				$post_obj = $this->create_post_object( $hit );
				if ( $post_obj ) {
					$posts[] = $post_obj;
				}
				continue;
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

			$post_obj = $this->create_post_object( $first_chunk );
			if ( ! $post_obj ) {
				continue;
			}

			$posts[] = $post_obj;
		}

		return $posts;
	}

	/**
	 * Fetch chunks for many posts with one request per site.
	 *
	 * @param array $parent_ids_by_site [ site_url => [parent_post_id, ...], ... ].
	 * @return array                    [ site_url => [ parent_post_id => [hits...], ... ], ... ].
	 */
	private function batch_fetch_chunks_by_site( array $parent_ids_by_site ): array {
		$result_by_site = [];

		foreach ( $parent_ids_by_site as $site_url => $parent_ids ) {
			$index = $this->get_index_for_site( $site_url );

			if ( is_wp_error( $index ) ) {
				$result_by_site[ $site_url ] = [];
				continue;
			}

			// Remove duplicates and split into small groups.
			$ids_list = array_values( array_unique( array_map( 'intval', $parent_ids ) ) );
			$groups   = array_chunk( $ids_list, 20 );

			$collected = [];

			foreach ( $groups as $group_ids ) {
				// Build "parent_post_id:ID1 OR parent_post_id:ID2 ..." filter.
				$parts = [];
				foreach ( $group_ids as $id ) {
					$parts[] = 'parent_post_id:' . $id;
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
					$pid = isset( $hit['parent_post_id'] ) ? (int) $hit['parent_post_id'] : 0;
					if ( ! $pid ) {
						continue;
					}

					$collected[ $pid ][] = $hit;
				}
			}

			$result_by_site[ $site_url ] = $collected;
		}

		return $result_by_site;
	}

	/**
	 * Execute multi-index search and return all matching records.
	 *
	 * @param string    $search_query User query string.
	 * @param \WP_Query $wp_query     WP_Query instance.
	 *
	 * @return array|\WP_Error Array of Algolia records or WP_Error.
	 */
	private function get_all_searched_record_ids( $search_query, $wp_query ) {

		$index = Algolia::get_instance()->get_index();
		if ( is_wp_error( $index ) ) {
			return $index;
		}

		$searchable_indices = Algolia::get_instance()->get_child_sites_indices();
		if ( is_wp_error( $searchable_indices ) ) {
			return $searchable_indices;
		}

		if ( empty( $searchable_indices ) ) {
			return new \WP_Error( 'no_searchable_indices', __( 'No searchable indices found.', 'onesearch' ) );
		}

		$default_params = [
			'attributesToHighlight' => [ 'title', 'content', 'excerpt' ],
			'highlightPreTag'       => '<span class="algolia-highlight">',
			'highlightPostTag'      => '</span>',
			'getRankingInfo'        => true,
			'distinct'              => false,
		];

		$dynamic_filters = $this->build_dynamic_filters( $wp_query );

		/**
		 * Filter Algolia search parameters (facets, filters, etc.).
		 *
		 * @param array $search_params Default search params.
		 * @param \WP_Query $wp_query  Query context.
		 * @param string $search_query Raw search string.
		 */
		$search_params = apply_filters( 'onesearch_algolia_search_params', array_merge( $default_params, $dynamic_filters ), $wp_query, $search_query );

		try {
			return $this->search_multiple_indices( $searchable_indices, $search_query, $search_params );
		} catch ( \Throwable $e ) {
			return new \WP_Error(
				'multi_search_failed',
				/* translators: %s: error message */
				sprintf( __( 'Multi-index search failed: %s', 'onesearch' ), $e->getMessage() )
			);
		}
	}

	/**
	 *  Build Algolia query-time filters from WP_Query using your record fields
	 *
	 * @param \WP_Query $wp_query query object.
	 *
	 * @return array
	 */
	private function build_dynamic_filters( \WP_Query $wp_query ): array {
		$facet_filters   = [];
		$numeric_filters = [];
		$filters_and     = []; // string-based fallbacks for non-faceted, simple equals.

		$qv = $wp_query->query_vars;

		// 1) Post type => facet: type
		if ( ! empty( $qv['post_type'] ) ) {
			$pt              = is_array( $qv['post_type'] ) ? $qv['post_type'] : [ $qv['post_type'] ];
			$facet_filters[] = array_map(
				static fn( $v ) => 'type:' . sanitize_text_field( $v ),
				$pt
			);
		}

		// 2) Author => facet: author_ID (or author_login)
		if ( ! empty( $qv['author__in'] ) && is_array( $qv['author__in'] ) ) {
			$facet_filters[] = array_map(
				static fn( $id ) => 'author_ID:' . intval( $id ),
				$qv['author__in']
			);
		} elseif ( ! empty( $qv['author'] ) ) {
			$facet_filters[] = 'author_ID:' . intval( $qv['author'] );
		}

		// 3) Category / taxonomy => facets: taxonomies.slug (+ optionally taxonomies.taxonomy)
		// category_name (slug)
		if ( ! empty( $qv['category_name'] ) ) {
			$slugs           = array_map( 'sanitize_title', explode( ',', $qv['category_name'] ) );
			$facet_filters[] = array_map(
				static fn( $slug ) => 'taxonomies.slug:' . $slug,
				$slugs
			);
		}

		// category__in (IDs) -> need slugs; if you don’t have slugs, map IDs→slugs first.
		if ( ! empty( $qv['category__in'] ) && is_array( $qv['category__in'] ) ) {
			$slugs = array_filter(
				array_map(
					static function ( $term_id ) {
						$term = get_term( intval( $term_id ), 'category' );
						return $term && ! is_wp_error( $term ) ? sanitize_title( $term->slug ) : null;
					},
					$qv['category__in']
				)
			);
			if ( $slugs ) {
				$facet_filters[] = array_map(
					static fn( $slug ) => 'taxonomies.slug:' . $slug,
					$slugs
				);
			}
		}

		// Generic tax_query (supports AND/OR groups).
		if ( ! empty( $qv['tax_query'] ) && is_array( $qv['tax_query'] ) ) {
			$txq = new \WP_Tax_Query( $qv['tax_query'] );
			// Flatten parsed_query for AND/OR logic.
			$parsed = $txq->queries;
			foreach ( $parsed as $clause ) {
				if ( ! isset( $clause['taxonomy'], $clause['terms'] ) ) {
					continue;
				}

				$terms       = (array) $clause['terms'];
				$operator    = strtoupper( $clause['operator'] ?? 'IN' );
				$relation_or = in_array( $operator, [ 'IN', 'OR' ], true );

				$group = [];

				foreach ( $terms as $term ) {
					$slug = null;

					if ( is_numeric( $term ) ) {
						$t = get_term( $term, $clause['taxonomy'] );
						if ( $t && ! is_wp_error( $t ) ) {
							$slug = $t->slug;
						}
					} else {
						$slug = sanitize_title( $term );
					}

					if ( ! $slug ) {
						continue;
					}

					$group[] = 'taxonomies.slug:' . $slug;
				}

				if ( ! $group ) {
					continue;
				}

				// OR within group, AND across groups.
				$facet_filters[] = $relation_or ? $group : $group; // Algolia facet_filters already ANDs groups; OR is within an array.
			}
		}

		$params = [];

		if ( $facet_filters ) {
			$params['facet_filters'] = $facet_filters;
		}
		if ( $numeric_filters ) {
			$params['numeric_filters'] = $numeric_filters;
		}
		if ( $filters_and ) {
			// AND-join simple string filters; keep this minimal since facet_filters is preferred.
			$params['filters'] = implode( ' AND ', $filters_and );
		}

		// Distinct across chunks so a post appears once in result lists.
		// Requires index setting "attributeForDistinct": "parent_post_id".
		$params['distinct'] = true;

		return $params;
	}

	/**
	 * Run a single multi-index API request and combine all hits.
	 *
	 * @param array  $searchable_indices Array of Algolia index instances.
	 * @param string $search_query       Query string.
	 * @param array  $search_params      Search parameters.
	 *
	 * @return array Combined list of hits across indices.
	 *
	 * @throws \Exception On client errors.
	 */
	private function search_multiple_indices( $searchable_indices, $search_query, $search_params ) {
		$client = Algolia::get_instance()->get_client();
		if ( is_wp_error( $client ) ) {
				throw new \Exception( esc_html__( 'Failed to get Algolia client.', 'onesearch' ) );
		}

		$queries = [];
		foreach ( $searchable_indices as $index ) {
			$queries[] = array_merge(
				$search_params,
				[
					'indexName' => $index->getIndexName(),
					'query'     => $search_query,
				]
			);
		}

		$response = $client->multipleQueries( $queries );

		$all_results = [];
		foreach ( $response['results'] as $index_result ) {
			$hits        = $index_result['hits'] ?? [];
			$all_results = array_merge( $all_results, $hits );
		}

		// TODO: Denormalize into a single index
		// Re-rank across indices using Algolia ranking info.
		usort(
			$all_results,
			function ( $a, $b ) {
					return $this->compute_algolia_score( $b ) <=> $this->compute_algolia_score( $a );
			}
		);

		return $all_results;
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
		$nb_typos           = (int) ( $r['nb_typos'] ?? 0 );
		$words              = (int) ( $r['words'] ?? 0 );
		$proximity_distance = (int) ( $r['proximity_distance'] ?? 0 );
		$user_score         = (int) ( $r['user_score'] ?? 0 );
		$geo_distance       = (int) ( $r['geo_distance'] ?? 0 );

		// Higher is better. Penalize typos/proximity/geo distance.
		return ( $user_score * 1_000_000 )
		+ ( $words * 1_000 )
		- ( $nb_typos * 10_000 )
		- $proximity_distance
		- ( $geo_distance / 1000.0 );
	}

	/**
	 * Create a WP_Post (local) or WP_Post-like placeholder (remote) from hit data.
	 *
	 * @param array $post_data Algolia hit.
	 *
	 * @return \WP_Post|null
	 */
	private function create_post_object( $post_data ) {
		$post_id  = $post_data['parent_post_id'];
		$site_url = trailingslashit( $post_data['site_url'] );

		$algolia_highlights = $this->extract_algolia_highlights( $post_data );

		// Local post: load from DB, optionally override content if chunked.
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
	 * Resolve the correct Algolia index for a given site URL.
	 *
	 * @param string $site_url Site URL.
	 *
	 * @return mixed \Algolia\AlgoliaSearch\Index|\WP_Error
	 */
	private function get_index_for_site( $site_url ) {
		if ( trailingslashit( get_site_url() ) === trailingslashit( $site_url ) ) {
			return Algolia::get_instance()->get_index();
		}

		$client = Algolia::get_instance()->get_client();
		if ( is_wp_error( $client ) ) {
			return $client;
		}

		$index_name = Algolia::get_instance()->get_algolia_index_name_from_url( $site_url );
		return $client->initIndex( $index_name );
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
	 * Group Algolia hits by (site_url|parent_post_id).
	 *
	 * @param array $hits Search results hits.
	 *
	 * @return array [ compositeKey => [ hits... ], ... ]
	 */
	private function onesearch_group_hits_by_post( array $hits ): array {
		$grouped = [];

		foreach ( $hits as $hit ) {
			$key               = ( $hit['site_url'] ?? '' ) . '|' . ( $hit['parent_post_id'] ?? '' );
			$grouped[ $key ][] = $hit;
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
	private function onesearch_paged_group_keys( array $grouped, \WP_Query $query ): array {
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
	private function onesearch_pick_representative_hits( array $grouped, array $page_keys ): array {
		$all_hits = [];

		foreach ( $page_keys as $key ) {
			$all_hits[] = $grouped[ $key ][0];
		}

		return $all_hits;
	}
}
