<?php
/**
 * Admin: register contextual Help tabs on the AJ Agent Crawl Optimizer settings screen.
 *
 * Hooks `load-{$hook}` so the tabs are added only when the page is actually
 * being rendered — no work done on other admin screens.
 *
 * @package Ajaco
 */

namespace Ajaco;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The settings screen's hook suffix depends on its parent menu (it moved
// under the "Agent Ready" top-level menu in v2), so bind to the real hook
// captured at registration time instead of a hardcoded 'load-settings_page_*'.
add_action(
	'admin_menu',
	function () {
		$settings_hook = admin_page_hook( 'settings' );
		if ( '' !== $settings_hook ) {
			add_action( 'load-' . $settings_hook, __NAMESPACE__ . '\\register_help_tabs' );
		}
	},
	20
);

/**
 * Register the contextual Help tabs.
 *
 * @return void
 */
function register_help_tabs(): void {
	$screen = get_current_screen();
	if ( ! $screen ) {
		return;
	}

	$screen->add_help_tab(
		array(
			'id'      => 'ajaco-overview',
			'title'   => __( 'Overview', 'aj-agent-crawl-optimizer' ),
			'content' => help_tab_overview(),
		)
	);

	$screen->add_help_tab(
		array(
			'id'      => 'ajaco-features',
			'title'   => __( 'Features', 'aj-agent-crawl-optimizer' ),
			'content' => help_tab_features(),
		)
	);

	$screen->add_help_tab(
		array(
			'id'      => 'ajaco-developers',
			'title'   => __( 'For Developers', 'aj-agent-crawl-optimizer' ),
			'content' => help_tab_developers(),
		)
	);

	$screen->add_help_tab(
		array(
			'id'      => 'ajaco-troubleshooting',
			'title'   => __( 'Troubleshooting', 'aj-agent-crawl-optimizer' ),
			'content' => help_tab_troubleshooting(),
		)
	);

	$screen->set_help_sidebar( help_sidebar() );
}

/**
 * @return string
 */
function help_tab_overview(): string {
	$html  = '<p><strong>' . esc_html__( 'AJ Agent Crawl Optimizer makes your site legible to AI agents.', 'aj-agent-crawl-optimizer' ) . '</strong></p>';
	$html .= '<p>' . esc_html__( 'It exposes machine-readable manifests at well-known URLs (API catalog, MCP server card, agent skills index, OpenAPI spec, llms.txt), serves clean Markdown when an AI requests it via the Accept header, and declares your AI usage preferences in robots.txt. Nothing changes for human visitors.', 'aj-agent-crawl-optimizer' ) . '</p>';
	$html .= '<p>' . esc_html__( 'Each capability is a separate toggle below — enable only what you need. All toggles default to off.', 'aj-agent-crawl-optimizer' ) . '</p>';
	return $html;
}

/**
 * @return string
 */
function help_tab_features(): string {
	$rows = array(
		array( __( 'Markdown Negotiation', 'aj-agent-crawl-optimizer' ), 'Accept: text/markdown' ),
		array( __( 'Content-Signals', 'aj-agent-crawl-optimizer' ), '/robots.txt' ),
		array( __( 'API Catalog', 'aj-agent-crawl-optimizer' ), '/.well-known/api-catalog' ),
		array( __( 'MCP Server Card', 'aj-agent-crawl-optimizer' ), '/.well-known/mcp/server-card.json' ),
		array( __( 'Agent Skills Index', 'aj-agent-crawl-optimizer' ), '/.well-known/agent-skills/index.json' ),
		array( __( 'WebMCP Tools', 'aj-agent-crawl-optimizer' ), 'navigator.modelContext.provideContext()' ),
		array( __( 'JSON-LD Schema', 'aj-agent-crawl-optimizer' ), '<script type="application/ld+json">' ),
		array( __( 'OpenAPI Spec', 'aj-agent-crawl-optimizer' ), '/openapi.json' ),
		array( __( 'llms.txt', 'aj-agent-crawl-optimizer' ), '/llms.txt' ),
		array( __( 'IndexNow', 'aj-agent-crawl-optimizer' ), 'POST api.indexnow.org' ),
	);

	$html  = '<p>' . esc_html__( 'Where each feature surfaces when its toggle is on:', 'aj-agent-crawl-optimizer' ) . '</p>';
	$html .= '<table style="width:100%; border-collapse:collapse;">';
	foreach ( $rows as $row ) {
		$html .= '<tr>';
		$html .= '<td style="padding:4px 12px 4px 0; vertical-align:top;"><strong>' . esc_html( $row[0] ) . '</strong></td>';
		$html .= '<td style="padding:4px 0; vertical-align:top;"><code>' . esc_html( $row[1] ) . '</code></td>';
		$html .= '</tr>';
	}
	$html .= '</table>';
	$html .= '<p style="margin-top:12px;">' . esc_html__( 'See the Testing section below the form for one-click curl commands to verify each endpoint.', 'aj-agent-crawl-optimizer' ) . '</p>';
	return $html;
}

