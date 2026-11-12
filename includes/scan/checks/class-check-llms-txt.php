<?php
/**
 * Scan check: llms.txt (id `llmsTxt`, contentAccessibility).
 *
 * Fetches /llms.txt and lints it the way hosted scanners do: it must exist,
 * open with an H1, contain Markdown links, and those links must resolve
 * (a sample of same-origin links is fetched). Size is reported as evidence.
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
 * The llms.txt check.
 */
class Check_Llms_Txt extends Check {

	/**
	 * Same-origin links fetched to verify they resolve.
	 */
	const LINK_SAMPLE = 6;

	/**
	 * Check id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'llmsTxt';
	}

	/**
	 * Category.
	 *
	 * @return string
	 */
	public function get_category(): string {
		return self::CATEGORY_CONTENT;
	}

	/**
	 * Display name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'llms.txt';
	}

	/**
	 * Run the check.
	 *
	 * @return Check_Result
	 */
	public function run(): Check_Result {
		$evidence = array();
		$origin   = $this->origin();
		$res      = $this->http_get( $origin . '/llms.txt', 'GET /llms.txt', $evidence );

		if ( '' !== $res['error'] && 0 === $res['code'] ) {
			return $this->unable( 'Could not reach /llms.txt — ' . $res['error'], $evidence );
		}
		if ( 200 !== $res['code'] ) {
			return $this->fail( 'No /llms.txt (HTTP ' . $res['code'] . ')', $evidence );
		}

		$body  = $res['body'];
		$bytes = strlen( $body );
		$ctype = isset( $res['headers']['content-type'] ) ? $res['headers']['content-type'] : '';

		if ( false !== stripos( $ctype, 'text/html' ) || preg_match( '/^\s*<(!doctype|html)/i', $body ) ) {
			$evidence[] = Evidence::parse( 'Inspect response', 'negative', 'The response is an HTML page, not a Markdown file.' );
			return $this->fail( '/llms.txt returns an HTML page (HTTP 200) — likely a theme 404 or a redirect to the homepage', $evidence );
		}

		$lines = preg_split( '/\r\n|\r|\n/', $body );
		$first = '';
		foreach ( $lines as $line ) {
			if ( '' !== trim( $line ) ) {
				$first = trim( $line );
				break;
			}
		}
		$has_h1    = 0 === strpos( $first, '# ' );
		$has_quote = (bool) preg_match( '/^\s*>\s*\S/m', $body );

		preg_match_all( '/\[[^\]]*\]\((https?:\/\/[^)\s]+)\)/', $body, $m );
		$links = array_values( array_unique( $m[1] ) );

		$evidence[] = Evidence::parse(
			'Lint structure',
			$has_h1 && ! empty( $links ) ? 'positive' : 'negative',
			sprintf(
				'%s; %s; %d Markdown links; %s bytes.',
				$has_h1 ? 'opens with an H1' : 'does not open with an H1',
				$has_quote ? 'has a blockquote summary' : 'no blockquote summary',
				count( $links ),
				number_format_i18n( $bytes )
			)
		);

		$host   = wp_parse_url( $origin, PHP_URL_HOST );
		$sample = array();
		foreach ( $links as $link ) {
			if ( 0 === strcasecmp( (string) wp_parse_url( $link, PHP_URL_HOST ), (string) $host ) ) {
				$sample[] = $link;
			}
			if ( count( $sample ) >= self::LINK_SAMPLE ) {
				break;
			}
		}

		$broken = array();
		foreach ( $sample as $link ) {
			$path = wp_parse_url( $link, PHP_URL_PATH );
			$r    = $this->http_get( $link, 'GET ' . ( is_string( $path ) ? $path : $link ), $evidence, array( 'timeout' => 6 ) );
			// 401/403 means the resource exists but wants credentials (an MCP
			// server, a protected API) — that link resolves; only a missing or
			// erroring target is broken.
			if ( $r['code'] < 200 || ( $r['code'] >= 400 && ! in_array( $r['code'], array( 401, 403 ), true ) ) ) {
				$broken[] = $link . ' (' . ( $r['code'] > 0 ? 'HTTP ' . $r['code'] : $r['error'] ) . ')';
			}
		}

		$details = array(
			'bytes'       => $bytes,
			'links'       => count( $links ),
			'sampled'     => count( $sample ),
			'broken'      => $broken,
			'hasH1'       => $has_h1,
			'hasSummary'  => $has_quote,
			'contentType' => $ctype,
		);

		if ( ! $has_h1 ) {
			return $this->fail( '/llms.txt does not start with an H1 title (llmstxt.org requires one)', $evidence, $details );
		}
		if ( empty( $links ) ) {
			return $this->fail( '/llms.txt contains no Markdown links — agents have nothing to follow', $evidence, $details );
		}
		if ( ! empty( $broken ) ) {
			return $this->fail(
				sprintf( '%d of %d sampled llms.txt links do not resolve: %s', count( $broken ), count( $sample ), implode( ', ', $broken ) ),
				$evidence,
				$details
			);
		}

		return $this->pass(
			sprintf( '/llms.txt is well-formed (%d links, %s sampled links resolve, %s bytes)', count( $links ), count( $sample ) . '/' . count( $sample ), number_format_i18n( $bytes ) ),
			$evidence,
			$details
		);
	}
}
