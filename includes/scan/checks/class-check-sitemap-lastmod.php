<?php
/**
 * Scan check: Sitemap freshness (id `sitemapLastmod`, discoverability).
 *
 * Locates the sitemap (robots.txt directive, then common paths), descends
 * into the first child of a sitemap index, and passes when URL entries carry
 * parseable <lastmod> dates. WordPress core sitemaps omit lastmod entirely;
 * the plugin's Sitemap lastmod feature adds it.
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
 * Sitemap lastmod check.
 */
class Check_Sitemap_Lastmod extends Check {

	/**
	 * Candidate paths when robots.txt declares no sitemap.
	 *
	 * @var string[]
	 */
	const FALLBACK_PATHS = array( '/sitemap_index.xml', '/sitemap.xml', '/wp-sitemap.xml' );

	/**
	 * Check id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'sitemapLastmod';
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
		return 'Sitemap freshness (lastmod)';
	}

	/**
	 * Run the check.
	 *
	 * @return Check_Result
	 */
	public function run(): Check_Result {
		$evidence = array();
		$origin   = $this->origin();

		$candidates = array();
		$robots     = $this->http_get( $origin . '/robots.txt', 'GET /robots.txt', $evidence );
		if ( 200 === $robots['code'] && preg_match_all( '/^\s*sitemap\s*:\s*(\S+)/im', $robots['body'], $m ) ) {
			$candidates = array_slice( array_map( 'trim', $m[1] ), 0, 3 );
		}
		if ( empty( $candidates ) ) {
			foreach ( self::FALLBACK_PATHS as $path ) {
				$candidates[] = $origin . $path;
			}
		}

		$body = '';
		$url  = '';
		foreach ( $candidates as $candidate ) {
			$res = $this->http_get( $candidate, 'GET ' . $this->display_path( $candidate ), $evidence );
			if ( 200 === $res['code'] && ( false !== stripos( $res['body'], '<urlset' ) || false !== stripos( $res['body'], '<sitemapindex' ) ) ) {
				$body = $res['body'];
				$url  = $candidate;
				break;
			}
		}

		if ( '' === $body ) {
			return $this->neutral( 'No sitemap found to inspect — see the Sitemap check', $evidence );
		}

		// Descend one level into an index: prefer a posts sitemap.
		if ( false !== stripos( $body, '<sitemapindex' ) && preg_match_all( '#<loc>\s*([^<\s]+)\s*</loc>#i', $body, $locs ) ) {
			$child = '';
			foreach ( $locs[1] as $loc ) {
				if ( false !== stripos( $loc, 'post' ) ) {
					$child = $loc;
					break;
				}
			}
			if ( '' === $child ) {
				$child = $locs[1][0];
			}
			$child = html_entity_decode( $child, ENT_QUOTES | ENT_XML1 );
			$res   = $this->http_get( $child, 'GET ' . $this->display_path( $child ) . ' (index child)', $evidence );
			if ( 200 !== $res['code'] ) {
				return $this->fail( 'Sitemap index child ' . $this->display_path( $child ) . ' returned HTTP ' . $res['code'], $evidence );
			}
			$body = $res['body'];
			$url  = $child;
		}

		$url_count     = preg_match_all( '#<url>#i', $body );
		$lastmod_count = preg_match_all( '#<lastmod>\s*([^<\s]+)\s*</lastmod>#i', $body, $dates );

		if ( 0 === $url_count ) {
			return $this->neutral( 'Sitemap ' . $this->display_path( $url ) . ' lists no URLs', $evidence );
		}

		$newest = 0;
		foreach ( $dates[1] as $date ) {
			$ts = strtotime( $date );
			if ( false !== $ts && $ts > $newest ) {
				$newest = $ts;
			}
		}

		$details = array(
			'sitemap'  => $url,
			'urls'     => $url_count,
			'lastmods' => $lastmod_count,
			'newest'   => $newest > 0 ? gmdate( 'c', $newest ) : null,
		);

		if ( 0 === $lastmod_count || 0 === $newest ) {
			$evidence[] = Evidence::parse(
				'Count <lastmod> elements',
				'negative',
				sprintf( '%d URL entries, none with a parseable <lastmod> date.', $url_count )
			);
			return $this->fail(
				'Sitemap ' . $this->display_path( $url ) . ' has no <lastmod> dates — crawlers cannot tell what changed',
				$evidence,
				$details
			);
		}

		$evidence[] = Evidence::parse(
			'Count <lastmod> elements',
			'positive',
			sprintf( '%d of %d URL entries carry <lastmod>; newest %s.', $lastmod_count, $url_count, gmdate( 'Y-m-d', $newest ) )
		);

		return $this->pass(
			sprintf( 'Sitemap carries <lastmod> dates (%d of %d entries, newest %s)', $lastmod_count, $url_count, gmdate( 'Y-m-d', $newest ) ),
			$evidence,
			$details
		);
	}

	/**
	 * Path-only form of a URL for messages.
	 *
	 * @param string $url Absolute URL.
	 * @return string
	 */
	private function display_path( string $url ): string {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		return is_string( $path ) && '' !== $path ? $path : $url;
	}
}
