<?php
/**
 * Scan check: Markdown alternate link (id `markdownAlternate`,
 * contentAccessibility).
 *
 * The homepage must advertise its Markdown representation with
 * `<link rel="alternate" type="text/markdown">` or an equivalent Link
 * header (AgentReady AR-READ-09), and the advertised URL must really serve
 * Markdown.
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
 * Markdown alternate link check.
 */
class Check_Markdown_Alternate extends Check {

	/**
	 * Check id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'markdownAlternate';
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
		return 'Markdown alternate link';
	}

	/**
	 * Run the check.
	 *
	 * @return Check_Result
	 */
	public function run(): Check_Result {
		$evidence = array();
		$res      = $this->http_get( $this->origin() . '/', 'GET /', $evidence );

		if ( '' !== $res['error'] && 0 === $res['code'] ) {
			return $this->unable( 'Could not reach the homepage — ' . $res['error'], $evidence );
		}

		$href   = '';
		$source = '';

		// <link rel="alternate" type="text/markdown" href="…"> in any attribute order.
		if ( preg_match_all( '#<link\b[^>]*>#i', $res['body'], $tags ) ) {
			foreach ( $tags[0] as $tag ) {
				if ( preg_match( '#\brel=["\']alternate["\']#i', $tag )
					&& preg_match( '#\btype=["\']text/markdown["\']#i', $tag )
					&& preg_match( '#\bhref=["\']([^"\']+)["\']#i', $tag, $h ) ) {
					$href   = html_entity_decode( $h[1], ENT_QUOTES );
					$source = 'link tag';
					break;
				}
			}
		}

		// Link: <…>; rel="alternate"; type="text/markdown".
		if ( '' === $href && isset( $res['headers']['link'] ) ) {
			foreach ( explode( ',', $res['headers']['link'] ) as $part ) {
				if ( preg_match( '#<([^>]+)>#', $part, $u )
					&& preg_match( '#rel="?alternate"?#i', $part )
					&& preg_match( '#type="?text/markdown"?#i', $part ) ) {
					$href   = trim( $u[1] );
					$source = 'Link header';
					break;
				}
			}
		}

		if ( '' === $href ) {
			$evidence[] = Evidence::parse( 'Look for a markdown alternate', 'negative', 'No <link rel="alternate" type="text/markdown"> tag and no matching Link header.' );
			return $this->fail( 'Homepage does not advertise a Markdown alternate (no rel="alternate" type="text/markdown")', $evidence );
		}

		$evidence[] = Evidence::parse( 'Look for a markdown alternate', 'positive', 'Found via ' . $source . ': ' . $href );

		if ( 0 === strpos( $href, '/' ) ) {
			$href = $this->origin() . $href;
		}

		$target = $this->http_get( $href, 'GET ' . $href . ' (Accept: text/markdown)', $evidence, array( 'headers' => array( 'Accept' => 'text/markdown' ) ) );
		$ctype  = isset( $target['headers']['content-type'] ) ? $target['headers']['content-type'] : '';

		if ( 200 !== $target['code'] || 0 !== stripos( $ctype, 'text/markdown' ) ) {
			return $this->fail(
				'Markdown alternate is advertised but ' . $href . ' returned ' . ( 200 !== $target['code'] ? 'HTTP ' . $target['code'] : ( '' !== $ctype ? $ctype : 'no Content-Type' ) ),
				$evidence,
				array(
					'href'   => $href,
					'source' => $source,
				)
			);
		}

		return $this->pass(
			'Homepage advertises a Markdown alternate (' . $source . ') that serves text/markdown',
			$evidence,
			array(
				'href'   => $href,
				'source' => $source,
			)
		);
	}
}
