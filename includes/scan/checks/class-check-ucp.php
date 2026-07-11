<?php
/**
 * Scan check: Universal Commerce Protocol (commerce).
 *
 * Fetches /.well-known/ucp and validates the UCP profile declares a protocol
 * version plus services, capabilities, and endpoints (ucp.dev).
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
 * Universal Commerce Protocol check.
 */
class Check_Ucp extends Check {

	/**
	 * Check id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'ucp';
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
		return 'Universal Commerce Protocol';
	}

	/**
	 * Run the check.
	 *
	 * @return Check_Result
	 */
	public function run(): Check_Result {
		$evidence = array();

		$response = $this->http_get( $this->origin() . '/.well-known/ucp', 'GET /.well-known/ucp', $evidence );
		if ( '' !== $response['error'] && 0 === $response['code'] ) {
			return $this->unable( 'Could not reach /.well-known/ucp — ' . $response['error'], $evidence );
		}

		if ( 404 === $response['code'] ) {
			return $this->fail( 'UCP profile not found', $evidence );
		}

		if ( 200 !== $response['code'] ) {
			return $this->fail( '/.well-known/ucp returned HTTP ' . $response['code'], $evidence );
		}

		$profile = json_decode( $response['body'], true );
		if ( ! is_array( $profile ) ) {
			$evidence[] = Evidence::parse(
				'Parse UCP profile',
				'negative',
				'Response body is not valid JSON.'
			);

			return $this->fail( '/.well-known/ucp did not return valid JSON', $evidence );
		}

		$missing = array();
		if ( ! array_key_exists( 'protocol_version', $profile ) && ! array_key_exists( 'version', $profile ) ) {
			$missing[] = 'protocol_version';
		}
		foreach ( array( 'services', 'capabilities', 'endpoints' ) as $field ) {
			if ( ! array_key_exists( $field, $profile ) ) {
				$missing[] = $field;
			}
		}

		if ( ! empty( $missing ) ) {
			$evidence[] = Evidence::parse(
				'Validate UCP profile fields',
				'negative',
				'Missing required fields: ' . implode( ', ', $missing ) . '.'
			);

			return $this->fail( 'UCP profile is missing required fields: ' . implode( ', ', $missing ), $evidence );
		}

		$version = '';
		if ( isset( $profile['protocol_version'] ) && is_scalar( $profile['protocol_version'] ) ) {
			$version = (string) $profile['protocol_version'];
		} elseif ( isset( $profile['version'] ) && is_scalar( $profile['version'] ) ) {
			$version = (string) $profile['version'];
		}

		$evidence[] = Evidence::parse(
			'Validate UCP profile fields',
			'positive',
			'Profile declares protocol version, services, capabilities, and endpoints.'
		);

		return $this->pass(
			'UCP profile found at /.well-known/ucp' . ( '' !== $version ? ' (version ' . $version . ')' : '' ),
			$evidence
		);
	}
}