/**
 * @return string
 */
function help_tab_developers(): string {
	$hooks = array(
		array( 'ajaco_required_capability', __( 'Capability required to manage settings (default manage_options).', 'aj-agent-crawl-optimizer' ) ),
		array( 'ajaco_skill_definitions', __( 'Register custom skills in the Agent Skills Index.', 'aj-agent-crawl-optimizer' ) ),
		array( 'ajaco_content_signal', __( 'Customize the Content-Signal directive (e.g. ai-train=yes).', 'aj-agent-crawl-optimizer' ) ),
		array( 'ajaco_api_catalog_linkset', __( 'Add anchors or rels to the linkset (e.g. a GraphQL endpoint).', 'aj-agent-crawl-optimizer' ) ),
		array( 'ajaco_mcp_server_card', __( 'Override transport / capabilities for a real MCP implementation.', 'aj-agent-crawl-optimizer' ) ),
		array( 'ajaco_json_ld_graph', __( 'Add custom Schema.org entries (Product, Recipe, Event, etc.).', 'aj-agent-crawl-optimizer' ) ),
		array( 'ajaco_openapi_spec', __( 'Add securitySchemes, tags, additional servers.', 'aj-agent-crawl-optimizer' ) ),
		array( 'ajaco_llms_txt_content', __( 'Append sections or replace the llms.txt body wholesale.', 'aj-agent-crawl-optimizer' ) ),
		array( 'ajaco_llms_full_txt_content', __( 'Append content-type sections or replace the llms-full.txt body.', 'aj-agent-crawl-optimizer' ) ),
		array( 'ajaco_auth_md_content', __( 'Customize the /auth.md agent-authentication document.', 'aj-agent-crawl-optimizer' ) ),
		array( 'ajaco_ai_bot_list', __( 'Add or remove AI crawlers managed by the robots.txt bot rules.', 'aj-agent-crawl-optimizer' ) ),
		array( 'ajaco_ai_bot_policy', __( 'Override the per-bot allow/block policy (e.g. block all training bots).', 'aj-agent-crawl-optimizer' ) ),
		array( 'ajaco_commerce_signals', __( 'Adjust commerce-site detection for the readiness scan.', 'aj-agent-crawl-optimizer' ) ),
		array( 'ajaco_scan_sslverify', __( 'Re-enable TLS verification for same-origin scan probes.', 'aj-agent-crawl-optimizer' ) ),
		array( 'ajaco_active_seo_plugin', __( 'Override SEO-plugin detection for JSON-LD auto-suppress.', 'aj-agent-crawl-optimizer' ) ),
	);

	$html  = '<p>' . esc_html__( 'Filter hooks for extending or customizing the plugin:', 'aj-agent-crawl-optimizer' ) . '</p>';
	$html .= '<dl style="margin:0;">';
	foreach ( $hooks as $hook ) {
		$html .= '<dt style="margin-top:8px;"><code>' . esc_html( $hook[0] ) . '</code></dt>';
		$html .= '<dd style="margin:2px 0 0 16px; color:#3c434a;">' . esc_html( $hook[1] ) . '</dd>';
	}
	$html .= '</dl>';
	return $html;
}

