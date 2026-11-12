<?php
/**
 * Feature: AI Catalog — /.well-known/ard.json (+ /.well-known/ai-catalog.json).
 *
 * A typed index of every agent-facing artifact the plugin currently serves
 * (MCP server card, Agent Skills index, API catalog, OpenAPI document,
 * llms.txt, auth.md), so an agent that lands on the homepage can discover
 * all of them from one well-known document (AgentReady AR-FIND-04). Each
 * entry carries a stable identifier, a media type, and a URL; the catalog
 * never redefines the artifacts' own schemas.
 *
 * @package Ajaco
 */

namespace Ajaco;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', __NAMESPACE__ . '\\handle_ai_catalog_request' );

/**
 * Serve the catalog at both well-known paths.
 *
 * @return void
 */
function handle_ai_catalog_request(): void {
	// ARD v0.91 moved the catalog to /.well-known/ard.json; the original
	// ai-catalog.json path is still recognised, so both are served.
	if ( ! request_path_is( '/.well-known/ard.json' ) && ! request_path_is( '/.well-known/ai-catalog.json' ) ) {
		return;
	}

	if ( ! is_feature_enabled( 'ai_catalog' ) ) {
		return;
	}

	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Access-Control-Allow-Origin: *' );

	echo wp_json_encode( build_ai_catalog(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	exit;
}

/**
 * Build the catalog document. Only artifacts whose feature is enabled are
 * listed, so the catalog never points agents at a dead endpoint.
 *
 * @return array{specVersion: string, host: array, entries: array<int, array>}
 */
function build_ai_catalog(): array {
	$host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
	$urn  = static function ( string $kind, string $name ) use ( $host ): string {
		return 'urn:air:' . $host . ':' . $kind . ':' . $name;
	};

	$entries = array();

	$mcp = detect_mcp_server();
	if ( $mcp ) {
		$entries[] = array(
			'identifier' => $urn( 'mcp', 'server' ),
			'name'       => $mcp['name'] . ' (Streamable HTTP)',
			'type'       => 'application/json',
			'protocol'   => 'mcp',
			'url'        => $mcp['endpoint'],
		);
	}
	if ( is_feature_enabled( 'mcp_server_card' ) ) {
		$entries[] = array(
			'identifier' => $urn( 'mcp', 'server-card' ),
			'name'       => 'MCP Server Card',
			'type'       => 'application/mcp-server-card+json',
			'url'        => home_url( '/.well-known/mcp/server-card.json' ),
		);
	}
	if ( is_feature_enabled( 'agent_skills_index' ) ) {
		$entries[] = array(
			'identifier' => $urn( 'skills', 'index' ),
			'name'       => 'Agent Skills Index',
			'type'       => 'application/json',
			'url'        => home_url( '/.well-known/agent-skills/index.json' ),
		);
	}
	if ( is_feature_enabled( 'api_catalog' ) ) {
		$entries[] = array(
			'identifier' => $urn( 'api', 'catalog' ),
			'name'       => 'API Catalog (RFC 9727)',
			'type'       => 'application/linkset+json',
			'url'        => home_url( '/.well-known/api-catalog' ),
		);
	}
	if ( is_feature_enabled( 'openapi' ) ) {
		$entries[] = array(
			'identifier' => $urn( 'api', 'openapi' ),
			'name'       => 'OpenAPI document',
			'type'       => 'application/vnd.oai.openapi+json',
			'url'        => home_url( '/openapi.json' ),
		);
	}
	if ( is_feature_enabled( 'llms_txt' ) ) {
		$entries[] = array(
			'identifier' => $urn( 'content', 'llms-txt' ),
			'name'       => 'llms.txt',
			'type'       => 'text/markdown',
			'url'        => home_url( '/llms.txt' ),
		);
	}
	if ( is_feature_enabled( 'auth_md' ) ) {
		$entries[] = array(
			'identifier' => $urn( 'auth', 'auth-md' ),
			'name'       => 'auth.md',
			'type'       => 'text/markdown',
			'url'        => home_url( '/auth.md' ),
		);
	}

	$catalog = array(
		'specVersion' => '1.0',
		'host'        => array(
			'name' => markdown_safe_text( get_bloginfo( 'name' ) ),
			'url'  => home_url( '/' ),
		),
		'entries'     => $entries,
	);

	/**
	 * Filter the AI Catalog document before it's serialized.
	 *
	 * @param array $catalog Catalog with `specVersion`, `host`, `entries`.
	 */
	$filtered = apply_filters( 'ajaco_ai_catalog', $catalog );

	return is_array( $filtered ) ? $filtered : $catalog;
}
