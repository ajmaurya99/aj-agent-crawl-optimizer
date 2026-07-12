<?php
/**
 * Scan engine: hosting compatibility diagnosis.
 *
 * The plugin serves every agent endpoint virtually (no files are written) —
 * so when a feature toggle is ON but its check still fails, the request most
 * likely never reached WordPress. Two hosting patterns cause this:
 *
 *  - 403 on /.well-known/* — nginx `location ~ /\. { deny all; }` dot-path
 *    rules without a well-known exception (the server denies the URL before
 *    PHP runs).
 *  - 404 on /llms.txt, /openapi.json, /auth.md — static-asset location
 *    blocks (`location ~* \.(txt|json)$ { ... }`) without try_files, so the
 *    server 404s missing static files instead of routing to index.php.
 *
 * Two sources feed the diagnosis: the finished scan (for endpoints the 21
 * readiness checks cover) and a direct probe of the ones they don't — the
 * check list mirrors the external scanner, which has no llms.txt or OpenAPI
 * check, so a rule that 404s /llms.txt would otherwise be invisible.
 *
 * Output is the list of blocked endpoints plus copy-paste nginx/Apache
 * remedies the dashboard surfaces.
 *
 * @package Ajaco
 */

namespace Ajaco\Scan;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post-scan hosting diagnosis.
 */
class Hosting_Diagnosis {

	/**
	 * Endpoint checks whose failure (while their feature is enabled) points
	 * at the hosting layer rather than the plugin.
	 *
	 * checkId => [ option, path, kind (dot-path | static-ext) ].
	 *
	 * @return array<string, array{option: string, path: string, kind: string}>
	 */
	public static function endpoint_map(): array {
		return array(
			'apiCatalog'    => array(
				'option' => 'ajaco_api_catalog_enabled',
				'path'   => '/.well-known/api-catalog',
				'kind'   => 'dot-path',
			),
			'mcpServerCard' => array(
				'option' => 'ajaco_mcp_server_card_enabled',
				'path'   => '/.well-known/mcp/server-card.json',
				'kind'   => 'dot-path',
			),
			'agentSkills'   => array(
				'option' => 'ajaco_agent_skills_index_enabled',
				'path'   => '/.well-known/agent-skills/index.json',
				'kind'   => 'dot-path',
			),
			'authMd'        => array(
				'option' => 'ajaco_auth_md_enabled',
				'path'   => '/auth.md',
				'kind'   => 'static-ext',
			),
		);
	}

	/**
	 * Analyze a serialized scan (the Scanner's checks-by-category array) and
	 * return hosting issues: enabled features whose endpoint the server
	 * blocked or failed to route to WordPress.
	 *
	 * @param array<string, array<string, array>> $checks_by_category Scan 'checks' member.
	 * @param bool                                 $probe              Whether to make live probes of the
	 *                                                                 uncovered endpoints. Skipped during
	 *                                                                 single-check re-verify to avoid firing
	 *                                                                 unrelated loopback requests on every fix.
	 * @param array                                $carry              Previously computed unchecked-endpoint
	 *                                                                 issues to reuse when $probe is false.
	 * @return array{issues: array<int, array>, snippets: array<string, string>}
	 */
	public static function analyze( array $checks_by_category, bool $probe = true, array $carry = array() ): array {
		$flat = array();
		foreach ( $checks_by_category as $cat_checks ) {
			foreach ( $cat_checks as $id => $result ) {
				$flat[ $id ] = $result;
			}
		}

		$issues = array();
		foreach ( self::endpoint_map() as $check_id => $endpoint ) {
			if ( ! isset( $flat[ $check_id ] ) ) {
				continue;
			}
			$result = $flat[ $check_id ];
			if ( Check_Result::STATUS_FAIL !== ( isset( $result['status'] ) ? $result['status'] : '' ) ) {
				continue;
			}
			if ( ! get_option( $endpoint['option'], false ) ) {
				// Feature off — a plain fail, not a hosting problem.
				continue;
			}

			$http_status = self::endpoint_http_status( $result, $endpoint['path'] );

			// Only a 403/404 means the server intercepted the request before it
			// reached the plugin. A 2xx/3xx that failed the check is a
			// content-level failure (already reported as an ordinary fail) — NOT
			// a hosting block, and the server-config snippets wouldn't help it.
			if ( 403 === $http_status ) {
				$cause = 'denied';
			} elseif ( 404 === $http_status ) {
				$cause = 'not-routed';
			} else {
				continue;
			}

			$issues[] = array(
				'check'      => $check_id,
				'path'       => $endpoint['path'],
				'kind'       => $endpoint['kind'],
				'httpStatus' => $http_status,
				'cause'      => $cause,
				'summary'    => self::summary_for( $endpoint['path'], $cause, $http_status ),
			);
		}

		// Endpoints the plugin serves that NO readiness check covers (the 21
		// checks come from the external scanner's list, which has no llms.txt
		// or /openapi.json check). Without this, a static-file rule that 404s
		// /llms.txt would be completely invisible — probe them directly. These
		// probes are check-independent, so a single-check re-verify reuses the
		// last full scan's result instead of re-probing (see $probe/$carry).
		if ( $probe ) {
			$issues = array_merge( $issues, self::probe_unchecked_endpoints() );
		} else {
			$issues = array_merge( $issues, self::carried_unchecked_issues( $carry ) );
		}

		return array(
			'issues'   => $issues,
			'snippets' => empty( $issues ) ? array() : array(
				'nginx'  => self::nginx_snippet(),
				'apache' => self::apache_snippet(),
			),
		);
	}

