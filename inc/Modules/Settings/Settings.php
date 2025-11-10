<?php
/**
 * Registers the plugin's settings and options
 *
 * @package Onesearch
 */

declare(strict_types = 1);

namespace Onesearch\Modules\Settings;

use Onesearch\Contracts\Interfaces\Registrable;
use Onesearch\Utils;

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
	// Shared settings.
	public const OPTION_SITE_TYPE = self::SETTING_PREFIX . 'site_type';
	// Consumer settings.
	public const OPTION_CONSUMER_API_KEY         = self::SETTING_PREFIX . 'consumer_api_key';
	public const OPTION_CONSUMER_PARENT_SITE_URL = self::SETTING_PREFIX . 'parent_site_url';
	// Governing settings.
	public const OPTION_GOVERNING_ALGOLIA_CREDENTIALS = self::SETTING_PREFIX . 'algolia_credentials';
	public const OPTION_GOVERNING_INDEXABLE_SITES     = self::SETTING_PREFIX . 'indexable_entities';
	public const OPTION_GOVERNING_SHARED_SITES        = self::SETTING_PREFIX . 'shared_sites';
	public const OPTION_GOVERNING_SEARCH_SETTINGS     = self::SETTING_PREFIX . 'sites_search_settings';

	/**
	 * Site type keys.
	 */
	public const SITE_TYPE_CONSUMER  = 'consumer';
	public const SITE_TYPE_GOVERNING = 'governing';

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'rest_api_init', [ $this, 'register_settings' ] );

		// Listen to updates.
		add_action( 'update_option_' . self::OPTION_SITE_TYPE, [ $this, 'on_site_type_change' ], 10, 2 );
		add_action( 'update_option_' . self::OPTION_GOVERNING_SHARED_SITES, [ $this, 'on_brand_sites_update' ], 10, 2 );
		add_action( 'onesearch_url_changes', [ $this, 'migrate_sites_on_url_changes' ] );
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings(): void {
		$shared_settings = [
			self::OPTION_SITE_TYPE => [
				'type'              => 'string',
				'label'             => __( 'Site Type', 'onesearch' ),
				'description'       => __( 'Defines whether this site is a governing or a brand site.', 'onesearch' ),
				'sanitize_callback' => static function ( $value ) {
					$valid_values = [
						self::SITE_TYPE_CONSUMER  => true,
						self::SITE_TYPE_GOVERNING => true,
					];

					return is_string( $value ) && isset( $valid_values[ $value ] ) ? $value : null;
				},
				'show_in_rest'      => [
					'schema' => [
						'enum' => [ self::SITE_TYPE_CONSUMER, self::SITE_TYPE_GOVERNING ],
					],
				],
			],
		];

		$consumer_settings = [
			self::OPTION_CONSUMER_API_KEY         => [
				'type'              => 'string',
				'label'             => __( 'Consumer API Key', 'onesearch' ),
				'description'       => __( 'API key used by governing site to authenticate requests from this consumer site.', 'onesearch' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => [
					'schema' => [
						'type' => 'string',
					],
				],
			],
			self::OPTION_CONSUMER_PARENT_SITE_URL => [
				'type'              => 'string',
				'label'             => __( 'Parent Site URL', 'onesearch' ),
				'description'       => __( 'The URL of the governing site that manages this consumer site.', 'onesearch' ),
				'sanitize_callback' => static function ( $value ) {
					return is_string( $value ) ? untrailingslashit( esc_url_raw( $value ) ) : null;
				},
				'show_in_rest'      => [
					'schema' => [
						'type'   => 'string',
						'format' => 'uri',
					],
				],
			],
		];

		$governing_settings = [
			self::OPTION_GOVERNING_SHARED_SITES        => [
				'type'              => 'array',
				'label'             => __( 'Brand Sites', 'onesearch' ),
				'description'       => __( 'An array of brand sites connected to this governing site.', 'onesearch' ),
				'sanitize_callback' => [ self::class, 'sanitize_shared_sites' ],
				'show_in_rest'      => [
					'schema' => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'id'        => [
									'type' => 'string',
								],
								'name'      => [
									'type' => 'string',
								],
								'siteUrl'   => [
									'type'   => 'string',
									'format' => 'uri',
								],
								'logo'      => [
									'type'   => 'string',
									'format' => 'uri',
								],
								'publicKey' => [
									'type' => 'string',
								],
							],
						],
					],
				],
			],
			self::OPTION_GOVERNING_ALGOLIA_CREDENTIALS => [
				'type'              => 'object',
				'label'             => __( 'Algolia Credentials', 'onesearch' ),
				'description'       => __( 'Credentials used to connect to the Algolia service.', 'onesearch' ),
				'sanitize_callback' => static function ( $value ) {
					if ( ! is_array( $value ) ) {
						return null;
					}

					return [
						'app_id'    => isset( $value['app_id'] ) ? sanitize_text_field( $value['app_id'] ) : null,
						'write_key' => isset( $value['write_key'] ) ? sanitize_text_field( $value['write_key'] ) : null,
						'admin_key' => isset( $value['admin_key'] ) ? sanitize_text_field( $value['admin_key'] ) : null,
					];
				},
				'show_in_rest'      => false,
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

		$all_settings = array_merge(
			$shared_settings,
			self::is_consumer_site() ? $consumer_settings : $governing_settings
		);

		foreach ( $all_settings as $key => $args ) {
			register_setting(
				self::SETTING_GROUP,
				$key,
				$args
			);
		}
	}

	/**
	 * Ensures the API key is generated when the site type changes to 'consumer'.
	 *
	 * @param mixed $old_value The old value.
	 * @param mixed $new_value The new value.
	 */
	public function on_site_type_change( $old_value, $new_value ): void {
		if ( self::SITE_TYPE_CONSUMER !== $new_value ) {
			return;
		}

		// By getting the API key, it will be generated if it doesn't exist.
		self::get_api_key();
	}

	/**
	 * Triggers functionality when the brand sites are updated.
	 *
	 * @param array<int, array{id: string, siteUrl: string, name?: string, logo?: string, publicKey?: string}>|mixed $old_sites The previous option value.
	 * @param array<int, array{id: string, siteUrl: string, name?: string, logo?: string, publicKey?: string}>|mixed $updated_sites The updated option value.
	 *
	 * Detects changes in brand site URLs (by ID) and triggers a migration if any URLs have changed.
	 */
	public function on_brand_sites_update( $old_sites, $updated_sites ): void {
		// Bail if there's no previous value.
		if ( ! is_array( $old_sites ) ) {
			return;
		}

		$old_map = self::build_id_url_map( $old_sites );
		$new_map = self::build_id_url_map( $updated_sites );

		// Determine URL changes: old_url => new_url by matching ids that exist in both.
		$url_changes = [];
		foreach ( $new_map as $id => $new_url ) {
			if ( ! isset( $old_map[ $id ] ) ) {
				continue;
			}

			$old_url = $old_map[ $id ];
			if ( strcasecmp( $old_url, $new_url ) === 0 ) {
				continue;
			}
			$url_changes[ $old_url ] = $new_url;
		}

		if ( empty( $url_changes ) ) {
			return;
		}

		/**
		 * Fires when brand site URLs have changed.
		 *
		 * @internal
		 * @param array<string, string> $url_changes Map of old_url => new_url.
		 */
		do_action( 'onesearch_url_changes', $url_changes );
	}

	/**
	 * Migrates sites settings when the url changes on the governing site.
	 *
	 * @param array<string, string> $url_changes Map of old URLs to new ones.
	 */
	public function migrate_sites_on_url_changes( array $url_changes ): void {
		if ( empty( $url_changes ) ) {
			return;
		}

		$configs = self::get_shared_sites();

		$updated_configs = [];

		foreach ( $configs as $site_url => $config ) {
			if ( ! isset( $url_changes[ $site_url ] ) ) {
				$updated_configs[ $site_url ] = $config;
				continue;
			}

			$new_url                     = $url_changes[ $site_url ];
			$updated_configs[ $new_url ] = array_merge( $config, [ 'siteUrl' => $new_url ] );
		}

		self::set_shared_sites( $updated_configs );
	}

	/**
	 * Sanitize the `shared_sites` option.
	 *
	 * @param mixed $input The input value.
	 *
	 * @return array{
	 * id: string,
	 * name: string,
	 * siteUrl: string,
	 * logo: string,
	 * publicKey: string
	 * }[]
	 */
	public static function sanitize_shared_sites( $input ): array {
		if ( ! is_array( $input ) || empty( $input ) ) {
			return [];
		}

		$sanitized = [];

		foreach ( $input as $site_data ) {
			if ( ! is_array( $site_data ) ) {
				continue;
			}

			$site_id      = isset( $site_data['id'] ) ? sanitize_text_field( $site_data['id'] ) : '';
			$site_name    = isset( $site_data['name'] ) ? sanitize_text_field( $site_data['name'] ) : '';
			$site_url     = isset( $site_data['siteUrl'] ) ? esc_url_raw( $site_data['siteUrl'] ) : '';
			$site_logo    = isset( $site_data['logo'] ) ? esc_url_raw( $site_data['logo'] ) : '';
			$site_api_key = isset( $site_data['publicKey'] ) ? sanitize_text_field( $site_data['publicKey'] ) : '';

			// Only save if required fields are filled.
			if ( empty( $site_name ) || empty( $site_url ) ) {
				continue;
			}

			$sanitized[] = [
				'id'        => $site_id ?: wp_generate_uuid4(),
				'name'      => $site_name,
				'siteUrl'   => Utils::normalize_url( $site_url ),
				'logo'      => $site_logo,
				'publicKey' => $site_api_key,
			];
		}

		return $sanitized;
	}

	/**
	 * Static setters and getters for the individual settings.
	 */

	/**
	 * Get brand sites configured for this governing site.
	 *
	 * @return array<string,array{
	 *  publicKey: string,
	 *  id: string,
	 *  logo: string,
	 *  name: string,
	 *  siteUrl: string,
	 * }>
	 */
	public static function get_shared_sites(): array {
		$brands = get_option( self::OPTION_GOVERNING_SHARED_SITES, null ) ?: [];

		$brands_to_return = [];
		foreach ( $brands as $brand ) {
			if ( ! is_array( $brand ) ) {
				continue;
			}

			$brands_to_return[ $brand['siteUrl'] ] = [
				'publicKey' => $brand['publicKey'] ?? '',
				'id'        => $brand['id'] ?? '',
				'logo'      => $brand['logo'] ?? '',
				'name'      => $brand['name'] ?? '',
				'siteUrl'   => $brand['siteUrl'] ?? '',
			];
		}

		return $brands_to_return;
	}

	/**
	 * Get a single brand site by URL
	 *
	 * @param string $site_url The site URL.
	 *
	 * @return ?array{
	 *   publicKey: string,
	 *   id: string,
	 *   logo: string,
	 *   name: string,
	 *   siteUrl: string,
	 * }
	 */
	public static function get_shared_site_by_url( string $site_url ): ?array {
		$brand_sites = self::get_shared_sites();

		$normalized_url = Utils::normalize_url( $site_url );

		return $brand_sites[ $normalized_url ] ?? null;
	}

	/**
	 * Set the shared sites.
	 *
	 * @param array<string,array<string,mixed>> $sites The sites to set.
	 *
	 * @phpstan-param array<string,array{
	 *   publicKey?: string,
	 *   id?: string,
	 *   logo?: string,
	 *   name?: string,
	 *   siteUrl?: string,
	 * }> $sites The sites to set.
	 */
	public static function set_shared_sites( array $sites ): bool {
		return update_option( self::OPTION_GOVERNING_SHARED_SITES, array_values( $sites ) );
	}

	/**
	 * Get the current site type.
	 */
	public static function get_site_type(): ?string {
		$value = get_option( self::OPTION_SITE_TYPE, null );

		return is_string( $value ) ? $value : null;
	}

	/**
	 * Check if the current site is a governing site.
	 */
	public static function is_governing_site(): bool {
		return self::SITE_TYPE_GOVERNING === self::get_site_type();
	}

	/**
	 * Check if the current site is a consumer site.
	 */
	public static function is_consumer_site(): bool {
		return self::SITE_TYPE_CONSUMER === self::get_site_type();
	}

	/**
	 * Gets the API key, generating a new one if it doesn't exist.
	 */
	public static function get_api_key(): string {
		$api_key = get_option( self::OPTION_CONSUMER_API_KEY, '' );

		if ( empty( $api_key ) ) {
			$api_key = self::generate_api_key();
			update_option( self::OPTION_CONSUMER_API_KEY, $api_key );
		}

		return $api_key;
	}

	/**
	 * Regenerates the API key.
	 */
	public static function regenerate_api_key(): string {
		$api_key = self::generate_api_key();
		update_option( self::OPTION_CONSUMER_API_KEY, $api_key );

		return $api_key;
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

		return [
			'app_id'    => $creds['app_id'] ?? null,
			'write_key' => $creds['write_key'] ?? null,
			'admin_key' => $creds['admin_key'] ?? null,
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

		$sanitized = [
			'app_id'    => isset( $value['app_id'] ) ? sanitize_text_field( $value['app_id'] ) : null,
			'write_key' => isset( $value['write_key'] ) ? sanitize_text_field( $value['write_key'] ) : null,
			'admin_key' => isset( $value['admin_key'] ) ? sanitize_text_field( $value['admin_key'] ) : null,
		];

		return update_option( self::OPTION_GOVERNING_ALGOLIA_CREDENTIALS, $sanitized );
	}

	/**
	 * Get the parent URL for consumer sites.
	 */
	public static function get_parent_site_url(): ?string {
		$value = get_option( self::OPTION_CONSUMER_PARENT_SITE_URL, null );
		return is_string( $value ) ? $value : null;
	}

	/**
	 * Set the parent URL for consumer sites.
	 *
	 * @param string $url The parent site URL.
	 */
	public static function set_parent_site_url( string $url ): bool {
		return update_option( self::OPTION_CONSUMER_PARENT_SITE_URL, untrailingslashit( esc_url_raw( $url ) ) );
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
	 * Generate a random API key.
	 *
	 * @return string API key prefixed with 'token_'.
	 */
	private static function generate_api_key(): string {
		return 'token_' . wp_generate_password( 32, false );
	}

	/**
	 * Build a map of id => url from a list of brand sites.
	 *
	 * @param array<int, array{id?: string, siteUrl?: string}>|mixed $sites The sites array.
	 * @return array<string, string> id => url
	 */
	private static function build_id_url_map( $sites ): array {
		if ( ! is_array( $sites ) ) {
			return [];
		}
		$map = [];
		foreach ( $sites as $site ) {
			if ( ! is_array( $site ) || empty( $site['id'] ) || empty( $site['siteUrl'] ) ) {
				continue;
			}
			$map[ (string) $site['id'] ] = Utils::normalize_url( $site['siteUrl'] );
		}
		return $map;
	}
}
