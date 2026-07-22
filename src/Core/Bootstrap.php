<?php
/**
 * Plugin bootstrap.
 *
 * @package EightyFourEM\ApiCatalog
 */

namespace EightyFourEM\ApiCatalog\Core;

defined( 'ABSPATH' ) || exit;

use EightyFourEM\ApiCatalog\Cli\TestCommand;

/**
 * Wires the plugin's components to WordPress hooks.
 */
class Bootstrap {

	/**
	 * Initialize the plugin.
	 *
	 * @param string $plugin_file Absolute path to the main plugin file.
	 * @return void
	 */
	public static function init( string $plugin_file ): void {
		unset( $plugin_file );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( '84em api-catalog', TestCommand::class );
		}
	}
}
