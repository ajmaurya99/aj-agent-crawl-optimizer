=== AJ Agent Crawl Optimizer – AI Readiness Scanner & llms.txt for WordPress ===
Contributors:      ajmaurya
Tags:              llms-txt, ai, chatgpt, mcp, ai crawlers
Requires at least: 5.6
Tested up to:      7.1
Requires PHP:      7.4
Stable tag:        2.1.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

llms.txt + AI-readiness scanner for WordPress: get found by ChatGPT & Claude, manage AI crawlers in robots.txt, fix failures in one click.

== Description ==

**AJ Agent Crawl Optimizer is a free WordPress plugin that scans your site against 33 AI-agent-readiness checks — Cloudflare's 21 isitagentready.com checks plus 12 modelled on what hosted scanners like ora.ai and is-agentic.com grade for — scores it 0–100 with a letter grade and a Level 0–5 readiness ladder, fixes failing checks in one click, and lets you hand-curate the `llms.txt` that AI agents like ChatGPT, Claude, and Perplexity read.**

It makes your site legible to AI agents — and *proves* it. The built-in **readiness scanner** runs 33 checks against your live site, scores you **0–100 with a letter grade (A+ to F)** and on the **Level 0–5 agent-readiness ladder** (Not Ready → Basic Web Presence → Bot-Aware → Agent-Readable → Agent-Integrated → Agent-Native), and shows the full evidence trail — every request and response — behind every verdict. Failing checks get a **Fix now** button that enables the right feature and re-scans that single check to prove it went green; two more (Organization details, a real MCP server) get a guided button to the right screen; anything the plugin can't fix in WordPress (DNS records, server config) gets a copy-paste prompt for your coding agent.

This is the technical, agent-readiness layer of **answer engine optimization (AEO)** and **generative engine optimization (GEO)**: it improves your **AI visibility** by making your content legible to AI agents through llms.txt, JSON-LD, Markdown negotiation, and AI crawler rules. It is *not* a content SEO plugin — it doesn't write titles or meta descriptions, and it doesn't track brand mentions or AI citations. It prepares your WordPress site so AI search engines and agents can read, trust, and route on it, then verifies that over real HTTP.

= The scan → fix → verify loop =

* **Dashboard** (Agent Ready → Dashboard) — a 0–100 score ring with letter grade, per-layer point bars, the Level badge, a trend since your last full scan, check cards ranked by impact with points at stake, filters (failing / one-click fixes / bonus), and a "Next level" panel listing exactly which checks unlock the next level.
* **33 checks across 5 categories** — Discoverability (robots.txt, sitemap and lastmod, Link headers, identity metadata, trust pages, Organization schema, DNS-AID), Content Accessibility (Markdown negotiation, alternate link, frontmatter, agent-friendly 404, content without JavaScript, llms.txt lint), Bot Access Control (AI bot rules, user-request agents allowed, Content Signals, Web Bot Auth), API/Auth/MCP Discovery (API catalog, agent resource catalog, OAuth discovery, OAuth Protected Resource, auth.md, MCP server card, MCP server, A2A agent card, Agent Skills, WebMCP), and Commerce (x402, MPP, UCP, ACP, AP2 — informational bonus, never counted against you).
* **Weighted score and grade** — every check carries points; checks that don't apply (or can't be reached) drop out of the denominator instead of counting against you, emerging protocols add capped bonus points, and grade bands match ora.ai (A+ 95, A 86, B 70, C 48, D 28). The Level 0–5 ladder stays criterion-referenced to Cloudflare.
* **Evidence timelines** — fetch/parse/conclude steps with request/response snapshots, so you can audit *why* a check passed or failed and catch page caches or server rules silently breaking your endpoints.
* **One-click fixes with verification** — 17 checks are fixable in one click, each re-scanned immediately; a bulk "Fix all safe items" sheet handles the rest. Organization schema and MCP server get a guided button instead (fill in the fields; install the official WordPress MCP Adapter).
* **Hosting diagnosis** — the plugin writes no files, but some hosts block requests before WordPress runs (nginx dot-path rules 403 `/.well-known/*`, static-file rules 404 `/llms.txt`). When that happens the Dashboard says so and hands you copy-paste nginx and Apache fixes.
* **Score badge & hosted-scanner cross-check** — the `[agent_ready_badge]` shortcode (and an opt-in public `/agent-ready-badge.svg`) shows the score, grade and Level as a static SVG; on request, the Dashboard looks up this site's score on ora.ai and is-agentic.com (read-only, keyless) next to ours.
* **REST API and WP-CLI** — `POST /wp-json/ajaco/v1/scan`, `/scan/check`, `/fix`, `GET /external`, `POST /llms/preview`, a public `GET /health`; `wp agent-ready scan|status|fix` with `--format=summary|json|agent` and `--min-score` / `--min-level` CI gates that exit non-zero.

