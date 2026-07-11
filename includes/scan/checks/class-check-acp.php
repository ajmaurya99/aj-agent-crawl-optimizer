<?php
/**
 * Scan check: ACP (Agentic Commerce Protocol) (commerce).
 *
 * Fetches /.well-known/acp.json and validates the discovery document:
 * protocol.name "acp", protocol.version, an absolute api_base_url, non-empty
 * transports, and capabilities.services (agenticcommerce.dev discovery RFC).
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
 * ACP (Agentic Commerce Protocol) check.
 */
class Check_Acp extends Check {

	/**
	 * Check id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'acp';
	}

	/**
	 * Category.
	 *
	 * @return string
	 */
	public function get_category(): string {
		return self::CATEGORY_COMMERCE;
	}

	/**
	 * Display name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'ACP (Agentic Commerce Protocol)';
	}

	/**
	 * Run the check.
	 *
	 * @return Check_Result
	 */
	public function run(): Check_Result {
		$evidence = array();

		$response = $this->http_get( $this->origin() . '/.well-known/acp.json', 'GET /.well-known/acp.json', $evidence );
		if ( '' !== $response['error'] && 0 === $response['code'] ) {
			return $this->unable( 'Could not reach /.well-known/acp.json — ' . $response['error'], $evidence );
		}

		if ( 404 === $response['code'] ) {
			return $this->fail( '/.well-known/acp.json returned 404', $evidence );
		}

		if ( 200 !== $response['code'] ) {
			return $this->fail( '/.well-known/acp.json returned HTTP ' . $response['code'], $evidence );
		}

		$document = json_decode( $response['body'], true );
		if ( ! is_array( $document ) ) {
			$evidence[] = Evidence::parse(
				'Parse ACP discovery document',
				'negative',
				'Response body is not valid JSON.'
			);

			return $this->fail( '/.well-known/acp.json is not valid JSON', $evidence );
		}

		$issues   = array();
		$protocol = isset( $document['protocol'] ) && is_array( $document['protocol'] ) ? $document['protocol'] : array();

		if ( ! isset( $protocol['name'] ) || 'acp' !== $protocol['name'] ) {
			$issues[] = 'protocol.name is not "acp"';
		}

		$version = isset( $protocol['version'] ) && is_scalar( $protocol['version'] ) ? (string) $protocol['version'] : '';
		if ( '' === $version ) {
			$issues[] = 'protocol.version is missing';
		}

		$api_base_url = isset( $document['api_base_url'] ) && is_string( $document['api_base_url'] ) ? $document['api_base_url'] : '';
		if ( ! preg_match( '#^https?://#i', $api_base_url ) ) {
			$issues[] = 'api_base_url is not an absolute http(s) URL';
		}

		if ( empty( $document['transports'] ) || ! is_array( $document['transports'] ) ) {
			$issues[] = 'transports is missing or empty';
		}

		$capabilities = isset( $document['capabilities'] ) && is_array( $document['capabilities'] ) ? $document['capabilities'] : array();
		if ( empty( $capabilities['services'] ) ) {
			$issues[] = 'capabilities.services is missing or empty';
		}

		if ( ! empty( $issues ) ) {
			$evidence[] = Evidence::parse(
				'Validate ACP discovery document',
				'negative',
				implode( '; ', $issues ) . '.'
			);

			return $this->fail( 'ACP discovery document is invalid — ' . implode( '; ', $issues ), $evidence );
		}

		$transport_count = count( $document['transports'] );

		$evidence[] = Evidence::parse(
			'Validate ACP discovery document',
			'positive',
			'protocol.name "acp", version ' . $version . ', absolute api_base_url, ' . $transport_count . ' transport(s), and capabilities.services present.'
		);

		return $this->pass(
			'Valid ACP discovery document at /.well-known/acp.json (version ' . $version . ', ' . $transport_count . ' transport(s))',
			$evidence
		);
	}
}
