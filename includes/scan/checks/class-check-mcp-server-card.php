<?php
/**
 * Scan check: MCP Server Card (MCP SEP-1649).
 *
 * Probes the candidate well-known paths in order —
 * /.well-known/mcp/server-card.json, /.well-known/mcp/server-cards.json,
 * /.well-known/mcp.json — and validates the first JSON card found for
 * serverInfo (name, version), a transport endpoint, and capabilities.
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
 * MCP Server Card check.
 */
class Check_Mcp_Server_Card extends Check {

	/**
	 * Check id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'mcpServerCard';
	}

	/**
	 * Category.
	 *
	 * @return string
	 */
	public function get_category(): string {
		return Check::CATEGORY_DISCOVERY;
	}

	/**
	 * Display name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'MCP Server Card';
	}

	/**
	 * Run the check.
	 *
	 * @return Check_Result
	 */
	public function run(): Check_Result {
		$evidence = array();

		$candidates = array(
			'/.well-known/mcp/server-card.json',
			'/.well-known/mcp/server-cards.json',
			'/.well-known/mcp.json',
		);

		$card = null;
		$path = '';

		foreach ( $candidates as $i => $candidate ) {
			$r = $this->http_get( $this->origin() . $candidate, 'GET ' . $candidate, $evidence );

			if ( '' !== $r['error'] && 0 === $r['code'] ) {
				if ( 0 === $i ) {
					return $this->unable( 'Could not reach ' . $candidate . ' — ' . $r['error'], $evidence );
				}
				continue;
			}

			if ( 200 !== $r['code'] ) {
				continue;
			}

			$data = json_decode( $r['body'], true );
			if ( ! is_array( $data ) ) {
				$evidence[] = Evidence::parse(
					'Parse ' . $candidate,
					'negative',
					'Returned 200 but the body is not valid JSON — trying the next candidate path.'
				);
				continue;
			}

			$card = $data;
			$path = $candidate;
			break;
		}

		if ( null === $card ) {
			return $this->fail(
				'No MCP server card found (checked /.well-known/mcp/server-card.json, /.well-known/mcp/server-cards.json, /.well-known/mcp.json)',
				$evidence
			);
		}

		// Validate the card per MCP SEP-1649.
		$name             = isset( $card['serverInfo']['name'] ) && is_string( $card['serverInfo']['name'] ) ? $card['serverInfo']['name'] : '';
		$has_version      = ! empty( $card['serverInfo']['version'] );
		$has_capabilities = isset( $card['capabilities'] );

		// Accept either a single transport object or a transports array.
		$has_transport = false;
		if ( isset( $card['transport'] ) && is_array( $card['transport'] ) && ! empty( $card['transport']['endpoint'] ) ) {
			$has_transport = true;
		} elseif ( isset( $card['transports'] ) && is_array( $card['transports'] ) ) {
			foreach ( $card['transports'] as $transport ) {
				if ( is_array( $transport ) && ! empty( $transport['endpoint'] ) ) {
					$has_transport = true;
					break;
				}
			}
		}

		$missing = array();
		if ( '' === $name ) {
			$missing[] = 'serverInfo.name';
		}
		if ( ! $has_version ) {
			$missing[] = 'serverInfo.version';
		}
		if ( ! $has_transport ) {
			$missing[] = 'transport endpoint';
		}
		if ( ! $has_capabilities ) {
			$missing[] = 'capabilities';
		}

		$details = array(
			'path'            => $path,
			'name'            => $name,
			'hasVersion'      => $has_version,
			'hasTransport'    => $has_transport,
			'hasCapabilities' => $has_capabilities,
		);

		if ( ! empty( $missing ) ) {
			$evidence[] = Evidence::parse(
				'Validate server card',
				'negative',
				'Card at ' . $path . ' is missing: ' . implode( ', ', $missing ) . ' (MCP SEP-1649).'
			);
			return $this->fail( 'MCP server card found at ' . $path . ' but missing: ' . implode( ', ', $missing ), $evidence, $details );
		}

		$evidence[] = Evidence::parse(
			'Validate server card',
			'positive',
			'serverInfo.name, serverInfo.version, a transport endpoint, and capabilities are all present (MCP SEP-1649).'
		);

		return $this->pass( 'MCP server card found at ' . $path . ' (' . $name . ')', $evidence, $details );
	}
}
