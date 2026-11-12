<?php
/**
 * Scan check: Agent-friendly 404 (id `agentFriendly404`,
 * contentAccessibility).
 *
 * Requests a path that cannot exist. A real 404/410 status is required
 * (AgentReady AR-READ-02: never a soft 200), and when the agent asks for
 * Markdown the error body should be Markdown too, so the agent can read why
 * it failed instead of parsing a theme's HTML.
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
 * Agent-friendly 404 check.
 */
class Check_Agent_Friendly_404 extends Check {

	/**
	 * Check id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'agentFriendly404';
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
		return 'Agent-friendly 404';
	}

	/**
	 * Run the check.
	 *
	 * @return Check_Result
	 */
	public function run(): Check_Result {
		$evidence = array();
		$path     = '/ajaco-missing-' . wp_generate_password( 10, false, false ) . '/';
		$url      = $this->origin() . $path;

		$plain = $this->http_get( $url, 'GET ' . $path, $evidence );
		if ( '' !== $plain['error'] && 0 === $plain['code'] ) {
			return $this->unable( 'Could not reach the site — ' . $plain['error'], $evidence );
		}

		$details = array(
			'path'       => $path,
			'htmlStatus' => $plain['code'],
		);

		if ( ! in_array( $plain['code'], array( 404, 410 ), true ) ) {
			$evidence[] = Evidence::parse( 'Check status code', 'negative', 'A nonexistent path returned HTTP ' . $plain['code'] . ' instead of 404.' );
			return $this->fail(
				'Missing pages return HTTP ' . $plain['code'] . ' instead of 404 — agents cannot tell a dead link from a real page',
				$evidence,
				$details
			);
		}
		$evidence[] = Evidence::parse( 'Check status code', 'positive', 'Nonexistent path returns HTTP ' . $plain['code'] . '.' );

		$md                             = $this->http_get( $url, 'GET ' . $path . ' (Accept: text/markdown)', $evidence, array( 'headers' => array( 'Accept' => 'text/markdown' ) ) );
		$ctype                          = isset( $md['headers']['content-type'] ) ? $md['headers']['content-type'] : '';
		$details['markdownStatus']      = $md['code'];
		$details['markdownContentType'] = $ctype;

		if ( ! in_array( $md['code'], array( 404, 410 ), true ) || 0 !== stripos( $ctype, 'text/markdown' ) ) {
			$evidence[] = Evidence::parse(
				'Check the Markdown variant of the error page',
				'negative',
				'HTTP ' . $md['code'] . ', Content-Type ' . ( '' !== $ctype ? $ctype : '(none)' ) . ' — agents asking for Markdown get an HTML error page.'
			);
			return $this->fail( '404 status is correct, but the error page is not served as Markdown for agents', $evidence, $details );
		}

		$evidence[] = Evidence::parse( 'Check the Markdown variant of the error page', 'positive', 'HTTP ' . $md['code'] . ' with a text/markdown body.' );
		return $this->pass( 'Missing pages return a real 404, as Markdown when an agent asks for it', $evidence, $details );
	}
}
