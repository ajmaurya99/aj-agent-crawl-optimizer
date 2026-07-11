<?php
/**
 * Scan engine: evidence step builders.
 *
 * Every check verdict carries an auditable timeline of what the scanner
 * actually did — the trust surface. Step shape mirrors isitagentready.com:
 *
 *   { action: fetch|parse|conclude, label,
 *     request?:  { url, method },
 *     response?: { status, statusText, headers, bodyPreview },
 *     finding?:  { outcome: positive|negative|neutral, summary } }
 *
 * @package Ajaco
 */

namespace Ajaco\Scan;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static factory for evidence steps.
 */
class Evidence {

	/**
	 * Max bytes of response body captured in evidence.
	 */
	const BODY_PREVIEW_LIMIT = 400;

	/**
	 * Response headers worth surfacing in evidence (lowercase names).
	 *
	 * @var string[]
	 */
	const HEADER_ALLOWLIST = array(
		'content-type',
		'link',
		'vary',
		'x-markdown-tokens',
		'www-authenticate',
		'server',
		'retry-after',
		'cache-control',
	);

	/**
	 * Build a fetch step from a wp_remote_get() response (or WP_Error).
	 *
	 * @param string          $label    Step label, e.g. "GET /robots.txt".
	 * @param string          $url      Requested URL.
	 * @param array|\WP_Error $response Return of wp_remote_get().
	 * @param string          $outcome  positive|negative|neutral.
	 * @param string          $summary  One-line finding.
	 * @return array
	 */
	public static function fetch( string $label, string $url, $response, string $outcome = '', string $summary = '' ): array {
		$step = array(
			'action'  => 'fetch',
			'label'   => $label,
			'request' => array(
				'url'    => $url,
				'method' => 'GET',
			),
		);

		if ( is_wp_error( $response ) ) {
			$step['finding'] = array(
				'outcome' => 'negative',
				'summary' => 'Request failed: ' . $response->get_error_message(),
			);
			return $step;
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$headers = array();
		foreach ( self::HEADER_ALLOWLIST as $name ) {
			$value = wp_remote_retrieve_header( $response, $name );
			if ( is_array( $value ) ) {
				$value = implode( ', ', $value );
			}
			if ( is_string( $value ) && '' !== $value ) {
				$headers[ $name ] = $value;
			}
		}

		$body    = (string) wp_remote_retrieve_body( $response );
		$preview = mb_substr( $body, 0, self::BODY_PREVIEW_LIMIT );

		$step['response'] = array(
			'status'      => $code,
			'statusText'  => (string) wp_remote_retrieve_response_message( $response ),
			'headers'     => $headers,
			'bodyPreview' => $preview,
		);

		if ( '' !== $summary ) {
			$step['finding'] = array(
				'outcome' => $outcome ? $outcome : 'neutral',
				'summary' => $summary,
			);
		}

		return $step;
	}

	/**
	 * Build a parse/validate step.
	 *
	 * @param string $label   Step label.
	 * @param string $outcome positive|negative|neutral.
	 * @param string $summary One-line finding.
	 * @return array
	 */
	public static function parse( string $label, string $outcome, string $summary ): array {
		return array(
			'action'  => 'parse',
			'label'   => $label,
			'finding' => array(
				'outcome' => $outcome,
				'summary' => $summary,
			),
		);
	}

	/**
	 * Build the terminal conclusion step.
	 *
	 * @param string $outcome positive|negative|neutral.
	 * @param string $summary Conclusion text (usually the result message).
	 * @return array
	 */
	public static function conclude( string $outcome, string $summary ): array {
		return array(
			'action'  => 'conclude',
			'label'   => 'Conclusion',
			'finding' => array(
				'outcome' => $outcome,
				'summary' => $summary,
			),
		);
	}
}
