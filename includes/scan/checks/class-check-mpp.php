<?php
/**
 * Scan check: MPP (Machine Payment Protocol) (commerce).
 *
 * Fetches /openapi.json and looks for `x-payment-info` extensions on payable
 * operations (mpp.dev; paymentauth.org payment-discovery draft).
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
 * MPP (Machine Payment Protocol) check.
 */
class Check_Mpp extends Check {

	/**
	 * Check id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'mpp';
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
		return 'MPP (Machine Payment Protocol)';
	}

	/**
	 * Run the check.
	 *
	 * @return Check_Result
	 */
	public function run(): Check_Result {
		$evidence = array();

		$response = $this->http_get( $this->origin() . '/openapi.json', 'GET /openapi.json', $evidence );
		if ( '' !== $response['error'] && 0 === $response['code'] ) {
			return $this->unable( 'Could not reach /openapi.json — ' . $response['error'], $evidence );
		}

		if ( 404 === $response['code'] ) {
			return $this->fail( '/openapi.json returned 404', $evidence );
		}

		if ( 200 !== $response['code'] ) {
			return $this->fail( '/openapi.json returned HTTP ' . $response['code'], $evidence );
		}

		$document = json_decode( $response['body'], true );
		if ( ! is_array( $document ) ) {
			$evidence[] = Evidence::parse(
				'Parse OpenAPI document',
				'negative',
				'Response body is not valid JSON.'
			);

			return $this->fail( '/openapi.json is not valid JSON', $evidence );
		}

		$openapi_version = isset( $document['openapi'] ) && is_scalar( $document['openapi'] ) ? (string) $document['openapi'] : '';
		$evidence[]      = Evidence::parse(
			'Parse OpenAPI document',
			'positive',
			'Valid JSON OpenAPI document' . ( '' !== $openapi_version ? ' (openapi ' . $openapi_version . ')' : '' ) . '.'
		);

		if ( false === strpos( $response['body'], '"x-payment-info"' ) ) {
			return $this->fail( 'OpenAPI document has no x-payment-info extensions', $evidence );
		}

		$payable = $this->count_payment_operations( $document );
		if ( 0 === $payable ) {
			$evidence[] = Evidence::parse(
				'Locate x-payment-info extensions',
				'negative',
				'x-payment-info appears in the document but not on any operation under paths.'
			);

			return $this->fail( 'OpenAPI document has no x-payment-info extensions on any operation', $evidence );
		}

		$evidence[] = Evidence::parse(
			'Locate x-payment-info extensions',
			'positive',
			$payable . ' operation(s) under paths carry an x-payment-info extension.'
		);

		return $this->pass( 'MPP payment discovery detected — ' . $payable . ' payable operation(s) in /openapi.json', $evidence );
	}

	/**
	 * Count operations under `paths` that carry an `x-payment-info` extension
	 * (path-item-level extensions count too).
	 *
	 * @param array $document Decoded OpenAPI document.
	 * @return int
	 */
	private function count_payment_operations( array $document ): int {
		if ( empty( $document['paths'] ) || ! is_array( $document['paths'] ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $document['paths'] as $path_item ) {
			if ( ! is_array( $path_item ) ) {
				continue;
			}
			if ( array_key_exists( 'x-payment-info', $path_item ) ) {
				++$count;
			}
			foreach ( $path_item as $operation ) {
				if ( is_array( $operation ) && array_key_exists( 'x-payment-info', $operation ) ) {
					++$count;
				}
			}
		}

		return $count;
	}
}
