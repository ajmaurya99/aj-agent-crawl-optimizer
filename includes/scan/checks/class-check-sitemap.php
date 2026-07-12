<?php
/**
 * Scan check: Sitemap (id `sitemap`).
 *
 * Extracts `Sitemap:` directives from robots.txt and validates the first
 * reachable one (XML sitemaps and sitemap indexes per sitemaps.org). When
 * robots.txt yields no directive, probes candidate paths in order:
 * /sitemap-index.xml, /sitemap.xml.gz, /sitemap_index.xml, /sitemap.xml, and
 * finally WordPress core's /wp-sitemap.xml. Mirrors the isitagentready.com
 * `sitemap` check.
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
 * Sitemap check.
 */
class Check_Sitemap extends Check {

	/**
	 * Fallback candidate paths, probed in order when robots.txt yields no
	 * Sitemap: directive. /wp-sitemap.xml (WordPress core) is probed last.
	 *
	 * @var string[]
	 */
	const FALLBACK_PATHS = array(
		'/sitemap-index.xml',
		'/sitemap.xml.gz',
		'/sitemap_index.xml',
		'/sitemap.xml',
		'/wp-sitemap.xml',
	);

	/**
	 * Max robots.txt Sitemap: directives probed before falling back.
	 */
	const MAX_DIRECTIVE_PROBES = 5;

	/**
	 * Number of sitemap candidate probes attempted.
	 *
	 * @var int
	 */
	private $probe_count = 0;

	/**
	 * Number of candidate probes that failed at the network level.
	 *
	 * @var int
	 */
	private $network_failures = 0;

	/**
	 * Last network error message seen while probing candidates.
	 *
	 * @var string
	 */
	private $last_network_error = '';

	/**
	 * Check id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'sitemap';
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
		return 'Sitemap';
	}

	/**
	 * Run the check.
	 *
	 * @return Check_Result
	 */
	public function run(): Check_Result {
		$evidence = array();
		$origin   = $this->origin();

		$robots = $this->http_get( $origin . '/robots.txt', 'GET /robots.txt', $evidence );

		if ( '' !== $robots['error'] && 0 === $robots['code'] ) {
			return $this->unable( 'Could not reach /robots.txt — ' . $robots['error'], $evidence );
		}

		$directives = array();
		if ( 200 === $robots['code'] ) {
			$directives = $this->extract_sitemap_directives( $robots['body'] );
		}

		if ( empty( $directives ) ) {
			$evidence[] = Evidence::parse(
				'Extract Sitemap directives from robots.txt',
				'neutral',
				'No Sitemap: directives found in robots.txt — falling back to common sitemap paths.'
			);
		} else {
			$evidence[] = Evidence::parse(
				'Extract Sitemap directives from robots.txt',
				'positive',
				sprintf( 'Found %d Sitemap directive(s): %s', count( $directives ), implode( ', ', $directives ) )
			);

			foreach ( array_slice( $directives, 0, self::MAX_DIRECTIVE_PROBES ) as $url ) {
				$format = $this->probe_sitemap( $url, $evidence );
				if ( null !== $format ) {
					return $this->pass(
						'Sitemap found at ' . $this->display_path( $url ) . ' (declared in robots.txt)',
						$evidence,
						array(
							'url'           => $url,
							'fromRobotsTxt' => true,
							'format'        => $format,
						)
					);
				}
			}
		}

		// Fallback candidates run ONLY when robots.txt declared no sitemap —
		// mirroring the external scanner. A declared-but-broken sitemap is a
		// real failure the site owner must see, not something to paper over.
		if ( empty( $directives ) ) {
			foreach ( self::FALLBACK_PATHS as $path ) {
				$url    = $origin . $path;
				$format = $this->probe_sitemap( $url, $evidence );
				if ( null !== $format ) {
					return $this->pass(
						'Sitemap found at ' . $path,
						$evidence,
						array(
							'url'           => $url,
							'fromRobotsTxt' => false,
							'format'        => $format,
						)
					);
				}
			}
		}

		if ( $this->probe_count > 0 && $this->probe_count === $this->network_failures ) {
			return $this->unable( 'Could not reach any sitemap candidate — ' . $this->last_network_error, $evidence );
		}

		if ( ! empty( $directives ) ) {
			return $this->fail(
				'robots.txt declares ' . count( $directives ) . ' Sitemap directive(s), but none serve a valid sitemap — fix or remove the broken declaration(s)',
				$evidence
			);
		}

		return $this->fail( 'No sitemap found — checked robots.txt directives and common sitemap paths', $evidence );
	}

