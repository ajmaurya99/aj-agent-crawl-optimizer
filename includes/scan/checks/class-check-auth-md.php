<?php
/**
 * Scan check: Auth.md agent registration (WorkOS Auth.md standard).
 *
 * Probes /auth.md for a Markdown document whose first H1 identifies it as
 * Auth.md — human- and agent-readable instructions for registering an agent
 * with the site. Registration endpoints are never probed during passive
 * scans.
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
 * Auth.md check.
 */
class Check_Auth_Md extends Check {

	/**
	 * Check id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'authMd';
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
		return 'Auth.md agent registration';
	}

	/**
	 * Run the check.
	 *
	 * @return Check_Result
	 */
	public function run(): Check_Result {
		$evidence = array();

		$r = $this->http_get( $this->origin() . '/auth.md', 'GET /auth.md', $evidence );

		if ( '' !== $r['error'] && 0 === $r['code'] ) {
			return $this->unable( 'Could not reach /auth.md — ' . $r['error'], $evidence );
		}

		if ( 404 === $r['code'] ) {
			return $this->fail( 'auth.md not found', $evidence );
		}

		if ( 200 !== $r['code'] ) {
			return $this->fail( '/auth.md returned HTTP ' . $r['code'], $evidence );
		}

		// Find the first Markdown H1 line (a line starting with "# ").
		$body = preg_replace( '/^\xEF\xBB\xBF/', '', $r['body'] );
		$h1   = '';
		foreach ( preg_split( '/\r\n|\r|\n/', $body ) as $line ) {
			if ( 0 === strpos( $line, '# ' ) ) {
				$h1 = trim( $line );
				break;
			}
		}

		if ( '' === $h1 ) {
			$evidence[] = Evidence::parse(
				'Parse auth.md heading',
				'negative',
				'Response has no Markdown H1 line (`# ...`) — the body does not look like an Auth.md document.'
			);
			return $this->fail( '/auth.md returned 200 but is not a Markdown document with an Auth.md H1', $evidence );
		}

		$h1_preview = mb_substr( $h1, 0, 120 );

		if ( false === stripos( $h1, 'auth.md' ) ) {
			$evidence[] = Evidence::parse(
				'Parse auth.md heading',
				'negative',
				'First H1 is `' . $h1_preview . '` — it does not mention "auth.md", so the document does not identify itself as Auth.md agent registration instructions.'
			);
			return $this->fail( '/auth.md found but its first H1 does not contain "auth.md"', $evidence );
		}

		$evidence[] = Evidence::parse(
			'Parse auth.md heading',
			'positive',
			'First H1 `' . $h1_preview . '` identifies the document as Auth.md.'
		);

		return $this->pass( 'auth.md found — agents can read registration instructions at /auth.md', $evidence );
	}
}
