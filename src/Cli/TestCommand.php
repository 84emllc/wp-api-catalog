<?php
/**
 * Baked-in integration test runner.
 *
 * @package EightyFourEM\ApiCatalog
 */

namespace EightyFourEM\ApiCatalog\Cli;

defined( 'ABSPATH' ) || exit;

use WP_CLI;
use EightyFourEM\ApiCatalog\Catalog\Builder;
use EightyFourEM\ApiCatalog\Catalog\Cache;

/**
 * Integration tests that run against the live install. No mocks.
 */
class TestCommand {

	/**
	 * Number of passed assertions.
	 *
	 * @var int
	 */
	private int $passed = 0;

	/**
	 * Failure messages.
	 *
	 * @var array
	 */
	private array $failures = array();

	/**
	 * Run the plugin's integration tests against this install.
	 *
	 * ## EXAMPLES
	 *
	 *     wp 84em api-catalog test
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Associative arguments (unused).
	 * @return void
	 */
	public function test( array $args, array $assoc_args ): void {
		unset( $args, $assoc_args );

		$this->test_builder_shape();
		$this->test_namespaces_present();
		$this->test_entry_filter();
		$this->test_linkset_filter();
		$this->test_cache_roundtrip();
		$this->test_cache_disabled();
		$this->test_cache_type_object();
		$this->test_http_get();
		$this->test_http_head();

		$this->report();
	}

	/**
	 * The built catalog is a linkset array with per-namespace anchors.
	 *
	 * @return void
	 */
	private function test_builder_shape(): void {
		Cache::delete();
		$linkset = Builder::build();
		$this->assert( isset( $linkset['linkset'] ) && is_array( $linkset['linkset'] ), 'build() returns array with linkset key' );
		$first = $linkset['linkset'][0] ?? array();
		$this->assert( isset( $first['anchor'] ) && is_string( $first['anchor'] ), 'first entry has string anchor' );
		$this->assert( isset( $first['service-desc'][0]['href'], $first['service-desc'][0]['type'] ), 'first entry has service-desc with href and type' );
		$this->assert( 'application/json' === ( $first['service-desc'][0]['type'] ?? '' ), 'service-desc type is application/json' );
	}

	/**
	 * Every registered REST namespace appears as an entry.
	 *
	 * @return void
	 */
	private function test_namespaces_present(): void {
		Cache::delete();
		$linkset    = Builder::build();
		$anchors    = wp_list_pluck( $linkset['linkset'], 'anchor' );
		$namespaces = rest_get_server()->get_namespaces();
		$missing    = array();
		foreach ( $namespaces as $namespace ) {
			if ( ! in_array( rest_url( $namespace ), $anchors, true ) ) {
				$missing[] = $namespace;
			}
		}
		$this->assert( array() === $missing, 'all registered namespaces present (missing: ' . implode( ',', $missing ) . ')' );
		$wp_v2 = null;
		foreach ( $linkset['linkset'] as $entry ) {
			if ( rest_url( 'wp/v2' ) === $entry['anchor'] ) {
				$wp_v2 = $entry;
			}
		}
		$this->assert( isset( $wp_v2['service-doc'][0]['href'] ) && false !== strpos( $wp_v2['service-doc'][0]['href'], 'developer.wordpress.org' ), 'wp/v2 entry links the REST API handbook as service-doc' );
	}

	/**
	 * wp_api_catalog_entry can modify and remove entries.
	 *
	 * @return void
	 */
	private function test_entry_filter(): void {
		Cache::delete();
		$remove_wp_v2 = static function ( $entry, string $namespace ) {
			return 'wp/v2' === $namespace ? null : $entry;
		};
		add_filter( 'wp_api_catalog_entry', $remove_wp_v2, 10, 2 );
		$linkset = Builder::build();
		remove_filter( 'wp_api_catalog_entry', $remove_wp_v2 );
		Cache::delete();
		$anchors = wp_list_pluck( $linkset['linkset'], 'anchor' );
		$this->assert( ! in_array( rest_url( 'wp/v2' ), $anchors, true ), 'entry filter returning null removes the entry' );
	}

	/**
	 * wp_api_catalog_linkset filters the final document.
	 *
	 * @return void
	 */
	private function test_linkset_filter(): void {
		Cache::delete();
		$add_external = static function ( array $linkset ): array {
			$linkset['linkset'][] = array(
				'anchor'       => 'https://external.example.com/api',
				'service-desc' => array(
					array(
						'href' => 'https://external.example.com/openapi.json',
						'type' => 'application/vnd.oai.openapi+json',
					),
				),
			);
			return $linkset;
		};
		add_filter( 'wp_api_catalog_linkset', $add_external );
		$linkset = Builder::build();
		remove_filter( 'wp_api_catalog_linkset', $add_external );
		Cache::delete();
		$anchors = wp_list_pluck( $linkset['linkset'], 'anchor' );
		$this->assert( in_array( 'https://external.example.com/api', $anchors, true ), 'linkset filter can append external APIs' );
	}