	/**
	 * The unchecked-endpoint issues (`check` === '') from a prior hosting
	 * result, reused when a single-check re-verify skips live probing.
	 *
	 * @param array $carry Prior scan's `hosting` member, or `issues` list.
	 * @return array<int, array>
	 */
	private static function carried_unchecked_issues( array $carry ): array {
		$issues = isset( $carry['issues'] ) && is_array( $carry['issues'] ) ? $carry['issues'] : $carry;
		$out    = array();
		foreach ( (array) $issues as $issue ) {
			if ( is_array( $issue ) && isset( $issue['check'] ) && '' === $issue['check'] ) {
				$out[] = $issue;
			}
		}
		return $out;
	}

	/**
	 * Endpoints the plugin serves that the 21 readiness checks never probe
	 * (the check list mirrors the external scanner, which has no llms.txt or
	 * OpenAPI check of its own).
	 *
	 * @return array<string, array{option: string, kind: string}>
	 */
	public static function unchecked_endpoint_map(): array {
		return array(
			'/llms.txt'      => array(
				'option' => 'ajaco_llms_txt_enabled',
				'kind'   => 'static-ext',
			),
			'/llms-full.txt' => array(
				'option' => 'ajaco_llms_txt_enabled',
				'kind'   => 'static-ext',
			),
			'/openapi.json'  => array(
				'option' => 'ajaco_openapi_enabled',
				'kind'   => 'static-ext',
			),
		);
	}

	/**
	 * Probe the enabled-but-unchecked endpoints directly and report any the
	 * server intercepts (403) or fails to route to WordPress (404).
	 *
	 * @return array<int, array>
	 */
	private static function probe_unchecked_endpoints(): array {
		$issues = array();
		$origin = untrailingslashit( home_url( '/' ) );

		foreach ( self::unchecked_endpoint_map() as $path => $endpoint ) {
			if ( ! get_option( $endpoint['option'], false ) ) {
				continue;
			}

			$response = wp_remote_get(
				$origin . $path,
				array(
					'timeout'     => 10,
					'redirection' => 3,
					'user-agent'  => 'AJACO-Scanner/' . AJACO_VERSION . ' (+' . home_url( '/' ) . ')',
					// Same-origin probe only — TLS verification relaxed so a scan
					// works on a local/staging site with a self-signed cert; the
					// filter re-enables it. External requests are never made here.
					'sslverify'   => apply_filters( 'ajaco_scan_sslverify', false ),
				)
			);

			if ( is_wp_error( $response ) ) {
				continue;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );

			if ( 403 === $code ) {
				$cause = 'denied';
			} elseif ( 404 === $code ) {
				$cause = 'not-routed';
			} else {
				// 2xx (or anything else) — the plugin is reaching the client.
				continue;
			}

			$issues[] = array(
				'check'      => '',
				'path'       => $path,
				'kind'       => $endpoint['kind'],
				'httpStatus' => $code,
				'cause'      => $cause,
				'summary'    => self::summary_for( $path, $cause, $code ),
			);
		}

		return $issues;
	}

