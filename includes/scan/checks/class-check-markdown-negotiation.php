<?php
/**
 * Scan check: Markdown Negotiation (contentAccessibility).
 *
 * GETs the homepage with `Accept: text/markdown` and passes when the response
 * Content-Type is `text/markdown` — HTML stays the default for browsers.
 * Recognizes the optional `x-markdown-tokens` token-count header and warns
 * when the response lacks `Vary: Accept` (Cloudflare "Markdown for Agents").
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
 * Markdown negotiation check.
 */
class Check_Markdown_Negotiation extends Check {

	/**
	 * Check id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'markdownNegotiation';
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
		return 'Markdown Negotiation';
	}

	/**
	 * Run the check.
	 *
	 * @return Check_Result
	 */
	public function run(): Check_Result {
		$evidence = array();

		$response = $this->http_get(
			$this->origin() . '/',
			'GET / (Accept: text/markdown)',
			$evidence,
			array(
				'headers' => array(
					'Accept' => 'text/markdown',
				),
			)
		);

		if ( '' !== $response['error'] && 0 === $response['code'] ) {
			return $this->unable( 'Could not reach the homepage — ' . $response['error'], $evidence );
		}

		$content_type = isset( $response['headers']['content-type'] ) ? $response['headers']['content-type'] : '';
		$details      = array(
			'contentType' => $content_type,
		);

		if ( 0 !== stripos( $content_type, 'text/markdown' ) ) {
			$evidence[] = Evidence::parse(
				'Check response Content-Type',
				'negative',
				'Content-Type is "' . ( '' !== $content_type ? $content_type : '(none)' ) . '", not text/markdown — the server ignored the Accept header.'
			);
			return $this->fail(
				'Homepage returned ' . ( '' !== $content_type ? $content_type : 'no Content-Type' ) . ' for Accept: text/markdown — markdown negotiation not supported',
				$evidence,
				$details
			);
		}

		$evidence[] = Evidence::parse(
			'Check response Content-Type',
			'positive',
			'Content-Type is "' . $content_type . '" — the server negotiated a markdown variant.'
		);

		if ( isset( $response['headers']['x-markdown-tokens'] ) ) {
			$evidence[] = Evidence::parse(
				'Check x-markdown-tokens header',
				'positive',
				'x-markdown-tokens: ' . $response['headers']['x-markdown-tokens'] . ' — server reports the markdown token count for agents.'
			);
		}

		$vary_accept = false;
		$vary        = isset( $response['headers']['vary'] ) ? $response['headers']['vary'] : '';
		foreach ( explode( ',', $vary ) as $token ) {
			if ( 'accept' === strtolower( trim( $token ) ) ) {
				$vary_accept = true;
				break;
			}
		}

		if ( $vary_accept ) {
			$evidence[] = Evidence::parse(
				'Check Vary header',
				'positive',
				'Vary: Accept present — caches will keep the markdown and HTML variants separate.'
			);
		} else {
			$evidence[] = Evidence::parse(
				'Check Vary header',
				'neutral',
				'Response lacks a Vary: Accept header — caches may serve the wrong variant.'
			);
		}

		return $this->pass(
			'Homepage served text/markdown for Accept: text/markdown',
			$evidence,
			$details
		);
	}
}
