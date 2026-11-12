<?php
/**
 * Scan check: Identity metadata (id `metadata`, discoverability).
 *
 * Fetches the homepage HTML and looks for the four identity signals agents
 * rely on to understand and cite a page: a declared language, a canonical
 * URL, og:type and og:image.
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
 * Metadata completeness check.
 */
class Check_Metadata extends Check {

	/**
	 * Check id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'metadata';
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
		return 'Identity metadata (canonical, lang, Open Graph)';
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
		$head = preg_match( '#<head\b[^>]*>(.*?)</head>#is', $html, $m ) ? $m[1] : $html;

		$signals   = array(
			'lang'      => (bool) preg_match( '#<html\b[^>]*\blang=["\'][a-z]#i', $html ),
			'canonical' => (bool) preg_match( '#<link\b[^>]*\brel=["\']canonical["\']#i', $head ),
			'og:type'   => (bool) preg_match( '#<meta\b[^>]*\bproperty=["\']og:type["\']#i', $head ),
			'og:image'  => (bool) preg_match( '#<meta\b[^>]*\bproperty=["\']og:image["\']#i', $head ),
		);
		$has_title = (bool) preg_match( '#<title\b[^>]*>\s*\S#i', $head );
		$missing   = array_keys(
			array_filter(
				$signals,
				static function ( $v ) {
					return ! $v;
				}
			)
		);
		$present   = array_keys( array_filter( $signals ) );

		$details = array(
			'present' => $present,
			'missing' => $missing,
			'title'   => $has_title,
		);

		$evidence[] = Evidence::parse(
			'Inspect <head> for identity metadata',
			empty( $missing ) ? 'positive' : 'negative',
			'Present: ' . ( empty( $present ) ? 'none' : implode( ', ', $present ) ) . '. Missing: ' . ( empty( $missing ) ? 'none' : implode( ', ', $missing ) ) . '.'
		);

		if ( ! empty( $missing ) ) {
			return $this->fail( 'Homepage is missing ' . implode( ', ', $missing ), $evidence, $details );
		}

		return $this->pass( 'Homepage declares lang, canonical, og:type and og:image', $evidence, $details );
	}
}
