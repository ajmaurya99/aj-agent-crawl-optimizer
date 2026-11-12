<?php
/**
 * Scan check: MCP server (id `mcpServer`, discovery).
 *
 * The server card says where an MCP server is; this check talks to it. It
 * POSTs a JSON-RPC `initialize` request to the endpoint the site exposes
 * (detected server-side, or the adapter's default route) and passes on a
 * valid MCP response, or on a 401/403 that proves a server is there but
 * wants Application Password credentials. AgentReady AR-ACT-10 makes an
 * action interface a MUST for sites agents should act on.
 *
 * @package Ajaco
 */

namespace Ajaco\Scan\Checks;

use Ajaco\Scan\Check;
use Ajaco\Scan\Check_Result;
use Ajaco\Scan\Evidence;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MCP server check.
 */
class Check_Mcp_Server extends Check {

	/**
	 * Check id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'mcpServer';
	}

	/**
	 * Category.
	 *
	 * @return string
	 */
	public function get_category(): string {
		return self::CATEGORY_DISCOVERY;
	}

	/**
	 * Display name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'MCP server';
	}

	/**
	 * Run the check.
	 *
	 * @return Check_Result
	 */
	public function run(): Check_Result {
		$evidence = array();
		$server   = function_exists( 'Ajaco\\detect_mcp_server' ) ? \Ajaco\detect_mcp_server() : null;
		$endpoint = $server ? $server['endpoint'] : rest_url( ltrim( \Ajaco\MCP_ADAPTER_DEFAULT_ROUTE, '/' ) );

		$evidence[] = Evidence::parse(
			'Locate the MCP endpoint',
			$server ? 'positive' : 'neutral',
			$server
				? sprintf( '%s detected (%s): %s', $server['name'], $server['source'], $endpoint )
				: 'No MCP server registered on this site; probing the adapter\'s default route ' . $endpoint
		);

		$body = wp_json_encode(
			array(
				'jsonrpc' => '2.0',
				'id'      => 1,
				'method'  => 'initialize',
				'params'  => array(
					'protocolVersion' => '2025-06-18',
					'capabilities'    => (object) array(),
					'clientInfo'      => array(
						'name'    => 'AJACO-Scanner',
						'version' => AJACO_VERSION,
					),
				),
			)
		);

		$path = (string) wp_parse_url( $endpoint, PHP_URL_PATH );
		$res  = $this->http_post_json( $endpoint, 'POST ' . $path . ' (initialize)', $evidence, (string) $body );

		if ( '' !== $res['error'] && 0 === $res['code'] ) {
			return $this->unable( 'Could not reach ' . $path . ' — ' . $res['error'], $evidence );
		}

		$details = array(
			'endpoint' => $endpoint,
			'status'   => $res['code'],
			'detected' => (bool) $server,
			'adapter'  => function_exists( 'Ajaco\\mcp_adapter_active' ) && \Ajaco\mcp_adapter_active(),
		);

		if ( 404 === $res['code'] || 405 === $res['code'] || 410 === $res['code'] ) {
			$evidence[] = Evidence::parse( 'Interpret response', 'negative', 'HTTP ' . $res['code'] . ' — nothing speaks MCP at this route.' );
			return $this->fail( 'No MCP server on this site — agents can read it but cannot act on it', $evidence, $details );
		}

		if ( in_array( $res['code'], array( 401, 403 ), true ) ) {
			$evidence[] = Evidence::parse(
				'Interpret response',
				'positive',
				'HTTP ' . $res['code'] . ' — an MCP server is present and requires authentication (WordPress Application Passwords), as the adapter is designed to.'
			);
			return $this->pass(
				'MCP server present at ' . $path . ' (authentication required — see /auth.md)',
				$evidence,
				$details
			);
		}

		$json   = json_decode( $res['body'], true );
		$result = is_array( $json ) && isset( $json['result'] ) && is_array( $json['result'] ) ? $json['result'] : null;
		if ( null !== $result && ( isset( $result['protocolVersion'] ) || isset( $result['serverInfo'] ) ) ) {
			$name                  = isset( $result['serverInfo']['name'] ) ? (string) $result['serverInfo']['name'] : 'MCP server';
			$evidence[]            = Evidence::parse( 'Interpret response', 'positive', 'Valid initialize result from "' . $name . '" (protocol ' . ( isset( $result['protocolVersion'] ) ? $result['protocolVersion'] : '?' ) . ').' );
			$details['serverName'] = $name;
			return $this->pass( 'MCP server "' . $name . '" answered initialize at ' . $path, $evidence, $details );
		}

		$evidence[] = Evidence::parse( 'Interpret response', 'negative', 'HTTP ' . $res['code'] . ' but no JSON-RPC initialize result in the body.' );
		return $this->fail( $path . ' answered HTTP ' . $res['code'] . ' but did not return an MCP initialize result', $evidence, $details );
	}

	/**
	 * POST a JSON body (JSON-RPC) and record a fetch evidence step.
	 *
	 * @param string $url      Absolute URL.
	 * @param string $label    Evidence label.
	 * @param array  $evidence Evidence array, by reference.
	 * @param string $body     JSON body.
	 * @return array{code: int, body: string, headers: array<string,string>, error: string}
	 */
	private function http_post_json( string $url, string $label, array &$evidence, string $body ): array {
		$response = wp_remote_post(
			$url,
			array(
				'timeout'     => 10,
				'redirection' => 2,
				'user-agent'  => 'AJACO-Scanner/' . AJACO_VERSION . ' (+' . home_url( '/' ) . ')',
				'sslverify'   => apply_filters( 'ajaco_scan_sslverify', false ),
				'headers'     => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json, text/event-stream',
				),
				'body'        => $body,
			)
		);

		$evidence[] = Evidence::fetch( $label, $url, $response );

		if ( is_wp_error( $response ) ) {
			return array(
				'code'    => 0,
				'body'    => '',
				'headers' => array(),
				'error'   => $response->get_error_message(),
			);
		}

		$headers = array();
		$raw     = wp_remote_retrieve_headers( $response );
		if ( is_object( $raw ) && method_exists( $raw, 'getAll' ) ) {
			foreach ( $raw->getAll() as $name => $value ) {
				$headers[ strtolower( (string) $name ) ] = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
			}
		}

		return array(
			'code'    => (int) wp_remote_retrieve_response_code( $response ),
			'body'    => (string) wp_remote_retrieve_body( $response ),
			'headers' => $headers,
			'error'   => '',
		);
	}
}
