<?php
/**
 * Scan engine: static metadata registry for every check.
 *
 * Descriptions, spec URLs, LLM-ready fix prompts, and SKILL.md guide links —
 * mirrors the remediation registry of isitagentready.com so our copy-prompt
 * buttons and nextLevel guidance emit the same class of instructions.
 *
 * @package Ajaco
 */

namespace Ajaco\Scan;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check metadata lookup.
 */
class Check_Info {

	/**
	 * Base URL of the hosted per-check implementation guides.
	 */
	const SKILL_BASE = 'https://isitagentready.com/.well-known/agent-skills';

	/**
	 * Metadata for a check id.
	 *
	 * @param string $id Check id.
	 * @return array{description: string, specUrls: string[], prompt: string, skillUrl: string}
	 */
	public static function get( string $id ): array {
		$all = self::all();
		if ( isset( $all[ $id ] ) ) {
			return $all[ $id ];
		}
		return array(
			'description' => '',
			'specUrls'    => array(),
			'prompt'      => '',
			'skillUrl'    => '',
		);
	}

	/**
	 * Full registry.
	 *
	 * @return array<string, array{description: string, specUrls: string[], prompt: string, skillUrl: string}>
	 */
	public static function all(): array {
		return array(
			'robotsTxt'              => array(
				'description' => __( 'Publish /robots.txt with clear crawl rules', 'aj-agent-crawl-optimizer' ),
				'specUrls'    => array( 'https://www.rfc-editor.org/rfc/rfc9309' ),
				'prompt'      => 'Create /robots.txt at the site root with explicit User-agent directives and allow/disallow rules for key paths. Ensure it is plain text and returns 200.',
				'skillUrl'    => self::SKILL_BASE . '/robots-txt/SKILL.md',
			),
			'sitemap'                => array(
				'description' => __( 'Publish a sitemap and reference it from robots.txt', 'aj-agent-crawl-optimizer' ),
				'specUrls'    => array( 'https://www.sitemaps.org/protocol.html' ),
				'prompt'      => 'Generate /sitemap.xml listing canonical URLs, keep it updated on publish, and reference it from /robots.txt. WordPress core serves /wp-sitemap.xml since 5.5 — ensure it is not disabled.',
				'skillUrl'    => self::SKILL_BASE . '/sitemap/SKILL.md',
			),
			'linkHeaders'            => array(
				'description' => __( 'Include Link response headers for agent discovery (RFC 8288)', 'aj-agent-crawl-optimizer' ),
				'specUrls'    => array( 'https://www.rfc-editor.org/rfc/rfc8288', 'https://www.rfc-editor.org/rfc/rfc9727#section-3' ),
				'prompt'      => 'Add Link response headers to your homepage that point agents to useful resources, e.g. Link: </.well-known/api-catalog>; rel="api-catalog".',
				'skillUrl'    => self::SKILL_BASE . '/link-headers/SKILL.md',
			),
			'dnsAid'                 => array(
				'description' => __( 'Publish DNS for AI Discovery (DNS-AID) records', 'aj-agent-crawl-optimizer' ),
				'specUrls'    => array( 'https://datatracker.ietf.org/doc/draft-mozleywilliams-dnsop-dnsaid/', 'https://www.rfc-editor.org/rfc/rfc9460' ),
				'prompt'      => 'Publish DNS-AID records under your domain (e.g. _index._agents.example.com) using ServiceMode SVCB/HTTPS records with alpn and endpoint parameters, and sign the zone with DNSSEC.',
				'skillUrl'    => self::SKILL_BASE . '/dns-aid/SKILL.md',
			),
			'markdownNegotiation'    => array(
				'description' => __( 'Return HTML responses as markdown when agents request it', 'aj-agent-crawl-optimizer' ),
				'specUrls'    => array( 'https://www.rfc-editor.org/rfc/rfc9110#section-12.5.1', 'https://agentready.org/' ),
				'prompt'      => 'Serve a markdown representation when a request includes Accept: text/markdown, with Content-Type: text/markdown and a Vary: Accept header. HTML stays the default for browsers.',
				'skillUrl'    => self::SKILL_BASE . '/markdown-negotiation/SKILL.md',
			),
			'robotsTxtAiRules'       => array(
				'description' => __( 'Add User-agent rules for AI crawlers like GPTBot and Claude-Web', 'aj-agent-crawl-optimizer' ),
				'specUrls'    => array( 'https://www.rfc-editor.org/rfc/rfc9309' ),
				'prompt'      => 'Add explicit User-agent entries for AI crawlers (GPTBot, Claude-Web, Google-Extended, PerplexityBot, CCBot, …) with allow/disallow rules that match your policy.',
				'skillUrl'    => self::SKILL_BASE . '/ai-rules/SKILL.md',
			),
			'contentSignals'         => array(
				'description' => __( 'Declare AI content usage preferences with Content Signals in robots.txt', 'aj-agent-crawl-optimizer' ),
				'specUrls'    => array( 'https://contentsignals.org/', 'https://datatracker.ietf.org/doc/draft-romm-aipref-contentsignals/' ),
				'prompt'      => "Add Content-Signal directives to your robots.txt declaring preferences for ai-train, search, and ai-input. For example:\nContent-Signal: ai-train=no, search=yes, ai-input=no",
				'skillUrl'    => self::SKILL_BASE . '/content-signals/SKILL.md',
			),
			'webBotAuth'             => array(
				'description' => __( 'Let your site identify itself as a bot with Web Bot Auth', 'aj-agent-crawl-optimizer' ),
				'specUrls'    => array( 'https://datatracker.ietf.org/wg/webbotauth/about/', 'https://datatracker.ietf.org/doc/draft-meunier-web-bot-auth-architecture/' ),
				'prompt'      => 'Publish a JWKS at /.well-known/http-message-signatures-directory so your site can identify itself when it sends bot or agent requests.',
				'skillUrl'    => self::SKILL_BASE . '/web-bot-auth/SKILL.md',
			),
			'apiCatalog'             => array(
				'description' => __( 'Publish an API catalog for automated API discovery (RFC 9727)', 'aj-agent-crawl-optimizer' ),
				'specUrls'    => array( 'https://www.rfc-editor.org/rfc/rfc9727', 'https://www.rfc-editor.org/rfc/rfc9264' ),
				'prompt'      => 'Create /.well-known/api-catalog returning application/linkset+json with a "linkset" array. Each entry should include an "anchor" URL and link relations for service-desc, service-doc, and status.',
				'skillUrl'    => self::SKILL_BASE . '/api-catalog/SKILL.md',
			),
			'oauthDiscovery'         => array(
				'description' => __( 'Publish OAuth/OIDC discovery metadata so agents can authenticate', 'aj-agent-crawl-optimizer' ),
				'specUrls'    => array( 'https://openid.net/specs/openid-connect-discovery-1_0.html', 'https://www.rfc-editor.org/rfc/rfc8414' ),
				'prompt'      => 'If your site has protected APIs, publish /.well-known/openid-configuration or /.well-known/oauth-authorization-server with issuer, authorization_endpoint, token_endpoint, jwks_uri, and grant_types_supported.',
				'skillUrl'    => self::SKILL_BASE . '/oauth-discovery/SKILL.md',
			),
			'oauthProtectedResource' => array(
				'description' => __( 'Publish OAuth Protected Resource Metadata (RFC 9728)', 'aj-agent-crawl-optimizer' ),
				'specUrls'    => array( 'https://www.rfc-editor.org/rfc/rfc9728' ),
				'prompt'      => 'Publish /.well-known/oauth-protected-resource with your resource identifier, authorization_servers, and scopes_supported.',
				'skillUrl'    => self::SKILL_BASE . '/oauth-protected-resource/SKILL.md',
			),
			'authMd'                 => array(
				'description' => __( 'Publish Auth.md metadata for agent registration', 'aj-agent-crawl-optimizer' ),
				'specUrls'    => array( 'https://workos.com/auth-md', 'https://github.com/workos/auth.md' ),
				'prompt'      => 'Serve /auth.md at the site root with agent registration instructions (for WordPress: how to create and use an Application Password), and publish OAuth metadata where applicable.',
				'skillUrl'    => self::SKILL_BASE . '/auth-md/SKILL.md',
			),
			'mcpServerCard'          => array(
				'description' => __( 'Publish an MCP Server Card for agent discovery', 'aj-agent-crawl-optimizer' ),
				'specUrls'    => array( 'https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2127' ),
				'prompt'      => 'Serve an MCP Server Card at /.well-known/mcp/server-card.json with serverInfo (name, version), a transport endpoint, and capabilities (SEP-1649/SEP-2127).',
				'skillUrl'    => self::SKILL_BASE . '/mcp-server-card/SKILL.md',
			),
			'a2aAgentCard'           => array(
				'description' => __( 'Publish an A2A Agent Card for agent-to-agent discovery', 'aj-agent-crawl-optimizer' ),
				'specUrls'    => array( 'https://a2a-protocol.org/latest/specification/', 'https://a2a-protocol.org/latest/topics/agent-discovery/' ),
				'prompt'      => 'Serve an A2A Agent Card (JSON) at /.well-known/agent-card.json with name, version, description, supportedInterfaces, capabilities, and skills. Only do this if your site actually runs an agent.',
				'skillUrl'    => self::SKILL_BASE . '/a2a-agent-card/SKILL.md',
			),
			'agentSkills'            => array(
				'description' => __( 'Publish an agent skills discovery index', 'aj-agent-crawl-optimizer' ),
				'specUrls'    => array( 'https://github.com/cloudflare/agent-skills-discovery-rfc', 'https://agentskills.io/' ),
				'prompt'      => 'Publish a skills discovery index at /.well-known/agent-skills/index.json (Agent Skills Discovery RFC v0.2.0) with a $schema field and a skills array where each entry has name, type (skill-md|archive), description, url, and a sha256 digest.',
				'skillUrl'    => self::SKILL_BASE . '/agent-skills/SKILL.md',
			),
			'webMcp'                 => array(
				'description' => __( 'Support WebMCP to expose site tools to AI agents via the browser', 'aj-agent-crawl-optimizer' ),
				'specUrls'    => array( 'https://webmachinelearning.github.io/webmcp/', 'https://developer.chrome.com/blog/webmcp-epp' ),
				'prompt'      => 'Implement the WebMCP API: call document.modelContext.registerTool() for each tool with a name, description, inputSchema (JSON Schema), an execute callback, and annotations (readOnlyHint for read-only tools, consequentialHint for actions that need confirmation).',
				'skillUrl'    => self::SKILL_BASE . '/webmcp/SKILL.md',
			),
			'x402'                   => array(
				'description' => __( 'Support x402 protocol for agent-native HTTP payments', 'aj-agent-crawl-optimizer' ),
				'specUrls'    => array( 'https://x402.org', 'https://github.com/coinbase/x402', 'https://docs.x402.org' ),
				'prompt'      => 'Add x402 payment middleware to your API routes so protected routes return HTTP 402 with payment requirements agents can fulfill automatically.',
				'skillUrl'    => self::SKILL_BASE . '/x402/SKILL.md',
			),
			'mpp'                    => array(
				'description' => __( 'Support MPP (Machine Payment Protocol) for agent-native HTTP payments', 'aj-agent-crawl-optimizer' ),
				'specUrls'    => array( 'https://mpp.dev', 'https://paymentauth.org/draft-payment-discovery-00.txt' ),
				'prompt'      => 'Publish an OpenAPI document at /openapi.json with x-payment-info extensions on payable operations declaring intent, method, amount, and currency.',
				'skillUrl'    => self::SKILL_BASE . '/mpp/SKILL.md',
			),
			'ucp'                    => array(
				'description' => __( 'Enable content payments via Universal Commerce Protocol', 'aj-agent-crawl-optimizer' ),
				'specUrls'    => array( 'https://ucp.dev/specification/overview/' ),
				'prompt'      => 'Serve /.well-known/ucp with protocol version, services, capabilities, and endpoints, and ensure spec URLs and schemas are reachable.',
				'skillUrl'    => self::SKILL_BASE . '/ucp/SKILL.md',
			),
			'acp'                    => array(
				'description' => __( 'Publish ACP discovery metadata so agents can discover your commerce API', 'aj-agent-crawl-optimizer' ),
				'specUrls'    => array( 'https://agenticcommerce.dev' ),
				'prompt'      => 'Serve /.well-known/acp.json at the origin root with protocol.name "acp", protocol.version, api_base_url, supported transports, and capabilities.services.',
				'skillUrl'    => self::SKILL_BASE . '/acp/SKILL.md',
			),
			'ap2'                    => array(
				'description' => __( 'AP2 agent payments (requires an A2A Agent Card)', 'aj-agent-crawl-optimizer' ),
				'specUrls'    => array(),
				'prompt'      => '',
				'skillUrl'    => '',
			),

			// Checks added in 2.1 — modelled on the ora.ai / is-agentic.com
			// catalog and the AgentReady.org standard; no Cloudflare skill.
			'sitemapLastmod'         => array(
				'description' => __( 'Include <lastmod> dates in the sitemap so crawlers know what changed', 'aj-agent-crawl-optimizer' ),
				'specUrls'    => array( 'https://www.sitemaps.org/protocol.html#lastmoddef' ),
				'prompt'      => 'Add a <lastmod> element (W3C datetime) to every <url> in the sitemap from the content\'s modification date. For WordPress core sitemaps, filter wp_sitemaps_posts_entry and set $entry["lastmod"] from $post->post_modified_gmt.',
				'skillUrl'    => '',
			),
			'metadata'               => array(
				'description' => __( 'Declare page identity: html lang, canonical URL, og:type and og:image', 'aj-agent-crawl-optimizer' ),
				'specUrls'    => array( 'https://ogp.me/', 'https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls' ),
				'prompt'      => 'In the document head output <link rel="canonical">, <meta property="og:type">, <meta property="og:image"> (site logo or featured image) and ensure <html lang="…"> is set. If an SEO plugin is active, enable its Open Graph output instead of adding duplicate tags.',
				'skillUrl'    => '',
			),
			'organizationSchema'     => array(
				'description' => __( 'Give the Organization schema sameAs profiles and a contact point or address', 'aj-agent-crawl-optimizer' ),
				'specUrls'    => array( 'https://schema.org/Organization', 'https://schema.org/ContactPoint' ),
				'prompt'      => 'In the homepage JSON-LD Organization node add sameAs (array of official profile URLs: LinkedIn, GitHub, Wikidata, X), a contactPoint (ContactPoint with contactType and email or telephone) and, where applicable, a PostalAddress. Keep the same facts visible as text on the contact/about pages.',
				'skillUrl'    => '',
			),
			'trustPages'             => array(
				'description' => __( 'Publish real privacy, about and contact pages', 'aj-agent-crawl-optimizer' ),
				'specUrls'    => array( 'https://agentready.org/' ),
				'prompt'      => 'Create and publish a Privacy Policy page and select it under Settings → Privacy, plus an About and/or Contact page (slugs /about, /contact) linked from the site footer.',
				'skillUrl'    => '',
			),
			'markdownAlternate'      => array(
				'description' => __( 'Advertise the Markdown version of each page with rel="alternate" type="text/markdown"', 'aj-agent-crawl-optimizer' ),
				'specUrls'    => array( 'https://agentready.org/', 'https://www.rfc-editor.org/rfc/rfc8288' ),
				'prompt'      => 'Add <link rel="alternate" type="text/markdown" href="<page url>"> to the head of every page and a matching Link response header; the href must return text/markdown when requested with Accept: text/markdown.',
				'skillUrl'    => '',
			),
			'markdownFrontmatter'    => array(
				'description' => __( 'Open served Markdown with a frontmatter metadata block', 'aj-agent-crawl-optimizer' ),
				'specUrls'    => array( 'https://agentready.org/' ),
				'prompt'      => 'Prefix every Markdown response with a YAML frontmatter block (--- … ---) carrying at least title and url, plus modified and site where known.',
				'skillUrl'    => '',
			),
			'agentFriendly404'       => array(
				'description' => __( 'Return real 404s, as Markdown when an agent asks for it', 'aj-agent-crawl-optimizer' ),
				'specUrls'    => array( 'https://agentready.org/' ),
				'prompt'      => 'Missing paths must return HTTP 404 (or 410), never 200. When the request carries Accept: text/markdown, serve the error page as text/markdown with a short explanation and links to the homepage and llms.txt.',
				'skillUrl'    => '',
			),
			'contentNoJs'            => array(
				'description' => __( 'Serve the homepage content in raw HTML without JavaScript', 'aj-agent-crawl-optimizer' ),
				'specUrls'    => array( 'https://agentready.org/' ),
				'prompt'      => 'Server-render (or prerender) the homepage so at least 500 characters of meaningful text, a single <h1>, and a <main> landmark are present in the initial HTML without executing JavaScript.',
				'skillUrl'    => '',
			),
			'llmsTxt'                => array(
				'description' => __( 'Publish a well-formed /llms.txt whose links resolve', 'aj-agent-crawl-optimizer' ),
				'specUrls'    => array( 'https://llmstxt.org/' ),
				'prompt'      => 'Serve /llms.txt as text/markdown: an H1 with the site name, a blockquote summary, and H2 sections of described Markdown links to key pages. Every link must return 200.',
				'skillUrl'    => '',
			),
			'robotsAgentUserPolicy'  => array(
				'description' => __( 'Allow user-request agents (ChatGPT-User, Claude-User, Perplexity-User) in robots.txt', 'aj-agent-crawl-optimizer' ),
				'specUrls'    => array( 'https://www.rfc-editor.org/rfc/rfc9309', 'https://agentready.org/' ),
				'prompt'      => 'Make sure no robots.txt group that applies to ChatGPT-User, Claude-User or Perplexity-User contains Disallow: /. Blocking training crawlers (GPTBot, ClaudeBot, CCBot) is fine; user-fetch agents must stay allowed or answer engines cannot cite the site.',
				'skillUrl'    => '',
			),
			'mcpServer'              => array(
				'description' => __( 'Run a Model Context Protocol server so agents can act on the site, not just read it', 'aj-agent-crawl-optimizer' ),
				'specUrls'    => array( 'https://modelcontextprotocol.io/specification/latest', 'https://wordpress.org/plugins/mcp-adapter/', 'https://agentready.org/' ),
				'prompt'      => 'Install and activate the official WordPress MCP Adapter plugin (slug mcp-adapter, WordPress 6.9+). It serves a Streamable HTTP MCP server at /wp-json/mcp/mcp-adapter-default-server, authenticated with Application Passwords, exposing the site\'s Abilities as tools. Then re-scan: the server card, catalogs, llms.txt and auth.md pick the endpoint up automatically.',
				'skillUrl'    => '',
			),
			'aiCatalog'              => array(
				'description' => __( 'Publish an agent resource catalog (ARD / AI Catalog) at /.well-known/ard.json', 'aj-agent-crawl-optimizer' ),
				'specUrls'    => array( 'https://github.com/Agent-Card/ai-catalog', 'https://agentready.org/' ),
				'prompt'      => 'Serve /.well-known/ard.json (and the predecessor /.well-known/ai-catalog.json) as JSON with specVersion "1.0" and an entries array; each entry has identifier, type (media type) and url for an agent-facing artifact (MCP server card, agent skills index, OpenAPI, llms.txt). Link it from the homepage with <link rel="ard"> and <link rel="ai-catalog">.',
				'skillUrl'    => '',
			),
		);
	}

	/**
	 * Compose the copy-paste fix prompt block for a failing/neutral check —
	 * same Goal / Issue / Fix / Skill / Docs shape isitagentready.com copies
	 * to the clipboard, so it can be pasted into any coding agent.
	 *
	 * @param string $id      Check id.
	 * @param string $message Current result message ('' to omit the line).
	 * @param bool   $is_fail Whether the current status is a failure (vs note).
	 * @return string
	 */
	public static function fix_prompt( string $id, string $message = '', bool $is_fail = true ): string {
		$info  = self::get( $id );
		$parts = array();

		if ( '' !== $info['description'] ) {
			$parts[] = 'Goal: ' . $info['description'];
		}
		if ( '' !== $message ) {
			$parts[] = ( $is_fail ? 'Issue: ' : 'Note: ' ) . $message;
		}
		if ( '' !== $info['prompt'] ) {
			$parts[] = 'Fix: ' . $info['prompt'];
		}
		if ( '' !== $info['skillUrl'] ) {
			$parts[] = 'Skill: ' . $info['skillUrl'];
		}
		if ( ! empty( $info['specUrls'] ) ) {
			$parts[] = 'Docs: ' . implode( ', ', $info['specUrls'] );
		}

		return implode( "\n\n", $parts );
	}
}
