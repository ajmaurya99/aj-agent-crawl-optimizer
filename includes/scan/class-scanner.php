<?php
/**
 * Scan engine: orchestrator.
 *
 * Runs the registered checks against the site's own origin, applies commerce
 * gating, computes category scores and the Level 0-5 ladder, and persists the
 * last scan for the dashboard. Check ids, statuses, and the result shape
 * mirror Cloudflare's Agent Readiness scanner so results are comparable.
 *
 * @package Ajaco
 */

namespace Ajaco\Scan;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scanner.
 */
class Scanner {

	/**
	 * Option storing the most recent scan result array.
	 */
	const LAST_SCAN_OPTION = 'ajaco_last_scan';

	/**
	 * Check ids hidden from the default preset (mirrors the external
	 * scanner's "All Checks" behavior).
	 *
	 * @var string[]
	 */
	const HIDDEN_BY_DEFAULT = array( 'a2aAgentCard', 'ap2' );

	/**
	 * Ordered check id => class name map.
	 *
	 * @return array<string, string>
	 */
	public static function check_classes(): array {
		return array(
			'robotsTxt'              => __NAMESPACE__ . '\\Checks\\Check_Robots_Txt',
			'sitemap'                => __NAMESPACE__ . '\\Checks\\Check_Sitemap',
			'linkHeaders'            => __NAMESPACE__ . '\\Checks\\Check_Link_Headers',
			'dnsAid'                 => __NAMESPACE__ . '\\Checks\\Check_Dns_Aid',
			'markdownNegotiation'    => __NAMESPACE__ . '\\Checks\\Check_Markdown_Negotiation',
			'robotsTxtAiRules'       => __NAMESPACE__ . '\\Checks\\Check_Robots_Txt_Ai_Rules',
			'contentSignals'         => __NAMESPACE__ . '\\Checks\\Check_Content_Signals',
			'webBotAuth'             => __NAMESPACE__ . '\\Checks\\Check_Web_Bot_Auth',
			'apiCatalog'             => __NAMESPACE__ . '\\Checks\\Check_Api_Catalog',
			'oauthDiscovery'         => __NAMESPACE__ . '\\Checks\\Check_Oauth_Discovery',
			'oauthProtectedResource' => __NAMESPACE__ . '\\Checks\\Check_Oauth_Protected_Resource',
			'authMd'                 => __NAMESPACE__ . '\\Checks\\Check_Auth_Md',
			'mcpServerCard'          => __NAMESPACE__ . '\\Checks\\Check_Mcp_Server_Card',
			'a2aAgentCard'           => __NAMESPACE__ . '\\Checks\\Check_A2a_Agent_Card',
			'agentSkills'            => __NAMESPACE__ . '\\Checks\\Check_Agent_Skills',
			'webMcp'                 => __NAMESPACE__ . '\\Checks\\Check_Web_Mcp',
			'x402'                   => __NAMESPACE__ . '\\Checks\\Check_X402',
			'mpp'                    => __NAMESPACE__ . '\\Checks\\Check_Mpp',
			'ucp'                    => __NAMESPACE__ . '\\Checks\\Check_Ucp',
			'acp'                    => __NAMESPACE__ . '\\Checks\\Check_Acp',
			'ap2'                    => __NAMESPACE__ . '\\Checks\\Check_Ap2',
		);
	}

	/**
	 * Instantiate every check, ordered.
	 *
	 * @return array<string, Check>
	 */
	public static function get_checks(): array {
		static $instances = null;
		if ( null !== $instances ) {
			return $instances;
		}

		$instances = array();
		foreach ( self::check_classes() as $id => $class ) {
			if ( class_exists( $class ) ) {
				$instances[ $id ] = new $class();
			}
		}
		return $instances;
	}

	/**
	 * Default check ids for a full scan (19 — hidden ones excluded).
	 *
	 * @return string[]
	 */
	public static function default_check_ids(): array {
		return array_values( array_diff( array_keys( self::check_classes() ), self::HIDDEN_BY_DEFAULT ) );
	}

	/**
	 * Detect whether this site is a commerce site, with signals.
	 *
	 * @return array{is_commerce: bool, signals: string[]}
	 */
	public static function detect_commerce(): array {
		$signals = array();
		if ( class_exists( 'WooCommerce' ) ) {
			$signals[] = 'plugin:woocommerce';
		}
		if ( class_exists( 'Easy_Digital_Downloads' ) || function_exists( 'EDD' ) ) {
			$signals[] = 'plugin:edd';
		}

		/**
		 * Filter the detected commerce signals for the self-scan.
		 *
		 * @param string[] $signals Signal tokens (e.g. `plugin:woocommerce`).
		 */
		$signals = (array) apply_filters( 'ajaco_commerce_signals', $signals );

		return array(
			'is_commerce' => ! empty( $signals ),
			'signals'     => array_values( $signals ),
		);
	}

