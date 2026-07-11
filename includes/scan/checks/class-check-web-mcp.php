<?php
/**
 * Scan check: WebMCP (W3C WebMCP draft / Chrome EPP).
 *
 * The external scanner loads the page in a real browser and observes
 * navigator.modelContext tool registrations. No browser is available
 * server-side, so this check applies a static heuristic instead: it searches
 * the homepage HTML for `navigator.modelContext` usage and for same-origin
 * script URLs containing "webmcp" (fetching up to two of those scripts and
 * searching their bodies). A pass therefore means "WebMCP indicators found",
 * not "registration observed".
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
 * WebMCP check (static heuristic).
 */
class Check_Web_Mcp extends Check {

	/**
	 * Check id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'webMcp';
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
		return 'WebMCP';
	}

	/**
	 * Run the check.
	 *
	 * @return Check_Result
	 */
	public function run(): Check_Result {
		$evidence = array();

		$evidence[] = Evidence::parse(
			'Static WebMCP heuristic',
			'neutral',
			'This server-side scanner cannot execute JavaScript, so it searches the page source for navigator.modelContext usage and same-origin webmcp scripts — a static approximation of the external scanner\'s real-browser detection.'
		);

		$r = $this->http_get( $this->origin() . '/', 'GET / (homepage HTML)', $evidence );

		if ( '' !== $r['error'] && 0 === $r['code'] ) {
			return $this->unable( 'Could not reach the homepage — ' . $r['error'], $evidence );
		}

		// Indicator 1: navigator.modelContext in the page source (inline scripts).
		if ( false !== strpos( $r['body'], 'navigator.modelContext' ) ) {
			$evidence[] = Evidence::parse( 'Search inline scripts', 'positive', 'Found `navigator.modelContext` in the page source.' );
			return $this->pass(
				'WebMCP indicator found: navigator.modelContext appears in the page source (detected via static heuristic — registration confirmed only by a browser-based scan)',
				$evidence,
				array(
					'heuristic' => true,
					'indicator' => 'inline',
				)
			);
		}

		$evidence[] = Evidence::parse( 'Search inline scripts', 'neutral', 'No `navigator.modelContext` reference in the page source.' );

		// Indicator 2: same-origin script src URLs containing "webmcp".
		$srcs = $this->find_webmcp_script_srcs( $r['body'] );

		if ( empty( $srcs ) ) {
			$evidence[] = Evidence::parse( 'Search script tags', 'neutral', 'No same-origin script src containing "webmcp" found.' );
			return $this->fail(
				'No WebMCP tool registration detected on page load (static heuristic)',
				$evidence,
				array(
					'heuristic' => true,
					'indicator' => null,
				)
			);
		}

		$evidence[] = Evidence::parse(
			'Search script tags',
			'positive',
			count( $srcs ) . ' same-origin script(s) with "webmcp" in the URL: ' . implode( ', ', $srcs )
		);

		// Fetch up to two of those scripts and search their bodies — still a
		// static approximation; only a real browser can observe registration.
		$confirmed = false;
		foreach ( array_slice( $srcs, 0, 2 ) as $src ) {
			$sr = $this->http_get( $src, 'GET ' . $src, $evidence );

			if ( 200 !== $sr['code'] ) {
				$evidence[] = Evidence::parse( 'Search script body', 'neutral', 'Script not fetchable (HTTP ' . $sr['code'] . ') — cannot inspect its body statically.' );
				continue;
			}

			if ( false !== strpos( $sr['body'], 'navigator.modelContext' ) ) {
				$confirmed  = true;
				$evidence[] = Evidence::parse( 'Search script body', 'positive', 'Script body references `navigator.modelContext`.' );
				break;
			}

			$evidence[] = Evidence::parse( 'Search script body', 'neutral', 'Script fetched but its body has no `navigator.modelContext` reference.' );
		}

		$message = $confirmed
			? 'WebMCP indicator found: a same-origin webmcp script references navigator.modelContext (detected via static heuristic — registration confirmed only by a browser-based scan)'
			: 'WebMCP indicator found: same-origin webmcp script on the page (detected via static heuristic — registration confirmed only by a browser-based scan)';

		return $this->pass(
			$message,
			$evidence,
			array(
				'heuristic' => true,
				'indicator' => 'script',
			)
		);
	}

	/**
	 * Extract same-origin <script src> URLs containing "webmcp" from HTML.
	 *
	 * @param string $html Homepage HTML.
	 * @return string[] Absolute same-origin script URLs (deduplicated).
	 */
	private function find_webmcp_script_srcs( string $html ): array {
		if ( ! preg_match_all( '/<script\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']/i', $html, $matches ) ) {
			return array();
		}

		$origin      = $this->origin();
		$origin_host = strtolower( (string) wp_parse_url( $origin, PHP_URL_HOST ) );
		$scheme      = (string) wp_parse_url( $origin, PHP_URL_SCHEME );

		$found = array();
		foreach ( $matches[1] as $src ) {
			$src = html_entity_decode( $src, ENT_QUOTES );

			if ( false === stripos( $src, 'webmcp' ) ) {
				continue;
			}

			// Resolve to an absolute URL.
			if ( 0 === strpos( $src, '//' ) ) {
				$absolute = $scheme . ':' . $src;
			} elseif ( 0 === strpos( $src, 'http://' ) || 0 === strpos( $src, 'https://' ) ) {
				$absolute = $src;
			} elseif ( 0 === strpos( $src, '/' ) ) {
				$absolute = $origin . $src;
			} else {
				$absolute = $origin . '/' . $src;
			}

			$host = strtolower( (string) wp_parse_url( $absolute, PHP_URL_HOST ) );
			if ( $host !== $origin_host ) {
				continue;
			}

			$found[] = $absolute;
		}

		return array_values( array_unique( $found ) );
	}
}
