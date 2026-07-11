<?php
/**
 * Scan check: OAuth Protected Resource (RFC 9728).
 *
 * Notes whether the homepage advertises OAuth via a WWW-Authenticate header
 * (evidence only), then probes /.well-known/oauth-protected-resource for JSON
 * metadata with a `resource` identifier and a non-empty
 * `authorization_servers` array.
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
 * OAuth Protected Resource check.
 */
class Check_Oauth_Protected_Resource extends Check {

	/**
	 * Check id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'oauthProtectedResource';
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
		return 'OAuth Protected Resource';
	}

	/**
	 * Run the check.
	 *
	 * @return Check_Result
	 */
	public function run(): Check_Result {
		$evidence = array();

		// Step 1 (evidence only): does the homepage carry a WWW-Authenticate
		// header pointing agents at OAuth?
		$home = $this->http_get( $this->origin() . '/', 'GET / (WWW-Authenticate probe)', $evidence );
		if ( '' === $home['error'] ) {
			if ( isset( $home['headers']['www-authenticate'] ) ) {
				$evidence[] = Evidence::parse(
					'Check WWW-Authenticate header',
					'positive',
					'Homepage response carries a WWW-Authenticate header: ' . $home['headers']['www-authenticate']
				);
			} else {
				$evidence[] = Evidence::parse(
					'Check WWW-Authenticate header',
					'neutral',
					'Homepage response has no WWW-Authenticate header (informational — not required for a pass).'
				);
			}
		}

		// Step 2: the protected resource metadata document itself.
		$r = $this->http_get(
			$this->origin() . '/.well-known/oauth-protected-resource',
			'GET /.well-known/oauth-protected-resource',
			$evidence
		);

		if ( '' !== $r['error'] && 0 === $r['code'] ) {
			return $this->unable( 'Could not reach /.well-known/oauth-protected-resource — ' . $r['error'], $evidence );
		}

		if ( 200 !== $r['code'] ) {
			return $this->fail(
				'No OAuth protected resource metadata found at /.well-known/oauth-protected-resource (HTTP ' . $r['code'] . ')',
				$evidence
			);
		}

		$data = json_decode( $r['body'], true );
		if ( ! is_array( $data ) ) {
			$evidence[] = Evidence::parse( 'Parse protected resource metadata', 'negative', 'Returned 200 but the body is not valid JSON.' );
			return $this->fail( 'OAuth protected resource metadata is not valid JSON', $evidence );
		}

		$missing = array();
		if ( empty( $data['resource'] ) || ! is_string( $data['resource'] ) ) {
			$missing[] = 'resource';
		}
		if ( empty( $data['authorization_servers'] ) || ! is_array( $data['authorization_servers'] ) ) {
			$missing[] = 'authorization_servers (non-empty array)';
		}

		if ( ! empty( $missing ) ) {
			$evidence[] = Evidence::parse(
				'Validate protected resource metadata',
				'negative',
				'Missing required RFC 9728 fields: ' . implode( ', ', $missing ) . '.'
			);
			return $this->fail( 'OAuth protected resource metadata found but missing: ' . implode( ', ', $missing ), $evidence );
		}

		$summary = 'resource: ' . $data['resource'] . '; ' . count( $data['authorization_servers'] ) . ' authorization server(s)';
		if ( isset( $data['scopes_supported'] ) && is_array( $data['scopes_supported'] ) ) {
			$summary .= '; ' . count( $data['scopes_supported'] ) . ' scopes_supported';
		}

		$evidence[] = Evidence::parse( 'Validate protected resource metadata', 'positive', $summary . '.' );

		return $this->pass( 'OAuth protected resource metadata found at /.well-known/oauth-protected-resource', $evidence );
	}
}
