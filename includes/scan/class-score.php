<?php
/**
 * Scan engine: weighted 0-100 score + letter grade.
 *
 * Complements the criterion-referenced Level 0-5 ladder (class-level.php)
 * with the points model hosted scanners (ora.ai, is-agentic.com) use, so a
 * site's number here is comparable to what those scanners report:
 *
 *  - Every scored check carries a point weight; weights per category sum to
 *    100 when every check is applicable.
 *  - Checks that do not apply (neutral), could not be reached
 *    (unableToCheck), or were excluded from the scan are left out of the
 *    denominator — they never count against the site.
 *  - Bonus checks (emerging protocols, informational probes, commerce) add
 *    points when they pass and never subtract; the bonus is capped.
 *  - score = round( earned / available * 100 + bonus ), capped at 100.
 *
 * Grade bands follow the ora.ai methodology (A+ 95, A 86, B 70, C 48, D 28).
 *
 * @package Ajaco
 */

namespace Ajaco\Scan;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Weighted score computation.
 */
class Score {

	/**
	 * Maximum bonus points added on top of the scored percentage.
	 */
	const BONUS_CAP = 5;

	/**
	 * Default weight for a check with no explicit entry (future checks).
	 */
	const DEFAULT_POINTS = 2;

	/**
	 * Point weights. Scored (non-bonus) weights sum to 100:
	 *
	 *   Discoverability 20 · Content Accessibility 25 · Bot Access Control 20
	 *   API/Auth/MCP Discovery 35 · Commerce 0 (bonus only)
	 *
	 * @return array<string, array{points: int, bonus: bool}>
	 */
	public static function weights(): array {
		$w = static function ( int $points, bool $bonus = false ): array {
			return array(
				'points' => $points,
				'bonus'  => $bonus,
			);
		};
		return array(
			// Discoverability (20).
			'robotsTxt'              => $w( 4 ),
			'sitemap'                => $w( 4 ),
			'sitemapLastmod'         => $w( 2 ),
			'linkHeaders'            => $w( 3 ),
			'metadata'               => $w( 3 ),
			'trustPages'             => $w( 2 ),
			'organizationSchema'     => $w( 2 ),
			'dnsAid'                 => $w( 2, true ),

			// Content Accessibility (25).
			'markdownNegotiation'    => $w( 8 ),
			'markdownAlternate'      => $w( 3 ),
			'markdownFrontmatter'    => $w( 2 ),
			'agentFriendly404'       => $w( 3 ),
			'contentNoJs'            => $w( 4 ),
			'llmsTxt'                => $w( 5 ),

			// Bot Access Control (20).
			'robotsTxtAiRules'       => $w( 7 ),
			'robotsAgentUserPolicy'  => $w( 6 ),
			'contentSignals'         => $w( 7 ),
			'webBotAuth'             => $w( 3, true ),

			// API, Auth, MCP & Skill Discovery (35).
			'apiCatalog'             => $w( 5 ),
			'mcpServerCard'          => $w( 4 ),
			'mcpServer'              => $w( 7 ),
			'agentSkills'            => $w( 5 ),
			'webMcp'                 => $w( 5 ),
			'authMd'                 => $w( 4 ),
			'aiCatalog'              => $w( 3 ),
			'oauthDiscovery'         => $w( 1 ),
			'oauthProtectedResource' => $w( 1 ),
			'a2aAgentCard'           => $w( 2, true ),

			// Commerce — emerging payment protocols, informational, bonus only.
			'x402'                   => $w( 2, true ),
			'mpp'                    => $w( 2, true ),
			'ucp'                    => $w( 2, true ),
			'acp'                    => $w( 2, true ),
			'ap2'                    => $w( 2, true ),
		);
	}

	/**
	 * Weight entry for a check id.
	 *
	 * @param string $id Check id.
	 * @return array{points: int, bonus: bool}
	 */
	public static function weight( string $id ): array {
		$all = self::weights();
		return isset( $all[ $id ] ) ? $all[ $id ] : array(
			'points' => self::DEFAULT_POINTS,
			'bonus'  => false,
		);
	}

