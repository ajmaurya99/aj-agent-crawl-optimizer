<?php
/**
 * Admin: register contextual Help tabs on the Agent-Ready settings screen.
 *
 * Hooks `load-{$hook}` so the tabs are added only when the page is actually
 * being rendered — no work done on other admin screens.
 *
 * @package AgentReady
 */

namespace AgentReady;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'load-settings_page_agent-ready', __NAMESPACE__ . '\\register_help_tabs' );

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
			'id'      => 'agent-ready-overview',
			'title'   => __( 'Overview', 'agent-ready' ),
			'content' => help_tab_overview(),
		)
	);

	$screen->add_help_tab(
		array(
			'id'      => 'agent-ready-features',
			'title'   => __( 'Features', 'agent-ready' ),
			'content' => help_tab_features(),
		)
	);

	$screen->add_help_tab(
		array(
			'id'      => 'agent-ready-developers',
			'title'   => __( 'For Developers', 'agent-ready' ),
			'content' => help_tab_developers(),
		)
	);

	$screen->add_help_tab(
		array(
			'id'      => 'agent-ready-troubleshooting',
			'title'   => __( 'Troubleshooting', 'agent-ready' ),
			'content' => help_tab_troubleshooting(),
		)
	);

	$screen->set_help_sidebar( help_sidebar() );
}

/**
 * @return string
 */
function help_tab_overview(): string {
	$html  = '<p><strong>' . esc_html__( 'Agent-Ready makes your site legible to AI agents.', 'agent-ready' ) . '</strong></p>';
	$html .= '<p>' . esc_html__( 'It exposes machine-readable manifests at well-known URLs (API catalog, MCP server card, agent skills index, OpenAPI spec, llms.txt), serves clean Markdown when an AI requests it via the Accept header, and declares your AI usage preferences in robots.txt. Nothing changes for human visitors.', 'agent-ready' ) . '</p>';
	$html .= '<p>' . esc_html__( 'Each capability is a separate toggle below — enable only what you need. All toggles default to off.', 'agent-ready' ) . '</p>';
	return $html;
}

/**
 * @return string
 */
function help_tab_features(): string {
	$rows = array(
		array( __( 'Markdown Negotiation', 'agent-ready' ), 'Accept: text/markdown' ),
		array( __( 'Content-Signals', 'agent-ready' ), '/robots.txt' ),
		array( __( 'API Catalog', 'agent-ready' ), '/.well-known/api-catalog' ),
		array( __( 'MCP Server Card', 'agent-ready' ), '/.well-known/mcp/server-card.json' ),
		array( __( 'Agent Skills Index', 'agent-ready' ), '/.well-known/agent-skills/index.json' ),
		array( __( 'WebMCP Tools', 'agent-ready' ), 'navigator.modelContext.provideContext()' ),
		array( __( 'JSON-LD Schema', 'agent-ready' ), '<script type="application/ld+json">' ),
		array( __( 'OpenAPI Spec', 'agent-ready' ), '/?format=openapi' ),
		array( __( 'llms.txt', 'agent-ready' ), '/llms.txt' ),
		array( __( 'IndexNow', 'agent-ready' ), 'POST api.indexnow.org' ),
	);

	$html  = '<p>' . esc_html__( 'Where each feature surfaces when its toggle is on:', 'agent-ready' ) . '</p>';
	$html .= '<table style="width:100%; border-collapse:collapse;">';
	foreach ( $rows as $row ) {
		$html .= '<tr>';
		$html .= '<td style="padding:4px 12px 4px 0; vertical-align:top;"><strong>' . esc_html( $row[0] ) . '</strong></td>';
		$html .= '<td style="padding:4px 0; vertical-align:top;"><code>' . esc_html( $row[1] ) . '</code></td>';
		$html .= '</tr>';
	}
	$html .= '</table>';
	$html .= '<p style="margin-top:12px;">' . esc_html__( 'See the Testing section below the form for one-click curl commands to verify each endpoint.', 'agent-ready' ) . '</p>';
	return $html;
}

/**
 * @return string
 */