	/**
	 * Run a scan.
	 *
	 * @param string[]|null $enabled_ids Check ids to run; null = default preset.
	 * @return array Full scan result (also persisted to LAST_SCAN_OPTION).
	 */
	public function run( ?array $enabled_ids = null ): array {
		$checks  = self::get_checks();
		$enabled = null === $enabled_ids ? self::default_check_ids() : array_values( array_intersect( $enabled_ids, array_keys( $checks ) ) );

		$commerce    = self::detect_commerce();
		$is_commerce = $commerce['is_commerce'];

		$results  = array();
		$statuses = array();

		foreach ( $checks as $id => $check ) {
			if ( ! in_array( $id, $enabled, true ) ) {
				$result = new Check_Result(
					Check_Result::STATUS_NEUTRAL,
					$check->get_name() . ' not checked (excluded by scan configuration)',
					array()
				);
			} else {
				$result = $this->run_check( $check, $is_commerce );
			}

			$results[ $id ]  = $result;
			$statuses[ $id ] = $result->status;
		}

		$level      = Level::compute( $statuses );
		$names      = Level::names();
		$categories = $this->group_by_category( $checks, $results );

		$scan = array(
			'url'             => home_url( '/' ),
			'scannedAt'       => gmdate( 'c' ),
			'level'           => $level,
			'levelName'       => isset( $names[ $level ] ) ? $names[ $level ] : '',
			'checks'          => $categories,
			'scores'          => $this->score_categories( $checks, $results ),
			'nextLevel'       => Level::next_level( $level, $statuses ),
			'isCommerce'      => $is_commerce,
			'commerceSignals' => $commerce['signals'],
			'hosting'         => Hosting_Diagnosis::analyze( $categories, true ),
			'enabledChecks'   => $enabled,
			'scannerVersion'  => AJACO_VERSION,
		);

		update_option( self::LAST_SCAN_OPTION, $scan, false );

		return $scan;
	}

	/**
	 * Re-run a single check and merge it into the stored scan (the Fix-now →
	 * verify loop). Falls back to a full scan when no stored scan exists.
	 *
	 * @param string $id Check id.
	 * @return array{scan: array, check: array}|null Null for unknown id.
	 */
	public function run_one( string $id ): ?array {
		$checks = self::get_checks();
		if ( ! isset( $checks[ $id ] ) ) {
			return null;
		}

		$stored = get_option( self::LAST_SCAN_OPTION );
		if ( ! is_array( $stored ) || empty( $stored['checks'] ) ) {
			// No stored scan to merge into — run a full scan, force-including
			// the requested id so hidden-by-default checks (a2aAgentCard, ap2)
			// actually execute instead of returning an "excluded" placeholder.
			$scan = $this->run( array_values( array_unique( array_merge( self::default_check_ids(), array( $id ) ) ) ) );
			return array(
				'scan'  => $scan,
				'check' => $this->find_check_result( $scan, $id ),
			);
		}

		$commerce = self::detect_commerce();
		$result   = $this->run_check( $checks[ $id ], $commerce['is_commerce'] );

		$category = $checks[ $id ]->get_category();

		$stored['checks'][ $category ][ $id ] = $result->to_array();

		// Recompute level/scores from the merged statuses.
		$statuses = array();
		foreach ( $stored['checks'] as $cat_checks ) {
			foreach ( $cat_checks as $check_id => $check_result ) {
				$statuses[ $check_id ] = isset( $check_result['status'] ) ? $check_result['status'] : Check_Result::STATUS_NEUTRAL;
			}
		}

		$level = Level::compute( $statuses );
		$names = Level::names();

		$stored['level']           = $level;
		$stored['levelName']       = isset( $names[ $level ] ) ? $names[ $level ] : '';
		$stored['nextLevel']       = Level::next_level( $level, $statuses );
		$stored['scannedAt']       = gmdate( 'c' );
		$stored['isCommerce']      = $commerce['is_commerce'];
		$stored['commerceSignals'] = $commerce['signals'];
		$stored['scores']          = $this->rescore_stored( $stored['checks'] );
		// Single-check re-verify: recompute the checked-endpoint issues (they
		// depend on the just-updated check results) but reuse the last full
		// scan's live probes of the uncovered endpoints, so a fix doesn't fire
		// unrelated loopback requests (and risk a gateway timeout) each time.
		$prior_hosting             = isset( $stored['hosting'] ) && is_array( $stored['hosting'] ) ? $stored['hosting'] : array();
		$stored['hosting']         = Hosting_Diagnosis::analyze( $stored['checks'], false, $prior_hosting );

		update_option( self::LAST_SCAN_OPTION, $stored, false );

		return array(
			'scan'  => $stored,
			'check' => $result->to_array(),
		);
	}

