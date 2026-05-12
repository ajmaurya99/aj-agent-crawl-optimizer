<?php
/**
 * Feature: MCP Server Card (SEP-1649 draft).
 *
 * Serves /.well-known/mcp/server-card.json describing the site to MCP-aware
 * agents — serverInfo, transport, capabilities flag objects, and instructions
 * pointing agents at the API catalog and OpenAPI spec.
 *
 * @see https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2127
 *
 * @package Ajaco
 */

namespace Ajaco;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', __NAMESPACE__ . '\\handle_mcp_server_card_request' );

/**
 * Serve /.well-known/mcp/server-card.json.
 *
 * @return void
 */
function handle_mcp_server_card_request(): void {
	if ( ! request_path_is( '/.well-known/mcp/server-card.json' ) ) {
		return;
	}

	if ( ! is_feature_enabled( 'mcp_server_card' ) ) {
		return;
	}

	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );

	$api_url = rest_url( '/' );

	$server_card = array(
		'serverInfo'      => array(
			'name'        => get_bloginfo( 'name' ),
			'version'     => get_bloginfo( 'version' ) ?: '1.0',
			'description' => get_bloginfo( 'description' ) ?: '',
			'websiteUrl'  => home_url( '/' ),
		),
		'protocolVersion' => '2024-11-05',
		'transport'       => array(
			'type' => 'http',
			'url'  => $api_url,
		),
		'capabilities'    => array(
			'resources' => (object) array(),
			'tools'     => (object) array(),
		),
		'instructions'    => sprintf(
			'WordPress site exposing a REST API at %s. Discover endpoints via /.well-known/api-catalog or fetch the OpenAPI spec at /?format=openapi.',
			$api_url
		),
	);

	/**
	 * Filter the MCP server card before serialization.
	 *
	 * Plugins that actually implement an MCP transport (JSON-RPC over HTTP/SSE)
	 * can override `transport.type` to `streamable-http` and populate proper
	 * MCP `capabilities` flag objects. The default capabilities are empty
	 * because vanilla WP doesn't speak the MCP wire protocol.
	 *
	 * @param array $server_card
	 */
	$server_card = apply_filters( 'ajaco_mcp_server_card', $server_card );

	echo wp_json_encode( $server_card, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	exit;
}
