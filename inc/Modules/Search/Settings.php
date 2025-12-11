<?php
/**
 * Registers the plugin's search settings.
 *
 * @package OneSearch\Modules\Search
 */

declare(strict_types = 1);

namespace OneSearch\Modules\Search;

use OneSearch\Contracts\Interfaces\Registrable;
use OneSearch\Encryptor;
use OneSearch\Inc\Algolia\Algolia;
use OneSearch\Modules\Settings\Settings as Admin_Settings;
use OneSearch\Utils;

/**
 * Class - Settings
 */
final class Settings implements Registrable {
	/**
	 * The setting prefix.
	 */
	private const SETTING_PREFIX = 'onesearch_';

	/**
	 * The setting group.
	 */
	public const SETTING_GROUP = self::SETTING_PREFIX . 'settings';

	/**
	 * Setting keys
	 */
	// Governing settings.
	public const OPTION_GOVERNING_ALGOLIA_CREDENTIALS = self::SETTING_PREFIX . 'algolia_credentials';
	public const OPTION_GOVERNING_INDEXABLE_SITES     = self::SETTING_PREFIX . 'indexable_entities';
	public const OPTION_GOVERNING_SEARCH_SETTINGS     = self::SETTING_PREFIX . 'sites_search_settings';

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'rest_api_init', [ $this, 'register_settings' ] );

		// Listen to updates.
		add_action( 'update_option_' . Admin_Settings::OPTION_SITE_TYPE, [ $this, 'on_site_type_change' ], 10, 2 );
		add_action( 'update_option_' . Admin_Settings::OPTION_GOVERNING_SHARED_SITES, [ $this, 'on_shared_sites_change' ], 10, 2 );

		// Before getting algolia credentials decrypt them.
		add_filter( 'rest_pre_get_setting', [ self::class, 'pre_get_algolia_credentials' ], 10, 3 );
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings(): void {

		$governing_settings = [
			self::OPTION_GOVERNING_ALGOLIA_CREDENTIALS => [
				'type'              => 'object',
				'label'             => __( 'Algolia Credentials', 'onesearch' ),
				'description'       => __( 'Credentials used to connect to the Algolia service.', 'onesearch' ),
				'sanitize_callback' => static function ( $value ) {
					if ( ! is_array( $value ) ) {
						return null;
					}
					// @todo add check if algolia creds are valid or not.
					return [
						'app_id'    => isset( $value['app_id'] ) ? sanitize_text_field( $value['app_id'] ) : null,
						'write_key' => isset( $value['write_key'] ) ? Encryptor::encrypt( sanitize_text_field( $value['write_key'] ) ) : null,
						'admin_key' => isset( $value['admin_key'] ) ? Encryptor::encrypt( sanitize_text_field( $value['admin_key'] ) ) : null,
					];
				},
				'show_in_rest'      => [
					'schema' => [
						'type'       => 'object',
						'properties' => [
							'app_id'    => [
								'type' => 'string',
							],
							'write_key' => [
								'type' => 'string',
							],
							'admin_key' => [
								'type' => 'string',
							],
						],
					],
				],
			],
			self::OPTION_GOVERNING_INDEXABLE_SITES     => [
				// It's an object with a string key and string[] values.
				'type'              => 'object',
				'label'             => __( 'Indexable Entities', 'onesearch' ),
				'description'       => __( 'List of content types that can be indexed by brand sites.', 'onesearch' ),
				'sanitize_callback' => static function ( $value ) {
					if ( ! is_array( $value ) ) {
						return [];
					}

					// @todo what is this array shape?
					return $value;
				},
				'show_in_rest'      => true,
			],
			self::OPTION_GOVERNING_SEARCH_SETTINGS     => [
				'type'              => 'object',
				'label'             => __( 'Sites Search Settings', 'onesearch' ),
				'description'       => __( 'Search settings for brand sites.', 'onesearch' ),
				'sanitize_callback' => static function ( $value ) {
					if ( ! is_array( $value ) ) {
						return [];
					}

					$sanitized = [];
					foreach ( $value as $site_url => $settings ) {
						$normalized_url = Utils::normalize_url( $site_url );
						if ( ! is_array( $settings ) ) {
							$sanitized[ $normalized_url ] = [
								'algolia_enabled'  => false,
								'searchable_sites' => [],
							];
							continue;
						}

						$sanitized[ $normalized_url ] = [
							'algolia_enabled'  => isset( $settings['algolia_enabled'] ) ? (bool) $settings['algolia_enabled'] : false,
							'searchable_sites' => isset( $settings['searchable_sites'] ) && is_array( $settings['searchable_sites'] ) ? array_map( 'sanitize_text_field', $settings['searchable_sites'] ) : [],
						];
					}

					return $sanitized;
				},
				'show_in_rest'      => true,
			],
		];

		foreach ( $governing_settings as $key => $args ) {
			register_setting(
				self::SETTING_GROUP,
				$key,
				$args
			);
		}
	}

	/**
	 * Deletes Algolia index when site type is changed to consumer.
	 *
	 * @param mixed $old_value The old value.
	 * @param mixed $new_value The new value.
	 */
	public function on_site_type_change( $old_value, $new_value ): void {
		if ( Admin_Settings::SITE_TYPE_CONSUMER !== $new_value ) {
			return;
		}

		try {
			$index = Algolia::instance()->get_index();

			if ( is_wp_error( $index ) ) {
				return;
			}

			$index->delete()->wait();
		} catch ( \Throwable $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- We need visibility.
			error_log( 'Algolia Exception: ' . $e->getMessage() );
			return;
		}
	}

	/**
	 * Deletes algolia entries when a site is removed from the list of shared sites.
	 *
	 * @param mixed $old_value The old value.
	 * @param mixed $new_value The new value.
	 */
	public function on_shared_sites_change( $old_value, $new_value ): void {
		// If there is no old value, nothing to do.
		if ( ! is_array( $old_value ) || empty( $old_value ) ) {
			return;
		}

		$old_site_urls = array_map(
			static function ( $site ) {
				return ! empty( $site['url'] ) ? Utils::normalize_url( $site['url'] ) : null;
			},
			$old_value
		);
		$new_site_urls = is_array( $new_value ) ? array_map(
			static function ( $site ) {
				return ! empty( $site['url'] ) ? Utils::normalize_url( $site['url'] ) : null;
			},
			$new_value
		) : [];

		$removed_sites = array_filter( array_diff( $old_site_urls, $new_site_urls ) );
		if ( empty( $removed_sites ) ) {
			return;
		}

		try {
			$index = Algolia::instance()->get_index();

			// Bubble up if there was an error.
			if ( is_wp_error( $index ) ) {
				return;
			}

			$filters = implode(
				' OR ',
				array_map(
					static function ( $site_url ) {
						return sprintf( 'site_url:"%s"', $site_url );
					},
					$removed_sites
				)
			);

			$index->deleteBy(
				// OR filters should be wrapped in quotes.
				// @see: https://www.algolia.com/doc/rest-api/search/delete-by#body-filters .
				[ 'filters' => "\"$filters\"" ]
			)->wait();
		} catch ( \Throwable $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- We need visibility.
			error_log( 'Algolia Exception: ' . $e->getMessage() );
			return;
		}

		// Then remove from indexable entities.
		$indexable_entities = self::get_indexable_entities();
		$entities_map       = isset( $indexable_entities['entities'] ) && is_array( $indexable_entities['entities'] ) ? $indexable_entities['entities'] : [];

		$updated_map = [];
		foreach ( $entities_map as $entity => $site_urls ) {
			$updated_site_urls = array_diff( $site_urls, $removed_sites );
			if ( empty( $updated_site_urls ) ) {
				continue;
			}

			$updated_map[ $entity ] = array_values( $updated_site_urls );
		}

		if ( $updated_map === $entities_map ) {
			return;
		}

		$indexable_entities['entities'] = $updated_map;
		update_option( self::OPTION_GOVERNING_INDEXABLE_SITES, $indexable_entities );
	}

	/**
	 * Get algolia credentials.
	 *
	 * @return array{
	 *   app_id: ?string,
	 *   write_key: ?string,
	 *   admin_key: ?string
	 * }
	 */
	public static function get_algolia_credentials(): array {
		$creds = get_option( self::OPTION_GOVERNING_ALGOLIA_CREDENTIALS, [] );

		// @todo we are only taking 2 things from user so where does admin_key come from?
		return [
			'app_id'    => $creds['app_id'] ?: null,
			'write_key' => Encryptor::decrypt( $creds['write_key'] ?? null ) ?: null,
			'admin_key' => Encryptor::decrypt( $creds['admin_key'] ?? null ) ?: null,
		];
	}

	/**
	 * Sets the algolia credentials
	 *
	 * @param array<string,mixed> $value The credentials.
	 * @phpstan-param array{
	 *   app_id: string,
	 *   write_key: string,
	 *   admin_key: string
	 * } $value
	 */
	public static function set_algolia_credentials( $value ): bool {
		if ( ! is_array( $value ) ) {
			return false;
		}

		$write_key = isset( $value['write_key'] ) ? sanitize_text_field( $value['write_key'] ) : null;
		$write_key = ! empty( $write_key ) ? Encryptor::encrypt( $write_key ) : null;

		$admin_key = isset( $value['admin_key'] ) ? sanitize_text_field( $value['admin_key'] ) : null;
		$admin_key = ! empty( $admin_key ) ? Encryptor::encrypt( $admin_key ) : null;

		$sanitized = [
			'app_id'    => isset( $value['app_id'] ) ? sanitize_text_field( $value['app_id'] ) : null,
			'write_key' => $write_key ?: null,
			'admin_key' => $admin_key ?: null,
		];

		return update_option( self::OPTION_GOVERNING_ALGOLIA_CREDENTIALS, $sanitized );
	}

	/**
	 * Get the indexable entities.
	 *
	 * @return array<string, mixed> The indexable entities.
	 */
	public static function get_indexable_entities(): array {
		$value = get_option( self::OPTION_GOVERNING_INDEXABLE_SITES, [] );
		return is_array( $value ) ? $value : [];
	}

	/**
	 * Get search settings for all sites.
	 *
	 * @return array<string, array{
	 *   algolia_enabled: bool,
	 *   searchable_sites: string[]
	 * }>
	 */
	public static function get_search_settings(): array {
		$value = get_option( self::OPTION_GOVERNING_SEARCH_SETTINGS, [] );
		return is_array( $value ) ? $value : [];
	}

	/**
	 * Decrypt algolia credentials before sending to REST.
	 *
	 * @param mixed  $result  The current value.
	 * @param string $name    The setting name.
	 * @param mixed  $args    $args The default value to return if the option does not exist.
	 *
	 * @return mixed
	 */
	public static function pre_get_algolia_credentials( $result, $name, $args ): mixed {
		if ( self::OPTION_GOVERNING_ALGOLIA_CREDENTIALS !== $name ) {
			return $result;
		}

		$value = get_option( $name, $args );

		if ( ! is_array( $value ) ) {
			return [
				'app_id'    => '',
				'write_key' => '',
				'admin_key' => '',
			];
		}

		// Decrypt before sending to frontend.
		return [
			'app_id'    => $value['app_id'] ?? '',
			'write_key' => ! empty( $value['write_key'] )
				? Encryptor::decrypt( $value['write_key'] )
				: '',
			'admin_key' => ! empty( $value['admin_key'] )
				? Encryptor::decrypt( $value['admin_key'] )
				: '',
		];
	}
}