	/**
	 * Latest stored scan, or null.
	 *
	 * @return array|null
	 */
	public static function get_last_scan(): ?array {
		$stored = get_option( self::LAST_SCAN_OPTION );
		return is_array( $stored ) ? $stored : null;
	}

	/**
	 * Execute one check with timing and commerce gating.
	 *
	 * @param Check $check       Check instance.
	 * @param bool  $is_commerce Whether the site is a commerce site.
	 * @return Check_Result
	 */
	private function run_check( Check $check, bool $is_commerce ): Check_Result {
		$start = microtime( true );

		try {
			$result = $check->run();
		} catch ( \Throwable $e ) {
			$result = new Check_Result(
				Check_Result::STATUS_UNABLE,
				'Check crashed: ' . $e->getMessage(),
				array( Evidence::conclude( 'neutral', 'Internal error while running this check.' ) )
			);
		}

		$result->duration_ms = (int) round( ( microtime( true ) - $start ) * 1000 );

		// Commerce gating (mirrors the external scanner): on non-commerce
		// sites an absent commerce protocol is informational, not a failure.
		if ( Check::CATEGORY_COMMERCE === $check->get_category()
			&& ! $is_commerce
			&& Check_Result::STATUS_FAIL === $result->status ) {
			$result->status   = Check_Result::STATUS_NEUTRAL;
			$result->message .= ' (not a commerce site)';
		}

		return $result;
	}

	/**
	 * Group serialized results by category, preserving check order.
	 *
	 * @param array<string, Check>        $checks  Check instances.
	 * @param array<string, Check_Result> $results Results by id.
	 * @return array<string, array<string, array>>
	 */
	private function group_by_category( array $checks, array $results ): array {
		$grouped = array(
			Check::CATEGORY_DISCOVERABILITY => array(),
			Check::CATEGORY_CONTENT         => array(),
			Check::CATEGORY_BOT_ACCESS      => array(),
			Check::CATEGORY_DISCOVERY       => array(),
			Check::CATEGORY_COMMERCE        => array(),
		);
		foreach ( $checks as $id => $check ) {
			$grouped[ $check->get_category() ][ $id ] = $results[ $id ]->to_array();
		}
		return $grouped;
	}

	/**
	 * Category scores per the external scanner's model: neutral checks are
	 * excluded from the denominator; commerce never counts toward overall.
	 *
	 * @param array<string, Check>        $checks  Check instances.
	 * @param array<string, Check_Result> $results Results by id.
	 * @return array
	 */
	private function score_categories( array $checks, array $results ): array {
		$serialized = array();
		foreach ( $checks as $id => $check ) {
			$serialized[ $check->get_category() ][ $id ] = array( 'status' => $results[ $id ]->status );
		}
		return $this->rescore_stored( $serialized );
	}

	/**
	 * Score categories from serialized check arrays.
	 *
	 * @param array<string, array<string, array>> $categories Serialized checks by category.
	 * @return array{categories: array, overall: int, passed: int, total: int}
	 */
	private function rescore_stored( array $categories ): array {
		$out            = array();
		$overall_passed = 0;
		$overall_total  = 0;

		foreach ( $categories as $category => $cat_checks ) {
			$passed = 0;
			$total  = 0;
			foreach ( $cat_checks as $check_result ) {
				$status = isset( $check_result['status'] ) ? $check_result['status'] : Check_Result::STATUS_NEUTRAL;
				if ( Check_Result::STATUS_NEUTRAL === $status ) {
					continue;
				}
				$total++;
				if ( Check_Result::STATUS_PASS === $status ) {
					$passed++;
				}
			}

			$counts_in_score = Check::CATEGORY_COMMERCE !== $category;
			if ( $counts_in_score ) {
				$overall_passed += $passed;
				$overall_total  += $total;
			}

			$out[ $category ] = array(
				'passed'       => $passed,
				'total'        => $total,
				'score'        => $total > 0 ? (int) round( ( $passed / $total ) * 100 ) : 0,
				'countInScore' => $counts_in_score,
				'checkCount'   => count( $cat_checks ),
			);
		}

		return array(
			'categories' => $out,
			'overall'    => $overall_total > 0 ? (int) round( ( $overall_passed / $overall_total ) * 100 ) : 0,
			'passed'     => $overall_passed,
			'total'      => $overall_total,
		);
	}

	/**
	 * Find a serialized check result inside a scan array.
	 *
	 * @param array  $scan Scan array.
	 * @param string $id   Check id.
	 * @return array|null
	 */
	private function find_check_result( array $scan, string $id ): ?array {
		foreach ( $scan['checks'] as $cat_checks ) {
			if ( isset( $cat_checks[ $id ] ) ) {
				return $cat_checks[ $id ];
			}
		}
		return null;
	}
}
