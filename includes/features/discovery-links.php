<?php
/**
 * Feature: discovery links — advertise agent-facing files from the pages
 * agents already visit (AgentReady AR-READ-06).
 *
 * Prints `<link rel>` tags in the document head and matching `Link` response
 * headers (RFC 8288) for every enabled discovery endpoint: the sitemap, the
 * AI Catalog, the OpenAPI document, llms.txt, and the API catalog. Each
 * link is gated on its own feature so nothing is advertised that is not
 * actually served. The sitemap link rides on the API Catalog toggle, which
 * is the plugin's "Link headers" feature.
 *
 * @package Ajaco
 */

namespace Ajaco;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_head', __NAMESPACE__ . '\\output_discovery_link_tags', 3 );
add_action( 'send_headers', __NAMESPACE__ . '\\send_discovery_link_headers' );

/**
 * Enabled discovery links: [ href, rel, type ].
 *
 * @return array<int, array{href: string, rel: string, type: string}>
 */
function discovery_links(): array {
	$links = array();

	if ( is_feature_enabled( 'api_catalog' ) && function_exists( 'wp_sitemaps_get_server' ) ) {
		$server = wp_sitemaps_get_server();
		if ( $server && $server->sitemaps_enabled() ) {
			$links[] = array(
				'href' => home_url( '/wp-sitemap.xml' ),
				'rel'  => 'sitemap',
				'type' => 'application/xml',
			);
		}
	}
	if ( is_feature_enabled( 'ai_catalog' ) ) {
		$links[] = array(
			'href' => home_url( '/.well-known/ard.json' ),
			'rel'  => 'ard',
			'type' => 'application/json',
		);
		$links[] = array(
			'href' => home_url( '/.well-known/ai-catalog.json' ),
			'rel'  => 'ai-catalog',
			'type' => 'application/json',
		);
	}
	if ( is_feature_enabled( 'openapi' ) ) {
		$links[] = array(
			'href' => home_url( '/openapi.json' ),
			'rel'  => 'service-desc',
			'type' => 'application/json',
		);
	}
	if ( is_feature_enabled( 'llms_txt' ) ) {
		$links[] = array(
			'href' => home_url( '/llms.txt' ),
			'rel'  => 'llms-txt',
			'type' => 'text/markdown',
		);
	}

	return $links;
}

/**
 * `<link>` tags in the head. The API catalog gets a head link here too
 * (its response header lives in api-catalog.php).
 *
 * @return void
 */
function output_discovery_link_tags(): void {
	if ( is_admin() ) {
		return;
	}
	foreach ( discovery_links() as $link ) {
		echo '<link rel="' . esc_attr( $link['rel'] ) . '" type="' . esc_attr( $link['type'] ) . '" href="' . esc_url( $link['href'] ) . '" />' . "\n";
	}
	if ( is_feature_enabled( 'api_catalog' ) ) {
		echo '<link rel="api-catalog" type="application/linkset+json" href="' . esc_url( home_url( '/.well-known/api-catalog' ) ) . '" />' . "\n";
	}
}

/**
 * Matching `Link:` response headers.
 *
 * @return void
 */
function send_discovery_link_headers(): void {
	if ( is_admin() ) {
		return;
	}
	foreach ( discovery_links() as $link ) {
		header( 'Link: <' . esc_url_raw( $link['href'] ) . '>; rel="' . $link['rel'] . '"; type="' . $link['type'] . '"', false );
	}
}
