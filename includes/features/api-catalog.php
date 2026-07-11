<?php
/**
 * Feature: API Catalog (RFC 9727) + Link header advertisement.
 *
 * Serves a `linkset+json` document at /.well-known/api-catalog describing the
 * site's REST API, and emits a `Link: <url>; rel="api-catalog"` header on
 * every frontend response so agents can discover the catalog without having
 * to know about /.well-known/.
 *
 * @package Ajaco
 */

namespace Ajaco;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', __NAMESPACE__ . '\\handle_api_catalog_request' );
add_action( 'send_headers', __NAMESPACE__ . '\\send_api_catalog_link_header' );

/**
 * Serve /.well-known/api-catalog (RFC 9727).
 *
 * @return void
 */
function handle_api_catalog_request(): void {
	if ( ! request_path_is( '/.well-known/api-catalog' ) ) {
		return;
	}

	if ( ! is_feature_enabled( 'api_catalog' ) ) {
		return;
	}

	nocache_headers();
	header( 'Content-Type: application/linkset+json; charset=utf-8' );

	$api_url = rest_url( '/' );

	// Build linkset per RFC 9727 (flat form, RFC 9264 §4.2.4.3).
	$entry = array(
		'anchor'      => $api_url,
		'service-doc' => array(
			array(
				'href' => 'https://developer.wordpress.org/rest-api/',
				'type' => 'text/html',
			),
		),
		'status'      => array(
			array(
				// Dedicated lightweight health endpoint (public), not the
				// multi-KB REST index — the RFC 9727 `status` relation is
				// meant for liveness.
				'href' => rest_url( 'ajaco/v1/health' ),
				'type' => 'application/json',
			),
		),
	);

	// Only advertise a service-desc when the OpenAPI feature is actually
	// enabled — otherwise the catalog points agents at a dead link. Media type
	// matches what the endpoint really serves (application/json).
	if ( is_feature_enabled( 'openapi' ) ) {
		$entry['service-desc'] = array(
			array(
				'href' => home_url( '/openapi.json' ),
				'type' => 'application/json',
			),
		);
	}

	$linkset = array( $entry );

	/**
	 * Filter the RFC 9727 linkset before it's serialized.
	 *
	 * Allows other plugins to add anchors (e.g. for a GraphQL endpoint) or
	 * additional rels (e.g. `service-meta` pointing at a JSON Schema).
	 *
	 * @param array $linkset
	 */
	$linkset = apply_filters( 'ajaco_api_catalog_linkset', $linkset );

	echo wp_json_encode( array( 'linkset' => $linkset ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	exit;
}

/**
 * Append a Link header advertising the API catalog so agents can discover it
 * from any response without having to know about /.well-known/.
 *
 * @see https://www.rfc-editor.org/rfc/rfc9727#section-3
 *
 * @return void
 */
function send_api_catalog_link_header(): void {
	if ( ! is_feature_enabled( 'api_catalog' ) ) {
		return;
	}
	if ( is_admin() ) {
		return;
	}

	$catalog_url = home_url( '/.well-known/api-catalog' );
	// `false` so we append rather than replace any existing Link header.
	header( 'Link: <' . $catalog_url . '>; rel="api-catalog"', false );
}
