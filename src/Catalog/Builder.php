<?php
/**
 * Builds the RFC 9264 linkset from registered REST namespaces.
 *
 * @package EightyFourEM\ApiCatalog
 */

namespace EightyFourEM\ApiCatalog\Catalog;

defined( 'ABSPATH' ) || exit;

/**
 * One linkset entry per registered REST API namespace.
 */
class Builder {

	/**
	 * Build (or fetch from cache) the linkset document.
	 *
	 * @return array
	 */
	public static function build(): array {
		$cached = Cache::get();
		if ( null !== $cached ) {
			return $cached;
		}

		$entries = array();
		foreach ( rest_get_server()->get_namespaces() as $namespace ) {
			$entry = array(
				'anchor'       => rest_url( $namespace ),
				'service-desc' => array(
					array(
						'href' => rest_url( $namespace ),
						'type' => 'application/json',
					),
				),
			);

			if ( 'wp/v2' === $namespace ) {
				$entry['service-doc'] = array(
					array(
						'href' => 'https://developer.wordpress.org/rest-api/reference/',
						'type' => 'text/html',
					),
				);
			}

			/**
			 * Filters a single catalog entry.
			 *
			 * @param array|null $entry     Linkset entry. Return null to remove.
			 * @param string     $namespace REST namespace, e.g. 'wp/v2'.
			 */
			$entry = apply_filters( 'wp_api_catalog_entry', $entry, $namespace );
			if ( is_array( $entry ) && array() !== $entry ) {
				$entries[] = $entry;
			}
		}

		/**
		 * Filters the complete linkset document before encoding.
		 *
		 * @param array $linkset Document with a 'linkset' key.
		 */
		$linkset = apply_filters( 'wp_api_catalog_linkset', array( 'linkset' => array_values( $entries ) ) );

		Cache::set( $linkset );

		return $linkset;
	}
}
