<?php
/**
 * Scan check: AI bot rules in robots.txt (botAccessControl).
 *
 * Scans robots.txt User-agent lines for the exact 15 AI crawlers the external
 * scanner checks. Explicit entries are the strong pass; a `User-agent: *`
 * wildcard block also passes ("wildcard rules apply to all crawlers including
 * AI bots"); no robots.txt or no groups at all fails.
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
 * AI bot rules in robots.txt check.
 */
class Check_Robots_Txt_Ai_Rules extends Check {

	/**
	 * The exact 15 AI crawler tokens checked, mirroring isitagentready.com.
	 *
	 * @var string[]
	 */
	const CHECKED_BOTS = array(
		'gptbot',
		'chatgpt-user',
		'google-extended',
		'ccbot',
		'anthropic-ai',
		'claude-web',
		'bytespider',
		'perplexitybot',
		'cohere-ai',
		'applebot-extended',
		'amazonbot',
		'meta-externalagent',
		'facebookbot',
		'omgilibot',
		'diffbot',
	);

	/**
	 * Check id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'robotsTxtAiRules';
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
		return 'AI bot rules in robots.txt';
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
			'checkedBots'  => self::CHECKED_BOTS,
			'foundBots'    => array(),
			'wildcardOnly' => false,
		);

		if ( 200 !== $response['code'] ) {
			$evidence[] = Evidence::parse(
				'Scan User-agent lines for known AI crawlers',
				'negative',
				'No robots.txt to scan (HTTP ' . $response['code'] . ').'
			);
			return $this->fail(
				'No robots.txt found (HTTP ' . $response['code'] . ') — no AI bot rules declared',
				$evidence,
				$details
			);
		}

		$found        = array();
		$has_wildcard = false;
		$group_count  = 0;

		foreach ( preg_split( '/\r\n|\r|\n/', $response['body'] ) as $line ) {
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
			if ( 'user-agent' !== strtolower( trim( substr( $line, 0, $colon ) ) ) ) {
				continue;
			}

			$group_count++;
			$agent = strtolower( trim( substr( $line, $colon + 1 ) ) );

			if ( '*' === $agent ) {
				$has_wildcard = true;
				continue;
			}
			if ( in_array( $agent, self::CHECKED_BOTS, true ) && ! in_array( $agent, $found, true ) ) {
				$found[] = $agent;
			}
		}

		$details['foundBots'] = $found;

		if ( ! empty( $found ) ) {
			$evidence[] = Evidence::parse(
				'Scan User-agent lines for known AI crawlers',
				'positive',
				'Explicit rules found for: ' . implode( ', ', $found ) . '.'
			);
			return $this->pass(
				'AI bot rules found for ' . count( $found ) . ' crawler' . ( 1 === count( $found ) ? '' : 's' ) . ': ' . implode( ', ', $found ),
				$evidence,
				$details
			);
		}

		if ( $has_wildcard ) {
			$details['wildcardOnly'] = true;
			$evidence[] = Evidence::parse(
				'Scan User-agent lines for known AI crawlers',
				'neutral',
				'No explicit AI crawler entries, but a User-agent: * block covers all crawlers.'
			);
			return $this->pass(
				'No AI-specific bot rules; wildcard rules apply to all crawlers including AI bots',
				$evidence,
				$details
			);
		}

		if ( 0 === $group_count ) {
			$evidence[] = Evidence::parse(
				'Scan User-agent lines for known AI crawlers',
				'negative',
				'robots.txt contains no User-agent groups at all.'
			);
			return $this->fail(
				'robots.txt has no User-agent rules — AI crawlers receive no directives',
				$evidence,
				$details
			);
		}

		$evidence[] = Evidence::parse(
			'Scan User-agent lines for known AI crawlers',
			'negative',
			'User-agent groups exist but none address the 15 checked AI crawlers and there is no wildcard group.'
		);
		return $this->fail(
			'No AI bot rules in robots.txt — none of the 15 checked AI crawlers are addressed and there is no wildcard group',
			$evidence,
			$details
		);
	}
}
