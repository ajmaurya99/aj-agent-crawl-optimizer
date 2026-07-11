<?php
/**
 * Scan check: robots.txt (id `robotsTxt`).
 *
 * GET /robots.txt must return 200 with a text/plain-ish Content-Type and parse
 * with at least one `User-agent` directive (RFC 9309). Mirrors the
 * isitagentready.com `robotsTxt` check.
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
 * robots.txt check.
 */
class Check_Robots_Txt extends Check {

	/**
	 * Check id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'robotsTxt';
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
		return 'robots.txt';
	}

	/**
	 * Run the check.
	 *
	 * @return Check_Result
	 */
	public function run(): Check_Result {
		$evidence = array();

		$response = $this->http_get( $this->origin() . '/robots.txt', 'GET /robots.txt', $evidence );

		if ( '' !== $response['error'] && 0 === $response['code'] ) {
			return $this->unable( 'Could not reach /robots.txt — ' . $response['error'], $evidence );
		}

		if ( 200 !== $response['code'] ) {
			$evidence[] = Evidence::parse(
				'Validate robots.txt structure',
				'negative',
				'Expected HTTP 200, got ' . $response['code'] . '.'
			);
			return $this->fail( 'robots.txt not found (HTTP ' . $response['code'] . ')', $evidence );
		}

		$content_type = isset( $response['headers']['content-type'] ) ? strtolower( $response['headers']['content-type'] ) : '';

		// "text/plain-ish": accept any text/plain variant (charset params etc.);
		// a missing Content-Type header is tolerated, a wrong one is not.
		if ( '' !== $content_type && false === strpos( $content_type, 'text/plain' ) ) {
			$evidence[] = Evidence::parse(
				'Validate robots.txt structure',
				'negative',
				'Unexpected Content-Type "' . $content_type . '" — robots.txt should be served as text/plain (RFC 9309).'
			);
			return $this->fail( 'robots.txt is not served as text/plain (got ' . $content_type . ')', $evidence );
		}

		$user_agent_groups = $this->count_user_agent_directives( $response['body'] );

		if ( 0 === $user_agent_groups ) {
			$evidence[] = Evidence::parse(
				'Validate robots.txt structure',
				'negative',
				'No User-agent directives found — RFC 9309 requires at least one group.'
			);
			return $this->fail( 'robots.txt has no User-agent directives', $evidence );
		}

		$evidence[] = Evidence::parse(
			'Validate robots.txt structure',
			'positive',
			sprintf( 'Served as text/plain with %d User-agent directive(s).', $user_agent_groups )
		);

		return $this->pass( 'robots.txt exists with valid format', $evidence );
	}

	/**
	 * Count `User-agent:` directives (comments stripped, RFC 9309 line syntax).
	 *
	 * @param string $body robots.txt body.
	 * @return int
	 */
	private function count_user_agent_directives( string $body ): int {
		$count = 0;
		$lines = preg_split( '/\r\n|\r|\n/', $body );

		foreach ( $lines as $line ) {
			$line = trim( (string) preg_replace( '/#.*$/', '', $line ) );
			if ( '' === $line ) {
				continue;
			}
			if ( preg_match( '/^user-agent\s*:\s*\S/i', $line ) ) {
				$count++;
			}
		}

		return $count;
	}
}
