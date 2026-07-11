<?php
/**
 * Scan check: OAuth / OIDC discovery.
 *
 * Probes /.well-known/oauth-authorization-server (RFC 8414) and then
 * /.well-known/openid-configuration (OIDC Discovery); passes when either
 * returns 200 with JSON metadata declaring an `issuer`. The presence of
 * authorization_endpoint, token_endpoint, and jwks_uri is noted in evidence.
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
 * OAuth / OIDC discovery check.
 */
class Check_Oauth_Discovery extends Check {

	/**
	 * Check id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'oauthDiscovery';
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
		return 'OAuth / OIDC discovery';
	}

	/**
	 * Run the check.
	 *
	 * @return Check_Result
	 */
	public function run(): Check_Result {
		$evidence = array();

		$paths = array(
			'/.well-known/oauth-authorization-server',
			'/.well-known/openid-configuration',
		);

		$codes = array();

		foreach ( $paths as $path ) {
			$r = $this->http_get( $this->origin() . $path, 'GET ' . $path, $evidence );

			if ( '' !== $r['error'] && 0 === $r['code'] ) {
				return $this->unable( 'Could not reach ' . $path . ' — ' . $r['error'], $evidence );
			}

			$codes[ $path ] = $r['code'];

			if ( 200 !== $r['code'] ) {
				continue;
			}

			$data = json_decode( $r['body'], true );
			if ( ! is_array( $data ) ) {
				$evidence[] = Evidence::parse( 'Parse ' . $path, 'negative', 'Returned 200 but the body is not valid JSON.' );
				continue;
			}

			if ( empty( $data['issuer'] ) || ! is_string( $data['issuer'] ) ) {
				$evidence[] = Evidence::parse(
					'Parse ' . $path,
					'negative',
					'JSON metadata has no `issuer` field (required by RFC 8414 / OIDC Discovery).'
				);
				continue;
			}

			$present = array();
			$missing = array();
			foreach ( array( 'authorization_endpoint', 'token_endpoint', 'jwks_uri' ) as $field ) {
				if ( ! empty( $data[ $field ] ) ) {
					$present[] = $field;
				} else {
					$missing[] = $field;
				}
			}

			// An issuer alone is not usable discovery metadata: agents need at
			// least the authorization and token endpoints to authenticate
			// (RFC 8414 / OIDC Discovery required members).
			if ( empty( $data['authorization_endpoint'] ) || empty( $data['token_endpoint'] ) ) {
				$evidence[] = Evidence::parse(
					'Validate discovery metadata',
					'negative',
					'issuer present but missing ' . implode( ', ', $missing ) . ' — agents cannot authenticate against incomplete metadata.'
				);
				continue;
			}

			$summary = 'issuer: ' . $data['issuer'] . '; declares ' . implode( ', ', $present );
			if ( ! empty( $missing ) ) {
				$summary .= '; does not declare ' . implode( ', ', $missing );
			}

			$evidence[] = Evidence::parse( 'Validate discovery metadata', 'positive', $summary . '.' );

			return $this->pass( 'OAuth/OIDC discovery metadata found at ' . $path, $evidence );
		}

		if ( 404 === $codes[ $paths[0] ] && 404 === $codes[ $paths[1] ] ) {
			return $this->fail( 'No OAuth/OIDC discovery metadata found at either well-known path', $evidence );
		}

		return $this->fail(
			'No valid OAuth/OIDC discovery metadata at /.well-known/oauth-authorization-server or /.well-known/openid-configuration',
			$evidence
		);
	}
}
