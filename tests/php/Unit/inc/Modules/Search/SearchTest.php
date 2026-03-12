<?php
/**
 * Test the Search module.
 *
 * @package OneSearch\Tests\Unit\inc\Modules\Search
 */

declare( strict_types = 1 );

namespace OneSearch\Tests\Unit\inc\Modules\Search;

use OneSearch\Modules\Search\Search;
use OneSearch\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Class - SearchTest
 */
#[CoversClass( Search::class )]
class SearchTest extends TestCase {
	/**
	 * Test the get_algolia_results fallback.
	 */
	public function test_get_algolia_results(): void {
		$search = new Search();

		$posts = [ 'existing_post' ];

		// If query is not main query or not search, it returns original posts.
		$query                = new \WP_Query();
		$query->is_main_query = false;
		$query->is_search     = false;

		$this->assertSame( $posts, $search->get_algolia_results( $posts, $query ) );

		$query->is_main_query = true;
		$query->is_search     = true;

		// If search term is empty, shouldn't alter.
		$query->set( 's', '' );
		$this->assertSame( $posts, $search->get_algolia_results( $posts, $query ) );
	}
}
