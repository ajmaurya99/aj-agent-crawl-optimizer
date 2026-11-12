<?php
/**
 * Scan check: Content without JavaScript (id `contentNoJs`,
 * contentAccessibility).
 *
 * Most agents fetch raw HTML and never run scripts (AgentReady AR-READ-01).
 * Fetches the homepage, strips script/style/noscript, and passes when at
 * least 500 characters of readable text and an H1 remain.
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
 * Content-without-JS check.
 */
class Check_Content_No_Js extends Check {

	/**
	 * Minimum readable characters in the raw HTML.
	 */
	const MIN_CHARS = 500;

	/**
	 * Check id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'contentNoJs';
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
		return 'Content without JavaScript';
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
		if ( 200 !== $res['code'] ) {
			return $this->fail( 'Homepage returned HTTP ' . $res['code'], $evidence );
		}

		$html = $res['body'];
		$body = preg_match( '#<body[^>]*>(.*)</body>#is', $html, $m ) ? $m[1] : $html;
		$body = preg_replace( '#<(script|style|noscript|template|svg)\b[^>]*>.*?</\1>#is', '', $body );
		$body = preg_replace( '#<!--.*?-->#s', '', $body );
		$text = html_entity_decode( wp_strip_all_tags( (string) $body ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
		$len  = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );

		$h1_count = preg_match_all( '#<h1\b#i', $html );
		$has_main = (bool) preg_match( '#<(main|article)\b#i', $html );

		$details = array(
			'textChars'   => $len,
			'h1Count'     => $h1_count,
			'hasLandmark' => $has_main,
		);

		$evidence[] = Evidence::parse(
			'Strip scripts and measure readable text',
			$len >= self::MIN_CHARS ? 'positive' : 'negative',
			sprintf( '%d readable characters in the raw HTML (minimum %d).', $len, self::MIN_CHARS )
		);
		$evidence[] = Evidence::parse(
			'Check document structure',
			$h1_count > 0 ? 'positive' : 'negative',
			sprintf( '%d <h1>; %s landmark.', $h1_count, $has_main ? 'has a <main>/<article>' : 'no <main>/<article>' )
		);

		if ( $len < self::MIN_CHARS ) {
			return $this->fail(
				sprintf( 'Only %d characters of readable text in the raw HTML — the homepage depends on JavaScript to render', $len ),
				$evidence,
				$details
			);
		}
		if ( 0 === $h1_count ) {
			return $this->fail( 'Homepage HTML has readable text but no <h1> heading', $evidence, $details );
		}

		return $this->pass(
			sprintf( 'Homepage serves %d characters of readable text and an <h1> without JavaScript', $len ),
			$evidence,
			$details
		);
	}
}