Each publishing capability is a separate toggle under **Agent Ready → Settings** and ships **opt-in** (everything starts off). On first activation, a **Quick Setup wizard** suggests defaults based on your environment (it skips JSON-LD when an SEO plugin is detected) — re-runnable any time.

= Curated llms.txt — you decide what agents read =

Most plugins generate `llms.txt` from fixed rules and hand you the result. This one hands you the controls.

* **A dedicated editor** (Agent Ready → llms.txt) — write the intro that tells an agent what your site is *for*, choose which content types appear (custom post types and WooCommerce products are detected automatically), and set each section's heading, item count and order. Add your own Markdown block above or below.
* **Live preview** — see exactly what `/llms.txt` will serve, with entry, byte and token counts, *before* you save.
* **Per-post control** — every post and page gets an "Agent Ready (llms.txt)" panel in the editor (block and classic): keep an entry out of the agent indexes entirely, or write a **Summary for AI agents** that overrides the excerpt. An excerpt is written for a human skimming; that line is written for a model deciding whether to fetch the page — the single highest-leverage field in the plugin.
* **`/llms-full.txt`** serves the full content of the same curated entries. Password-protected content is always excluded from both.

= Discovery — help agents find what your site offers =

* **API Catalog** (RFC 9727) — `/.well-known/api-catalog` linkset advertising your REST API, plus a `Link: rel="api-catalog"` header on every response.
* **MCP Server Card** (SEP-1649 draft) — `/.well-known/mcp/server-card.json` describing the site to MCP-aware agents. The plugin does not run an MCP server itself. When the official WordPress MCP Adapter (or any plugin registering REST routes under the `mcp` namespace) is active, the card advertises its real Streamable HTTP endpoint and the Application Password login; otherwise it states plainly that the REST API is the only machine interface.
* **MCP server integration** — with the adapter active, the agent resource catalog, API catalog, llms.txt and auth.md all list the server endpoint, and the `mcpServer` check connects to it (JSON-RPC `initialize`) to prove it answers. Without one, the check fails with an "Install the MCP Adapter" button (the adapter needs WordPress 6.9+).
* **Agent resource catalog** (ARD / AI Catalog) — `/.well-known/ard.json` (and the predecessor `/.well-known/ai-catalog.json`) indexing every enabled agent-facing endpoint, linked from every page with `rel="ard"` / `rel="ai-catalog"`.
* **Agent Skills Index** (RFC v0.2.0) — `/.well-known/agent-skills/index.json` listing six skills plus per-skill `SKILL.md` artifacts with verifiable sha256 digests.
* **Discovery links** — `<link rel>` tags and `Link` headers for the sitemap, OpenAPI document, llms.txt and catalogs, so agents find every file from the pages they already visit.
* **Sitemap lastmod** — adds `<lastmod>` dates to the WordPress core sitemap on versions that lack them; a no-op where core or an SEO plugin already provides them.
* **IndexNow** — non-blocking ping to Bing and Yandex on every post publish so search engines re-crawl within minutes.

= Presentation — format content for agents =

* **Markdown Negotiation** — when a request includes `Accept: text/markdown`, the page is served as clean Markdown that opens with a YAML frontmatter block (title, url, site, modified), with `X-Markdown-Tokens` for context budgeting. Every page advertises its Markdown twin with `<link rel="alternate" type="text/markdown">` and a matching `Link` header, and missing pages return a real 404 as Markdown. Browsers (which send `text/html`) are unaffected.
* **JSON-LD Schema** — WebSite, Organization, Article, BreadcrumbList, and auto-detected FAQPage. Logo resolved from your theme's custom logo or site icon. The **Organization details** fields (official profile URLs, contact email or phone, postal address) add `sameAs`, `contactPoint` and `address` to the Organization node.
* **Identity meta tags** — a canonical link on the home page and archives plus Open Graph basics (og:type, og:site_name, og:title, og:url, og:image, og:description). Auto-suppressed when an SEO plugin is active, like JSON-LD.
* **OpenAPI 3.0.3** — `/openapi.json` returns a complete spec generated dynamically from `rest_get_server()`, including plugin-registered REST routes.
* **WebMCP Tools** — four read-only in-page tools (search_site_content, list_recent_posts, list_site_pages, get_site_info) registered with `document.modelContext.registerTool()` per the W3C WebMCP draft (Chrome origin trial), each with a typed input schema and a `readOnlyHint` annotation, with a fallback for the earlier preview API.

= Declarations =

* **AI Bot Rules** — **to be cited by an AI engine, you must first allow its crawler.** The plugin writes explicit robots.txt User-agent groups for 20 AI crawlers (GPTBot, ChatGPT-User, OAI-SearchBot, ClaudeBot, Claude-User, Claude-Web, PerplexityBot, Perplexity-User, Google-Extended, CCBot, Bytespider, …), each allowed or blocked per a filterable per-bot policy. A crawler added in a later release inherits your stored decision for its purpose, so an upgrade never un-blocks a category you blocked. A dedicated check verifies the user-request agents are never blocked, since an answer engine cannot cite a page its agent cannot fetch. Groups honor "Discourage search engines".
* **Content-Signals** — appends a `Content-Signal: ai-train=no, search=yes, ai-input=no` directive to robots.txt per contentsignals.org. Composes with Yoast/Rank Math/AIOSEO — their additions are preserved, our directive lands last.
* **auth.md** — publishes `/auth.md` honestly documenting how agents authenticate to your REST API via Application Passwords (creation, Basic-auth usage, scope, revocation), and the MCP endpoint when one is detected. No fictional OAuth endpoints.

