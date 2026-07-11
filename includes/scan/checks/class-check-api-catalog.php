<?php
/**
 * Scan check: API Catalog (RFC 9727).
 *
 * Probes /.well-known/api-catalog for valid JSON containing a `linkset` array
 * (RFC 9264) whose entries carry an anchor plus a service-desc / service-doc /
 * status relation. The application/linkset+json media type is recommended and
 * reported in evidence, but not required for a pass — mirroring the external
 * scanner.
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
 * API Catalog check.
 */
class Check_Api_Catalog extends Check {

	/**
	 * Check id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'apiCatalog';
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
		return 'API Catalog';
	}

	/**
	 * Run the check.
	 *
	 * @return Check_Result
	 */
	public function run(): Check_Result {
		$evidence = array();

		$r = $this->http_get( $this->origin() . '/.well-known/api-catalog', 'GET /.well-known/api-catalog', $evidence );

		if ( '' !== $r['error'] && 0 === $r['code'] ) {
			return $this->unable( 'Could not reach /.well-known/api-catalog — ' . $r['error'], $evidence );
		}

		if ( 200 !== $r['code'] ) {
			return $this->fail( 'No API catalog found at /.well-known/api-catalog (HTTP ' . $r['code'] . ')', $evidence );
		}

		$data = json_decode( $r['body'], true );
		if ( ! is_array( $data ) ) {
			$evidence[] = Evidence::parse( 'Parse API catalog JSON', 'negative', 'Response body is not valid JSON.' );
			return $this->fail( 'API catalog at /.well-known/api-catalog is not valid JSON', $evidence );
		}

		if ( ! isset( $data['linkset'] ) || ! is_array( $data['linkset'] ) ) {
			$evidence[] = Evidence::parse( 'Parse API catalog JSON', 'negative', 'JSON parsed but contains no `linkset` array (RFC 9264).' );
			return $this->fail( 'API catalog JSON does not contain a linkset array', $evidence );
		}

		$linkset_entries = count( $data['linkset'] );
		$evidence[]      = Evidence::parse(
			'Parse API catalog JSON',
			'positive',
			'Valid JSON with a linkset array containing ' . $linkset_entries . ' ' . ( 1 === $linkset_entries ? 'entry' : 'entries' ) . '.'
		);

		// The media type is recommended, not required — the external scanner
		// passes catalogs served with a different Content-Type.
		$content_type         = isset( $r['headers']['content-type'] ) ? $r['headers']['content-type'] : '';
		$correct_content_type = ( false !== strpos( $content_type, 'application/linkset+json' ) );

		$evidence[] = Evidence::parse(
			'Check Content-Type',
			$correct_content_type ? 'positive' : 'neutral',
			$correct_content_type
				? 'Served as application/linkset+json (recommended RFC 9264 media type).'
				: 'Served as `' . ( '' !== $content_type ? $content_type : 'unknown' ) . '` — application/linkset+json is recommended but not required for a pass.'
		);

		$api_count = 0;
		foreach ( $data['linkset'] as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['anchor'] ) ) {
				continue;
			}
			if ( isset( $entry['service-desc'] ) || isset( $entry['service-doc'] ) || isset( $entry['status'] ) ) {
				$api_count++;
			}
		}

		$details = array(
			'apiCount'           => $api_count,
			'linksetEntries'     => $linkset_entries,
			'correctContentType' => $correct_content_type,
		);

		if ( 0 === $api_count ) {
			$evidence[] = Evidence::parse(
				'Validate linkset entries',
				'negative',
				'No linkset entry has an anchor plus a service-desc, service-doc, or status relation (RFC 9727).'
			);
			return $this->fail(
				'API catalog found but no linkset entry describes an API (anchor plus service-desc/service-doc/status required)',
				$evidence,
				$details
			);
		}

		$evidence[] = Evidence::parse(
			'Validate linkset entries',
			'positive',
			$api_count . ' of ' . $linkset_entries . ' linkset ' . ( 1 === $linkset_entries ? 'entry describes' : 'entries describe' ) . ' an API (anchor plus service relation).'
		);

		return $this->pass(
			'API catalog found at /.well-known/api-catalog describing ' . $api_count . ' API' . ( 1 === $api_count ? '' : 's' ),
			$evidence,
			$details
		);
	}
}
