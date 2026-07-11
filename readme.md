# AJ Agent Crawl Optimizer

**The agent-readiness scanner and fixer for WordPress.** Scan your site against the 21 agent-readiness checks (the same ones Cloudflare's [isitagentready.com](https://isitagentready.com/) runs), see your Level 0–5 with full request/response evidence, fix failures in one click, and re-verify instantly — then publish everything agents need: Markdown negotiation, llms.txt, MCP server card, Agent Skills, API catalog, AI bot rules, Content Signals, and more.

[![WordPress plugin](https://img.shields.io/wordpress/plugin/v/aj-agent-crawl-optimizer)](https://wordpress.org/plugins/aj-agent-crawl-optimizer/)
[![License: GPL v2+](https://img.shields.io/badge/License-GPLv2+-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

> **Branches:** `main` = released 1.0.x line · `v2-dev` = the 2.0 scan→fix→verify engine described below (in development).

## The scan → fix → verify loop (2.0)

Where external scanners stop at "here's a prompt, go fix it," this plugin closes the loop inside wp-admin:

1. **Scan** — 21 checks across 5 categories run against your live site over real HTTP, so page caches, CDNs, and server rules that silently break agent endpoints get caught. Every verdict carries an evidence timeline (fetch/parse/conclude with request/response snapshots).
2. **Level** — results map to the Level 0–5 agent-readiness ladder (Not Ready → Basic Web Presence → Bot-Aware → Agent-Readable → Agent-Integrated → Agent-Native), with a "Next level" panel listing exactly which checks unlock the next rung. Nothing scores unless the scan verifies it.
3. **Fix** — 9 checks have a **Fix now** button that enables the right feature; the plugin re-scans that single check immediately and shows it going green. Fixes needing DNS or server access get copy-paste prompts for your coding agent (Cursor, Claude Code, Windsurf, Copilot).

**Dashboard** (Agent Ready → Dashboard) is the verifier; **Settings** (Agent Ready → Settings) is the per-feature switchboard.

## What it publishes

| Feature | Endpoint / behavior | Standard |
|---|---|---|
| Markdown Negotiation | `Accept: text/markdown` → clean Markdown + `X-Markdown-Tokens`, with `Vary: Accept` | [Markdown for Agents](https://developers.cloudflare.com/fundamentals/reference/markdown-for-agents/) |
| llms.txt + llms-full.txt | `/llms.txt` curated index · `/llms-full.txt` full recent content as Markdown (password-protected content always excluded) | [llmstxt.org](https://llmstxt.org/) |
| AI Bot Rules | robots.txt User-agent groups for the 15 scanner-checked AI crawlers, per-bot allow/block policy | [RFC 9309](https://www.rfc-editor.org/rfc/rfc9309) |
| Content Signals | `Content-Signal: ai-train=no, search=yes, ai-input=no` inside a `User-agent: *` group | [contentsignals.org](https://contentsignals.org/) |
| API Catalog | `/.well-known/api-catalog` linkset + `Link: rel="api-catalog"` header | [RFC 9727](https://www.rfc-editor.org/rfc/rfc9727) |
| MCP Server Card | `/.well-known/mcp/server-card.json` with `transport.endpoint` | [MCP SEP-1649](https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2127) |
| Agent Skills Index | `/.well-known/agent-skills/index.json` (`type: skill-md`, `digest: sha256:…`) + per-skill `SKILL.md` | [Agent Skills Discovery RFC v0.2.0](https://github.com/cloudflare/agent-skills-discovery-rfc) |
| auth.md | `/auth.md` — honest agent-auth docs for Application Passwords | [workos.com/auth-md](https://workos.com/auth-md) |
| WebMCP Tools | 4 in-page tools via `navigator.modelContext` (search, posts, pages, site info) | [W3C WebMCP draft](https://webmachinelearning.github.io/webmcp/) |
| OpenAPI | `/openapi.json` (and `/?format=openapi`) generated from live REST routes | OpenAPI 3.0.3 |
| JSON-LD Schema | WebSite, Organization, Article, BreadcrumbList, FAQPage — auto-suppressed next to Yoast/Rank Math/AIOSEO/etc. | schema.org |
| IndexNow | Non-blocking ping to Bing/Yandex on publish | [indexnow.org](https://www.indexnow.org/) |

## Interfaces

**Dashboard** — Agent Ready → Dashboard in wp-admin.

**WP-CLI**

```bash
wp agent-ready scan                      # summary table + next-level guidance
wp agent-ready scan --format=agent       # markdown fix report (paste into any coding agent)
wp agent-ready scan --format=json        # full machine-readable scan
wp agent-ready fix agentSkills           # apply one fix, re-scan, exit 1 on unknown/unfixable
wp agent-ready fix --all-safe            # fix every failing check that has a one-click fix
wp agent-ready status                    # last stored scan without re-running
```

**REST API** (`manage_options` capability + REST nonce, except `/health`)

```
POST /wp-json/ajaco/v1/scan          {"checks": ["robotsTxt", ...]}   # omit for the 19-check default
GET  /wp-json/ajaco/v1/scan                                           # last stored scan
POST /wp-json/ajaco/v1/scan/check    {"check": "markdownNegotiation"} # re-run one check
POST /wp-json/ajaco/v1/fix           {"check": "agentSkills"}         # fix + re-verify
GET  /wp-json/ajaco/v1/health                                         # public liveness (RFC 9727 status target)
```

## Install

From wp-admin: **Plugins → Add New → search "AJ Agent Crawl Optimizer" → Install → Activate**, then open **Agent Ready → Dashboard** and run your first scan.

From source:

```bash
cd wp-content/plugins
git clone https://github.com/ajmaurya99/aj-agent-crawl-optimizer.git
cd aj-agent-crawl-optimizer && git checkout v2-dev   # for the 2.0 scanner
wp plugin activate aj-agent-crawl-optimizer
```

## Verify it works

```bash
wp agent-ready scan --format=agent                            # the whole loop in one command
curl -H "Accept: text/markdown" https://example.com/          # markdown + X-Markdown-Tokens
curl https://example.com/.well-known/api-catalog              # RFC 9727 linkset
curl https://example.com/.well-known/agent-skills/index.json  # skills + sha256 digests
curl https://example.com/llms.txt                             # LLM-readable index
curl https://example.com/llms-full.txt                        # full content as Markdown
curl https://example.com/auth.md                              # agent auth documentation
curl https://example.com/openapi.json                         # OpenAPI 3.0.3
curl https://example.com/robots.txt                           # AI bot groups + Content-Signal
```

Cross-check with Cloudflare's [Agent Readiness scanner](https://isitagentready.com/) — the plugin implements the same check ids, pass criteria, and level ladder, so the numbers should agree.

## For developers

Fifteen filter hooks: `ajaco_required_capability`, `ajaco_skill_definitions`, `ajaco_content_signal`, `ajaco_ai_bot_list`, `ajaco_ai_bot_policy`, `ajaco_auth_md_content`, `ajaco_json_ld_graph`, `ajaco_openapi_spec`, `ajaco_llms_txt_content`, `ajaco_llms_full_txt_content`, `ajaco_api_catalog_linkset`, `ajaco_mcp_server_card`, `ajaco_commerce_signals`, `ajaco_scan_sslverify`, `ajaco_active_seo_plugin`, plus the standard WordPress surface. Examples in the Help tab → For Developers, or see [readme.txt](readme.txt).

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

// Block all training crawlers while allowing AI search/user-action bots.
add_filter( 'ajaco_ai_bot_policy', function ( $policy ) {
    foreach ( Ajaco\ai_bot_list() as $bot => $purpose ) {
        if ( 'training' === $purpose ) {
            $policy[ $bot ] = 'block';
        }
    }
    return $policy;
} );
```

Scan engine architecture: `includes/scan/` — one class per check extending `Ajaco\Scan\Check` (evidence helpers built in), `Scanner` orchestration, `Level` ladder, `Fix_Registry` mapping checks to fixes. Check ids and result shapes mirror isitagentready.com for direct comparability.

## Development

```bash
composer install
composer lint     # PHPCS (WordPress Coding Standards)
composer lint:fix # PHPCBF
```

## Privacy

No telemetry, no analytics, no cookies. Outbound requests: the (opt-in) IndexNow ping, and — only while a scan you started is running — DNS-over-HTTPS lookups to Cloudflare/Google resolvers for the DNS-AID check plus HTTP probes of your own site. Full details in [readme.txt](readme.txt) → Privacy.

## License

GPL-2.0-or-later.