= How it compares to other AI / llms.txt plugins =

Most AI and `llms.txt` plugins only *generate* a file from fixed rules. AJ Agent Crawl Optimizer also **scans** your live site over real HTTP, **scores** it 0–100 with a grade and the Level 0–5 ladder, shows the request/response **evidence** behind every verdict, and **fixes** failures in one click — then re-scans to prove the check turned green. It writes per-bot robots.txt rules for 20 AI crawlers and lets you hand-curate what AI agents read instead of auto-dumping every post. Scan → score → fix → verify, not generate-and-hope.

= Why use it =

* **No conflicts with your SEO plugin.** JSON-LD and meta tags auto-suppress when Yoast SEO, Rank Math, All in One SEO, SEOPress, The SEO Framework, Slim SEO, Squirrly SEO, Schema Pro, or SASWP is active.
* **Multisite-aware.** Every endpoint also resolves at `/{subsite}/...` paths automatically.
* **Cached and performance-conscious.** OpenAPI is cached for a day, llms.txt for an hour, with proper invalidation. Markdown conversion runs in an output-buffer callback so other shutdown hooks are untouched; endpoint handlers early-return after one path check; IndexNow pings are non-blocking.
* **Extensible.** Nineteen filter hooks cover skills, schemas, bot policies, llms.txt curation, catalogs, MCP detection, scan behavior, and content — see the FAQ.
* **Private by design.** Everything is stored on your own server; the only outbound requests are the ones you trigger (scans, IndexNow, the hosted-scanner cross-check) — see the FAQ for exactly what is sent where.
* **Accessible.** The Dashboard score ring carries a dynamic `aria-label`, copy buttons announce success via `aria-live`, and decorative icons are hidden from screen readers.
== Installation ==

= From the WordPress plugin directory =

1. In your WP admin, go to **Plugins → Add New** and search for "AJ Agent Crawl Optimizer".
2. Click **Install Now**, then **Activate**.
3. The Quick Setup wizard runs automatically on first activation — review the recommended toggles and click **Apply**.
4. Open **Agent Ready → Dashboard**, run your first scan, and use **Fix now** on failing checks. Toggles live under **Agent Ready → Settings**.

= Manual install =

1. Download the plugin zip.
2. Upload the `aj-agent-crawl-optimizer` folder to `/wp-content/plugins/`.
3. Activate **AJ Agent Crawl Optimizer** from the **Plugins** screen.
4. Visit **Agent Ready → Dashboard** to scan, or **Agent Ready → Settings** to run the wizard / configure manually.

= IndexNow setup (optional) =

If you want Bing and Yandex to re-crawl your content within minutes of publish:

1. Generate a key at https://www.bing.com/webmasters/indexnow.
2. Paste it into the **IndexNow API Key** field on the settings page.
3. Enable the IndexNow toggle. The plugin hosts the key file at `/{key}.txt` for ownership verification automatically.

== Frequently Asked Questions ==

= Is my WordPress site ready for AI agents? =

Run the built-in readiness scanner. AJ Agent Crawl Optimizer checks your live site against 33 AI-agent-readiness checks — Cloudflare's 21 isitagentready.com checks plus 12 modelled on what ora.ai / is-agentic.com grade for — scores you 0–100 with a grade and a Level 0–5 ladder from Not Ready to Agent-Native. Every verdict shows the real request and response behind it, and failing checks get a one-click **Fix now** button that re-scans to prove it passed.

= What is llms.txt and does my WordPress site need one? =

`llms.txt` is a plain-Markdown file at `/llms.txt` that gives AI agents a curated index of your most important content, proposed at llmstxt.org. AJ Agent Crawl Optimizer lets you hand-curate it: write the intro, pick which post types (and WooCommerce products) appear, and set a per-post "Summary for AI agents" that overrides the excerpt. It is a surface AI agents can route on — not a guaranteed path to AI citations, but a machine-readable map of what your site is for.

= How do I allow or block GPTBot and other AI crawlers in WordPress? =

The plugin writes explicit robots.txt rules for 20 AI crawlers — GPTBot, ChatGPT-User and OAI-SearchBot (OpenAI), ClaudeBot, Claude-User and Claude-Web (Anthropic), PerplexityBot and Perplexity-User, Google-Extended, CCBot and Bytespider among them — each allowed or blocked per a per-bot policy. Presets like "Allow search & user requests, block training" make it a single choice. Remember: to be cited by an AI engine you must *allow* its crawler.

