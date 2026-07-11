<?php
/**
 * Scan check: AP2 (commerce).
 *
 * AP2 depends on A2A: fetches /.well-known/agent-card.json and, when an A2A
 * Agent Card exists, looks for an ap2/payments extension declared in its
 * capabilities. Without an Agent Card the check fails — AP2 requires one.
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
 * AP2 check.
 */
class Check_Ap2 extends Check {

	/**
	 * Check id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'ap2';
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
		return 'AP2';
	}

	/**
	 * Run the check.
	 *
	 * @return Check_Result
	 */
	public function run(): Check_Result {
		$evidence = array();

		$response = $this->http_get( $this->origin() . '/.well-known/agent-card.json', 'GET /.well-known/agent-card.json', $evidence );
		if ( '' !== $response['error'] && 0 === $response['code'] ) {
			return $this->unable( 'Could not reach /.well-known/agent-card.json — ' . $response['error'], $evidence );
		}

		if ( 200 !== $response['code'] ) {
			return $this->fail( 'No A2A Agent Card found — AP2 requires an A2A Agent Card', $evidence );
		}

		$card = json_decode( $response['body'], true );
		if ( ! is_array( $card ) ) {
			$evidence[] = Evidence::parse(
				'Parse A2A Agent Card',
				'negative',
				'Response body is not valid JSON.'
			);

			return $this->fail( 'No A2A Agent Card found — AP2 requires an A2A Agent Card', $evidence );
		}

		$name       = isset( $card['name'] ) && is_scalar( $card['name'] ) ? (string) $card['name'] : '';
		$evidence[] = Evidence::parse(
			'Parse A2A Agent Card',
			'positive',
			'A2A Agent Card present' . ( '' !== $name ? ' (name: ' . $name . ')' : '' ) . '.'
		);

		$capabilities = isset( $card['capabilities'] ) && is_array( $card['capabilities'] ) ? $card['capabilities'] : array();
		$found_in     = $this->find_ap2_capability( $capabilities );

		if ( '' === $found_in ) {
			$evidence[] = Evidence::parse(
				'Locate AP2 payment capability',
				'negative',
				'No ap2/payments extension found in the Agent Card capabilities.'
			);

			return $this->fail( 'Agent Card has no AP2 payment capability', $evidence );
		}

		$evidence[] = Evidence::parse(
			'Locate AP2 payment capability',
			'positive',
			'AP2 payment capability declared: ' . $found_in . '.'
		);

		return $this->pass( 'Agent Card declares an AP2 payment capability (' . $found_in . ')', $evidence );
	}

	/**
	 * Look for an ap2/payments extension in the Agent Card capabilities.
	 *
	 * Checks `capabilities.extensions[].uri` for ap2/payment markers, then
	 * falls back to direct `ap2`/`payments` capability keys.
	 *
	 * @param array $capabilities Decoded capabilities object.
	 * @return string Where the capability was found, or '' when absent.
	 */
	private function find_ap2_capability( array $capabilities ): string {
		if ( isset( $capabilities['extensions'] ) && is_array( $capabilities['extensions'] ) ) {
			foreach ( $capabilities['extensions'] as $extension ) {
				if ( ! is_array( $extension ) ) {
					continue;
				}
				$uri = isset( $extension['uri'] ) && is_scalar( $extension['uri'] ) ? (string) $extension['uri'] : '';
				if ( '' === $uri ) {
					continue;
				}
				if ( false !== stripos( $uri, 'ap2' ) || false !== stripos( $uri, 'payment' ) ) {
					return 'extension ' . $uri;
				}
			}
		}

		foreach ( array( 'ap2', 'payments' ) as $key ) {
			if ( ! empty( $capabilities[ $key ] ) ) {
				return 'capabilities.' . $key;
			}
		}

		return '';
	}
}
