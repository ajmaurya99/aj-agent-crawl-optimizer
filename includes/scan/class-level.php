<?php
/**
 * Scan engine: Level 0-5 maturity ladder + next-level gap computation.
 *
 * The ladder is criterion-referenced to Cloudflare's Agent Readiness scanner:
 *
 *   0 Not Ready          — fewer than 2 of: robots.txt, sitemap, Link headers
 *   1 Basic Web Presence — 2 of 3: robots.txt, sitemap, Link headers
 *   2 Bot-Aware          — L1 + both: AI bot rules AND Content Signals
 *   3 Agent-Readable     — L2 + markdown content negotiation
 *   4 Agent-Integrated   — L3 + 1 of 4: MCP card, A2A card, Agent Skills, API catalog
 *   5 Agent-Native       — L4 + 2 of 3 arms: Web Bot Auth, all 4 integrations,
 *                          auth metadata (OAuth discovery / PRM / auth.md)
 *
 * @package Ajaco
 */

namespace Ajaco\Scan;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Level computation over a map of check id => status string.
 */
class Level {

	const BASICS       = array( 'robotsTxt', 'sitemap', 'linkHeaders' );
	const BOT_AWARE    = array( 'robotsTxtAiRules', 'contentSignals' );
	const INTEGRATIONS = array( 'mcpServerCard', 'a2aAgentCard', 'agentSkills', 'apiCatalog' );
	const AUTH_CHECKS  = array( 'oauthDiscovery', 'oauthProtectedResource', 'authMd' );

	/**
	 * Level names, indexed by level number.
	 *
	 * @return array<int, string>
	 */
	public static function names(): array {
		return array(
			0 => __( 'Not Ready', 'aj-agent-crawl-optimizer' ),
			1 => __( 'Basic Web Presence', 'aj-agent-crawl-optimizer' ),
			2 => __( 'Bot-Aware', 'aj-agent-crawl-optimizer' ),
			3 => __( 'Agent-Readable', 'aj-agent-crawl-optimizer' ),
			4 => __( 'Agent-Integrated', 'aj-agent-crawl-optimizer' ),
			5 => __( 'Agent-Native', 'aj-agent-crawl-optimizer' ),
		);
	}

	/**
	 * Compute the level for a set of results.
	 *
	 * @param array<string, string> $statuses Map of check id => status.
	 * @return int 0-5.
	 */
	public static function compute( array $statuses ): int {
		$pass = static function ( $id ) use ( $statuses ) {
			return isset( $statuses[ $id ] ) && Check_Result::STATUS_PASS === $statuses[ $id ];
		};

		$l1 = count( array_filter( self::BASICS, $pass ) ) >= 2;
		$l2 = $l1 && $pass( 'robotsTxtAiRules' ) && $pass( 'contentSignals' );
		$l3 = $l2 && $pass( 'markdownNegotiation' );
		$l4 = $l3 && count( array_filter( self::INTEGRATIONS, $pass ) ) >= 1;

		$arms = 0;
		if ( $pass( 'webBotAuth' ) ) {
			$arms++;
		}
		if ( count( array_filter( self::INTEGRATIONS, $pass ) ) === count( self::INTEGRATIONS ) ) {
			$arms++;
		}
		if ( count( array_filter( self::AUTH_CHECKS, $pass ) ) >= 1 ) {
			$arms++;
		}
		$l5 = $l4 && $arms >= 2;

		if ( $l5 ) {
			return 5;
		}
		if ( $l4 ) {
			return 4;
		}
		if ( $l3 ) {
			return 3;
		}
		if ( $l2 ) {
			return 2;
		}
		if ( $l1 ) {
			return 1;
		}
		return 0;
	}

	/**
	 * Compute the nextLevel gap: target level, name, and the concrete failing
	 * checks that unlock it (with fix metadata attached).
	 *
	 * @param int                   $level    Current level.
	 * @param array<string, string> $statuses Map of check id => status.
	 * @return array|null Null when already at Level 5.
	 */
	public static function next_level( int $level, array $statuses ): ?array {
		if ( $level >= 5 ) {
			return null;
		}

		$pass = static function ( $id ) use ( $statuses ) {
			return isset( $statuses[ $id ] ) && Check_Result::STATUS_PASS === $statuses[ $id ];
		};
		$not_passing = static function ( array $ids ) use ( $pass ) {
			return array_values( array_filter( $ids, static function ( $id ) use ( $pass ) {
				return ! $pass( $id );
			} ) );
		};

		$target      = $level + 1;
		$satisfy_any = false;
		$note        = '';
		$missing     = array();

		switch ( $target ) {
			case 1:
				$missing     = $not_passing( self::BASICS );
				$satisfy_any = true;
				$needed      = 2 - count( array_filter( self::BASICS, $pass ) );
				/* translators: %d: number of checks still needed. */
				$note = sprintf( _n( 'Pass %d more of these basics.', 'Pass %d more of these basics.', max( 1, $needed ), 'aj-agent-crawl-optimizer' ), max( 1, $needed ) );
				break;
			case 2:
				$missing = $not_passing( self::BOT_AWARE );
				$note    = __( 'Both checks are required.', 'aj-agent-crawl-optimizer' );
				break;
			case 3:
				$missing = $not_passing( array( 'markdownNegotiation' ) );
				break;
			case 4:
				$missing     = $not_passing( self::INTEGRATIONS );
				$satisfy_any = true;
				$note        = __( 'Passing any one of these unlocks Level 4.', 'aj-agent-crawl-optimizer' );
				break;
			case 5:
				// List the failing checks of unsatisfied arms.
				$arm_integrations = count( array_filter( self::INTEGRATIONS, $pass ) ) === count( self::INTEGRATIONS );
				$arm_auth         = count( array_filter( self::AUTH_CHECKS, $pass ) ) >= 1;
				$arm_wba          = $pass( 'webBotAuth' );

				if ( ! $arm_wba ) {
					$missing[] = 'webBotAuth';
				}
				if ( ! $arm_integrations ) {
					$missing = array_merge( $missing, $not_passing( self::INTEGRATIONS ) );
				}
				if ( ! $arm_auth ) {
					$missing = array_merge( $missing, $not_passing( self::AUTH_CHECKS ) );
				}
				$satisfy_any = true;
				$note        = __( 'Level 5 needs 2 of 3 arms: Web Bot Auth, all four integrations, and auth metadata (any OAuth/auth.md check).', 'aj-agent-crawl-optimizer' );
				break;
		}

		$names        = self::names();
		$requirements = array();
		foreach ( array_unique( $missing ) as $id ) {
			$info           = Check_Info::get( $id );
			$requirements[] = array(
				'check'       => $id,
				'description' => $info['description'],
				'prompt'      => $info['prompt'],
				'specUrls'    => $info['specUrls'],
				'skillUrl'    => $info['skillUrl'],
			);
		}

		return array(
			'target'       => $target,
			'name'         => isset( $names[ $target ] ) ? $names[ $target ] : '',
			'satisfyAny'   => $satisfy_any,
			'note'         => $note,
			'requirements' => $requirements,
		);
	}
}