= How do I get my WordPress content found by ChatGPT and Perplexity? =

There is no guaranteed path, but three things help and this plugin does all three: allow the AI search crawlers (OAI-SearchBot, PerplexityBot, Google-Extended), publish the machine-readable surfaces agents look for (llms.txt, JSON-LD, Markdown negotiation), and write a clear "Summary for AI agents" on key pages. The readiness scanner then verifies each of these is actually live over real HTTP — so you fix what's broken instead of guessing.

= What is the difference between llms.txt and llms-full.txt? =

`/llms.txt` is a curated *index* — a Markdown list of your key pages with links and short summaries, so an agent can decide what to fetch. `/llms-full.txt` serves the full Markdown *content* of those same curated entries in one file, for agents that want everything inline. The plugin serves both, and password-protected content is always excluded from each.

= How is this different from an llms.txt generator or an AI SEO plugin? =

Most plugins only *generate* an llms.txt file from fixed rules. AJ Agent Crawl Optimizer is an agent-readiness scanner and fixer: it audits your live site over real HTTP against 33 checks, scores it 0–100 with a grade and a Level 0–5 ladder, shows the request/response evidence behind every verdict, fixes failures in one click, and re-verifies. It covers the answer engine optimization (AEO) and generative engine optimization (GEO) groundwork — structured data, AI crawler rules, and MCP discovery — that plain generators and content-writing SEO plugins skip.

= Does it work with WooCommerce? =

Yes. WooCommerce products are detected automatically and can appear as their own section in your curated llms.txt, so AI agents can see your catalogue. You control whether products appear, how many, and in what order, and you can exclude individual products or give any product a per-product "Summary for AI agents."

= What's the difference between the Dashboard and Settings screens? =

**Dashboard** is the verifier: it scans your live site over real HTTP, shows your score, grade and Level 0–5 with evidence for every check, and fixes failures with one click. **Settings** is the switchboard: the per-feature toggles, wizard, and testing tools. A toggle can be ON in Settings while the Dashboard still shows a fail — that gap usually means a page cache or server rule is intercepting the endpoint, and the evidence view shows exactly what happened.

= How are the score and the readiness Level calculated? =

The scanner runs Cloudflare's 21 isitagentready.com checks plus 12 checks modelled on the ora.ai / is-agentic.com catalog and the AgentReady.org standard. The 0–100 score weights every check; checks that don't apply or can't be reached drop out of the denominator, bonus checks (Web Bot Auth, DNS-AID, A2A card, commerce protocols) add up to 5 points and never subtract, and grades use the ora.ai bands (A+ 95, A 86, B 70, C 48, D 28). The Level 0–5 ladder stays identical to Cloudflare's: Level 1 needs 2 of robots.txt/sitemap/Link headers; Level 2 adds AI bot rules and Content Signals; Level 3 adds Markdown negotiation; Level 4 adds one of MCP card/Agent Skills/API catalog/A2A card; Level 5 adds two of Web Bot Auth, all four integrations, and auth metadata. Nothing counts unless the scan verifies it — enabling a toggle earns nothing until the endpoint actually responds. Commerce checks are informational and never scored.

= My host blocks /.well-known/ paths or doesn't allow creating files — will the plugin still work? =

