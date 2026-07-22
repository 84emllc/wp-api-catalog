<?php
/**
 * Plugin Name: API Catalog for WordPress
 * Description: Serves an RFC 9727 /.well-known/api-catalog endpoint built from the site's registered REST API namespaces.
 * Version: 1.0.0
 * Author: 84EM
 * Author URI: https://www.84em.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Text Domain: wp-api-catalog
 */

namespace EightyFourEM\ApiCatalog;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/src/Autoloader.php';

Autoloader::register();

Core\Bootstrap::init( __FILE__ );
