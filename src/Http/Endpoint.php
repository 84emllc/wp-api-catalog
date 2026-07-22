<?php
/**
 * Serves /.well-known/api-catalog per RFC 9727.
 *
 * @package EightyFourEM\ApiCatalog
 */

namespace EightyFourEM\ApiCatalog\Http;

defined( 'ABSPATH' ) || exit;

use EightyFourEM\ApiCatalog\Catalog\Builder;

/**
 * Rewrite rule, request handling, and discovery Link header.
 */
class Endpoint {

	public const QUERY_VAR = 'wp_api_catalog';

	/**
	 * Hook everything up.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rule' ) );
		add_filter( 'query_vars', array( __CLASS__, 'add_query_var' ) );
		add_action( 'parse_request', array( __CLASS__, 'maybe_serve' ) );
		add_action( 'send_headers', array( __CLASS__, 'send_link_header' ) );
	}

	/**
	 * Map /.well-known/api-catalog onto our query var.
	 *
	 * @return void
	 */
	public static function add_rewrite_rule(): void {
		add_rewrite_rule( '^\.well-known/api-catalog/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	/**
	 * Register the query var.
	 *
	 * @param array $vars Public query vars.
	 * @return array
	 */
	public static function add_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Serve the catalog and exit when our query var is present.
	 *
	 * @param \WP $wp Current WordPress environment instance.
	 * @return void
	 */
	public static function maybe_serve( \WP $wp ): void {
		if ( empty( $wp->query_vars[ self::QUERY_VAR ] ) ) {
			return;
		}

		$linkset = Builder::build();

		status_header( 200 );
		header( 'Content-Type: application/linkset+json' );
		header( 'Cache-Control: max-age=3600' );
		header( 'Link: <' . esc_url_raw( home_url( '/.well-known/api-catalog' ) ) . '>; rel="api-catalog"' );

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET';
		if ( 'HEAD' !== $method ) {
			echo wp_json_encode( $linkset );
		}
		exit;
	}

	/**
	 * Advertise the catalog from every front-end response.
	 *
	 * @return void
	 */
	public static function send_link_header(): void {
		/**
		 * Filters whether to send the discovery Link header on front-end responses.
		 *
		 * @param bool $send Default true.
		 */
		if ( ! apply_filters( 'wp_api_catalog_send_link_header', true ) ) {
			return;
		}
		if ( headers_sent() ) {
			return;
		}
		header( 'Link: <' . esc_url_raw( home_url( '/.well-known/api-catalog' ) ) . '>; rel="api-catalog"', false );
	}
}
