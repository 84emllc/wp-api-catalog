<?php
/**
 * PSR-4-style autoloader for the plugin's own classes. No Composer.
 *
 * @package EightyFourEM\ApiCatalog
 */

namespace EightyFourEM\ApiCatalog;

defined( 'ABSPATH' ) || exit;

/**
 * Maps EightyFourEM\ApiCatalog\* class names onto files under src/.
 */
class Autoloader {

	/**
	 * Register the autoloader.
	 *
	 * @return void
	 */
	public static function register(): void {
		spl_autoload_register(
			static function ( string $class ): void {
				$prefix = __NAMESPACE__ . '\\';
				if ( 0 !== strpos( $class, $prefix ) ) {
					return;
				}
				$relative = substr( $class, strlen( $prefix ) );
				$file     = __DIR__ . '/' . str_replace( '\\', '/', $relative ) . '.php';
				if ( is_readable( $file ) ) {
					require $file;
				}
			}
		);
	}
}
