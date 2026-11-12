<?php
/**
 * Scan check: robots.txt user-request agent policy (id
 * `robotsAgentUserPolicy`, botAccessControl).
 *
 * To be cited by an answer engine, its user-fetch agent must be allowed to
 * read the page. Parses robots.txt per RFC 9309 (most-specific group,
 * longest-match rule) for ChatGPT-User, Claude-User and Perplexity-User and
 * fails when any of them is blocked from `/`. Blocking training crawlers is
 * fine and does not affect this check.
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
 * User-request agent policy check.
 */
class Check_Robots_Agent_User_Policy extends Check {

	/**
	 * User-request agents that must be allowed (lower-case tokens).
	 *
	 * @var string[]
	 */
	const USER_AGENTS = array( 'chatgpt-user', 'claude-user', 'perplexity-user' );

	/**
	 * Check id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'robotsAgentUserPolicy';
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
		return 'User-request agents allowed';
	}

	/**
	 * Run the check.
	 *
	 * @return Check_Result
	 */
	public function run(): Check_Result {
		$evidence = array();
		$res      = $this->http_get( $this->origin() . '/robots.txt', 'GET /robots.txt', $evidence );

		if ( '' !== $res['error'] && 0 === $res['code'] ) {
			return $this->unable( 'Could not reach /robots.txt — ' . $res['error'], $evidence );
		}

		if ( 200 !== $res['code'] ) {
			$evidence[] = Evidence::parse( 'Evaluate rules for user-request agents', 'positive', 'No robots.txt — nothing restricts ChatGPT-User, Claude-User or Perplexity-User.' );
			return $this->pass( 'No robots.txt restrictions on user-request agents', $evidence );
		}

		$groups   = $this->parse_groups( $res['body'] );
		$blocked  = array();
		$explicit = array();

		foreach ( self::USER_AGENTS as $agent ) {
			$group = $this->group_for( $groups, $agent );
			if ( null === $group ) {
				continue;
			}
			if ( in_array( $agent, $group['agents'], true ) ) {
				$explicit[] = $agent;
			}
			if ( $this->root_blocked( $group ) ) {
				$blocked[] = $agent;
			}
		}

		$details = array(
			'checked'  => self::USER_AGENTS,
			'blocked'  => $blocked,
			'explicit' => $explicit,
		);

		if ( ! empty( $blocked ) ) {
			$evidence[] = Evidence::parse(
				'Evaluate rules for user-request agents',
				'negative',
				'Disallow: / applies to ' . implode( ', ', $blocked ) . '.'
			);
			return $this->fail(
				'robots.txt blocks ' . implode( ', ', $blocked ) . ' — answer engines cannot fetch pages for their users, so they will not cite this site',
				$evidence,
				$details
			);
		}

		$evidence[] = Evidence::parse(
			'Evaluate rules for user-request agents',
			'positive',
			empty( $explicit )
				? 'No group blocks them; they fall under the wildcard rules.'
				: 'Explicitly allowed: ' . implode( ', ', $explicit ) . '.'
		);

		return $this->pass(
			'ChatGPT-User, Claude-User and Perplexity-User may read the site' . ( empty( $explicit ) ? '' : ' (explicit groups for ' . implode( ', ', $explicit ) . ')' ),
			$evidence,
			$details
		);
	}

	/**
	 * Parse robots.txt into groups: [ agents => string[], rules => [ [allow|disallow, path] ] ].
	 *
	 * @param string $body robots.txt body.
	 * @return array<int, array{agents: string[], rules: array<int, array{0: string, 1: string}>}>
	 */
	private function parse_groups( string $body ): array {
		$groups  = array();
		$current = null;
		$in_ua   = false;

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
				if ( ! $in_ua ) {
					if ( null !== $current ) {
						$groups[] = $current;
					}
					$current = array(
						'agents' => array(),
						'rules'  => array(),
					);
					$in_ua   = true;
				}
				$current['agents'][] = strtolower( $value );
				continue;
			}

			if ( null === $current ) {
				continue;
			}
			$in_ua = false;
			if ( 'allow' === $field || 'disallow' === $field ) {
				$current['rules'][] = array( $field, $value );
			}
		}
		if ( null !== $current ) {
			$groups[] = $current;
		}
		return $groups;
	}

	/**
	 * Effective group for an agent: all groups naming it, else the `*` groups
	 * (merged), else null.
	 *
	 * @param array  $groups Parsed groups.
	 * @param string $agent  Lower-case token.
	 * @return array|null
	 */
	private function group_for( array $groups, string $agent ): ?array {
		$merge = static function ( array $matches ): array {
			$out = array(
				'agents' => array(),
				'rules'  => array(),
			);
			foreach ( $matches as $g ) {
				$out['agents'] = array_merge( $out['agents'], $g['agents'] );
				$out['rules']  = array_merge( $out['rules'], $g['rules'] );
			}
			return $out;
		};

		$specific = array_filter(
			$groups,
			static function ( $g ) use ( $agent ) {
				return in_array( $agent, $g['agents'], true );
			}
		);
		if ( ! empty( $specific ) ) {
			return $merge( $specific );
		}
		$wild = array_filter(
			$groups,
			static function ( $g ) {
				return in_array( '*', $g['agents'], true );
			}
		);
		return empty( $wild ) ? null : $merge( $wild );
	}

	/**
	 * Whether `/` is disallowed in a group (longest match; Allow wins ties;
	 * an empty Disallow allows everything).
	 *
	 * @param array $group Group.
	 * @return bool
	 */
	private function root_blocked( array $group ): bool {
		$best_len  = -1;
		$best_rule = '';
		foreach ( $group['rules'] as $rule ) {
			list( $kind, $path ) = $rule;
			if ( '' === $path ) {
				// "Disallow:" with no value matches nothing.
				continue;
			}
			// Only rules that match the root path "/" are relevant.
			if ( '/' !== $path && '/*' !== $path && '*' !== $path ) {
				continue;
			}
			$len = strlen( $path );
			if ( $len > $best_len || ( $len === $best_len && 'allow' === $kind ) ) {
				$best_len  = $len;
				$best_rule = $kind;
			}
		}
		return 'disallow' === $best_rule;
	}
}
