# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-07-22

### Added

- RFC 9727 `/.well-known/api-catalog` endpoint serving an RFC 9264 linkset built from the site's registered REST API namespaces.
- `api-catalog` discovery `Link` header on the endpoint (GET and HEAD) and on front-end responses.
- Filters: `wp_api_catalog_entry`, `wp_api_catalog_linkset`, `wp_api_catalog_send_link_header`, `wp_api_catalog_cache_ttl`, `wp_api_catalog_cache_type`.
- TTL-based caching (default 300 seconds) via transients or the object cache.
- Baked-in WP-CLI integration test runner: `wp 84em api-catalog test`.
