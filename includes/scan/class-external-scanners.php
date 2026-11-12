<?php
/**
 * Scan engine: third-party verification.
 *
 * Reads the public, keyless report APIs of the two hosted agent-readiness
 * scanners (ora.ai and the Vercel Labs front end is-agentic.com) so the
 * Dashboard can show their score for this site next to our own. Both APIs
 * are read-only and never start a scan: a site that has not been submitted
 * to them yet comes back as "not scanned", with a link to run one.
 *
 * Only runs when an administrator asks for it (nothing is sent anywhere on
 * its own), and the result is cached for an hour.
 *
 * @package Ajaco
 */

namespace Ajaco\Scan;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hosted scanner lookups.
 */
class External_Scanners {

	/**
	 * Cache key.
	 */
	const TRANSIENT = 'ajaco_external_scores';

	/**
	 * Cached (or fresh) results for both providers.
	 *
	 * @param bool $refresh Bypass the cache.
	 * @return array{checkedAt: string, host: string, providers: array<string, array>}
	 */
	public static function get( bool $refresh = false ): array {
		if ( ! $refresh ) {
			$cached = get_transient( self::TRANSIENT );
			if ( is_array( $cached ) ) {
				$cached['cached'] = true;
				return $cached;
			}
		}

		$home = home_url( '/' );
		$host = (string) wp_parse_url( $home, PHP_URL_HOST );

		$out = array(
			'checkedAt' => gmdate( 'c' ),
			'host'      => $host,
			'cached'    => false,
			'providers' => array(
				'ora'       => self::fetch_ora( $host ),
				'isAgentic' => self::fetch_is_agentic( $home, $host ),
			),
		);

		set_transient( self::TRANSIENT, $out, HOUR_IN_SECONDS );

		return $out;
	}

	/**
	 * Ora: GET https://ora.ai/api/score/{domain}.
	 *
	 * @param string $host Site host.
	 * @return array
	 */
	private static function fetch_ora( string $host ): array {
		$base = array(
			'name'      => 'Ora',
			'reportUrl' => 'https://ora.ai/score/' . rawurlencode( $host ),
			'scanUrl'   => 'https://ora.ai/',
		);
		$res  = wp_remote_get(
			'https://ora.ai/api/score/' . rawurlencode( $host ),
			array(
				'timeout'    => 15,
				'headers'    => array( 'Accept' => 'application/json' ),
				'user-agent' => 'AJACO-Scanner/' . AJACO_VERSION . ' (+' . home_url( '/' ) . ')',
			)
		);
		if ( is_wp_error( $res ) ) {
			return $base + array(
				'status'  => 'error',
				'message' => $res->get_error_message(),
			);
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( 404 === $code ) {
			return $base + array( 'status' => 'none' );
		}
		$json = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( 200 !== $code || ! is_array( $json ) || ! isset( $json['score'] ) ) {
			return $base + array(
				'status'  => 'error',
				'message' => 'HTTP ' . $code,
			);
		}
		$scanned = '';
		foreach ( array( 'scannedAt', 'updatedAt', 'lastScannedAt', 'createdAt' ) as $key ) {
			if ( ! empty( $json[ $key ] ) && is_string( $json[ $key ] ) ) {
				$scanned = $json[ $key ];
				break;
			}
		}
		return $base + array(
			'status'    => 'ok',
			'score'     => (int) $json['score'],
			'grade'     => isset( $json['grade'] ) ? (string) $json['grade'] : '',
			'label'     => isset( $json['ctaMessage'] ) ? (string) $json['ctaMessage'] : '',
			'scannedAt' => $scanned,
		);
	}

	/**
	 * Is Agentic: GET https://is-agentic.com/api/v1/report?url=….
	 *
	 * @param string $home Home URL.
	 * @param string $host Site host.
	 * @return array
	 */
	private static function fetch_is_agentic( string $home, string $host ): array {
		$base = array(
			'name'      => 'Is Agentic',
			'reportUrl' => 'https://is-agentic.com/scan/' . rawurlencode( $host ),
			'scanUrl'   => 'https://is-agentic.com/',
		);
		$res  = wp_remote_get(
			'https://is-agentic.com/api/v1/report?url=' . rawurlencode( $home ),
			array(
				'timeout'    => 15,
				'headers'    => array( 'Accept' => 'application/json' ),
				'user-agent' => 'AJACO-Scanner/' . AJACO_VERSION . ' (+' . home_url( '/' ) . ')',
			)
		);
		if ( is_wp_error( $res ) ) {
			return $base + array(
				'status'  => 'error',
				'message' => $res->get_error_message(),
			);
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		// 404 = no stored report; 400 = the URL is not public (local/staging).
		if ( in_array( $code, array( 400, 404 ), true ) ) {
			return $base + array( 'status' => 'none' );
		}
		$json = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( 200 !== $code || ! is_array( $json ) || ! isset( $json['score'] ) ) {
			return $base + array(
				'status'  => 'error',
				'message' => 'HTTP ' . $code,
			);
		}
		// Third-party value rendered as an admin link: accept only an https
		// URL on the provider's own host, otherwise keep the default.
		if ( ! empty( $json['report_url'] ) && is_string( $json['report_url'] ) ) {
			$url    = $json['report_url'];
			$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
			$rhost  = wp_parse_url( $url, PHP_URL_HOST );
			if ( 'https' === $scheme && is_string( $rhost )
				&& in_array( strtolower( rtrim( $rhost, '.' ) ), array( 'is-agentic.com', 'www.is-agentic.com' ), true ) ) {
				$base['reportUrl'] = esc_url_raw( $url, array( 'https' ) );
			}
		}
		return $base + array(
			'status'    => 'ok',
			'score'     => (int) $json['score'],
			'grade'     => '',
			'label'     => isset( $json['score_label'] ) ? (string) $json['score_label'] : '',
			'scannedAt' => isset( $json['scanned_at'] ) ? (string) $json['scanned_at'] : '',
		);
	}
}
