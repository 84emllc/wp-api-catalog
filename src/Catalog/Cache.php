<?php
/**
 * Catalog cache. TTL is the invalidation; no invalidation hooks.
 *
 * @package EightyFourEM\ApiCatalog
 */

namespace EightyFourEM\ApiCatalog\Catalog;

defined( 'ABSPATH' ) || exit;

/**
 * Stores the built linkset in a transient (default) or the object cache.
 */
class Cache {

	private const KEY   = 'wp_api_catalog_linkset';
	private const GROUP = 'wp_api_catalog';

	/**
	 * Fetch the cached linkset.
	 *
	 * @return array|null Cached linkset, or null on miss or disabled cache.
	 */
	public static function get(): ?array {
		if ( 0 === self::ttl() ) {
			return null;
		}
		$value = 'object' === self::type()
			? wp_cache_get( self::KEY, self::GROUP )
			: get_transient( self::KEY );
		return is_array( $value ) ? $value : null;
	}

	/**
	 * Store the linkset.
	 *
	 * @param array $linkset Built linkset document.
	 * @return void
	 */
	public static function set( array $linkset ): void {
		$ttl = self::ttl();
		if ( 0 === $ttl ) {
			return;
		}
		if ( 'object' === self::type() ) {
			wp_cache_set( self::KEY, $linkset, self::GROUP, $ttl );
			return;
		}
		set_transient( self::KEY, $linkset, $ttl );
	}

	/**
	 * Delete the cached linkset from both backends.
	 *
	 * @return void
	 */
	public static function delete(): void {
		wp_cache_delete( self::KEY, self::GROUP );
		delete_transient( self::KEY );
	}

	/**
	 * Cache TTL in seconds. 0 disables caching.
	 *
	 * @return int
	 */
	private static function ttl(): int {
		/**
		 * Filters the catalog cache TTL in seconds.
		 *
		 * @param int $ttl Default 300. Return 0 to disable caching.
		 */
		return max( 0, (int) apply_filters( 'wp_api_catalog_cache_ttl', 300 ) );
	}

	/**
	 * Cache backend: transient (default) or object.
	 *
	 * @return string
	 */
	private static function type(): string {
		/**
		 * Filters the cache backend.
		 *
		 * @param string $type 'transient' (default) or 'object'.
		 */
		$type = apply_filters( 'wp_api_catalog_cache_type', 'transient' );
		return in_array( $type, array( 'transient', 'object' ), true ) ? $type : 'transient';
	}
}
