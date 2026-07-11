<?php
/**
 * Scan check: Web Bot Auth request signing (botAccessControl, informational).
 *
 * GETs /.well-known/http-message-signatures-directory expecting a JWKS with
 * at least one public key so the site can cryptographically identify the
 * bot/agent requests it sends (IETF WebBotAuth WG). Informational only:
 * absence yields neutral, never fail.
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
 * Web Bot Auth check.
 */
class Check_Web_Bot_Auth extends Check {

	/**
	 * Check id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'webBotAuth';
	}

	/**
	 * Category.
	 *
	 * @return string
	 */
	public function get_category(): string {
		return self::CATEGORY_BOT_ACCESS;
	}

	/**
	 * Display name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'Web Bot Auth request signing';
	}

	/**
	 * Informational-only: absence yields neutral, never fail.
	 *
	 * @return bool
	 */
	public function is_informational(): bool {
		return true;
	}

	/**
	 * Run the check.
	 *
	 * @return Check_Result
	 */
	public function run(): Check_Result {
		$evidence = array();

		$response = $this->http_get(
			$this->origin() . '/.well-known/http-message-signatures-directory',
			'GET /.well-known/http-message-signatures-directory',
			$evidence
		);

		if ( '' !== $response['error'] && 0 === $response['code'] ) {
			return $this->unable( 'Could not reach /.well-known/http-message-signatures-directory — ' . $response['error'], $evidence );
		}

		if ( 200 !== $response['code'] ) {
			$evidence[] = Evidence::parse(
				'Check signatures directory',
				'neutral',
				'HTTP ' . $response['code'] . ' — no Web Bot Auth key directory at the well-known path.'
			);
			return $this->neutral( 'Web Bot Auth directory not found (informational only)', $evidence );
		}

		$json = json_decode( $response['body'], true );
		if ( ! is_array( $json ) ) {
			$evidence[] = Evidence::parse(
				'Parse JWKS document',
				'neutral',
				'Response is not valid JSON — expected an RFC 7517 JWKS document.'
			);
			return $this->neutral( 'Web Bot Auth directory found but is not valid JSON (informational only)', $evidence );
		}

		if ( ! isset( $json['keys'] ) || ! is_array( $json['keys'] ) || 0 === count( $json['keys'] ) ) {
			$evidence[] = Evidence::parse(
				'Parse JWKS document',
				'neutral',
				'JSON has no non-empty "keys" array — a JWKS must list at least one public key.'
			);
			return $this->neutral( 'Web Bot Auth directory found but contains no keys (informational only)', $evidence );
		}

		$key_count = count( $json['keys'] );

		$evidence[] = Evidence::parse(
			'Parse JWKS document',
			'positive',
			'Valid JWKS with ' . $key_count . ' key' . ( 1 === $key_count ? '' : 's' ) . ' — agents can verify this site\'s signed requests.'
		);

		return $this->pass(
			'Web Bot Auth signatures directory found with ' . $key_count . ' key' . ( 1 === $key_count ? '' : 's' ),
			$evidence
		);
	}
}
