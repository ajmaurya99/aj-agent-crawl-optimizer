<?php
/**
 * Scan check: A2A Agent Card (A2A protocol spec).
 *
 * Probes /.well-known/agent-card.json for JSON with name, version,
 * description, a non-empty supportedInterfaces array, capabilities, and
 * skills. Hidden from the default scan preset (mirrors the external
 * scanner), but runs when explicitly enabled.
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
 * A2A Agent Card check.
 */
class Check_A2a_Agent_Card extends Check {

	/**
	 * Check id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'a2aAgentCard';
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
		return 'A2A Agent Card';
	}

	/**
	 * Run the check.
	 *
	 * @return Check_Result
	 */
	public function run(): Check_Result {
		$evidence = array();

		$r = $this->http_get( $this->origin() . '/.well-known/agent-card.json', 'GET /.well-known/agent-card.json', $evidence );

		if ( '' !== $r['error'] && 0 === $r['code'] ) {
			return $this->unable( 'Could not reach /.well-known/agent-card.json — ' . $r['error'], $evidence );
		}

		if ( 404 === $r['code'] ) {
			return $this->fail( 'A2A Agent Card not found', $evidence );
		}

		if ( 200 !== $r['code'] ) {
			return $this->fail( '/.well-known/agent-card.json returned HTTP ' . $r['code'], $evidence );
		}

		$data = json_decode( $r['body'], true );
		if ( ! is_array( $data ) ) {
			$evidence[] = Evidence::parse( 'Parse agent card', 'negative', 'Returned 200 but the body is not valid JSON.' );
			return $this->fail( 'A2A Agent Card is not valid JSON', $evidence );
		}

		$missing = array();
		foreach ( array( 'name', 'version', 'description' ) as $field ) {
			if ( empty( $data[ $field ] ) ) {
				$missing[] = $field;
			}
		}
		if ( empty( $data['supportedInterfaces'] ) || ! is_array( $data['supportedInterfaces'] ) ) {
			$missing[] = 'supportedInterfaces (non-empty array)';
		}
		if ( ! isset( $data['capabilities'] ) ) {
			$missing[] = 'capabilities';
		}
		if ( ! isset( $data['skills'] ) ) {
			$missing[] = 'skills';
		}

		if ( ! empty( $missing ) ) {
			$evidence[] = Evidence::parse(
				'Validate agent card',
				'negative',
				'Card is missing required A2A fields: ' . implode( ', ', $missing ) . '.'
			);
			return $this->fail( 'A2A Agent Card found but missing: ' . implode( ', ', $missing ), $evidence );
		}

		$evidence[] = Evidence::parse(
			'Validate agent card',
			'positive',
			'name, version, description, supportedInterfaces, capabilities, and skills are all present.'
		);

		return $this->pass( 'A2A Agent Card found at /.well-known/agent-card.json', $evidence );
	}
}
