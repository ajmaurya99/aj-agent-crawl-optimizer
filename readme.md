# AJ Agent Crawl Optimizer

Make your WordPress site legible to AI agents — Markdown negotiation, MCP server card, Agent Skills, llms.txt, API catalog, OpenAPI, Content Signals, JSON-LD, IndexNow.

[![WordPress plugin](https://img.shields.io/wordpress/plugin/v/aj-agent-crawl-optimizer)](https://wordpress.org/plugins/aj-agent-crawl-optimizer/)
[![License: GPL v2+](https://img.shields.io/badge/License-GPLv2+-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

**AJ Agent Crawl Optimizer** is a thin compatibility layer that teaches your site to speak the languages AI agents already use to discover and consume web content. It publishes machine-readable manifests at well-known URLs, serves clean Markdown when an AI requests it, and declares your AI-usage preferences — all without changing anything for human visitors.

Every capability is a separate toggle and ships **opt-in**. A one-time Quick Setup wizard suggests environment-aware defaults (it skips JSON-LD when an SEO plugin is detected, for example).

## What it publishes

| Feature | Endpoint / behavior | Standard |
|---|---|---|
| Markdown Negotiation | `Accept: text/markdown` → clean Markdown + `X-Markdown-Tokens`, with `Vary: Accept` | [Markdown for Agents](https://developers.cloudflare.com/fundamentals/reference/markdown-for-agents/) |
| llms.txt | `/llms.txt` — curated LLM-readable site index | [llmstxt.org](https://llmstxt.org/) |
| API Catalog | `/.well-known/api-catalog` linkset + `Link: rel="api-catalog"` header | [RFC 9727](https://www.rfc-editor.org/rfc/rfc9727) |
| MCP Server Card | `/.well-known/mcp/server-card.json` | [MCP SEP-1649](https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2127) |
| Agent Skills Index | `/.well-known/agent-skills/index.json` + per-skill `SKILL.md` with sha256 digests | [Agent Skills Discovery RFC v0.2.0](https://github.com/cloudflare/agent-skills-discovery-rfc) |
| WebMCP Tools | 4 in-page tools via `navigator.modelContext` (search, posts, pages, site info) | [W3C WebMCP draft](https://webmachinelearning.github.io/webmcp/) |
| OpenAPI | `/openapi.json` (and `/?format=openapi`) — generated from live REST routes | OpenAPI 3.0.3 |
| Content Signals | `Content-Signal: ai-train=no, search=yes, ai-input=no` in robots.txt, inside a `User-agent: *` group | [contentsignals.org](https://contentsignals.org/) |
| JSON-LD Schema | WebSite, Organization, Article, BreadcrumbList, FAQPage — auto-suppressed next to Yoast/Rank Math/AIOSEO/etc. | schema.org |
| IndexNow | Non-blocking ping to Bing/Yandex on publish | [indexnow.org](https://www.indexnow.org/) |

## Install

From wp-admin: **Plugins → Add New → search "AJ Agent Crawl Optimizer" → Install → Activate**, then follow the Quick Setup wizard under **Settings → AJ Agent Crawl Optimizer**.

From source:

```bash
cd wp-content/plugins
git clone https://github.com/ajmaurya99/aj-agent-crawl-optimizer.git
wp plugin activate aj-agent-crawl-optimizer
```

## Verify it works

```bash
curl -H "Accept: text/markdown" https://example.com/          # markdown + X-Markdown-Tokens
curl https://example.com/.well-known/api-catalog              # RFC 9727 linkset
curl https://example.com/.well-known/agent-skills/index.json  # skills + digests
curl https://example.com/llms.txt                             # LLM-readable index
curl https://example.com/openapi.json                         # OpenAPI 3.0.3
curl https://example.com/robots.txt                           # Content-Signal line
```

Or scan the whole site with Cloudflare's [Agent Readiness scanner](https://isitagentready.com/).

## For developers

Nine filter hooks let you extend or override behavior — `ajaco_skill_definitions`, `ajaco_json_ld_graph`, `ajaco_llms_txt_content`, `ajaco_openapi_spec`, `ajaco_api_catalog_linkset`, `ajaco_mcp_server_card`, `ajaco_content_signal`, `ajaco_active_seo_plugin`, `ajaco_required_capability`. Examples in the Help tab → For Developers, or see [readme.txt](readme.txt).

```php
// Register a WooCommerce products skill in the Agent Skills index.
add_filter( 'ajaco_skill_definitions', function ( $skills ) {
    $skills['products'] = array(
        'type'        => 'information-retrieval',
        'description' => 'WooCommerce products',
        'endpoint'    => rest_url( 'wc/v3/products' ),
    );
    return $skills;
} );
```

## Development

```bash
composer install
composer lint     # PHPCS (WordPress Coding Standards)
composer lint:fix # PHPCBF
```

## Privacy

No telemetry, no analytics, no cookies. The only outbound request is the (opt-in) IndexNow ping. Full details in [readme.txt](readme.txt) → Privacy.

## License

GPL-2.0-or-later.
