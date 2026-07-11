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
 * This class recognizes those signatures in a finished scan and produces
 * actionable server-config remedies the dashboard can surface.
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
	 * @return array{issues: array<int, array>, snippets: array<string, string>}
	 */
	public static function analyze( array $checks_by_category ): array {
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

			// The smoking gun: WE serve this endpoint, yet the live probe got
			// 403/404 — the request never reached the plugin.
			if ( 403 === $http_status ) {
				$cause = 'denied';
			} elseif ( 404 === $http_status ) {
				$cause = 'not-routed';
			} else {
				// 5xx / unexpected — still worth surfacing, generically.
				$cause = 'unknown';
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

		return array(
			'issues'   => $issues,
			'snippets' => empty( $issues ) ? array() : array(
				'nginx'  => self::nginx_snippet(),
				'apache' => self::apache_snippet(),
			),
		);
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