function help_tab_developers(): string {
	$hooks = array(
		array( 'agent_ready_required_capability', __( 'Capability required to manage settings (default manage_options).', 'agent-ready' ) ),
		array( 'agent_ready_skill_definitions', __( 'Register custom skills in the Agent Skills Index.', 'agent-ready' ) ),
		array( 'agent_ready_content_signal', __( 'Customize the Content-Signal directive (e.g. ai-train=yes).', 'agent-ready' ) ),
		array( 'agent_ready_api_catalog_linkset', __( 'Add anchors or rels to the linkset (e.g. a GraphQL endpoint).', 'agent-ready' ) ),
		array( 'agent_ready_mcp_server_card', __( 'Override transport / capabilities for a real MCP implementation.', 'agent-ready' ) ),
		array( 'agent_ready_json_ld_graph', __( 'Add custom Schema.org entries (Product, Recipe, Event, etc.).', 'agent-ready' ) ),
		array( 'agent_ready_openapi_spec', __( 'Add securitySchemes, tags, additional servers.', 'agent-ready' ) ),
		array( 'agent_ready_llms_txt_content', __( 'Append sections or replace the llms.txt body wholesale.', 'agent-ready' ) ),
		array( 'agent_ready_active_seo_plugin', __( 'Override SEO-plugin detection for JSON-LD auto-suppress.', 'agent-ready' ) ),
	);

	$html  = '<p>' . esc_html__( 'Filter hooks for extending or customizing the plugin:', 'agent-ready' ) . '</p>';
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
			__( 'My SEO plugin and JSON-LD both seem to be running.', 'agent-ready' ),
			__( 'They\'re not — Agent-Ready auto-suppresses its JSON-LD when Yoast, Rank Math, AIOSEO, SEOPress, The SEO Framework, Slim SEO, Squirrly, Schema Pro, or SASWP is active. Look for the red note under the JSON-LD Schema toggle.', 'agent-ready' ),
		),
		array(
			__( 'IndexNow is enabled but I\'m not seeing pings.', 'agent-ready' ),
			__( 'Check three things: (1) the IndexNow API Key field is filled, (2) you\'re publishing a public post type — revisions and autosaves are skipped, (3) you\'re on production. Pings are non-blocking, so failures are silent — check your server log for requests to api.indexnow.org.', 'agent-ready' ),
		),
		array(
			__( 'A /.well-known/ endpoint returns 404.', 'agent-ready' ),
			__( 'Confirm the matching toggle is on. The endpoints don\'t use WP rewrite rules, so a rewrite flush isn\'t needed — but caching plugins or CDNs might serve a stale 404; purge those if so.', 'agent-ready' ),
		),
		array(
			__( 'Multisite subsite paths.', 'agent-ready' ),
			__( 'Every endpoint also resolves at /{subsite}/ paths automatically (e.g. /travelwithpurpose/llms.txt). Each subsite has its own settings.', 'agent-ready' ),
		),
		array(
			__( 'Markdown Negotiation breaks on agent requests.', 'agent-ready' ),
			__( 'Browsers always get HTML — the feature only fires when Accept: text/markdown is in the request header. If you see broken admin or cache behavior, check whether an agent or curl is hitting your site with that header.', 'agent-ready' ),
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
		array( 'https://llmstxt.org/', __( 'llms.txt spec', 'agent-ready' ) ),
		array( 'https://contentsignals.org/', __( 'Content-Signals', 'agent-ready' ) ),
		array( 'https://www.rfc-editor.org/rfc/rfc9727', __( 'RFC 9727 — API catalog', 'agent-ready' ) ),
		array( 'https://agentskills.io/', __( 'Agent Skills Discovery', 'agent-ready' ) ),
		array( 'https://webmachinelearning.github.io/webmcp/', __( 'WebMCP draft', 'agent-ready' ) ),
		array( 'https://www.bing.com/webmasters/indexnow', __( 'IndexNow (Bing)', 'agent-ready' ) ),
		array( 'https://developer.wordpress.org/rest-api/', __( 'WP REST API handbook', 'agent-ready' ) ),
	);

	$html = '<p><strong>' . esc_html__( 'Reference', 'agent-ready' ) . '</strong></p>';
	foreach ( $links as $link ) {
		$html .= '<p><a href="' . esc_url( $link[0] ) . '" target="_blank" rel="noopener">' . esc_html( $link[1] ) . '</a></p>';
	}
	return $html;
}
