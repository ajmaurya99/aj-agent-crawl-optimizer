<?php
/**
 * WP-CLI: `wp agent-ready` — scan, status, and fix from the command line.
 *
 * Agency fleet scripting and the natural interface for AI coding agents
 * operating a site over SSH. `--format=agent` emits the same markdown report
 * style as isitagentready.com's agent format.
 *
 * @package Ajaco
 */

namespace Ajaco;

use Ajaco\Scan\Check_Info;
use Ajaco\Scan\Check_Result;
use Ajaco\Scan\Fix_Registry;
use Ajaco\Scan\Scanner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Audit and fix this site's AI-agent readiness.
 */
class Agent_Ready_Command {

	/**
	 * Run a readiness scan against this site.
	 *
	 * ## OPTIONS
	 *
	 * [--checks=<ids>]
	 * : Comma-separated check ids to run (default: the 19-check preset).
	 *
	 * [--format=<format>]
	 * : Output format: summary, json, or agent (markdown fix report).
	 * ---
	 * default: summary
	 * options:
	 *   - summary
	 *   - json
	 *   - agent
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp agent-ready scan
	 *     wp agent-ready scan --format=agent
	 *     wp agent-ready scan --checks=robotsTxt,sitemap --format=json
	 *
	 * @param array $args       Positional args (unused).
	 * @param array $assoc_args Named args.
	 * @return void
	 */
	public function scan( $args, $assoc_args ): void {
		unset( $args );
		$format  = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'summary';
		$enabled = null;

		if ( ! empty( $assoc_args['checks'] ) ) {
			// array_filter drops empty tokens so a trailing/double comma
			// doesn't abort the scan with "Invalid check names: ".
			$requested = array_values( array_filter( array_map( 'trim', explode( ',', (string) $assoc_args['checks'] ) ) ) );
			$known     = array_keys( Scanner::check_classes() );
			$invalid   = array_diff( $requested, $known );
			if ( ! empty( $invalid ) ) {
				\WP_CLI::error( 'Invalid check names: ' . implode( ', ', $invalid ) );
			}
			$enabled = $requested;
		}

		\WP_CLI::log( 'Scanning ' . home_url( '/' ) . ' …' );
		$scanner = new Scanner();
		$scan    = $scanner->run( $enabled );

		$this->output_scan( $scan, $format );
	}

	/**
	 * Show the most recent scan without re-running it.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format: summary, json, or agent.
	 * ---
	 * default: summary
	 * ---
	 *
	 * @param array $args       Positional args (unused).
	 * @param array $assoc_args Named args.
	 * @return void
	 */
	public function status( $args, $assoc_args ): void {
		unset( $args );
		$scan = Scanner::get_last_scan();
		if ( null === $scan ) {
			\WP_CLI::error( 'No scan stored yet. Run: wp agent-ready scan' );
		}
		$this->output_scan( $scan, isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'summary' );
	}

	/**
	 * Apply the one-click fix for a failing check, then re-scan it.
	 *
	 * ## OPTIONS
	 *
	 * [<check>]
	 * : The check id to fix (e.g. agentSkills, markdownNegotiation).
	 *
	 * [--all-safe]
	 * : Apply every available fix for currently failing checks.
	 *
	 * ## EXAMPLES
	 *
	 *     wp agent-ready fix agentSkills
	 *     wp agent-ready fix --all-safe
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Named args.
	 * @return void
	 */
	public function fix( $args, $assoc_args ): void {
		$all_safe = ! empty( $assoc_args['all-safe'] );

		if ( ! $all_safe && empty( $args[0] ) ) {
			\WP_CLI::error( 'Pass a check id or --all-safe. Fixable checks: ' . implode( ', ', array_keys( Fix_Registry::all() ) ) );
		}

		$targets = array();
		if ( $all_safe ) {
			$scan = Scanner::get_last_scan();
			if ( null === $scan ) {
				\WP_CLI::log( 'No stored scan — running one first.' );
				$scanner = new Scanner();
				$scan    = $scanner->run();
			}
			foreach ( $scan['checks'] as $cat_checks ) {
				foreach ( $cat_checks as $id => $result ) {
					if ( Check_Result::STATUS_FAIL === $result['status'] && Fix_Registry::can_fix( $id ) ) {
						$targets[] = $id;
					}
				}
			}
			if ( empty( $targets ) ) {
				\WP_CLI::success( 'Nothing to fix — no failing checks have an automatic fix.' );
				return;
			}
		} else {
			$targets[] = (string) $args[0];
		}

		$scanner = new Scanner();
		foreach ( $targets as $id ) {
			if ( ! Fix_Registry::can_fix( $id ) ) {
				if ( ! $all_safe ) {
					// Explicit single target: hard error (exit 1) so scripted
					// fleet usage can detect the failure.
					\WP_CLI::error( "$id: unknown check or no automatic fix available. Fixable: " . implode( ', ', array_keys( Fix_Registry::all() ) ) );
				}
				\WP_CLI::warning( "$id: no automatic fix available." );
				continue;
			}
			$fix = Fix_Registry::apply( $id );
			\WP_CLI::log( "$id: " . $fix['message'] );

			$result = $scanner->run_one( $id );
			if ( null === $result || null === $result['check'] ) {
				\WP_CLI::warning( "$id: could not re-verify." );
				continue;
			}
			$status = $result['check']['status'];
			if ( Check_Result::STATUS_PASS === $status ) {
				\WP_CLI::success( "$id: re-scan passed ✓  (now Level " . $result['scan']['level'] . ' — ' . $result['scan']['levelName'] . ')' );
			} else {
				\WP_CLI::warning( "$id: still $status after fix — " . $result['check']['message'] );
			}
		}
	}

