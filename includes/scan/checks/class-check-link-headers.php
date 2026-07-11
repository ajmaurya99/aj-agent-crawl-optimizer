<?php
/**
 * Scan check: Link headers (id `linkHeaders`).
 *
 * The homepage response must carry a `Link` header pointing agents to
 * machine-readable resources (RFC 8288; relations like `api-catalog`,
 * `service-desc`, `describedby` per RFC 9727 §3). Mirrors the
 * isitagentready.com `linkHeaders` check.
 *
 * Note: this probes the live HTTP response of our own origin. The plugin's
 * own send_headers hooks do not run inside this wp_remote_get from CLI/cron
 * contexts unless the front end actually emits them — which is exactly the
 * point of verification: we report what agents really receive.
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
 * Link headers check.
 */
class Check_Link_Headers extends Check {

	/**
	 * Check id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'linkHeaders';
	}

	/**
	 * Category.
	 *
	 * @return string
	 */
	public function get_category(): string {
		return self::CATEGORY_DISCOVERABILITY;
	}

	/**
	 * Display name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'Link headers';
	}

	/**
	 * Run the check.
	 *
	 * @return Check_Result
	 */
	public function run(): Check_Result {
		$evidence = array();

		$response = $this->http_get( $this->origin() . '/', 'GET /', $evidence );

		if ( '' !== $response['error'] && 0 === $response['code'] ) {
			return $this->unable( 'Could not reach the homepage — ' . $response['error'], $evidence );
		}

		$header = isset( $response['headers']['link'] ) ? trim( $response['headers']['link'] ) : '';

		if ( '' === $header ) {
			$evidence[] = Evidence::parse(
				'Parse Link header relations',
				'negative',
				'No Link header present on the homepage response (RFC 8288).'
			);
			return $this->fail(
				'No Link header on the homepage response',
				$evidence,
				array(
					'relationsFound' => array(),
					'totalLinks'     => 0,
				)
			);
		}

		$links     = $this->split_link_values( $header );
		$relations = array();

		foreach ( $links as $value ) {
			$href = '';
			if ( preg_match( '/<([^>]*)>/', $value, $m ) ) {
				$href = trim( $m[1] );
			}
			if ( '' === $href ) {
				continue;
			}

			$rel = '';
			if ( preg_match( '/rel\s*=\s*"([^"]*)"/i', $value, $m ) ) {
				$rel = trim( $m[1] );
			} elseif ( preg_match( '/rel\s*=\s*([^;,"\s]+)/i', $value, $m ) ) {
				$rel = trim( $m[1] );
			}

			if ( '' === $rel ) {
				$relations[] = array(
					'rel'  => '',
					'href' => $href,
				);
				continue;
			}

			// A single rel parameter may carry multiple space-separated
			// relation types (RFC 8288 §3.3) — record each one.
			foreach ( preg_split( '/\s+/', $rel ) as $token ) {
				if ( '' !== $token ) {
					$relations[] = array(
						'rel'  => strtolower( $token ),
						'href' => $href,
					);
				}
			}
		}

		$rel_names = array();
		foreach ( $relations as $relation ) {
			if ( '' !== $relation['rel'] && ! in_array( $relation['rel'], $rel_names, true ) ) {
				$rel_names[] = $relation['rel'];
			}
		}

		$evidence[] = Evidence::parse(
			'Parse Link header relations',
			empty( $rel_names ) ? 'negative' : 'positive',
			empty( $rel_names )
				? sprintf( 'Link header present with %d link value(s), but no rel parameters could be parsed.', count( $links ) )
				: sprintf( 'Found %d link value(s) with relation(s): %s.', count( $links ), implode( ', ', $rel_names ) )
		);

		// Presence alone is not enough: every WP site emits rel="https://api.w.org/"
		// and most themes emit preload/preconnect links. The external scanner
		// requires an agent-useful relation type (RFC 9727 §3 / RFC 8288).
		$agent_rels = array( 'api-catalog', 'service-desc', 'service-doc', 'service-meta', 'describedby' );
		$useful     = array_values( array_intersect( $agent_rels, $rel_names ) );

		$evidence[] = Evidence::parse(
			'Match agent-useful relations',
			empty( $useful ) ? 'negative' : 'positive',
			empty( $useful )
				? 'No agent-useful relation types (api-catalog, service-desc, service-doc, service-meta, describedby) found.'
				: 'Agent-useful relation(s) found: ' . implode( ', ', $useful ) . '.'
		);

		$details = array(
			'relationsFound'  => $relations,
			'totalLinks'      => count( $links ),
			'agentUsefulRels' => $useful,
		);

		if ( empty( $useful ) ) {
			return $this->fail( 'Link headers present but no agent-useful relation types found', $evidence, $details );
		}

		return $this->pass(
			'Link header advertises agent-useful relation(s): ' . implode( ', ', $useful ),
			$evidence,
			$details
		);
	}

	/**
	 * Split a (possibly combined) Link header into individual link values —
	 * on commas outside <...> URI references.
	 *
	 * @param string $header Raw Link header value.
	 * @return string[]
	 */
	private function split_link_values( string $header ): array {
		$values   = array();
		$current  = '';
		$in_angle = false;
		$length   = strlen( $header );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $header[ $i ];

			if ( '<' === $char ) {
				$in_angle = true;
			} elseif ( '>' === $char ) {
				$in_angle = false;
			}

			if ( ',' === $char && ! $in_angle ) {
				$values[] = trim( $current );
				$current  = '';
				continue;
			}

			$current .= $char;
		}
		$values[] = trim( $current );

		return array_values(
			array_filter(
				$values,
				static function ( $value ) {
					return '' !== $value;
				}
			)
		);
	}
}
