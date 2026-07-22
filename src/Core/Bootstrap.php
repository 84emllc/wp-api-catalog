<?php
/**
 * Plugin bootstrap.
 *
 * @package EightyFourEM\ApiCatalog
 */

namespace EightyFourEM\ApiCatalog\Core;

defined( 'ABSPATH' ) || exit;

use EightyFourEM\ApiCatalog\Cli\TestCommand;
use EightyFourEM\ApiCatalog\Http\Endpoint;

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
		Endpoint::register();

		register_activation_hook( $plugin_file, array( __CLASS__, 'activate' ) );
		register_deactivation_hook( $plugin_file, array( __CLASS__, 'deactivate' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( '84em api-catalog', TestCommand::class );
		}
	}

	/**
	 * Register the rewrite rule and flush on activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		Endpoint::add_rewrite_rule();
		flush_rewrite_rules();
	}

	/**
	 * Remove the rewrite rule and flush on deactivation.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		Endpoint::remove_rewrite_rule();
		flush_rewrite_rules();
	}
}
