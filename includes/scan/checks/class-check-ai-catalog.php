<?php
/**
 * Scan check: AI Catalog (id `aiCatalog`, discovery).
 *
 * GETs /.well-known/ard.json (ARD v0.91), falling back to the predecessor
 * /.well-known/ai-catalog.json, and passes on a JSON document with a
 * `specVersion` and a non-empty `entries` array whose items carry a media
 * `type` and a `url` (AgentReady AR-FIND-04).
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
 * AI Catalog check.
 */
class Check_Ai_Catalog extends Check {

	/**
	 * Check id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'aiCatalog';
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
		return 'AI Catalog';
	}

	/**
	 * Run the check.
	 *
	 * @return Check_Result
	 */
	public function run(): Check_Result {
		$evidence = array();
		$path     = '/.well-known/ard.json';
		$res      = $this->http_get( $this->origin() . $path, 'GET ' . $path, $evidence );

		if ( '' !== $res['error'] && 0 === $res['code'] ) {
			return $this->unable( 'Could not reach ' . $path . ' — ' . $res['error'], $evidence );
		}
		if ( 200 !== $res['code'] ) {
			// Predecessor path, still recognised by scanners.
			$path = '/.well-known/ai-catalog.json';
			$res  = $this->http_get( $this->origin() . $path, 'GET ' . $path, $evidence );
		}
		if ( 200 !== $res['code'] ) {
			return $this->fail( 'No agent resource catalog at /.well-known/ard.json or /.well-known/ai-catalog.json (HTTP ' . $res['code'] . ')', $evidence );
		}

		$json = json_decode( $res['body'], true );
		if ( ! is_array( $json ) ) {
			$evidence[] = Evidence::parse( 'Parse JSON', 'negative', 'Response body is not valid JSON.' );
			return $this->fail( $path . ' is not valid JSON', $evidence );
		}

		$entries = isset( $json['entries'] ) && is_array( $json['entries'] ) ? $json['entries'] : array();
		$valid   = 0;
		foreach ( $entries as $entry ) {
			if ( is_array( $entry ) && ! empty( $entry['type'] ) && ! empty( $entry['url'] ) ) {
				++$valid;
			}
		}
		$has_version = isset( $json['specVersion'] ) && is_string( $json['specVersion'] );

		$details = array(
			'specVersion'  => $has_version ? $json['specVersion'] : null,
			'entries'      => count( $entries ),
			'validEntries' => $valid,
		);

		if ( ! $has_version || 0 === $valid ) {
			$evidence[] = Evidence::parse(
				'Validate catalog shape',
				'negative',
				( $has_version ? 'specVersion present' : 'specVersion missing' ) . '; ' . $valid . ' entries with type + url.'
			);
			return $this->fail( 'AI Catalog is missing ' . ( $has_version ? 'entries with a type and url' : 'specVersion' ), $evidence, $details );
		}

		$evidence[] = Evidence::parse( 'Validate catalog shape', 'positive', 'specVersion ' . $json['specVersion'] . '; ' . $valid . ' valid entries.' );
		return $this->pass( 'Agent resource catalog at ' . $path . ' with ' . $valid . ' entr' . ( 1 === $valid ? 'y' : 'ies' ), $evidence, $details );
	}
}