	/**
	 * Print a scan in the requested format.
	 *
	 * @param array  $scan   Scan array.
	 * @param string $format summary|json|agent.
	 * @return void
	 */
	private function output_scan( array $scan, string $format ): void {
		// WP_CLI::line (not ::log) for machine formats — ::log is suppressed
		// by --quiet, which is exactly how scripts silence the progress noise
		// while still capturing the structured output.
		if ( 'json' === $format ) {
			\WP_CLI::line( (string) wp_json_encode( $scan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			return;
		}

		if ( 'agent' === $format ) {
			\WP_CLI::line( $this->agent_report( $scan ) );
			return;
		}

		\WP_CLI::log( '' );
		\WP_CLI::log( sprintf( 'Level %d — %s   (%s)', $scan['level'], $scan['levelName'], $scan['url'] ) );
		\WP_CLI::log( sprintf( 'Scored checks passed: %d/%d', $scan['scores']['passed'], $scan['scores']['total'] ) );
		\WP_CLI::log( '' );

		$rows = array();
		foreach ( $scan['checks'] as $category => $cat_checks ) {
			foreach ( $cat_checks as $id => $result ) {
				$rows[] = array(
					'check'    => $id,
					'category' => $category,
					'status'   => $result['status'],
					'message'  => $result['message'],
				);
			}
		}
		\WP_CLI\Utils\format_items( 'table', $rows, array( 'check', 'category', 'status', 'message' ) );

		if ( ! empty( $scan['nextLevel'] ) ) {
			$next = $scan['nextLevel'];
			\WP_CLI::log( '' );
			\WP_CLI::log( sprintf( 'Next: Level %d — %s. %s', $next['target'], $next['name'], $next['note'] ) );
			foreach ( $next['requirements'] as $req ) {
				$fixable = Fix_Registry::can_fix( $req['check'] ) ? '  [wp agent-ready fix ' . $req['check'] . ']' : '';
				\WP_CLI::log( '  • ' . $req['check'] . ' — ' . $req['description'] . $fixable );
			}
		}
	}

	/**
	 * Markdown report in the external scanner's agent format.
	 *
	 * @param array $scan Scan array.
	 * @return string
	 */
	private function agent_report( array $scan ): string {
		$out  = '# Site Analysis: ' . $scan['url'] . "\n\n";
		$out .= 'Score: ' . $scan['level'] . '/5 (' . $scan['levelName'] . ")\n\n";

		$failing = array();
		foreach ( $scan['checks'] as $cat_checks ) {
			foreach ( $cat_checks as $id => $result ) {
				if ( Check_Result::STATUS_FAIL === $result['status'] ) {
					$failing[ $id ] = $result;
				}
			}
		}

		if ( empty( $failing ) ) {
			return $out . "All enabled checks pass. This site is agent-ready at its current scan profile.\n";
		}

		$out .= "The following issues were found. Fix them to improve your agent-readiness score:\n\n";
		foreach ( $failing as $id => $result ) {
			$info = Check_Info::get( $id );
			$out .= '## ' . ( '' !== $info['description'] ? $info['description'] : $id ) . "\n";
			$out .= $result['message'] . "\n";
			if ( '' !== $info['prompt'] ) {
				$out .= $info['prompt'] . "\n";
			}
			if ( Fix_Registry::can_fix( $id ) ) {
				$out .= 'One-click fix: wp agent-ready fix ' . $id . "\n";
			}
			if ( '' !== $info['skillUrl'] ) {
				$out .= 'Implementation guide: ' . $info['skillUrl'] . "\n";
			}
			$out .= "\n";
		}
		return $out;
	}
}

\WP_CLI::add_command( 'agent-ready', __NAMESPACE__ . '\\Agent_Ready_Command' );
