<?php
/**
 * Cache class.
 *
 * @package onesearch
 */

namespace Onesearch\Inc;

/**
 * Class Cache
 */
class Cache {

	/**
	 * Error code.
	 *
	 * @var string
	 */
	public const ERROR_CODE = 'onesearch_cache';

	/**
	 * Cache group name.
	 *
	 * @var string
	 */
	protected static $cache_group = 'onesearch_cache_v1';

	/**
	 * Cache key name.
	 *
	 * @var string
	 */
	protected $key;

	/**
	 * Cache expiry time in seconds.
	 * 15 minutes, default expiry.
	 *
	 * @var int
	 */
	protected $expiry = 900;

	/**
	 * Callable callback function.
	 *
	 * @var string|array
	 */
	protected $callback;

	/**
	 * List of param that need to pass in callback function.
	 *
	 * @var array
	 */
	protected $params = [];

	/**
	 * Constructor method.
	 *
	 * @param string $cache_key   Cache key.
	 * @param string $cache_group Cache group.
	 *
	 * @return void|\WP_Error
	 */
	public function __construct( $cache_key = '', $cache_group = '' ) {

		if ( empty( $cache_key ) || ! is_string( $cache_key ) ) {
			return new \WP_Error( self::ERROR_CODE, esc_html__( 'Cache key is required to create cache object', 'onesearch' ) );
		}

		$this->key = md5( $cache_key );

		if ( ! empty( $cache_group ) && is_string( $cache_group ) ) {
			self::$cache_group = $cache_group;
		}

		// Call init.
		$this->init();
	}

	/**
	 * Init Method.
	 *
	 * @return void
	 */
	protected function init() {

		/**
		 * NOTE!!!
		 * Be very careful filtering this cache_group value!!
		 * Check the passed $_key value to ensure you're only
		 * filtering the group for your cache instance or similar.
		 */
		$cache_group = apply_filters( 'onesearch_cache_group_override', self::$cache_group, $this->key );

		if ( ! empty( $cache_group ) && is_string( $cache_group ) ) {
			self::$cache_group = $cache_group;
		}

		unset( $cache_group );
	}

	/**
	 * This function is for deleting the cache
	 *
	 * @return $this Current instance.
	 */
	public function invalidate() {

		wp_cache_delete( $this->key, self::$cache_group );

		return $this;
	}

	/**
	 * This function is used to invalidate the cache when passed hooks are executed.
	 *
	 * Usage:
	 * $actions_and_filters = [
	 *      'action' => [ 'hook_1', 'hook_2' ],
	 *      'filter' => [ 'hook_1', 'hook_2' ]
	 * ];
	 * $instance->invalidate_on( $actions_and_filters );
	 *
	 * @param array $actions_and_filters Hooks to disable caching.
	 *
	 * @return $this Current instance.
	 */
	public function invalidate_on( $actions_and_filters = [] ) {

		// If no actions or filters are passed, return.
		if ( empty( $actions_and_filters ) || ( empty( $actions_and_filters['action'] ) && empty( $actions_and_filters['filter'] ) ) ) {
			return $this;
		}

		// Set cache key and group.
		$cache_key   = $this->key;
		$cache_group = self::$cache_group;

		// Add actions.
		if ( ! empty( $actions_and_filters['action'] ) ) {
			foreach ( $actions_and_filters['action'] as $action_hook ) {
				if ( ! is_string( $action_hook ) || empty( $action_hook ) ) {
					continue;
				}

				add_action(
					$action_hook,
					static function () use ( $cache_key, $cache_group ) {
						wp_cache_delete( $cache_key, $cache_group );
					},
					1
				);
			}
		}

		// Add filters.
		if ( ! empty( $actions_and_filters['filter'] ) ) {
			foreach ( $actions_and_filters['filter'] as $filter_hook ) {
				if ( ! is_string( $filter_hook ) || empty( $filter_hook ) ) {
					continue;
				}

				add_filter(
					$filter_hook,
					static function ( $params ) use ( $cache_key, $cache_group ) {
						wp_cache_delete( $cache_key, $cache_group );
						return $params;
					},
					1
				);
			}
		}

		return $this;
	}

	/**
	 * This function accepts the cache expiry.
	 *
	 * @param int $expiry Expiry time in seconds.
	 *
	 * @return $this Current instance.
	 */
	public function expires_in( $expiry ) {

		$expiry = intval( $expiry );

		if ( $expiry > 0 ) {
			$this->expiry = $expiry;
		}

		unset( $expiry );

		return $this;
	}

	/**
	 * Accepts the callback from which data is to be received
	 *
	 * @param string|array $callback Callable function.
	 * @param array        $params   Params that needed for callable function.
	 *
	 * @return $this|\WP_Error Current instance on success.
	 */
	public function updates_with( $callback, $params = [] ) {

		if ( empty( $callback ) || ! is_callable( $callback ) ) {
			return new \WP_Error( self::ERROR_CODE, esc_html__( 'Callback passed is not callable', 'onesearch' ) );
		}

		if ( ! is_array( $params ) ) {
			return new \WP_Error( self::ERROR_CODE, esc_html__( 'All parameters for the callback must be in an array', 'onesearch' ) );
		}

		$this->callback = $callback;
		$this->params   = $params;

		return $this;
	}

	/**
	 * This function returns the data from cache if it exists or returns the
	 * data it gets back from the callback and caches it as well.
	 *
	 * @return mixed
	 */
	public function get() {

		$data = wp_cache_get( $this->key, self::$cache_group );

		if ( ! empty( $data ) ) {
			if ( 'empty' === $data ) {
				return false;
			}

			return $data;
		}

		/**
		 * If we don't have a callback to get data from or if its not a valid
		 * callback then return error. This will happen in the case when
		 * updates_with() is not called before get()
		 */
		if ( empty( $this->callback ) || ! is_callable( $this->callback ) ) {
			return new \WP_Error( self::ERROR_CODE, esc_html__( 'No valid callback set', 'onesearch' ) );
		}

		try {
			$data = call_user_func_array( $this->callback, $this->params );

			if ( empty( $data ) ) {
				$data = 'empty';
			}

			wp_cache_set( $this->key, $data, self::$cache_group, $this->expiry ); // phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined

			if ( 'empty' === $data ) {
				return false;
			}
		} catch ( \Throwable $e ) {
			$data = false;
		}

		return $data;
	}
}
