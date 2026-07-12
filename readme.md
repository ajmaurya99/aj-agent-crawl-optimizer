# AJ Agent Crawl Optimizer — AI Readiness Scanner & llms.txt for WordPress

**AJ Agent Crawl Optimizer is a free WordPress plugin that scans your site against 21 AI-agent-readiness checks — the same set used by Cloudflare's [isitagentready.com](https://isitagentready.com/) — grades it on a Level 0–5 ladder, fixes failing checks in one click, and lets you hand-curate the `llms.txt` that AI agents like ChatGPT, Claude, and Perplexity read.**

It's the technical, agent-readiness layer of **answer engine optimization (AEO)** and **generative engine optimization (GEO)**: make your WordPress content legible and visible to AI agents through llms.txt, JSON-LD, Markdown negotiation, and AI crawler rules — then *prove* it over real HTTP. See your Level 0–5 with full request/response evidence, fix failures in one click, and re-verify instantly.

Then publish everything agents need — Markdown negotiation, MCP server card, Agent Skills, API catalog, AI bot rules, Content Signals — and **hand-curate the `llms.txt` they actually read**: your intro, your sections, your per-post summaries. [Jump to curation →](#curating-llmstxt)

[![WordPress plugin](https://img.shields.io/wordpress/plugin/v/aj-agent-crawl-optimizer)](https://wordpress.org/plugins/aj-agent-crawl-optimizer/)
[![License: GPL v2+](https://img.shields.io/badge/License-GPLv2+-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

> **Version 2.0** — the scan → fix → verify engine described below — is the current stable release on wordpress.org.

## The scan → fix → verify loop (2.0)

Where external scanners stop at "here's a prompt, go fix it," this plugin closes the loop inside wp-admin:

1. **Scan** — 21 checks across 5 categories run against your live site over real HTTP, so page caches, CDNs, and server rules that silently break agent endpoints get caught. Every verdict carries an evidence timeline (fetch/parse/conclude with request/response snapshots).
2. **Level** — results map to the Level 0–5 agent-readiness ladder (Not Ready → Basic Web Presence → Bot-Aware → Agent-Readable → Agent-Integrated → Agent-Native), with a "Next level" panel listing exactly which checks unlock the next rung. Nothing scores unless the scan verifies it.
3. **Fix** — 9 checks have a **Fix now** button that enables the right feature; the plugin re-scans that single check immediately and shows it going green. Fixes needing DNS or server access get copy-paste prompts for your coding agent (Cursor, Claude Code, Windsurf, Copilot).

**Hosting diagnosis.** The plugin writes no files — every endpoint is served by WordPress — but some hosts block the request before WordPress runs (nginx dot-path rules `403` on `/.well-known/*`; static-file rules `404` on `/llms.txt`). When a feature is on and the server blocks it, the Dashboard says so and hands you a copy-paste nginx or Apache fix.

**Dashboard** (Agent Ready → Dashboard) is the verifier; **Settings** (Agent Ready → Settings) is the per-feature switchboard; **llms.txt** (Agent Ready → llms.txt) is where you curate the index.

## How it compares to other AI / llms.txt plugins

Most AI and llms.txt plugins stop at *generating* a file. This one closes the loop:

| Capability | Typical llms.txt generator / AI SEO plugin | AJ Agent Crawl Optimizer |
|---|---|---|
| Generate `llms.txt` | ✅ fixed rules | ✅ **hand-curated** — your intro, sections, per-post summaries |
| Scan the live site over real HTTP | ❌ | ✅ 21 checks across 5 categories |
| Score agent-readiness | ❌ | ✅ Level 0–5 ladder |
| Evidence behind each verdict | ❌ | ✅ request/response timeline |
| One-click fix + re-verify | ❌ | ✅ 9 fixes, re-scanned to prove green |
| Per-bot AI crawler rules (GPTBot, Claude-Web, PerplexityBot…) | rare | ✅ 15 crawlers, allow/block each |
| MCP server card + WebMCP + Agent Skills | ❌ | ✅ |

**Scan → score → fix → verify, not generate-and-hope.**

## What it publishes

| Feature | Endpoint / behavior | Standard |
|---|---|---|
| Markdown Negotiation | `Accept: text/markdown` → clean Markdown + `X-Markdown-Tokens`, with `Vary: Accept` | [Markdown for Agents](https://developers.cloudflare.com/fundamentals/reference/markdown-for-agents/) |
| llms.txt + llms-full.txt | `/llms.txt` curated index (your intro, your sections, your per-post choices) · `/llms-full.txt` full content of those entries (password-protected content always excluded) | [llmstxt.org](https://llmstxt.org/) |
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

## Curating llms.txt

The index is **curated, not auto-generated from fixed rules** — but the defaults keep the same sections and ordering as the old automatic output (2.0 adds a one-line pointer to `/llms-full.txt`), so upgrading needs no attention until you choose to curate.

**Site-wide** (Agent Ready → llms.txt):

- **Intro** — replaces the boilerplate line. This is where you tell an agent what the site is actually for.
- **Sections** — one per post type; custom post types and WooCommerce products appear automatically. Each has include on/off, heading, item count, order (recent / menu order / title), top-level-only (hierarchical types), and show-dates.
- **Custom Markdown block** — pinned above or below the generated sections.
- **Live preview** — renders the file (with byte, token and entry counts) from your *unsaved* edits, via `POST /wp-json/ajaco/v1/llms/preview`.

**Per entry** — the "Agent Ready (llms.txt)" panel in the editor (block editor, with a classic-editor metabox fallback):

- **Include in llms.txt** — off keeps the entry out of both `/llms.txt` and `/llms-full.txt`.
- **Summary for AI agents** — overrides the excerpt for that entry. An excerpt is written for a human skimming; this line is written for a model deciding whether to fetch the page.

Both are plain post meta (REST-exposed), so they script cleanly:

```bash
wp post meta update 42 _ajaco_llms_exclude 1                       # keep a thin page out of the agent indexes
wp post meta update 17 _ajaco_llms_summary "Step-by-step V60 recipe: grind, ratio, timing."
```

## Interfaces

### Dashboard

Agent Ready → Dashboard in wp-admin.

### WP-CLI

```bash
wp agent-ready scan                      # summary table + next-level guidance
wp agent-ready scan --format=agent       # markdown fix report (paste into any coding agent)
wp agent-ready scan --format=json        # full machine-readable scan
wp agent-ready fix agentSkills           # apply one fix, re-scan, exit 1 on unknown/unfixable
wp agent-ready fix --all-safe            # fix every failing check that has a one-click fix
wp agent-ready status                    # last stored scan without re-running
```

### REST API

Requires the `manage_options` capability + a REST nonce, except `/health` (public).

```text
POST /wp-json/ajaco/v1/scan          {"checks": ["robotsTxt", ...]}   # omit for the 19-check default
GET  /wp-json/ajaco/v1/scan                                           # last stored scan
POST /wp-json/ajaco/v1/scan/check    {"check": "markdownNegotiation"} # re-run one check
POST /wp-json/ajaco/v1/fix           {"check": "agentSkills"}         # fix + re-verify
POST /wp-json/ajaco/v1/llms/preview  {"config": { ... }}              # preview llms.txt for an unsaved config
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

## FAQ

**Is my WordPress site ready for AI agents?** Run the built-in scanner — it checks your live site against 21 AI-agent-readiness checks (the same set as Cloudflare's isitagentready.com) and grades you Level 0–5, with the request/response evidence behind every verdict and a one-click fix for failures.

**What is llms.txt and does my site need one?** `llms.txt` is a Markdown file at `/llms.txt` that gives AI agents a curated index of your key content ([llmstxt.org](https://llmstxt.org/)). This plugin lets you hand-curate it — intro, sections, per-post "Summary for AI agents" — rather than auto-dumping every post.

**How do I allow or block GPTBot and other AI crawlers in WordPress?** The plugin writes explicit robots.txt rules for the 15 AI crawlers readiness scanners check (GPTBot, ChatGPT-User, Claude-Web, PerplexityBot, Google-Extended, CCBot, Bytespider…), each allowed or blocked per a per-bot policy. To be cited by an AI engine, you must first allow its crawler.

**How do I get my content found by ChatGPT and Perplexity?** No guarantees, but three things help and this plugin does all three: allow the AI search crawlers, publish the machine-readable surfaces agents look for (llms.txt, JSON-LD, Markdown negotiation), and write a clear per-page "Summary for AI agents" — then the scanner verifies each is live over real HTTP.

**What's the difference between llms.txt and llms-full.txt?** `/llms.txt` is a curated index (links + short summaries so an agent can choose what to fetch); `/llms-full.txt` is the full Markdown content of those same entries inline. The plugin serves both; password-protected content is always excluded from each.

**How is this different from an llms.txt generator or an AI SEO plugin?** Generators only produce a file. This is an agent-readiness scanner and fixer: it audits your live site over real HTTP, scores a Level 0–5 ladder, shows the evidence, fixes failures in one click, and re-verifies — covering the answer engine optimization (AEO) and generative engine optimization (GEO) groundwork (structured data, AI crawler rules, MCP discovery) that generators skip.

## For developers

Seventeen filter hooks: `ajaco_required_capability`, `ajaco_skill_definitions`, `ajaco_content_signal`, `ajaco_ai_bot_list`, `ajaco_ai_bot_policy`, `ajaco_auth_md_content`, `ajaco_json_ld_graph`, `ajaco_openapi_spec`, `ajaco_llms_txt_content`, `ajaco_llms_full_txt_content`, `ajaco_llms_post_types`, `ajaco_llms_exclude_post`, `ajaco_api_catalog_linkset`, `ajaco_mcp_server_card`, `ajaco_commerce_signals`, `ajaco_scan_sslverify`, `ajaco_active_seo_plugin`, plus the standard WordPress surface. Examples in the Help tab → For Developers, or see [readme.txt](readme.txt).

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
