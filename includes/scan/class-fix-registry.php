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
	 * Optional `callback` runs after the toggle is enabled (e.g. to adjust a
	 * related setting).
	 *
	 * @return array<string, array{option: string, label: string, note: string, callback?: callable}>
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
				'note'   => __( 'Adds explicit User-agent groups for 20 AI crawlers per your per-bot policy.', 'aj-agent-crawl-optimizer' ),
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
				'note'   => __( 'Registers four read-only in-page tools via document.modelContext.registerTool (W3C WebMCP draft, Chrome origin trial).', 'aj-agent-crawl-optimizer' ),
			),
			'authMd'              => array(
				'option' => 'ajaco_auth_md_enabled',
				'label'  => __( 'Publish auth.md', 'aj-agent-crawl-optimizer' ),
				'note'   => __( 'Serves /auth.md documenting Application Password authentication for agents.', 'aj-agent-crawl-optimizer' ),
			),
			'sitemapLastmod'      => array(
				'option' => 'ajaco_sitemap_lastmod_enabled',
				'label'  => __( 'Add lastmod to the core sitemap', 'aj-agent-crawl-optimizer' ),
				'note'   => __( 'Adds <lastmod> dates to WordPress core sitemap entries from each post\'s modification date.', 'aj-agent-crawl-optimizer' ),
			),
			'metadata'            => array(
				'option' => 'ajaco_meta_tags_enabled',
				'label'  => __( 'Enable identity meta tags', 'aj-agent-crawl-optimizer' ),
				'note'   => __( 'Outputs canonical and Open Graph (og:type, og:image, og:title, og:url) tags; auto-suppressed when an SEO plugin is active. og:image needs a Site Icon or custom logo (Appearance → Customize) to point at.', 'aj-agent-crawl-optimizer' ),
			),
			'markdownAlternate'   => array(
				'option' => 'ajaco_markdown_enabled',
				'label'  => __( 'Enable Markdown Negotiation', 'aj-agent-crawl-optimizer' ),
				'note'   => __( 'Adds <link rel="alternate" type="text/markdown"> and a matching Link header to every page.', 'aj-agent-crawl-optimizer' ),
			),
			'markdownFrontmatter' => array(
				'option' => 'ajaco_markdown_enabled',
				'label'  => __( 'Enable Markdown Negotiation', 'aj-agent-crawl-optimizer' ),
				'note'   => __( 'Served Markdown opens with a frontmatter block (title, url, site, modified).', 'aj-agent-crawl-optimizer' ),
			),
			'agentFriendly404'    => array(
				'option' => 'ajaco_markdown_enabled',
				'label'  => __( 'Enable Markdown Negotiation', 'aj-agent-crawl-optimizer' ),
				'note'   => __( 'Error pages keep their 404 status and are served as Markdown when an agent asks for it.', 'aj-agent-crawl-optimizer' ),
			),
			'llmsTxt'             => array(
				'option' => 'ajaco_llms_txt_enabled',
				'label'  => __( 'Enable llms.txt', 'aj-agent-crawl-optimizer' ),
				'note'   => __( 'Serves the curated /llms.txt and /llms-full.txt.', 'aj-agent-crawl-optimizer' ),
			),
			'robotsAgentUserPolicy' => array(
				'option'   => 'ajaco_ai_bot_rules_enabled',
				'label'    => __( 'Allow user-request agents', 'aj-agent-crawl-optimizer' ),
				'note'     => __( 'Enables AI bot rules and sets ChatGPT-User, Claude-User and Perplexity-User to Allow in the per-bot policy.', 'aj-agent-crawl-optimizer' ),
				'callback' => array( __CLASS__, 'allow_user_request_agents' ),
			),
			'aiCatalog'           => array(
				'option' => 'ajaco_ai_catalog_enabled',
				'label'  => __( 'Enable AI Catalog', 'aj-agent-crawl-optimizer' ),
				'note'   => __( 'Publishes /.well-known/ard.json (and ai-catalog.json) listing every enabled agent-facing endpoint.', 'aj-agent-crawl-optimizer' ),
			),
		);
	}

	/**
	 * Fix callback: make sure the user-request agents are allowed in the
	 * per-bot policy (the ai-bot-rules feature writes the robots.txt groups).
	 *
	 * @return void
	 */
	public static function allow_user_request_agents(): void {
		$policy = get_option( 'ajaco_ai_bot_policy', array() );
		$policy = is_array( $policy ) ? $policy : array();
		foreach ( array( 'ChatGPT-User', 'Claude-User', 'Perplexity-User' ) as $token ) {
			$policy[ $token ] = 'allow';
		}
		update_option( 'ajaco_ai_bot_policy', $policy );
	}

	/**
	 * Fixes that are a guided manual step rather than a toggle: a button that
	 * takes the admin to the right screen. Not applied by `apply()` or by
	 * "Fix all safe items".
	 *
	 * @return array<string, array{url: string, label: string, note: string}>
	 */
	public static function links(): array {
		$links = array();

		if ( function_exists( 'Ajaco\\settings_page_url' ) ) {
			$links['organizationSchema'] = array(
				'url'   => \Ajaco\settings_page_url() . '#ajaco-org-schema',
				'label' => __( 'Add organization details', 'aj-agent-crawl-optimizer' ),
				'note'  => __( 'Fill in the Organization details under Settings → JSON-LD Schema (official profile URLs, contact email or phone, address). They are added to the Organization node; an SEO plugin\'s own schema settings apply instead when one is active.', 'aj-agent-crawl-optimizer' ),
			);
		}

		if ( function_exists( 'Ajaco\\mcp_adapter_supported' ) && \Ajaco\mcp_adapter_supported() ) {
			$links['mcpServer'] = array(
				'url'   => \Ajaco\mcp_adapter_install_url(),
				'label' => __( 'Install the MCP Adapter', 'aj-agent-crawl-optimizer' ),
				'note'  => __( 'The official WordPress MCP Adapter plugin serves a real MCP server from this site\'s Abilities. Install and activate it, then re-scan — the server card, catalogs, llms.txt and auth.md pick it up automatically.', 'aj-agent-crawl-optimizer' ),
			);
		}

		return $links;
	}

	/**
	 * Guided manual fix for a check, or null.
	 *
	 * @param string $check_id Check id.
	 * @return array{url: string, label: string, note: string}|null
	 */
	public static function link_fix( string $check_id ): ?array {
		$links = self::links();
		return isset( $links[ $check_id ] ) ? $links[ $check_id ] : null;
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
		if ( isset( $fix['callback'] ) && is_callable( $fix['callback'] ) ) {
			call_user_func( $fix['callback'] );
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
