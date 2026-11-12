<?php
/**
 * MCP server detection — the bridge to a real Model Context Protocol server.
 *
 * This plugin publishes discovery documents; it does not speak the MCP wire
 * protocol itself. The official WordPress MCP Adapter plugin (mcp-adapter,
 * WordPress 6.9+) does: it serves Streamable HTTP MCP servers under the `mcp`
 * REST namespace (default: /wp-json/mcp/mcp-adapter-default-server). When
 * such a server is present, every discovery surface here — the MCP server
 * card, the agent resource catalog, the API catalog, llms.txt and auth.md —
 * points agents at its real endpoint instead of the REST root.
 *
 * Detection is generic: any plugin that registers REST routes under the
 * `mcp` namespace is recognised, and the `ajaco_mcp_endpoint` filter lets a
 * site declare a server this code cannot see.
 *
 * @package Ajaco
 */

namespace Ajaco;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Slug of the official adapter plugin on WordPress.org.
 */
const MCP_ADAPTER_SLUG = 'mcp-adapter';

/**
 * Default server route the adapter registers.
 */
const MCP_ADAPTER_DEFAULT_ROUTE = '/mcp/mcp-adapter-default-server';

/**
 * The MCP server this site exposes, or null.
 *
 * @return array{endpoint: string, name: string, source: string, transport: string}|null
 */
function detect_mcp_server(): ?array {
	static $cached = false;
	if ( false !== $cached ) {
		return $cached;
	}

	$server = null;

	// 1. REST routes under the `mcp` namespace (the adapter, or anything
	// following its convention). rest_get_server() boots the REST API if
	// it has not been yet, which is what the OpenAPI feature does too.
	if ( function_exists( 'rest_get_server' ) ) {
		$routes = array_keys( rest_get_server()->get_routes() );
		$mcp    = array_values(
			array_filter(
				$routes,
				static function ( $route ) {
					// Concrete server routes only — no regex placeholders.
					return 0 === strpos( $route, '/mcp/' ) && false === strpos( $route, '(' );
				}
			)
		);
		if ( ! empty( $mcp ) ) {
			$route  = in_array( MCP_ADAPTER_DEFAULT_ROUTE, $mcp, true ) ? MCP_ADAPTER_DEFAULT_ROUTE : $mcp[0];
			$server = array(
				'endpoint'  => rest_url( ltrim( $route, '/' ) ),
				'name'      => mcp_adapter_active() ? 'WordPress MCP Adapter' : 'MCP server',
				'source'    => mcp_adapter_active() ? MCP_ADAPTER_SLUG : 'rest-namespace',
				'transport' => 'streamable-http',
			);
		}
	}

	/**
	 * Filter the detected MCP server.
	 *
	 * Return an array with at least an `endpoint` (absolute URL) to declare a
	 * server this plugin cannot detect, or null to declare none.
	 *
	 * @param array|null $server Detected server, or null.
	 */
	$filtered = apply_filters( 'ajaco_mcp_endpoint', $server );
	if ( is_array( $filtered ) && ! empty( $filtered['endpoint'] ) ) {
		$server = array_merge(
			array(
				'name'      => 'MCP server',
				'source'    => 'filter',
				'transport' => 'streamable-http',
			),
			$filtered
		);
	} elseif ( null === $filtered ) {
		$server = null;
	}

	$cached = $server;
	return $server;
}

/**
 * Whether the official adapter plugin is loaded.
 *
 * @return bool
 */
function mcp_adapter_active(): bool {
	return class_exists( 'WP\\MCP\\Plugin' ) || class_exists( 'WP\\MCP\\Core\\McpAdapter' );
}

/**
 * Whether this WordPress can run the adapter (it requires the core
 * Abilities API, WordPress 6.9+).
 *
 * @return bool
 */
function mcp_adapter_supported(): bool {
	return version_compare( get_bloginfo( 'version' ), '6.9', '>=' );
}

/**
 * Admin URL that installs/searches the adapter on WordPress.org.
 *
 * @return string
 */
function mcp_adapter_install_url(): string {
	return admin_url( 'plugin-install.php?s=' . rawurlencode( MCP_ADAPTER_SLUG ) . '&tab=search&type=term' );
}
