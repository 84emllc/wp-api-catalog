# API Catalog for WordPress

A WordPress plugin that serves an RFC 9727 `/.well-known/api-catalog` endpoint, built automatically from the site's registered REST API namespaces. It publishes an RFC 9264 linkset so API clients and tooling can discover what's available on the site without guessing at endpoint paths.

## What it serves

`GET /.well-known/api-catalog` returns an `application/linkset+json` document listing every registered REST namespace as a linkset entry, each with a `service-desc` link back to itself. The `wp/v2` entry also gets a `service-doc` link to the WordPress REST API handbook.

Response headers:

```
HTTP/2 200
content-type: application/linkset+json
cache-control: max-age=3600
link: <https://example.com/.well-known/api-catalog>; rel="api-catalog"
```

Response body:

```json
{
  "linkset": [
    {
      "anchor": "https://example.com/wp-json/oembed/1.0",
      "service-desc": [
        {
          "href": "https://example.com/wp-json/oembed/1.0",
          "type": "application/json"
        }
      ]
    },
    {
      "anchor": "https://example.com/wp-json/wp/v2",
      "service-desc": [
        {
          "href": "https://example.com/wp-json/wp/v2",
          "type": "application/json"
        }
      ],
      "service-doc": [
        {
          "href": "https://developer.wordpress.org/rest-api/reference/",
          "type": "text/html"
        }
      ]
    }
  ]
}
```

Every other front-end response on the site also carries the discovery header, so a client that has never heard of the catalog can find it from any page:

```
link: <https://example.com/.well-known/api-catalog>; rel="api-catalog"
```

## Install

Clone the repo, or download a zip, into `wp-content/plugins/`:

```
git clone <repo-url> wp-content/plugins/wp-api-catalog
```

Activate it from the Plugins screen. There's no build step and no Composer dependency to install; the plugin runs as-is. Pretty permalinks are required (Settings > Permalinks set to anything other than Plain); with plain permalinks WordPress never consults rewrite rules and the endpoint 404s.

## Verification

```
curl -sS -D - https://example.com/.well-known/api-catalog
curl -sS -I https://example.com/.well-known/api-catalog
```

The GET should return `200`, `content-type: application/linkset+json`, and a `link` header with `rel="api-catalog"`. The HEAD should return the same status and `link` header with an empty body.

## Security note

The catalog enumerates REST namespaces directly, so it reveals the namespace list (and thus active-plugin hints) even on sites that restrict `/wp-json/` via authentication filters. Sites that lock down the REST API should filter entries with `wp_api_catalog_entry` or remove the plugin.

## Multisite note

This plugin is single-site focused. On multisite, network activation flushes rewrite rules only for the network-admin site; activate per site or re-save permalinks on each sub-site.

## Filters

### `wp_api_catalog_entry`

Modify or remove a single namespace's entry before it's added to the linkset. Return `null` (or an empty array) to drop the entry. Add an OpenAPI `service-desc` to a custom namespace:

```php
add_filter( 'wp_api_catalog_entry', function ( $entry, string $namespace ) {
	if ( 'myplugin/v1' === $namespace && is_array( $entry ) ) {
		$entry['service-desc'][] = array(
			'href' => home_url( '/wp-json/myplugin/v1/openapi.json' ),
			'type' => 'application/vnd.oai.openapi+json',
		);
	}
	return $entry;
}, 10, 2 );
```

### `wp_api_catalog_linkset`

Modify the complete document before it's cached and encoded. Append an entry for an API that isn't a WordPress REST namespace at all:

```php
add_filter( 'wp_api_catalog_linkset', function ( array $linkset ): array {
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
} );
```

### `wp_api_catalog_cache_ttl`

Cache lifetime in seconds. Default is 300. Return 0 to disable caching entirely (every request rebuilds the linkset):

```php
add_filter( 'wp_api_catalog_cache_ttl', fn (): int => 3600 );
```

### `wp_api_catalog_cache_type`

Cache backend: `transient` (default) or `object`:

```php
add_filter( 'wp_api_catalog_cache_type', fn (): string => 'object' );
```

### `wp_api_catalog_send_link_header`

Whether to send the discovery `Link` header on front-end responses. Default `true`. The endpoint itself always sends the header regardless of this filter:

```php
add_filter( 'wp_api_catalog_send_link_header', '__return_false' );
```

## Caching

The built linkset is cached with a TTL: 300 seconds by default, via a transient (key `wp_api_catalog_linkset`). Set `wp_api_catalog_cache_type` to `object` to use the object cache instead (useful if the site runs Redis or Memcached). There's no invalidation hook: the TTL is the invalidation. If you add or remove a REST namespace and need the catalog to reflect it immediately, lower the TTL temporarily, or clear it directly with `wp transient delete wp_api_catalog_linkset` (transient backend) or `wp cache flush` (object cache backend). Note that `wp cache flush` flushes the entire object cache, not just this plugin's entry.

## Server config note

Some nginx configurations block requests to dot-prefixed paths like `/.well-known/`. If the endpoint 404s even though the plugin is active, check for a rule like `location ~ /\. { deny all; }` and add an exception ahead of it:

```nginx
location ^~ /.well-known/ {
	try_files $uri $uri/ /index.php?$args;
}
```

## Tests

The plugin ships a baked-in integration test runner that exercises the live install, no mocks:

```
wp 84em api-catalog test
```

## Background

Built against [RFC 9727](https://www.rfc-editor.org/rfc/rfc9727) (the `api-catalog` well-known URI) and [RFC 9264](https://www.rfc-editor.org/rfc/rfc9264) (the linkset document format).

Blog post: coming soon.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