	/**
	 * Second build within TTL is served from cache.
	 *
	 * @return void
	 */
	private function test_cache_roundtrip(): void {
		Cache::delete();
		$mark = static function ( array $linkset ): array {
			$linkset['marker'] = 'cached';
			return $linkset;
		};
		add_filter( 'wp_api_catalog_linkset', $mark );
		Builder::build();
		remove_filter( 'wp_api_catalog_linkset', $mark );
		$second = Builder::build();
		Cache::delete();
		$this->assert( 'cached' === ( $second['marker'] ?? '' ), 'second build served from cache (marker survives filter removal)' );
	}

	/**
	 * TTL 0 disables caching.
	 *
	 * @return void
	 */
	private function test_cache_disabled(): void {
		Cache::delete();
		$zero = static fn (): int => 0;
		add_filter( 'wp_api_catalog_cache_ttl', $zero );
		$mark = static function ( array $linkset ): array {
			$linkset['marker'] = 'cached';
			return $linkset;
		};
		add_filter( 'wp_api_catalog_linkset', $mark );
		Builder::build();
		remove_filter( 'wp_api_catalog_linkset', $mark );
		$second = Builder::build();
		remove_filter( 'wp_api_catalog_cache_ttl', $zero );
		Cache::delete();
		$this->assert( ! isset( $second['marker'] ), 'TTL 0 disables caching (rebuild reflects removed filter)' );
	}

	/**
	 * Cache type object uses wp_cache_* and still round-trips.
	 *
	 * @return void
	 */
	private function test_cache_type_object(): void {
		Cache::delete();
		$object_type = static fn (): string => 'object';
		add_filter( 'wp_api_catalog_cache_type', $object_type );
		$mark = static function ( array $linkset ): array {
			$linkset['marker'] = 'cached';
			return $linkset;
		};
		add_filter( 'wp_api_catalog_linkset', $mark );
		Builder::build();
		remove_filter( 'wp_api_catalog_linkset', $mark );
		$second = Builder::build();
		$this->assert( 'cached' === ( $second['marker'] ?? '' ), 'object cache type round-trips within a request' );
		Cache::delete();
		$this->assert( null === Cache::get(), 'Cache::delete clears the object cache entry' );
		remove_filter( 'wp_api_catalog_cache_type', $object_type );
	}

	/**
	 * GET /.well-known/api-catalog serves the linkset with correct headers.
	 *
	 * @return void
	 */
	private function test_http_get(): void {
		$response = wp_remote_get(
			home_url( '/.well-known/api-catalog' ),
			array( 'sslverify' => false )
		);
		if ( is_wp_error( $response ) ) {
			$this->assert( false, 'GET request failed: ' . $response->get_error_message() );
			return;
		}
		$this->assert( 200 === wp_remote_retrieve_response_code( $response ), 'GET returns 200' );
		$this->assert( 'application/linkset+json' === wp_remote_retrieve_header( $response, 'content-type' ), 'GET content-type is application/linkset+json' );
		$link = (string) wp_remote_retrieve_header( $response, 'link' );
		$this->assert( false !== strpos( $link, 'rel="api-catalog"' ), 'GET carries api-catalog Link header' );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$this->assert( isset( $body['linkset'] ) && is_array( $body['linkset'] ) && array() !== $body['linkset'], 'GET body decodes to non-empty linkset' );
	}

	/**
	 * HEAD /.well-known/api-catalog returns the Link header and no body.
	 *
	 * @return void
	 */
	private function test_http_head(): void {
		$response = wp_remote_head(
			home_url( '/.well-known/api-catalog' ),
			array( 'sslverify' => false )
		);
		if ( is_wp_error( $response ) ) {
			$this->assert( false, 'HEAD request failed: ' . $response->get_error_message() );
			return;
		}
		$this->assert( 200 === wp_remote_retrieve_response_code( $response ), 'HEAD returns 200' );
		$link = (string) wp_remote_retrieve_header( $response, 'link' );
		$this->assert( false !== strpos( $link, 'rel="api-catalog"' ), 'HEAD carries api-catalog Link header' );
		$this->assert( '' === wp_remote_retrieve_body( $response ), 'HEAD body is empty' );
	}

	/**
	 * Assert a condition, recording the result.
	 *
	 * @param bool   $condition Condition under test.
	 * @param string $message   Description of the assertion.
	 * @return void
	 */
	private function assert( bool $condition, string $message ): void {
		if ( $condition ) {
			++$this->passed;
			WP_CLI::log( 'PASS: ' . $message );
		} else {
			$this->failures[] = $message;
			WP_CLI::log( 'FAIL: ' . $message );
		}
	}

	/**
	 * Print the summary and exit non-zero on failure.
	 *
	 * @return void
	 */
	private function report(): void {
		if ( array() !== $this->failures ) {
			WP_CLI::error( sprintf( '%d passed, %d failed: %s', $this->passed, count( $this->failures ), implode( '; ', $this->failures ) ) );
		}
		WP_CLI::success( sprintf( '%d passed, 0 failed', $this->passed ) );
	}
}