/**
 * @return string
 */
function help_tab_troubleshooting(): string {
	$items = array(
		array(
			__( 'My SEO plugin and JSON-LD both seem to be running.', 'aj-agent-crawl-optimizer' ),
			__( 'They\'re not — AJ Agent Crawl Optimizer auto-suppresses its JSON-LD when Yoast, Rank Math, AIOSEO, SEOPress, The SEO Framework, Slim SEO, Squirrly, Schema Pro, or SASWP is active. Look for the red note under the JSON-LD Schema toggle.', 'aj-agent-crawl-optimizer' ),
		),
		array(
			__( 'IndexNow is enabled but I\'m not seeing pings.', 'aj-agent-crawl-optimizer' ),
			__( 'Check three things: (1) the IndexNow API Key field is filled, (2) you\'re publishing a public post type — revisions and autosaves are skipped, (3) you\'re on production. Pings are non-blocking, so failures are silent — check your server log for requests to api.indexnow.org.', 'aj-agent-crawl-optimizer' ),
		),
		array(
			__( 'A /.well-known/ endpoint returns 403 or 404.', 'aj-agent-crawl-optimizer' ),
			__( 'Confirm the matching toggle is on, then run a scan from Agent Ready → Dashboard: if the feature is enabled but the server blocks the request (common nginx dot-path deny rules, or static-file blocks without try_files), the dashboard shows a hosting notice with copy-paste nginx/Apache fixes you can apply or send to your host. No files or rewrite flushes are involved — the endpoints are served virtually. Caching plugins or CDNs can also serve a stale 404; purge those too.', 'aj-agent-crawl-optimizer' ),
		),
		array(
			__( 'Multisite subsite paths.', 'aj-agent-crawl-optimizer' ),
			__( 'Every endpoint also resolves at /{subsite}/ paths automatically (e.g. /blog/llms.txt). Each subsite has its own settings.', 'aj-agent-crawl-optimizer' ),
		),
		array(
			__( 'Markdown Negotiation breaks on agent requests.', 'aj-agent-crawl-optimizer' ),
			__( 'Browsers always get HTML — the feature only fires when Accept: text/markdown is in the request header. If you see broken admin or cache behavior, check whether an agent or curl is hitting your site with that header.', 'aj-agent-crawl-optimizer' ),
		),
	);

	$html = '';
	foreach ( $items as $item ) {
		$html .= '<p style="margin-top:10px;"><strong>' . esc_html( $item[0] ) . '</strong><br>';
		$html .= esc_html( $item[1] ) . '</p>';
	}
	return $html;
}

/**
 * @return string
 */
function help_sidebar(): string {
	$links = array(
		array( 'https://llmstxt.org/', __( 'llms.txt spec', 'aj-agent-crawl-optimizer' ) ),
		array( 'https://contentsignals.org/', __( 'Content-Signals', 'aj-agent-crawl-optimizer' ) ),
		array( 'https://www.rfc-editor.org/rfc/rfc9727', __( 'RFC 9727 — API catalog', 'aj-agent-crawl-optimizer' ) ),
		array( 'https://agentskills.io/', __( 'Agent Skills Discovery', 'aj-agent-crawl-optimizer' ) ),
		array( 'https://webmachinelearning.github.io/webmcp/', __( 'WebMCP draft', 'aj-agent-crawl-optimizer' ) ),
		array( 'https://www.bing.com/webmasters/indexnow', __( 'IndexNow (Bing)', 'aj-agent-crawl-optimizer' ) ),
		array( 'https://developer.wordpress.org/rest-api/', __( 'WP REST API handbook', 'aj-agent-crawl-optimizer' ) ),
	);

	$html = '<p><strong>' . esc_html__( 'Reference', 'aj-agent-crawl-optimizer' ) . '</strong></p>';
	foreach ( $links as $link ) {
		$html .= '<p><a href="' . esc_url( $link[0] ) . '" target="_blank" rel="noopener">' . esc_html( $link[1] ) . '</a></p>';
	}
	return $html;
}
