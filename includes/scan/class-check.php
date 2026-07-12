<?php
/**
 * Scan engine: abstract base class for a single readiness check.
 *
 * Each check probes the site's own origin from the server side (real HTTP
 * requests through the full stack where possible) and returns a Check_Result
 * with an evidence timeline. Check ids, categories, and pass criteria mirror
 * Cloudflare's Agent Readiness scanner (isitagentready.com) so levels are
 * directly comparable.
 *
 * @package Ajaco
 */

namespace Ajaco\Scan;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base check.
 */
abstract class Check {

	const CATEGORY_DISCOVERABILITY = 'discoverability';
	const CATEGORY_CONTENT         = 'contentAccessibility';
	const CATEGORY_BOT_ACCESS      = 'botAccessControl';
	const CATEGORY_DISCOVERY       = 'discovery';
	const CATEGORY_COMMERCE        = 'commerce';

	/**
	 * Check id — must match the isitagentready.com check id exactly
	 * (e.g. `robotsTxt`, `mcpServerCard`).
	 *
	 * @return string
	 */
	abstract public function get_id(): string;

	/**
	 * One of the CATEGORY_* constants.
	 *
	 * @return string
	 */
	abstract public function get_category(): string;

	/**
	 * Human-readable display name.
	 *
	 * @return string
	 */
	abstract public function get_name(): string;

	/**
	 * Execute the check.
	 *
	 * @return Check_Result
	 */
	abstract public function run(): Check_Result;

	/**
	 * Whether this check is informational-only: absence yields neutral, never
	 * fail (e.g. webBotAuth). Commerce checks are handled separately by the
	 * Scanner via is_commerce gating.
	 *
	 * @return bool
	 */
	public function is_informational(): bool {
		return false;
	}

	/**
	 * Origin base URL for probes (no trailing slash).
	 *
	 * @return string
	 */
	protected function origin(): string {
		return untrailingslashit( home_url( '/' ) );
	}

	/**
	 * GET a URL through wp_remote_get with scanner defaults and append a fetch
	 * evidence step.
	 *
	 * @param string $url      Absolute URL to fetch.
	 * @param string $label    Evidence label (e.g. "GET /robots.txt").
	 * @param array  $evidence Evidence array, appended to by reference.
	 * @param array  $args     Extra wp_remote_get args (headers, etc.).
	 * @param string $outcome  Optional finding outcome for the step.
	 * @param string $summary  Optional finding summary for the step.
	 * @return array{code: int, body: string, headers: array<string,string>, error: string}
	 */
	protected function http_get( string $url, string $label, array &$evidence, array $args = array(), string $outcome = '', string $summary = '' ): array {
		// Self-scan must survive local/staging certs when probing our OWN
		// origin — but external requests (e.g. DNS-over-HTTPS resolvers) keep
		// full TLS verification.
		$url_host    = wp_parse_url( $url, PHP_URL_HOST );
		$origin_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$same_origin = is_string( $url_host ) && is_string( $origin_host )
			&& 0 === strcasecmp( $url_host, $origin_host );

		$defaults = array(
			'timeout'     => 10,
			'redirection' => 3,
			'user-agent'  => 'AJACO-Scanner/' . AJACO_VERSION . ' (+' . home_url( '/' ) . ')',
			// TLS verification stays ON for external requests (e.g. DNS-over-
			// HTTPS resolvers). It is relaxed ONLY for probes of the site's own
			// origin, so a scan still works on a local/staging site behind a
			// self-signed certificate; the filter re-enables it if desired.
			'sslverify'   => $same_origin ? apply_filters( 'ajaco_scan_sslverify', false ) : true,
		);
		$response = wp_remote_get( $url, array_merge( $defaults, $args ) );

		$evidence[] = Evidence::fetch( $label, $url, $response, $outcome, $summary );

		if ( is_wp_error( $response ) ) {
			return array(
				'code'    => 0,
				'body'    => '',
				'headers' => array(),
				'error'   => $response->get_error_message(),
			);
		}

		$headers = array();
		$raw     = wp_remote_retrieve_headers( $response );
		if ( is_object( $raw ) && method_exists( $raw, 'getAll' ) ) {
			foreach ( $raw->getAll() as $name => $value ) {
				$headers[ strtolower( (string) $name ) ] = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
			}
		}

		return array(
			'code'    => (int) wp_remote_retrieve_response_code( $response ),
			'body'    => (string) wp_remote_retrieve_body( $response ),
			'headers' => $headers,
			'error'   => '',
		);
	}

	/**
	 * Shorthand: pass result.
	 *
	 * @param string     $message  Result message.
	 * @param array      $evidence Evidence steps (conclusion appended).
	 * @param array|null $details  Structured details.
	 * @return Check_Result
	 */
	protected function pass( string $message, array $evidence = array(), $details = null ): Check_Result {
		$evidence[] = Evidence::conclude( 'positive', $message );
		return new Check_Result( Check_Result::STATUS_PASS, $message, $evidence, $details );
	}

	/**
	 * Shorthand: fail result.
	 *
	 * @param string     $message  Result message.
	 * @param array      $evidence Evidence steps (conclusion appended).
	 * @param array|null $details  Structured details.
	 * @return Check_Result
	 */
	protected function fail( string $message, array $evidence = array(), $details = null ): Check_Result {
		$evidence[] = Evidence::conclude( 'negative', $message );
		return new Check_Result( Check_Result::STATUS_FAIL, $message, $evidence, $details );
	}

	/**
	 * Shorthand: neutral / not-applicable result.
	 *
	 * @param string     $message  Result message.
	 * @param array      $evidence Evidence steps (conclusion appended).
	 * @param array|null $details  Structured details.
	 * @return Check_Result
	 */
	protected function neutral( string $message, array $evidence = array(), $details = null ): Check_Result {
		$evidence[] = Evidence::conclude( 'neutral', $message );
		return new Check_Result( Check_Result::STATUS_NEUTRAL, $message, $evidence, $details );
	}

	/**
	 * Shorthand: unable-to-check result (network failure etc.).
	 *
	 * @param string $message  Result message.
	 * @param array  $evidence Evidence steps (conclusion appended).
	 * @return Check_Result
	 */
	protected function unable( string $message, array $evidence = array() ): Check_Result {
		$evidence[] = Evidence::conclude( 'neutral', $message );
		return new Check_Result( Check_Result::STATUS_UNABLE, $message, $evidence );
	}
}
