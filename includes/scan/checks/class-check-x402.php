<?php
/**
 * Scan check: x402 Protocol (commerce).
 *
 * Detects agent-native HTTP-402 payments: confirms the homepage is not
 * payment-gated, then probes /api and /api/v1 for a 402 response carrying
 * x402 payment requirements. The external Coinbase CDP x402 Bazaar registry
 * lookup performed by isitagentready.com is skipped here — the self-scan only
 * probes its own origin.
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
 * The x402 Protocol check.
 */
class Check_X402 extends Check {

	/**
	 * Check id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'x402';
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
		return 'x402 Protocol';
	}

	/**
	 * Run the check.
	 *
	 * @return Check_Result
	 */
	public function run(): Check_Result {
		$evidence = array();

		$home = $this->http_get( $this->origin() . '/', 'GET /', $evidence );
		if ( '' !== $home['error'] && 0 === $home['code'] ) {
			return $this->unable( 'Could not reach the homepage — ' . $home['error'], $evidence );
		}

		if ( 402 === $home['code'] ) {
			$evidence[] = Evidence::parse(
				'Check homepage status',
				'neutral',
				'Homepage returned HTTP 402 — the whole origin appears payment-gated.'
			);
		} else {
			$evidence[] = Evidence::parse(
				'Check homepage status',
				'neutral',
				'Homepage returned HTTP ' . $home['code'] . ' (not 402) — the site itself is not payment-gated.'
			);
		}

		foreach ( array( '/api', '/api/v1' ) as $path ) {
			$response = $this->http_get( $this->origin() . $path, 'GET ' . $path, $evidence );

			if ( 402 !== $response['code'] ) {
				continue;
			}

			if ( $this->has_payment_requirements( $response['body'] ) ) {
				$evidence[] = Evidence::parse(
					'Inspect 402 payment requirements',
					'positive',
					$path . ' returned HTTP 402 with x402 payment requirements in the response body.'
				);
				$this->add_registry_note( $evidence );

				return $this->pass( 'x402 payment protocol detected at ' . $path . ' (HTTP 402 with payment requirements)', $evidence );
			}

			$evidence[] = Evidence::parse(
				'Inspect 402 payment requirements',
				'negative',
				$path . ' returned HTTP 402 but the body carries no recognizable x402 payment requirements.'
			);
		}

		$this->add_registry_note( $evidence );

		return $this->fail( 'x402 payment protocol not detected', $evidence );
	}

	/**
	 * Whether a 402 response body looks like x402 payment-requirements JSON
	 * ('x402' marker, or 'accepts'/'payTo' style fields).
	 *
	 * @param string $body Response body.
	 * @return bool
	 */
	private function has_payment_requirements( string $body ): bool {
		if ( false !== strpos( $body, 'x402' ) ) {
			return true;
		}

		return false !== strpos( $body, '"accepts"' ) || false !== strpos( $body, '"payTo"' );
	}

	/**
	 * Note that the external Bazaar registry lookup is skipped in self-scan.
	 *
	 * @param array $evidence Evidence array, appended to by reference.
	 * @return void
	 */
	private function add_registry_note( array &$evidence ): void {
		$evidence[] = Evidence::parse(
			'Coinbase x402 Bazaar registry',
			'neutral',
			'External registry lookup (api.cdp.coinbase.com x402 Bazaar) skipped — self-scan probes this origin only.'
		);
	}
}
