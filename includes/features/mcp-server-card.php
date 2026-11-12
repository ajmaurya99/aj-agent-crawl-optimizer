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
	$server  = detect_mcp_server();

	if ( $server ) {
		// A real MCP server is installed: advertise it, and say how to log in.
		$transport    = array(
			'type'     => 'streamable-http',
			'endpoint' => $server['endpoint'],
		);
		$instructions = sprintf(
			'%1$s — Model Context Protocol server (Streamable HTTP) at %2$s. Authenticate with WordPress Application Passwords over HTTP Basic; see %3$s. The REST API at %4$s is described by /.well-known/api-catalog and /openapi.json.',
			$server['name'],
			$server['endpoint'],
			home_url( '/auth.md' ),
			$api_url
		);
	} else {
		// No MCP wire-protocol server on this site. The card still identifies
		// the site to MCP-aware agents, and says plainly that the only machine
		// interface is the REST API.
		$transport    = array(
			'type'     => 'streamable-http',
			'endpoint' => $api_url,
		);
		$instructions = sprintf(
			'This WordPress site does not run an MCP server. Its machine interface is the REST API at %s: discover endpoints via /.well-known/api-catalog or fetch the OpenAPI spec at /openapi.json.',
			$api_url
		);
	}

	$server_card = array(
		'serverInfo'      => array(
			'name'        => get_bloginfo( 'name' ),
			'version'     => get_bloginfo( 'version' ) ?: '1.0',
			'description' => get_bloginfo( 'description' ) ?: '',
			'websiteUrl'  => home_url( '/' ),
		),
		'protocolVersion' => '2025-06-18',
		'transport'       => $transport,
		'capabilities'    => array(
			'resources' => (object) array(),
			'tools'     => (object) array(),
		),
		'instructions'    => $instructions,
	);

	/**
	 * Filter the MCP server card before serialization.
	 *
	 * The card follows SEP-1649/SEP-2127 structure (`serverInfo`, `transport`
	 * with an `endpoint`, `capabilities`). When the WordPress MCP Adapter (or
	 * any plugin registering routes under the `mcp` REST namespace) is active
	 * the endpoint is its real server; otherwise it is the REST API root and
	 * the instructions say so. Use `ajaco_mcp_endpoint` to declare a server
	 * this plugin cannot detect, and this filter to populate `capabilities`.
	 *
	 * @param array $server_card
	 */
	$server_card = apply_filters( 'ajaco_mcp_server_card', $server_card );

	echo wp_json_encode( $server_card, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	exit;
}
