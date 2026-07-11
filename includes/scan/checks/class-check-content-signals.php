<?php
/**
 * Scan check: Content Signals in robots.txt (botAccessControl).
 *
 * Parses robots.txt for `Content-Signal:` directives declaring ai-train,
 * search, and ai-input preferences, attributing each directive to the
 * preceding User-agent group (contentsignals.org; IETF
 * draft-romm-aipref-contentsignals).
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
 * Content Signals check.
 */
class Check_Content_Signals extends Check {

	/**
	 * Check id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'contentSignals';
	}

	/**
	 * Category.
	 *
	 * @return string
	 */
	public function get_category(): string {
		return self::CATEGORY_BOT_ACCESS;
	}

	/**
	 * Display name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'Content Signals in robots.txt';
	}

	/**
	 * Run the check.
	 *
	 * @return Check_Result
	 */
	public function run(): Check_Result {
		$evidence = array();

		$response = $this->http_get( $this->origin() . '/robots.txt', 'GET /robots.txt', $evidence );

		if ( '' !== $response['error'] && 0 === $response['code'] ) {
			return $this->unable( 'Could not reach /robots.txt — ' . $response['error'], $evidence );
		}

		$details = array(
			'signals'     => array(),
			'signalCount' => 0,
		);

		if ( 200 !== $response['code'] ) {
			$evidence[] = Evidence::parse(
				'Parse Content-Signal directives',
				'negative',
				'No robots.txt to parse (HTTP ' . $response['code'] . ').'
			);
			return $this->fail(
				'No robots.txt found (HTTP ' . $response['code'] . ') — no Content Signals declared',
				$evidence,
				$details
			);
		}

		$signals = $this->parse_signals( $response['body'] );

		$details['signals']     = $signals;
		$details['signalCount'] = count( $signals );

		if ( empty( $signals ) ) {
			$evidence[] = Evidence::parse(
				'Parse Content-Signal directives',
				'negative',
				'robots.txt contains no Content-Signal directives.'
			);
			return $this->fail( 'No Content-Signal directives found in robots.txt', $evidence, $details );
		}

		$evidence[] = Evidence::parse(
			'Parse Content-Signal directives',
			'positive',
			count( $signals ) . ' directive' . ( 1 === count( $signals ) ? '' : 's' ) . ' found — e.g. "Content-Signal: ' . $signals[0]['raw'] . '" for User-agent: ' . $signals[0]['userAgent'] . '.'
		);

		return $this->pass(
			'Found ' . count( $signals ) . ' Content-Signal directive' . ( 1 === count( $signals ) ? '' : 's' ) . ' in robots.txt',
			$evidence,
			$details
		);
	}

	/**
	 * Extract Content-Signal directives from a robots.txt body, attributing
	 * each to the preceding User-agent group.
	 *
	 * @param string $body robots.txt body.
	 * @return array<int, array{userAgent: string, aiTrain: ?string, search: ?string, aiInput: ?string, raw: string}>
	 */
	private function parse_signals( string $body ): array {
		$signals       = array();
		$current_group = array();
		$last_was_ua   = false;

		foreach ( preg_split( '/\r\n|\r|\n/', $body ) as $line ) {
			$hash = strpos( $line, '#' );
			if ( false !== $hash ) {
				$line = substr( $line, 0, $hash );
			}
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}

			$colon = strpos( $line, ':' );
			if ( false === $colon ) {
				continue;
			}
			$field = strtolower( trim( substr( $line, 0, $colon ) ) );
			$value = trim( substr( $line, $colon + 1 ) );

			if ( 'user-agent' === $field ) {
				// Consecutive User-agent lines form one group; any other
				// directive ends the accumulation.
				if ( ! $last_was_ua ) {
					$current_group = array();
				}
				$current_group[] = $value;
				$last_was_ua     = true;
				continue;
			}
			$last_was_ua = false;

			if ( 'content-signal' !== $field ) {
				continue;
			}

			$parsed = array(
				'ai-train' => null,
				'search'   => null,
				'ai-input' => null,
			);
			foreach ( explode( ',', $value ) as $pair ) {
				$eq = strpos( $pair, '=' );
				if ( false === $eq ) {
					continue;
				}
				$key = strtolower( trim( substr( $pair, 0, $eq ) ) );
				if ( array_key_exists( $key, $parsed ) ) {
					$parsed[ $key ] = strtolower( trim( substr( $pair, $eq + 1 ) ) );
				}
			}

			$signals[] = array(
				'userAgent' => empty( $current_group ) ? '*' : implode( ', ', $current_group ),
				'aiTrain'   => $parsed['ai-train'],
				'search'    => $parsed['search'],
				'aiInput'   => $parsed['ai-input'],
				'raw'       => $value,
			);
		}

		return $signals;
	}
}
