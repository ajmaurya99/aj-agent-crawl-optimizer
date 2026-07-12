=== AJ Agent Crawl Optimizer ===
Contributors:      ajmaurya
Tags:              ai, mcp, openapi, structured-data, llms-txt
Requires at least: 5.5
Tested up to:      7.0
Requires PHP:      7.4
Stable tag:        1.0.1
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Agent-readiness scanner and fixer: audit 21 AI-agent standards, fix failures in one click, and hand-curate the llms.txt agents actually read.

== Description ==

**AJ Agent Crawl Optimizer** makes your site legible to AI agents — and, since 2.0, *proves* it. The built-in **readiness scanner** runs the same 21 checks as Cloudflare's isitagentready.com against your live site, grades you on the **Level 0–5 agent-readiness ladder** (Not Ready → Basic Web Presence → Bot-Aware → Agent-Readable → Agent-Integrated → Agent-Native), and shows the full evidence trail — every request and response — behind every verdict. Failing checks get a **Fix now** button that enables the right feature and re-scans that single check to prove it went green; anything the plugin can't fix in WordPress (DNS records, server config) gets a copy-paste prompt for your coding agent.

**Release status:** the scanner described below is part of **2.0, currently in development on the `v2-dev` branch** (github.com/ajmaurya99/aj-agent-crawl-optimizer). The current stable release is the 1.0.x publishing toolkit described from "Discovery" onward.

= The scan → fix → verify loop (new in 2.0) =

* **Dashboard** (Agent Ready → Dashboard) — segmented category gauge, Level badge, and a "Next level" panel listing exactly which checks unlock the next level.
* **21 checks across 5 categories** — Discoverability (robots.txt, sitemap, Link headers, DNS-AID), Content Accessibility (Markdown negotiation), Bot Access Control (AI bot rules, Content Signals, Web Bot Auth), API/Auth/MCP Discovery (API catalog, OAuth discovery, OAuth Protected Resource, auth.md, MCP server card, A2A agent card, Agent Skills, WebMCP), and Commerce (x402, MPP, UCP, ACP, AP2 — informational, never scored).
* **Evidence timelines** — fetch/parse/conclude steps with request/response snapshots, so you can audit *why* a check passed or failed (and catch page caches or server rules silently breaking your endpoints).
* **One-click fixes with verification** — 9 checks are fixable in one click; the plugin re-scans just that check afterwards. A bulk "Fix all safe items" sheet handles the rest.
* **Hosting diagnosis** — the plugin writes no files (every endpoint is served by WordPress), but some hosts block the requests before WordPress runs: nginx dot-path rules 403 `/.well-known/*`, and static-file rules 404 `/llms.txt`. When a feature is enabled and the server blocks it, the Dashboard says so and hands you copy-paste nginx and Apache fixes.
* **REST API** — `POST /wp-json/ajaco/v1/scan`, `POST /wp-json/ajaco/v1/scan/check`, `POST /wp-json/ajaco/v1/fix`, `POST /wp-json/ajaco/v1/llms/preview`, and a public `GET /wp-json/ajaco/v1/health`.
* **WP-CLI** — `wp agent-ready scan --format=summary|json|agent`, `wp agent-ready fix <check>|--all-safe`, `wp agent-ready status` for agency fleets and AI agents operating over SSH.

