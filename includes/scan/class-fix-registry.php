<?php
/**
 * Scan engine: fix registry — maps failing checks to one-click fixes.
 *
 * This is the plugin's structural advantage over external scanners: where
 * isitagentready.com can only emit a copy-paste prompt, we can flip the
 * feature that publishes the artifact, then re-scan that single check to
 * prove it went green.
 *
 * @package Ajaco
 */

namespace Ajaco\Scan;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry of automatic fixes.
 */
class Fix_Registry {

	/**
	 * Map of check id => fix definition.
	 *
	 * `option` is the feature toggle the fix enables. `label` is shown on the
	 * Fix button/confirmation. `note` explains what enabling actually does.
	 *
	 * @return array<string, array{option: string, label: string, note: string}>
	 */
	public static function all(): array {
		return array(
			'linkHeaders'         => array(
				'option' => 'ajaco_api_catalog_enabled',
				'label'  => __( 'Enable API Catalog + Link header', 'aj-agent-crawl-optimizer' ),
				'note'   => __( 'Emits Link: rel="api-catalog" on every response and serves the catalog.', 'aj-agent-crawl-optimizer' ),
			),
			'markdownNegotiation' => array(
				'option' => 'ajaco_markdown_enabled',
				'label'  => __( 'Enable Markdown Negotiation', 'aj-agent-crawl-optimizer' ),
				'note'   => __( 'Serves markdown with Vary: Accept when agents send Accept: text/markdown.', 'aj-agent-crawl-optimizer' ),
			),
			'robotsTxtAiRules'    => array(
				'option' => 'ajaco_ai_bot_rules_enabled',
				'label'  => __( 'Enable AI bot rules in robots.txt', 'aj-agent-crawl-optimizer' ),
				'note'   => __( 'Adds explicit User-agent groups for the 15 AI crawlers scanners check for.', 'aj-agent-crawl-optimizer' ),
			),
			'contentSignals'      => array(
				'option' => 'ajaco_content_signals_enabled',
				'label'  => __( 'Enable Content Signals', 'aj-agent-crawl-optimizer' ),
				'note'   => __( 'Declares ai-train / search / ai-input preferences in robots.txt.', 'aj-agent-crawl-optimizer' ),
			),
			'apiCatalog'          => array(
				'option' => 'ajaco_api_catalog_enabled',
				'label'  => __( 'Enable API Catalog', 'aj-agent-crawl-optimizer' ),
				'note'   => __( 'Serves an RFC 9727 linkset at /.well-known/api-catalog.', 'aj-agent-crawl-optimizer' ),
			),
			'agentSkills'         => array(
				'option' => 'ajaco_agent_skills_index_enabled',
				'label'  => __( 'Enable Agent Skills index', 'aj-agent-crawl-optimizer' ),
				'note'   => __( 'Publishes /.well-known/agent-skills/index.json with verifiable SKILL.md artifacts.', 'aj-agent-crawl-optimizer' ),
			),
			'mcpServerCard'       => array(
				'option' => 'ajaco_mcp_server_card_enabled',
				'label'  => __( 'Enable MCP Server Card', 'aj-agent-crawl-optimizer' ),
				'note'   => __( 'Publishes /.well-known/mcp/server-card.json.', 'aj-agent-crawl-optimizer' ),
			),
			'webMcp'              => array(
				'option' => 'ajaco_webmcp_enabled',
				'label'  => __( 'Enable WebMCP tools', 'aj-agent-crawl-optimizer' ),
				'note'   => __( 'Registers in-page tools on navigator.modelContext (experimental browser API).', 'aj-agent-crawl-optimizer' ),
			),
			'authMd'              => array(
				'option' => 'ajaco_auth_md_enabled',
				'label'  => __( 'Publish auth.md', 'aj-agent-crawl-optimizer' ),
				'note'   => __( 'Serves /auth.md documenting Application Password authentication for agents.', 'aj-agent-crawl-optimizer' ),
			),
		);
	}

	/**
	 * Whether an automatic fix exists for a check.
	 *
	 * @param string $check_id Check id.
	 * @return bool
	 */
	public static function can_fix( string $check_id ): bool {
		$all = self::all();
		return isset( $all[ $check_id ] );
	}

	/**
	 * Apply the fix for a check (enable the mapped feature toggle).
	 *
	 * @param string $check_id Check id.
	 * @return array{applied: bool, message: string, alreadyEnabled: bool}
	 */
	public static function apply( string $check_id ): array {
		$all = self::all();
		if ( ! isset( $all[ $check_id ] ) ) {
			return array(
				'applied'        => false,
				'alreadyEnabled' => false,
				'message'        => __( 'No automatic fix is available for this check.', 'aj-agent-crawl-optimizer' ),
			);
		}

		$fix     = $all[ $check_id ];
		$already = (bool) get_option( $fix['option'], false );

		if ( ! $already ) {
			update_option( $fix['option'], true );
		}

		// Endpoint bodies may be cached; make sure the re-scan sees fresh output.
		delete_transient( 'ajaco_llms_txt_cache' );
		delete_transient( 'ajaco_llms_full_txt_cache' );
		delete_transient( 'ajaco_openapi_cache' );

		return array(
			'applied'        => true,
			'alreadyEnabled' => $already,
			'message'        => $already
				? __( 'Feature was already enabled — re-scanning. If the check still fails, a page cache or server rule is likely intercepting the endpoint.', 'aj-agent-crawl-optimizer' )
				: $fix['note'],
		);
	}
}