The plugin never creates files: every endpoint (/.well-known/*, /llms.txt, /openapi.json, /auth.md, /agent-ready-badge.svg) is served virtually by WordPress, so hosts that forbid file creation are fully supported. What can break things is the web server intercepting requests before WordPress runs — typically an nginx dot-path deny rule returning 403 on /.well-known/*, or a static-file rule returning 404 for /llms.txt-style paths. The readiness scan detects exactly this (feature enabled + server blocking) and the Dashboard shows a hosting notice with copy-paste nginx and Apache fixes you can apply yourself or forward to your hosting support.

= Can I control what goes into llms.txt? =

Yes — it is curated, not auto-generated from fixed rules. Under **Agent Ready → llms.txt** you write the intro, pick which post types appear (custom post types and WooCommerce products show up automatically), and set each section's heading, item count and order, plus your own Markdown block. A live preview shows the file as you edit, before you save.

Individual entries are steered from the editor: the "Agent Ready (llms.txt)" panel has an **Include in llms.txt** toggle (excluded pages stay out of both `/llms.txt` and `/llms-full.txt`) and a **Summary for AI agents** field that overrides the excerpt for that entry — write it for a model deciding whether to fetch the page. The defaults keep the same sections and ordering as before, so upgrading needs no attention until you decide to curate.

= Can I run scans from the command line or scripts? =

Yes: `wp agent-ready scan` (add `--format=json` for machines or `--format=agent` for a markdown fix report), `wp agent-ready fix <check>` or `--all-safe`, and `wp agent-ready status`. Add `--min-score=<n>` or `--min-level=<n>` to `scan` or `status` for a CI gate that exits non-zero. The same operations are available over REST at `/wp-json/ajaco/v1/scan`, `/wp-json/ajaco/v1/fix` and `/wp-json/ajaco/v1/external` (admin capability required; `/wp-json/ajaco/v1/health` is public).

= Will this conflict with my SEO plugin? =

No. JSON-LD and the identity meta tags auto-suppress when Yoast SEO, Rank Math, All in One SEO, SEOPress, The SEO Framework, Slim SEO, Squirrly SEO, Schema Pro, or SASWP is active. The settings page shows a clear notice when this happens. Our `robots_txt` filter runs at `PHP_INT_MAX` priority, so any rules your SEO plugin adds (sitemap URLs, custom Disallow, etc.) are preserved — our `Content-Signal` line is appended last.

= Does this change anything for my regular site visitors? =

No. Browsers send `Accept: text/html` and get the normal HTML response. The `.well-known/...`, `/llms.txt`, and `/openapi.json` endpoints are paths a normal user never visits. What the enabled features add to a page is invisible to visitors: a JSON-LD `<script>` block and Open Graph meta tags (when no SEO plugin is active), a few `<link rel>` tags and `Link` headers pointing at the agent-facing files, and a small WebMCP script tag that no-ops in browsers without the API.

= My site is multisite. Does it work? =

Yes. Every endpoint resolves at both the root and per-subsite paths automatically — `/llms.txt`, `/blog/llms.txt`, `/.well-known/api-catalog`, `/blog/.well-known/api-catalog`, etc. Each subsite has its own settings.

= How do I delegate plugin access to a non-admin role? =

Use the `ajaco_required_capability` filter. The plugin defaults to `manage_options` (admin-only); change it to any capability of your choice, e.g. `edit_pages` for editors:

`add_filter( 'ajaco_required_capability', function () { return 'edit_pages'; } );`

= I enabled IndexNow but I'm not seeing pings to Bing or Yandex. =

Check three things: (1) the IndexNow API Key field is filled in, (2) you're publishing a post of a public post type — revisions and autosaves are skipped, (3) you're on production. Pings are non-blocking, so failures are silent. Tail your server log for outbound requests to `api.indexnow.org`.

= Can I add custom skills, schemas, or sections to llms.txt? Which filter hooks exist? =

Yes. The plugin exposes nineteen filter hooks. Examples:

`add_filter( 'ajaco_required_capability', function () { return 'edit_posts'; } );`
Delegate plugin access to a non-admin role.

`add_filter( 'ajaco_skill_definitions', function ( $skills ) { return $skills + [ 'products' => [ 'type' => 'information-retrieval', 'description' => 'WooCommerce products', 'endpoint' => rest_url( 'wc/v3/products' ) ] ]; } );`
Register custom skills that ship in the Agent Skills Index as SKILL.md artifacts with verifiable sha256 digests.

`add_filter( 'ajaco_content_signal', function () { return 'ai-train=yes, search=yes, ai-input=yes'; } );`
Customize the Content-Signal directive (e.g. permit AI training).

Other hooks: `ajaco_json_ld_graph` (add Product, Recipe, Event… schema), `ajaco_llms_txt_content` and `ajaco_llms_full_txt_content` (append to or replace the files), `ajaco_llms_post_types`, `ajaco_llms_exclude_post`, `ajaco_openapi_spec`, `ajaco_api_catalog_linkset`, `ajaco_ai_catalog`, `ajaco_mcp_server_card`, `ajaco_mcp_endpoint` (declare an MCP server the plugin cannot detect), `ajaco_auth_md_content`, `ajaco_ai_bot_list`, `ajaco_ai_bot_policy`, `ajaco_commerce_signals`, `ajaco_scan_sslverify`, `ajaco_active_seo_plugin`. The Help tab's "For Developers" section on the settings page lists every hook with a description.

Per-post curation is stored in two post meta fields: `_ajaco_llms_exclude` (boolean) and `_ajaco_llms_summary` (string, 300 chars). Both are registered with `show_in_rest`, so they round-trip through the REST API and WP-CLI (`wp post meta update 42 _ajaco_llms_summary "..."`).

= How do I undo everything and start over? =

Use the **Reset to Defaults** button under the Save Changes button on the settings page. It clears every plugin option, wipes cached endpoint outputs, and shows a confirmation dialog before wiping anything.

= Performance impact? =

Minimal. The OpenAPI document is cached for a day in a transient and only regenerates on plugin activation/deactivation or theme switch. The llms.txt body is cached for an hour and invalidates on post edits, site name/description changes, and setting toggles. Endpoint handlers run on `init` only when the request path matches; on every other request they early-return after one path check. Markdown conversion happens in an output-buffer callback for requests that ask for Markdown, so normal page loads are untouched. The hosted-scanner lookup runs only when you click it and is cached for an hour.

= How do I see if AI agents are actually using my site? =

The plugin doesn't include a built-in access log. To verify activity, check your server access log for User-Agents like `GPTBot`, `ClaudeBot`, `OAI-SearchBot`, `Google-Extended`, `PerplexityBot`, `CCBot`, `Bytespider`, etc. hitting any of the plugin endpoints.

= Can I disable a feature without deactivating the plugin? =

Yes. Every feature is an independent toggle. Uncheck what you don't want and Save Changes. The corresponding behavior reverts immediately on the next request — no rewrite flush, no cache flush needed (the plugin handles cache invalidation itself).

= Which external services does the plugin connect to, and what is sent? =

Only in three situations you trigger yourself; otherwise the plugin makes no outbound requests at all. Publishing features (manifests, JSON-LD, robots.txt) only answer incoming requests.

* **Readiness scans** (Dashboard, REST or WP-CLI, started by an administrator) — the scanner sends HTTP requests to your own site's URLs, including one unauthenticated MCP `initialize` request to your own MCP endpoint, and the DNS-AID check queries public DNS-over-HTTPS resolvers (`https://cloudflare-dns.com/dns-query`, with `https://dns.google/resolve` as fallback) for `_agents` records of your own domain. Only query names derived from your domain are sent, never visitor data. Policies: https://developers.cloudflare.com/1.1.1.1/privacy/public-dns-resolver/ and https://developers.google.com/speed/public-dns/privacy
* **IndexNow** (api.indexnow.org) — only when the IndexNow toggle is on and a key is configured. On each publish of a public post type the plugin fires one non-blocking POST to `https://api.indexnow.org/indexnow` containing exactly your site host, your IndexNow key (generated by you at Bing Webmaster Tools) and the published URL. No visitor data or content. Terms and policies: https://www.indexnow.org/documentation, https://www.indexnow.org/faq, https://www.bing.com/webmasters/help/webmaster-guidelines-30fba23a, https://www.microsoft.com/en-us/privacy/privacystatement
* **Hosted-scanner cross-check** (ora.ai, is-agentic.com) — only when an administrator clicks "Check hosted scanners". The plugin reads their public, read-only report APIs (`https://ora.ai/api/score/{host}` and `https://is-agentic.com/api/v1/report?url=…`), sending your host name / home URL and nothing else; the answer is cached for an hour. These APIs never start a scan of your site. Policies: https://ora.ai/privacy and https://is-agentic.com/

= What data does the plugin store, and what happens on uninstall? =

Everything lives on your own server — no telemetry, analytics or third-party logging. Option rows store toggle states, the per-bot crawler policy, the Organization details you enter, the llms.txt curation config, the most recent scan result (with evidence snapshots of your own site's responses), a compact score history (at most 30 entries) and the IndexNow key (plain text — keep your database secure). Transients cache the OpenAPI document, the llms.txt bodies and the last hosted-scanner lookup. No request data, IP addresses, User-Agents or visitor information is recorded, and the plugin sets no cookies. Deleting the plugin (not just deactivating) removes every option, the per-post curation meta and all transients; multisite networks have every site cleaned in turn.

== Screenshots ==

1. Dashboard — the 0–100 score ring with grade, Level badge, per-layer bars, "Next level" panel, and impact-ranked check cards with one-click fixes.
2. Evidence timeline — the fetch/parse/conclude audit trail behind a failing check, with the real request and response.
3. Bulk fix sheet — "Fix all safe items", plus copy-paste agent prompts for the fixes that need DNS or server access.
4. llms.txt curation — intro, per-content-type sections, custom Markdown block, and a live preview with entry/byte/token counts.
5. Settings — per-feature toggles, each with a live link to the endpoint it publishes.
6. AI bot rules — per-crawler allow/block policy for 20 AI crawlers, plus Content-Signal preferences.
7. Built-in feature guide — what every feature actually does, in the contextual Help tab.
8. Quick Setup wizard — environment-aware recommendations on first activation.
9. Per-post curation — the "Agent Ready (llms.txt)" panel in the editor: keep an entry out of the agent indexes, or write the one-line summary an AI agent sees instead of the excerpt.

== Changelog ==

= 2.1.0 =
* NEW: Weighted 0–100 agent-readiness score with letter grade (A+ to F) alongside the Level 0–5 ladder. Checks that don't apply or can't be reached drop out of the denominator; emerging protocols add capped bonus points; grade bands match ora.ai so numbers are comparable.
* NEW: Dashboard revamp — score ring with grade, per-layer point bars, score history with a trend since the last full scan, check cards ranked by impact with points at stake, and Failing / One-click fixes / Bonus filters. The fix sheet is sorted by impact.
* NEW: 12 checks modelled on the ora.ai / is-agentic.com catalog and the AgentReady.org standard: sitemap lastmod, identity metadata (canonical, lang, Open Graph), trust pages, Organization schema (sameAs + contact), Markdown alternate link, Markdown frontmatter, agent-friendly 404, content without JavaScript, llms.txt lint (structure + link resolution), user-request agents allowed in robots.txt, an agent resource catalog (ARD / AI Catalog), and a live MCP server probe. 33 checks in total, 17 fixable in one click, 2 with a guided button.
* NEW: MCP server integration — detects the official WordPress MCP Adapter (any plugin registering REST routes under the `mcp` namespace, or the new `ajaco_mcp_endpoint` filter). The server card, agent resource catalog, API catalog linkset, llms.txt and auth.md then advertise the real Streamable HTTP endpoint; the `mcpServer` check sends a JSON-RPC `initialize` and passes on a valid result or an authentication challenge. Without a server the card now says plainly that the REST API is the only machine interface, and the check offers an "Install the MCP Adapter" button (WordPress 6.9+).
* NEW: Organization details — Settings fields for official profile URLs (sameAs), contact email/phone and postal address, merged into the JSON-LD Organization node.
* CHANGED: WebMCP tools rewritten for the current W3C draft — `document.modelContext.registerTool()` with typed input schemas, titles and `readOnlyHint` annotations (search_site_content, list_recent_posts, list_site_pages, get_site_info), with a fallback to the earlier `navigator.modelContext.provideContext()` preview. The WebMCP check recognises both surfaces.
* NEW: Features behind the new fixes — Agent resource catalog at /.well-known/ard.json (+ /.well-known/ai-catalog.json), Sitemap lastmod for core sitemaps, Identity meta tags (canonical + Open Graph, auto-suppressed with an SEO plugin), discovery `<link rel>` tags and Link headers for the sitemap, OpenAPI, llms.txt and catalogs, a `rel="alternate" type="text/markdown"` link on every page, YAML frontmatter on served Markdown, and Markdown 404 pages.
* NEW: Score badge — `[agent_ready_badge]` shortcode, plus an opt-in public `/agent-ready-badge.svg` for embedding elsewhere.
* NEW: Hosted-scanner cross-check — the Dashboard looks up this site's score on ora.ai and is-agentic.com (keyless, read-only, on demand) and shows it next to ours. `GET /wp-json/ajaco/v1/external`.
* NEW: WP-CLI CI gates — `--min-score=<n>` and `--min-level=<n>` on `scan` and `status` exit non-zero below the threshold.
* NEW: Five more managed AI crawlers in the per-bot policy — ClaudeBot, Claude-User, Claude-SearchBot, OAI-SearchBot, Perplexity-User (20 in total). A crawler that has no stored policy inherits your majority decision for its purpose, so upgrading never un-blocks training crawlers you had blocked.
* FIXED: Markdown negotiation returned the original HTML under a text/markdown Content-Type. Core flushes every output buffer at shutdown priority 1, before the plugin's conversion ran; the conversion now happens in the output-buffer callback, so agents receive real Markdown (with X-Markdown-Tokens).
* Settings banner shows the score and grade next to the Level.
* New filter hooks: ajaco_ai_catalog, ajaco_mcp_endpoint.
* Check documentation links now point at the RFCs and standards rather than vendor docs (clears Plugin Check's offloading warnings).
* Tested up to WordPress 7.1.

= 2.0.0 =
* NEW: Built-in agent-readiness scanner — runs the same 21 checks as Cloudflare's isitagentready.com against your live site, with per-check evidence timelines (request/response snapshots) and the Level 0–5 maturity ladder with next-level guidance.
* NEW: Dashboard under a top-level "Agent Ready" menu — segmented category gauge, Level badge, check cards with Fix now / Copy agent prompt / Audit details, and a bulk fix sheet.
* NEW: One-click fixes with verification — 9 failing checks fixable in one click; the fixed check is re-scanned immediately to prove it passes.
* NEW: REST API `ajaco/v1` — POST/GET /scan, POST /scan/check, POST /fix, and a public GET /health (also the API catalog's RFC 9727 status target).
* NEW: WP-CLI — `wp agent-ready scan|status|fix [--all-safe]` with summary, json, and agent (markdown fix report) formats.
* NEW: AI Bot Rules — explicit robots.txt User-agent groups for the 15 AI crawlers readiness scanners check, with per-bot allow/block policy.
* NEW: /auth.md — agent authentication documentation for Application Passwords.
* NEW: /llms-full.txt — full recent content as Markdown alongside /llms.txt; password-protected content excluded from all agent-facing endpoints.
* NEW: Curated llms.txt — a dedicated **Agent Ready → llms.txt** screen with a custom intro, per-post-type sections (custom post types and WooCommerce products included) with heading/count/order controls, a custom Markdown block, and a live preview of the file before you save. The defaults keep the same sections and ordering as the 1.x output (plus a pointer to the new /llms-full.txt).
* NEW: Per-post curation — an "Agent Ready (llms.txt)" panel in the block editor (with a classic-editor fallback): "Include in llms.txt" excludes an entry from both agent files, and "Summary for AI agents" overrides its excerpt. Stored as the `_ajaco_llms_exclude` / `_ajaco_llms_summary` post meta (REST-exposed, so WP-CLI and the REST API can set them).
* NEW: Hosting diagnosis — when a feature is enabled but the server blocks its endpoint (nginx dot-path 403s, static-file 404s), the scan flags it and the Dashboard offers copy-paste nginx/Apache fixes.
* Settings page moved under Agent Ready → Settings and streamlined: the toggle-count score card is replaced by the scan-verified Level banner (one source of truth with the Dashboard), a per-bot AI crawler policy table with presets ("Allow search & user requests, block training"), Content-Signal yes/no selectors, inline dependency warnings (keyless IndexNow, catalog without OpenAPI), and live "View" links to every endpoint the plugin is serving.
* Feature documentation moved into the contextual Help tab, reachable from a "Read more" link on every toggle (which opens the guide at that feature's notes).
* FIXED: a settings page left open across a plugin update could silently switch features off. WordPress saves every registered option in a submitted group and treats one missing from the form as "unchecked" — so a form rendered before a new toggle existed would disable that toggle (and could wipe the per-bot AI crawler policy) on the next Save, without the user touching it. Our forms now declare which fields they rendered, and the save handlers leave everything else alone. The Quick Setup wizard had the same flaw and is fixed the same way.
* New filter hooks: ajaco_ai_bot_list, ajaco_ai_bot_policy, ajaco_auth_md_content, ajaco_llms_full_txt_content, ajaco_llms_post_types, ajaco_llms_exclude_post, ajaco_commerce_signals, ajaco_scan_sslverify.
* Translation template (.pot) regenerated for all 2.0 strings.

= 1.0.1 =
* Agent Skills index now validates against the Agent Skills Discovery RFC v0.2.0: entries use `type: skill-md` and a `digest` field with `sha256:` prefix (previously `type: information-retrieval` and a `sha256` key, which external scanners rejected); `$schema` corrected to the published schema URI.
* MCP Server Card: transport now declares an `endpoint` (SEP-1649 structure) with type `streamable-http`; `protocolVersion` bumped to 2025-06-18.
* Markdown Negotiation: sends `Vary: Accept` on all frontend responses so page caches and CDNs key correctly on the Accept header; buffer unwind at shutdown is now bounded (no hang on non-removable output buffers); `str_contains()` replaced for WP < 5.9 compatibility.
* OpenAPI spec now also served at the conventional `/openapi.json` path (query-var form kept as an alias).
* API Catalog: `service-desc` only advertised when the OpenAPI feature is enabled (no more dead link), and its media type now matches what the endpoint serves.
* Content-Signal directive is now emitted inside an explicit `User-agent: *` group so its scope is deterministic under RFC 9309 group semantics.
* llms.txt and SKILL.md bodies are no longer HTML-entity-escaped — agents now receive `Tom's Blog & Café` instead of `Tom&#039;s Blog &amp; Café`.
* Quick Setup wizard is now armed by a persistent option instead of a 5-minute transient (survives WP-CLI/bulk activations), and can be re-run any time via the "Run the setup wizard again" link on the settings page.
* Tested up to WordPress 7.1.

= 1.0.0 =
* Initial release.
* Ten feature toggles: Markdown Negotiation, Content-Signals, API Catalog (+ Link header), MCP Server Card, Agent Skills Index (+ SKILL.md artifacts), WebMCP Tools, JSON-LD Schema, OpenAPI Spec, IndexNow, llms.txt.
* Score card with 0–100 percentage and per-feature status badges.
* Quick Setup wizard on first activation, environment-aware defaults.
* Auto-suppression of JSON-LD when Yoast / Rank Math / AIOSEO / SEOPress / The SEO Framework / Slim SEO / Squirrly SEO / Schema Pro / SASWP is active.
* Section grouping: Discovery / Presentation / Declarations.
* Inline Read More navigation, Testing section with View output and validator links (Google Rich Results, Swagger Editor).
* Built-in Help tabs (Overview, Features, For Developers, Troubleshooting) with reference sidebar.
* Reset to Defaults button.
* Plugins-row Settings link.
* Multisite-aware path matching for every endpoint.
* Transient caching: 1 day for OpenAPI, 1 hour for llms.txt, with smart invalidation.
* Filter hooks for extending or overriding plugin behavior.
* Translation-ready (.pot file shipped) and accessibility-ready (screen-reader h1, dynamic SVG aria-label, aria-live success announcements).

== Upgrade Notice ==

= 2.1.0 =
Adds a 0–100 score and grade, 12 new checks with one-click or guided fixes, MCP Adapter integration, Organization details for JSON-LD, a revamped Dashboard and a score badge. Fixes Markdown negotiation serving HTML. Features stay opt-in; settings and bot policies are preserved.

= 2.0.0 =
Major update: adds an agent-readiness scanner (21 checks, Level 0–5, evidence trails), one-click fixes, a curated llms.txt editor, REST API and WP-CLI. All features stay opt-in and your existing settings are preserved. The menu moves to a new top-level Agent Ready.

= 1.0.1 =
Spec-compliance fixes: Agent Skills index and MCP Server Card now validate against external agent-readiness scanners; markdown negotiation is cache-safe (Vary: Accept); OpenAPI at /openapi.json.

= 1.0.0 =
Initial release.