Each publishing capability remains a separate toggle under **Agent Ready → Settings** and ships **opt-in** (everything starts off). On first activation, a **Quick Setup wizard** suggests sensible defaults based on your environment (for example, it skips JSON-LD when an SEO plugin is detected so you don't get duplicate structured data) — re-runnable any time.

= Curated llms.txt — you decide what agents read =

Most plugins generate `llms.txt` from fixed rules and hand you the result. This one hands you the controls.

* **A dedicated editor** (Agent Ready → llms.txt) — write the intro that tells an agent what your site is *for*, choose which content types appear (custom post types and WooCommerce products are detected automatically), and set each section's heading, item count and order. Add your own Markdown block above or below.
* **Live preview** — see exactly what `/llms.txt` will serve, with entry, byte and token counts, *before* you save.
* **Per-post control** — every post and page gets an "Agent Ready (llms.txt)" panel in the editor: keep an entry out of the agent indexes entirely, or write a **Summary for AI agents** that overrides the excerpt. An excerpt is written for a human skimming; that line is written for a model deciding whether to fetch the page — and it is the single highest-leverage field in the whole plugin.
* **`/llms-full.txt`** serves the full content of the same curated entries. Password-protected content is always excluded from both.

Defaults reproduce the previous automatic output exactly, so upgrading changes nothing until you decide to edit something.

= Discovery — help agents find what your site offers =

* **API Catalog** (RFC 9727) — `/.well-known/api-catalog` linkset advertising your REST API, plus a `Link: rel="api-catalog"` header on every response so agents discover it from any URL.
* **MCP Server Card** (SEP-1649 draft) — `/.well-known/mcp/server-card.json` describing the site to MCP-aware agents.
* **Agent Skills Index** (RFC v0.2.0) — `/.well-known/agent-skills/index.json` listing six skills (content-query, posts-read, pages-read, media-library, categories, tags) plus per-skill `SKILL.md` artifacts with verifiable sha256 digests.
* **llms.txt + llms-full.txt** (per llmstxt.org) — `/llms.txt` is a **curated** LLM-readable index: you write the intro, choose which post types appear (custom post types and WooCommerce products included), set each section's heading, count and order, and add your own Markdown block. `/llms-full.txt` serves the full content of those same entries as Markdown. Password-protected content is always excluded.
* **Per-post curation** — an "Agent Ready (llms.txt)" panel in the editor (block editor and classic) lets an author keep a page out of the agent indexes entirely, or write a **Summary for AI agents** that overrides the excerpt — a line aimed at a model deciding whether to fetch the page, not a human skimming.
* **IndexNow** — non-blocking ping to Bing and Yandex on every post publish so search engines re-crawl within minutes.

= Presentation — format content for agents =

* **Markdown Negotiation** — when a request includes `Accept: text/markdown`, the page is served as clean Markdown with `X-Markdown-Tokens` for context budgeting. Browsers (which send `text/html`) are completely unaffected.
* **JSON-LD Schema** — Schema.org structured data: WebSite, Organization, Article, BreadcrumbList, and auto-detected FAQPage. Logo resolved from your theme's custom logo or site icon.
* **OpenAPI 3.0.3** — `/openapi.json` (and legacy `/?format=openapi`) returns a complete spec generated dynamically from `rest_get_server()`, including plugin-registered REST routes.
* **WebMCP Tools** — registers four tools (search, posts, pages, site info) via `navigator.modelContext.provideContext()` for browsers that support the W3C WebMCP draft.

= Declarations =

* **AI Bot Rules** (new in 2.0) — explicit robots.txt User-agent groups for the 15 AI crawlers readiness scanners check for (GPTBot, ChatGPT-User, Claude-Web, PerplexityBot, CCBot, Google-Extended, Bytespider, …), each allowed or blocked per a filterable per-bot policy. Groups are self-contained (they replicate core's wp-admin protections) and honor "Discourage search engines".
* **Content-Signals** — appends a `Content-Signal: ai-train=no, search=yes, ai-input=no` directive to robots.txt (inside an explicit `User-agent: *` group) declaring your AI-usage preferences per contentsignals.org. Composes with Yoast/Rank Math/AIOSEO — their additions are preserved, our directive lands last.
* **auth.md** (new in 2.0) — publishes `/auth.md` honestly documenting how agents authenticate to your REST API via Application Passwords: how a human creates one, Basic-auth usage, scope, and revocation. No fictional OAuth endpoints.

= Why use it =

* **No conflicts with your SEO plugin.** JSON-LD auto-suppresses when Yoast SEO, Rank Math, All in One SEO, SEOPress, The SEO Framework, Slim SEO, Squirrly SEO, Schema Pro, or SASWP is active.
* **Multisite-aware.** Every endpoint also resolves at `/{subsite}/...` paths automatically.
* **Cached.** OpenAPI is cached for a day, llms.txt for an hour, with proper invalidation on plugin activation, theme switch, post changes, and setting toggles.
* **Performance-conscious.** Markdown handler runs at `PHP_INT_MAX` priority so it doesn't break object-cache flushes or Query Monitor. IndexNow pings are non-blocking.
* **Extensible.** Seventeen filter hooks let you customize skills, schemas, bot policies, llms.txt curation, scan behavior, and content. See "For Developers" below.
* **Accessible.** The Dashboard gauge carries a dynamic `aria-label`, copy buttons announce success via `aria-live`, and decorative icons are hidden from screen readers.

= For developers =

The plugin exposes seventeen filter hooks for extension. Examples:

`add_filter( 'ajaco_required_capability', function () { return 'edit_posts'; } );`
Delegate plugin access to a non-admin role.

`add_filter( 'ajaco_skill_definitions', function ( $skills ) { return $skills + [ 'products' => [ 'type' => 'information-retrieval', 'description' => 'WooCommerce products', 'endpoint' => rest_url( 'wc/v3/products' ) ] ]; } );`
Register custom skills that ship in the Agent Skills Index and are served as SKILL.md artifacts with verifiable sha256 digests.

`add_filter( 'ajaco_content_signal', function () { return 'ai-train=yes, search=yes, ai-input=yes'; } );`
Customize the Content-Signal directive (e.g. permit AI training).

Other hooks: `ajaco_api_catalog_linkset`, `ajaco_mcp_server_card`, `ajaco_json_ld_graph`, `ajaco_openapi_spec`, `ajaco_llms_txt_content`, `ajaco_llms_full_txt_content`, `ajaco_llms_post_types`, `ajaco_llms_exclude_post`, `ajaco_auth_md_content`, `ajaco_ai_bot_list`, `ajaco_ai_bot_policy`, `ajaco_commerce_signals`, `ajaco_scan_sslverify`, `ajaco_active_seo_plugin`. The Help tab (top right of the settings screen) lists all of them with descriptions.

Per-post curation is stored in two post meta fields you can read or set programmatically: `_ajaco_llms_exclude` (boolean) and `_ajaco_llms_summary` (string, 300 chars). Both are registered with `show_in_rest`, so they round-trip through the REST API and WP-CLI (`wp post meta update 42 _ajaco_llms_summary "..."`).

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

= What's the difference between the Dashboard and Settings screens? =

**Dashboard** is the verifier: it scans your live site over real HTTP, shows your Level 0–5 with evidence for every check, and fixes failures with one click. **Settings** is the switchboard: the per-feature toggles, wizard, and testing tools. A toggle can be ON in Settings while the Dashboard still shows a fail — that gap usually means a page cache or server rule is intercepting the endpoint, and the evidence view shows exactly what happened.

= How is the readiness Level calculated? =

The scanner runs the same 21 checks (and the same Level 0–5 ladder) as Cloudflare's isitagentready.com: Level 1 needs 2 of robots.txt/sitemap/Link headers; Level 2 adds AI bot rules and Content Signals; Level 3 adds Markdown negotiation; Level 4 adds one of MCP card/Agent Skills/API catalog/A2A card; Level 5 adds two of Web Bot Auth, all four integrations, and auth metadata. Nothing counts unless the scan verifies it — enabling a toggle earns nothing until the endpoint actually responds. Commerce checks are informational and never scored.

= My host blocks /.well-known/ paths or doesn't allow creating files — will the plugin still work? =

The plugin never creates files: every endpoint (/.well-known/*, /llms.txt, /openapi.json, /auth.md) is served virtually by WordPress, so hosts that forbid file creation are fully supported. What can break things is the web server intercepting requests before WordPress runs — typically an nginx dot-path deny rule returning 403 on /.well-known/*, or a static-file rule returning 404 for /llms.txt-style paths. The readiness scan detects exactly this (feature enabled + server blocking) and the Dashboard shows a hosting notice with copy-paste nginx and Apache fixes you can apply yourself or forward to your hosting support.

= Can I control what goes into llms.txt? =

Yes — it is curated, not auto-generated from fixed rules. Under **Agent Ready → llms.txt** you write the intro, pick which post types appear (custom post types and WooCommerce products show up automatically), and set each section's heading, item count and order, plus your own Markdown block. A live preview shows the file as you edit, before you save.

Individual entries are steered from the editor: the "Agent Ready (llms.txt)" panel has an **Include in llms.txt** toggle (excluded pages stay out of both `/llms.txt` and `/llms-full.txt`) and a **Summary for AI agents** field that overrides the excerpt for that entry — write it for a model deciding whether to fetch the page. Defaults reproduce the previous automatic output, so upgrading changes nothing until you edit something.

= Can I run scans from the command line or scripts? =

Yes: `wp agent-ready scan` (add `--format=json` for machines or `--format=agent` for a markdown fix report), `wp agent-ready fix <check>` or `--all-safe`, and `wp agent-ready status`. The same operations are available over REST at `/wp-json/ajaco/v1/scan` and `/wp-json/ajaco/v1/fix` (admin capability required; `/wp-json/ajaco/v1/health` is public).

= Will this conflict with my SEO plugin? =

No. JSON-LD output auto-suppresses when Yoast SEO, Rank Math, All in One SEO, SEOPress, The SEO Framework, Slim SEO, Squirrly SEO, Schema Pro, or SASWP is active. The settings page shows a clear notice when this happens. Our `robots_txt` filter runs at `PHP_INT_MAX` priority, so any rules your SEO plugin adds (sitemap URLs, custom Disallow, etc.) are preserved — our `Content-Signal` line is appended last.

= Does this change anything for my regular site visitors? =

No. Browsers send `Accept: text/html` and get the normal HTML response. The `.well-known/...`, `/llms.txt`, and `?format=openapi` endpoints are paths a normal user never visits. The only thing added to the page HTML is a single JSON-LD `<script>` block (when no SEO plugin is active) and a small WebMCP script tag (which no-ops in browsers without the experimental flag).

= My site is multisite. Does it work? =

Yes. Every endpoint resolves at both the root and per-subsite paths automatically — `/llms.txt`, `/blog/llms.txt`, `/.well-known/api-catalog`, `/blog/.well-known/api-catalog`, etc. Each subsite has its own settings.

= How do I delegate plugin access to a non-admin role? =

Use the `ajaco_required_capability` filter. The plugin defaults to `manage_options` (admin-only); change it to any capability of your choice, e.g. `edit_pages` for editors:

`add_filter( 'ajaco_required_capability', function () { return 'edit_pages'; } );`

= I enabled IndexNow but I'm not seeing pings to Bing or Yandex. =

Check three things: (1) the IndexNow API Key field is filled in, (2) you're publishing a post of a public post type — revisions and autosaves are skipped, (3) you're on production. Pings are non-blocking, so failures are silent. Tail your server log for outbound requests to `api.indexnow.org`.

= Can I add custom skills, schemas, or sections to llms.txt? =

Yes. Use the relevant filter:

* `ajaco_skill_definitions` — register custom Agent Skills.
* `ajaco_json_ld_graph` — add custom Schema.org entries (Product, Recipe, Event, etc.).
* `ajaco_llms_txt_content` — append sections to or replace the llms.txt body.
* `ajaco_openapi_spec` — add `securitySchemes`, custom tags, additional servers.
* `ajaco_api_catalog_linkset` — add anchors or rels (e.g. for a GraphQL endpoint).
* `ajaco_mcp_server_card` — override transport / capabilities for a real MCP implementation.

The Help tab's "For Developers" section on the settings page lists every hook with a description.

= How do I undo everything and start over? =

Use the **Reset to Defaults** button under the Save Changes button on the settings page. It clears every plugin option, wipes cached endpoint outputs, and shows a confirmation dialog before wiping anything.

= Performance impact? =

Minimal. The OpenAPI document is cached for a day in a transient and only regenerates on plugin activation/deactivation or theme switch. The llms.txt body is cached for an hour and invalidates on post edits, site name/description changes, and setting toggles. Endpoint handlers run on `init` only when the request path matches; on every other request they early-return after one regex check. The Markdown handler runs at `PHP_INT_MAX` priority on shutdown so it never breaks object-cache writes or other shutdown hooks.

= How do I see if AI agents are actually using my site? =

The plugin doesn't include a built-in access log. To verify activity, check your server access log for User-Agents like `GPTBot`, `ClaudeBot`, `OAI-SearchBot`, `Google-Extended`, `PerplexityBot`, `CCBot`, `Bytespider`, etc. hitting any of the plugin endpoints.

= Can I disable a feature without deactivating the plugin? =

Yes. Every feature is an independent toggle. Uncheck what you don't want and Save Changes. The corresponding behavior reverts immediately on the next request — no rewrite flush, no cache flush needed (the plugin handles cache invalidation itself).

== Screenshots ==

1. Dashboard — the Level 0–5 badge, category gauge, "Next level" panel, and check cards with one-click fixes.
2. Evidence timeline — the fetch/parse/conclude audit trail behind a failing check, with the real request and response.
3. Bulk fix sheet — "Fix all safe items", plus copy-paste agent prompts for the fixes that need DNS or server access.
4. llms.txt curation — intro, per-content-type sections, custom Markdown block, and a live preview with entry/byte/token counts.
5. Settings — per-feature toggles, each with a live link to the endpoint it publishes.
6. AI bot rules — per-crawler allow/block policy for the 15 AI crawlers scanners check, plus Content-Signal preferences.
7. Built-in feature guide — what every feature actually does, in the contextual Help tab.
8. Quick Setup wizard — environment-aware recommendations on first activation.
9. Per-post curation — the "Agent Ready (llms.txt)" panel in the editor: keep an entry out of the agent indexes, or write the one-line summary an AI agent sees instead of the excerpt.

== Changelog ==

= 2.0.0-alpha (in development on the v2-dev branch; not yet released) =
* NEW: Built-in agent-readiness scanner — runs the same 21 checks as Cloudflare's isitagentready.com against your live site, with per-check evidence timelines (request/response snapshots) and the Level 0–5 maturity ladder with next-level guidance.
* NEW: Dashboard under a top-level "Agent Ready" menu — segmented category gauge, Level badge, check cards with Fix now / Copy agent prompt / Audit details, and a bulk fix sheet.
* NEW: One-click fixes with verification — 9 failing checks fixable in one click; the fixed check is re-scanned immediately to prove it passes.
* NEW: REST API `ajaco/v1` — POST/GET /scan, POST /scan/check, POST /fix, and a public GET /health (also the API catalog's RFC 9727 status target).
* NEW: WP-CLI — `wp agent-ready scan|status|fix [--all-safe]` with summary, json, and agent (markdown fix report) formats.
* NEW: AI Bot Rules — explicit robots.txt User-agent groups for the 15 AI crawlers readiness scanners check, with per-bot allow/block policy.
* NEW: /auth.md — agent authentication documentation for Application Passwords.
* NEW: /llms-full.txt — full recent content as Markdown alongside /llms.txt; password-protected content excluded from all agent-facing endpoints.
* NEW: Curated llms.txt — a dedicated **Agent Ready → llms.txt** screen with a custom intro, per-post-type sections (custom post types and WooCommerce products included) with heading/count/order controls, a custom Markdown block, and a live preview of the file before you save. Defaults reproduce the previous automatic output exactly.
* NEW: Per-post curation — an "Agent Ready (llms.txt)" panel in the block editor (with a classic-editor fallback): "Include in llms.txt" excludes an entry from both agent files, and "Summary for AI agents" overrides its excerpt. Stored as the `_ajaco_llms_exclude` / `_ajaco_llms_summary` post meta (REST-exposed, so WP-CLI and the REST API can set them).
* NEW: Hosting diagnosis — when a feature is enabled but the server blocks its endpoint (nginx dot-path 403s, static-file 404s), the scan flags it and the Dashboard offers copy-paste nginx/Apache fixes.
* Settings page moved under Agent Ready → Settings and streamlined: the toggle-count score card is replaced by the scan-verified Level banner (one source of truth with the Dashboard), a per-bot AI crawler policy table with presets ("Allow search & user requests, block training"), Content-Signal yes/no selectors, inline dependency warnings (keyless IndexNow, catalog without OpenAPI), and live "View" links to every endpoint the plugin is serving.
* Feature documentation moved into the contextual Help tab, reachable from a "Read more" link on every toggle (which opens the guide at that feature's notes).
* New filter hooks: ajaco_ai_bot_list, ajaco_ai_bot_policy, ajaco_auth_md_content, ajaco_llms_full_txt_content, ajaco_llms_post_types, ajaco_llms_exclude_post, ajaco_commerce_signals, ajaco_scan_sslverify.

= 1.0.1 =
* Agent Skills index now validates against the Agent Skills Discovery RFC v0.2.0: entries use `type: skill-md` and a `digest` field with `sha256:` prefix (previously `type: information-retrieval` and a `sha256` key, which external scanners rejected); `$schema` corrected to the published schema URI.
* MCP Server Card: transport now declares an `endpoint` (SEP-1649 structure) with type `streamable-http`; `protocolVersion` bumped to 2025-06-18.
* Markdown Negotiation: sends `Vary: Accept` on all frontend responses so page caches and CDNs key correctly on the Accept header; buffer unwind at shutdown is now bounded (no hang on non-removable output buffers); `str_contains()` replaced for WP < 5.9 compatibility.
* OpenAPI spec now also served at the conventional `/openapi.json` path (query-var form kept as an alias).
* API Catalog: `service-desc` only advertised when the OpenAPI feature is enabled (no more dead link), and its media type now matches what the endpoint serves.
* Content-Signal directive is now emitted inside an explicit `User-agent: *` group so its scope is deterministic under RFC 9309 group semantics.
* llms.txt and SKILL.md bodies are no longer HTML-entity-escaped — agents now receive `Tom's Blog & Café` instead of `Tom&#039;s Blog &amp; Café`.
* Quick Setup wizard is now armed by a persistent option instead of a 5-minute transient (survives WP-CLI/bulk activations), and can be re-run any time via the "Run the setup wizard again" link on the settings page.
* Tested up to WordPress 7.0.

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

= 1.0.1 =
Spec-compliance fixes: Agent Skills index and MCP Server Card now validate against external agent-readiness scanners; markdown negotiation is cache-safe (Vary: Accept); OpenAPI at /openapi.json.

= 1.0.0 =
Initial release.

== External services ==

This plugin connects to external services only in two explicitly user-triggered situations: the opt-in IndexNow feature, and DNS lookups made while an administrator runs a readiness scan.

= DNS-over-HTTPS resolvers (cloudflare-dns.com, dns.google) — during scans only =

What they are and what they're used for: the readiness scanner's DNS-AID check queries public DNS-over-HTTPS resolvers (Cloudflare's `https://cloudflare-dns.com/dns-query`, with Google's `https://dns.google/resolve` as fallback) to look up `_agents` SVCB/HTTPS/TXT records for your own domain.

When data is sent: only while a scan that an administrator started (Dashboard, REST, or WP-CLI) is running. The scanner also sends ordinary HTTP requests to your own site's URLs to verify your endpoints.

What data is sent: only DNS query names derived from your own domain (e.g. `_index._agents.example.com`). No visitor information, IP addresses of visitors, or content is sent. Cloudflare's and Google's resolver privacy policies apply:

* Cloudflare 1.1.1.1 privacy: https://developers.cloudflare.com/1.1.1.1/privacy/public-dns-resolver/
* Google Public DNS privacy: https://developers.google.com/speed/public-dns/privacy

= IndexNow (api.indexnow.org) =

What it is and what it's used for: IndexNow is an open protocol (originally from Microsoft Bing and Yandex) that lets sites notify search engines the moment a URL is published or updated, so search engines can re-crawl within minutes instead of days.

When data is sent: only when the **IndexNow** feature toggle is turned on AND an IndexNow API Key is configured on the settings page. In that case, every time a post of a public post type transitions to the `publish` status, the plugin fires a single non-blocking HTTPS POST to `https://api.indexnow.org/indexnow`.

What data is sent: the request body is a JSON document containing exactly three fields — your site's host (e.g. `example.com`), your IndexNow API key (which you generated yourself at Bing Webmaster Tools), and the permalink URL of the post that was just published. No visitor information, IP addresses, user-agents, or post content is sent.

If the IndexNow feature toggle is off (the default) and no scan is running, the plugin makes no outbound network requests of any kind.

This service is provided by Microsoft (Bing) and the IndexNow project. Their terms and privacy policies apply:

* IndexNow protocol documentation: https://www.indexnow.org/documentation
* IndexNow FAQ and terms: https://www.indexnow.org/faq
* Microsoft Bing Webmaster Guidelines : https://www.bing.com/webmasters/help/webmaster-guidelines-30fba23a
* Microsoft Privacy Statement: https://www.microsoft.com/en-us/privacy/privacystatement

== Privacy ==

AJ Agent Crawl Optimizer stores data **only on your own server** — there is no telemetry, no analytics, and no third-party logging. Specifically:

= Local data =

* Plugin **option rows** in the WordPress options table store toggle states, the per-bot AI crawler policy, the most recent scan result (including its evidence snapshots of your own site's responses), and the IndexNow API Key (stored as plain text — keep your database secure).
* **Transients** cache the OpenAPI document and the llms.txt / llms-full.txt bodies. These are deleted on plugin uninstall.
* No request data, IP addresses, User-Agents, or visitor information is recorded by the plugin.

= Outbound network calls =

The plugin makes outbound HTTP requests only in these cases:

* **IndexNow** — when the IndexNow toggle is on and a key is configured, the plugin sends a non-blocking POST to `https://api.indexnow.org/indexnow` on every post publish. The payload contains the site host, the IndexNow key, and the URL of the published post (no visitor data).
* **Readiness scans** — while an administrator-initiated scan is running, the scanner sends HTTP requests to your own site's URLs (to verify endpoints exactly as an agent would see them) and DNS-over-HTTPS queries for your own domain to Cloudflare/Google resolvers (DNS-AID check). Scans never run automatically.

Outside those two cases the plugin makes zero outbound network requests. All publishing features (manifests, JSON-LD, robots.txt) only respond to incoming HTTP requests; they never call out.

= Cookies =

The plugin does not set any cookies.

= Uninstall =

When the plugin is **deleted** (not just deactivated), all plugin options, the IndexNow key, and all transients are removed from the database. Multisite networks have every site cleaned in turn.