	/**
	 * Extract the HTTP status of the fetch evidence step for a given path.
	 *
	 * @param array  $result Serialized check result.
	 * @param string $path   Endpoint path.
	 * @return int 0 when not found.
	 */
	private static function endpoint_http_status( array $result, string $path ): int {
		if ( empty( $result['evidence'] ) || ! is_array( $result['evidence'] ) ) {
			return 0;
		}
		foreach ( $result['evidence'] as $step ) {
			if ( empty( $step['request']['url'] ) || empty( $step['response']['status'] ) ) {
				continue;
			}
			$url_path = wp_parse_url( (string) $step['request']['url'], PHP_URL_PATH );
			if ( is_string( $url_path ) && substr( $url_path, -strlen( $path ) ) === $path ) {
				return (int) $step['response']['status'];
			}
		}
		return 0;
	}

	/**
	 * One-line human summary of an issue.
	 *
	 * @param string $path        Endpoint path.
	 * @param string $cause       denied|not-routed|unknown.
	 * @param int    $http_status Observed status.
	 * @return string
	 */
	private static function summary_for( string $path, string $cause, int $http_status ): string {
		if ( 'denied' === $cause ) {
			/* translators: 1: endpoint path. */
			return sprintf( __( '%s is enabled in the plugin but the server returns 403 — a dot-path deny rule (common nginx security snippet) is blocking it before WordPress runs.', 'aj-agent-crawl-optimizer' ), $path );
		}
		if ( 'not-routed' === $cause ) {
			/* translators: 1: endpoint path. */
			return sprintf( __( '%s is enabled in the plugin but the server returns 404 — the request is not being routed to WordPress (usually a static-file location block without try_files, or an aggressive page cache).', 'aj-agent-crawl-optimizer' ), $path );
		}
		/* translators: 1: endpoint path, 2: HTTP status code. */
		return sprintf( __( '%1$s is enabled in the plugin but the live probe returned HTTP %2$d — the response did not come from the plugin.', 'aj-agent-crawl-optimizer' ), $path, $http_status );
	}

	/**
	 * nginx remedy: a ^~ prefix location outranks the regex dot-path deny
	 * rules that cause the 403, and try_files restores index.php routing for
	 * the root text/json/md endpoints.
	 *
	 * @return string
	 */
	public static function nginx_snippet(): string {
		return "# Agent Ready: let agent-discovery endpoints reach WordPress.\n"
			. "# (^~ prefix match takes precedence over regex rules like `location ~ /\\.`)\n"
			. "location ^~ /.well-known/ {\n"
			. "    try_files \$uri \$uri/ /index.php?\$args;\n"
			. "}\n"
			. "\n"
			. "# Route the plugin's virtual root files to WordPress when they don't\n"
			. "# exist on disk (static-asset blocks often 404 these otherwise).\n"
			. "location ~* ^/(llms(-full)?\\.txt|openapi\\.json|auth\\.md)\$ {\n"
			. "    try_files \$uri /index.php?\$args;\n"
			. '}';
	}

	/**
	 * Apache remedy: standard WP .htaccess already routes unknown paths to
	 * index.php; this marked block re-asserts it for our endpoints on setups
	 * where a host-level rule (RedirectMatch 403 on dot-paths, or a static
	 * handler) intercepts them first.
	 *
	 * @return string
	 */
	public static function apache_snippet(): string {
		return "# BEGIN Agent Ready endpoint passthrough\n"
			. "<IfModule mod_rewrite.c>\n"
			. "RewriteEngine On\n"
			. "RewriteBase /\n"
			. "RewriteCond %{REQUEST_FILENAME} !-f\n"
			. "RewriteRule ^\\.well-known/ index.php [L]\n"
			. "RewriteCond %{REQUEST_FILENAME} !-f\n"
			. "RewriteRule ^(llms(-full)?\\.txt|openapi\\.json|auth\\.md)\$ index.php [L]\n"
			. "</IfModule>\n"
			. '# END Agent Ready endpoint passthrough';
	}
}