	/**
	 * Grade bands, highest first: [ min score, letter, label ].
	 *
	 * @return array<int, array{min: int, grade: string, label: string}>
	 */
	public static function grades(): array {
		return array(
			array(
				'min'   => 95,
				'grade' => 'A+',
				'label' => __( 'Leading', 'aj-agent-crawl-optimizer' ),
			),
			array(
				'min'   => 86,
				'grade' => 'A',
				'label' => __( 'Agent-ready', 'aj-agent-crawl-optimizer' ),
			),
			array(
				'min'   => 70,
				'grade' => 'B',
				'label' => __( 'Competitive', 'aj-agent-crawl-optimizer' ),
			),
			array(
				'min'   => 48,
				'grade' => 'C',
				'label' => __( 'Needs work', 'aj-agent-crawl-optimizer' ),
			),
			array(
				'min'   => 28,
				'grade' => 'D',
				'label' => __( 'At risk', 'aj-agent-crawl-optimizer' ),
			),
			array(
				'min'   => 0,
				'grade' => 'F',
				'label' => __( 'Not ready', 'aj-agent-crawl-optimizer' ),
			),
		);
	}

	/**
	 * Letter grade + label for a score.
	 *
	 * @param int $score 0-100.
	 * @return array{grade: string, label: string}
	 */
	public static function grade( int $score ): array {
		foreach ( self::grades() as $band ) {
			if ( $score >= $band['min'] ) {
				return array(
					'grade' => $band['grade'],
					'label' => $band['label'],
				);
			}
		}
		return array(
			'grade' => 'F',
			'label' => '',
		);
	}

	/**
	 * Compute the score from serialized checks grouped by category (the
	 * Scanner's `checks` member: category => id => [ 'status' => ... ]).
	 *
	 * @param array<string, array<string, array>> $categories Serialized checks by category.
	 * @return array{
	 *   score: int, grade: string, gradeLabel: string,
	 *   earned: float, available: float, bonus: float, bonusCap: int,
	 *   layers: array<string, array>
	 * }
	 */
	public static function compute( array $categories ): array {
		$earned    = 0.0;
		$available = 0.0;
		$bonus     = 0.0;
		$layers    = array();

		foreach ( $categories as $category => $cat_checks ) {
			$layer = array(
				'earned'      => 0.0,
				'available'   => 0.0,
				'bonusEarned' => 0.0,
				'bonusMax'    => 0.0,
				'passed'      => 0,
				'failed'      => 0,
				'applicable'  => 0,
			);

			foreach ( $cat_checks as $id => $check_result ) {
				$status = isset( $check_result['status'] ) ? $check_result['status'] : Check_Result::STATUS_NEUTRAL;
				$w      = self::weight( (string) $id );

				if ( $w['bonus'] ) {
					$layer['bonusMax'] += $w['points'];
					if ( Check_Result::STATUS_PASS === $status ) {
						$layer['bonusEarned'] += $w['points'];
					}
					continue;
				}

				// Neutral (N/A), unable, and excluded checks stay out of the
				// denominator.
				if ( Check_Result::STATUS_PASS !== $status && Check_Result::STATUS_FAIL !== $status ) {
					continue;
				}

				++$layer['applicable'];
				$layer['available'] += $w['points'];
				if ( Check_Result::STATUS_PASS === $status ) {
					$layer['earned'] += $w['points'];
					++$layer['passed'];
				} else {
					++$layer['failed'];
				}
			}

			$layer['score']      = $layer['available'] > 0 ? (int) round( ( $layer['earned'] / $layer['available'] ) * 100 ) : null;
			$layers[ $category ] = $layer;

			$earned    += $layer['earned'];
			$available += $layer['available'];
			$bonus     += $layer['bonusEarned'];
		}

		$bonus = min( (float) self::BONUS_CAP, $bonus );

		if ( $available <= 0 ) {
			$score = 0;
		} else {
			$score = (int) min( 100, round( ( $earned / $available ) * 100 + $bonus ) );
		}

		$grade = self::grade( $score );

		return array(
			'score'      => $score,
			'grade'      => $available > 0 ? $grade['grade'] : '',
			'gradeLabel' => $available > 0 ? $grade['label'] : '',
			'earned'     => $earned,
			'available'  => $available,
			'bonus'      => $bonus,
			'bonusCap'   => self::BONUS_CAP,
			'layers'     => $layers,
		);
	}

	/**
	 * Points a failing check would add to the score if it passed — the
	 * "impact" used to rank fixes. Bonus checks report their bonus points.
	 *
	 * @param string $id        Check id.
	 * @param float  $available Current available (denominator) points.
	 * @return int Score points, rounded.
	 */
	public static function impact( string $id, float $available ): int {
		$w = self::weight( $id );
		if ( $w['bonus'] ) {
			return (int) $w['points'];
		}
		if ( $available <= 0 ) {
			return (int) $w['points'];
		}
		return (int) round( ( $w['points'] / $available ) * 100 );
	}
}
