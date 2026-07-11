<?php
/**
 * Scan check: Agent Skills index (Agent Skills Discovery RFC v0.2.0).
 *
 * Probes /.well-known/agent-skills/index.json (falling back to the legacy
 * /.well-known/skills/index.json on 404) and validates each skills[] entry
 * against the v0.2.0 schema: name, type (skill-md|archive), description,
 * url, and a sha256:<64 hex> digest. Passes when at least one entry is valid.
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
 * Agent Skills index check.
 */
class Check_Agent_Skills extends Check {

	/**
	 * Check id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'agentSkills';
	}

	/**
	 * Category.
	 *
	 * @return string
	 */
	public function get_category(): string {
		return Check::CATEGORY_DISCOVERY;
	}

	/**
	 * Display name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'Agent Skills index';
	}

	/**
	 * Run the check.
	 *
	 * @return Check_Result
	 */
	public function run(): Check_Result {
		$evidence = array();

		$path = '/.well-known/agent-skills/index.json';
		$r    = $this->http_get( $this->origin() . $path, 'GET ' . $path, $evidence );

		if ( '' !== $r['error'] && 0 === $r['code'] ) {
			return $this->unable( 'Could not reach ' . $path . ' — ' . $r['error'], $evidence );
		}

		if ( 404 === $r['code'] ) {
			$evidence[] = Evidence::parse( 'Try legacy path', 'neutral', 'v0.2.0 path returned 404 — trying legacy path.' );

			$path = '/.well-known/skills/index.json';
			$r    = $this->http_get( $this->origin() . $path, 'GET ' . $path, $evidence );

			if ( '' !== $r['error'] && 0 === $r['code'] ) {
				return $this->unable( 'Could not reach ' . $path . ' — ' . $r['error'], $evidence );
			}
		}

		if ( 200 !== $r['code'] ) {
			return $this->fail(
				'No Agent Skills index found at /.well-known/agent-skills/index.json or the legacy /.well-known/skills/index.json',
				$evidence
			);
		}

		$data = json_decode( $r['body'], true );
		if ( ! is_array( $data ) ) {
			$evidence[] = Evidence::parse( 'Parse skills index', 'negative', 'Returned 200 but the body is not valid JSON.' );
			return $this->fail( 'Agent Skills index at ' . $path . ' is not valid JSON', $evidence );
		}

		if ( ! isset( $data['skills'] ) || ! is_array( $data['skills'] ) ) {
			$evidence[] = Evidence::parse(
				'Parse skills index',
				'negative',
				'JSON parsed but contains no `skills` array (Agent Skills Discovery RFC v0.2.0).'
			);
			return $this->fail( 'Agent Skills index found but contains no skills array', $evidence );
		}

		$spec_version = isset( $data['$schema'] ) && is_string( $data['$schema'] ) ? $data['$schema'] : '';
		$skill_count  = count( $data['skills'] );

		$evidence[] = Evidence::parse(
			'Parse skills index',
			'positive',
			'Valid JSON with a skills array of ' . $skill_count . ' ' . ( 1 === $skill_count ? 'entry' : 'entries' )
				. ( '' !== $spec_version ? ' ($schema: ' . $spec_version . ')' : ' (no $schema declared)' ) . '.'
		);

		$valid    = 0;
		$problems = array();

		foreach ( array_values( $data['skills'] ) as $i => $skill ) {
			$entry_problems = $this->validate_entry( $skill );

			if ( empty( $entry_problems ) ) {
				$valid++;
				continue;
			}

			$label = 'entry ' . ( $i + 1 );
			if ( is_array( $skill ) && isset( $skill['name'] ) && is_string( $skill['name'] ) && '' !== $skill['name'] ) {
				$label .= ' (`' . $skill['name'] . '`)';
			}
			$problems[] = $label . ': ' . implode( ', ', $entry_problems );
		}

		$details = array(
			'skillCount'     => $skill_count,
			'v2ValidEntries' => $valid,
			'specVersion'    => $spec_version,
			'path'           => $path,
		);

		if ( 0 === $skill_count ) {
			$evidence[] = Evidence::parse( 'Validate skill entries', 'negative', 'The skills array is empty.' );
			return $this->fail( 'Agent Skills index found but its skills array is empty', $evidence, $details );
		}

		$problem_summary = implode( '; ', array_slice( $problems, 0, 3 ) );
		if ( count( $problems ) > 3 ) {
			$problem_summary .= '; and ' . ( count( $problems ) - 3 ) . ' more';
		}

		if ( 0 === $valid ) {
			$evidence[] = Evidence::parse(
				'Validate skill entries',
				'negative',
				'0 of ' . $skill_count . ' entries match the v0.2.0 schema: ' . $problem_summary . '.'
			);
			return $this->fail( 'Agent Skills index found but no entries match the v0.2.0 schema: ' . $problem_summary, $evidence, $details );
		}

		$summary = $valid . ' of ' . $skill_count . ' entries valid per RFC v0.2.0';
		if ( ! empty( $problems ) ) {
			$summary .= ' (invalid: ' . $problem_summary . ')';
		}
		$evidence[] = Evidence::parse( 'Validate skill entries', 'positive', $summary . '.' );

		return $this->pass(
			'Agent Skills index found at ' . $path . ' with ' . $valid . ' valid skill' . ( 1 === $valid ? '' : 's' ),
			$evidence,
			$details
		);
	}

	/**
	 * Validate one skills[] entry against the RFC v0.2.0 schema.
	 *
	 * @param mixed $skill Decoded entry.
	 * @return string[] Problems found; empty when the entry is valid.
	 */
	private function validate_entry( $skill ): array {
		if ( ! is_array( $skill ) ) {
			return array( 'not an object' );
		}

		$problems = array();

		if ( empty( $skill['name'] ) || ! is_string( $skill['name'] ) ) {
			$problems[] = 'missing name';
		}

		if ( ! isset( $skill['type'] ) || ! is_string( $skill['type'] ) || '' === $skill['type'] ) {
			$problems[] = 'missing type';
		} elseif ( ! in_array( $skill['type'], array( 'skill-md', 'archive' ), true ) ) {
			$problems[] = 'invalid type `' . $skill['type'] . '` (expected skill-md or archive)';
		}

		if ( empty( $skill['description'] ) || ! is_string( $skill['description'] ) ) {
			$problems[] = 'missing description';
		}

		if ( empty( $skill['url'] ) || ! is_string( $skill['url'] ) ) {
			$problems[] = 'missing url';
		}

		if ( ! isset( $skill['digest'] ) || ! is_string( $skill['digest'] ) ) {
			if ( isset( $skill['sha256'] ) ) {
				$problems[] = 'uses a legacy `sha256` key instead of `digest`';
			} else {
				$problems[] = 'missing digest';
			}
		} elseif ( ! preg_match( '/^sha256:[0-9a-f]{64}$/', $skill['digest'] ) ) {
			$problems[] = 'digest is not sha256:<64 hex>';
		}

		return $problems;
	}
}