	/**
	 * Extract Sitemap: directive URLs from robots.txt, resolved to absolute.
	 *
	 * @param string $body robots.txt body.
	 * @return string[]
	 */
	private function extract_sitemap_directives( string $body ): array {
		$urls = array();

		$origin_host = wp_parse_url( $this->origin(), PHP_URL_HOST );

		if ( preg_match_all( '/^\s*sitemap\s*:\s*(\S+)/im', $body, $matches ) ) {
			foreach ( $matches[1] as $url ) {
				$url = trim( $url );
				if ( '' === $url ) {
					continue;
				}
				// Directives should be absolute; resolve stray relative paths
				// against our own origin.
				if ( ! preg_match( '#^https?://#i', $url ) ) {
					$url = $this->origin() . '/' . ltrim( $url, '/' );
				}

				// Only probe directives on our OWN host. A robots.txt (which
				// another plugin or a physical file may control) could point
				// Sitemap: at an internal address (169.254.169.254, localhost,
				// an intranet host); the scanner must not turn into an SSRF
				// relay by fetching it. Off-origin directives are skipped —
				// the scanner's job is this site's sitemap, not a third party's.
				$url_host = wp_parse_url( $url, PHP_URL_HOST );
				if ( ! is_string( $url_host ) || ! is_string( $origin_host )
					|| 0 !== strcasecmp( $url_host, $origin_host ) ) {
					continue;
				}

				$urls[] = $url;
			}
		}

		return array_values( array_unique( $urls ) );
	}

	/**
	 * Fetch a sitemap candidate and validate its structure.
	 *
	 * @param string $url      Absolute candidate URL.
	 * @param array  $evidence Evidence array, appended by reference.
	 * @return string|null Detected format (`sitemapindex`|`urlset`), or null.
	 */
	private function probe_sitemap( string $url, array &$evidence ): ?string {
		$this->probe_count++;

		$response = $this->http_get( $url, 'GET ' . $this->display_path( $url ), $evidence );

		if ( '' !== $response['error'] && 0 === $response['code'] ) {
			$this->network_failures++;
			$this->last_network_error = $response['error'];
			return null;
		}

		if ( 200 !== $response['code'] ) {
			return null;
		}

		$format = $this->detect_sitemap_format( $response['body'] );

		if ( null === $format ) {
			$evidence[] = Evidence::parse(
				'Validate sitemap XML',
				'negative',
				'Response is not a recognizable XML sitemap or sitemap index (sitemaps.org protocol).'
			);
			return null;
		}

		$evidence[] = Evidence::parse(
			'Validate sitemap XML',
			'positive',
			'sitemapindex' === $format
				? 'Valid sitemap index (<sitemapindex> root) per the sitemaps.org protocol.'
				: 'Valid XML sitemap per the sitemaps.org protocol.'
		);

		return $format;
	}

	/**
	 * Detect the sitemap format of a response body (gzip-aware).
	 *
	 * @param string $body Response body.
	 * @return string|null `sitemapindex`, `urlset`, `xml`, or null when invalid.
	 */
	private function detect_sitemap_format( string $body ): ?string {
		// Gzipped payloads (e.g. /sitemap.xml.gz served without
		// Content-Encoding, which WP's HTTP API won't auto-inflate).
		if ( "\x1f\x8b" === substr( $body, 0, 2 ) && function_exists( 'gzdecode' ) ) {
			$decoded = gzdecode( $body );
			if ( is_string( $decoded ) ) {
				$body = $decoded;
			}
		}

		$body = ltrim( $body, "\xEF\xBB\xBF \t\r\n" );

		if ( false !== strpos( $body, '<sitemapindex' ) ) {
			return 'sitemapindex';
		}
		if ( false !== strpos( $body, '<urlset' ) ) {
			return 'urlset';
		}

		// Arbitrary XML without a sitemaps.org root element is NOT a sitemap —
		// an RSS feed or WSDL at /sitemap.xml must not pass.
		return null;
	}

	/**
	 * Short display form of a URL: path when on our origin, full URL otherwise.
	 *
	 * @param string $url Absolute URL.
	 * @return string
	 */
	private function display_path( string $url ): string {
		$origin = $this->origin();
		if ( 0 === strpos( $url, $origin . '/' ) ) {
			return substr( $url, strlen( $origin ) );
		}
		return $url;
	}
}
