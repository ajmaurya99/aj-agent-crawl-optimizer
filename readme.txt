=== Agent-Ready ===
Contributors:      ajmaurya
Tags:              ai, mcp, openapi, structured-data, llms-txt
Requires at least: 5.5
Tested up to:      6.9
Requires PHP:      7.4
Stable tag:        1.0.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Make your WordPress site legible to AI agents — Markdown negotiation, JSON-LD, OpenAPI, MCP server card, llms.txt, IndexNow, and more.

== Description ==

**Agent-Ready** is a thin compatibility layer that teaches your site to speak the languages AI agents already use to discover and consume web content. It publishes machine-readable manifests at well-known URLs, serves clean Markdown when an AI requests it, and declares your AI-usage preferences — all without changing anything for human visitors.

Each capability is a separate toggle under **Settings → Agent-Ready** and ships **opt-in** (everything starts off). On first activation, a one-time **Quick Setup wizard** suggests sensible defaults based on your environment (for example, it skips JSON-LD when an SEO plugin is detected so you don't get duplicate structured data).

= Discovery — help agents find what your site offers =

* **API Catalog** (RFC 9727) — `/.well-known/api-catalog` linkset advertising your REST API, plus a `Link: rel="api-catalog"` header on every response so agents discover it from any URL.
* **MCP Server Card** (SEP-1649 draft) — `/.well-known/mcp/server-card.json` describing the site to MCP-aware agents.
* **Agent Skills Index** (RFC v0.2.0) — `/.well-known/agent-skills/index.json` listing six skills (search, posts, pages, media, categories, tags) plus per-skill `SKILL.md` artifacts with verifiable sha256 digests.
* **llms.txt** (per llmstxt.org) — `/llms.txt` curated, LLM-readable index of your top pages and recent posts, with a Discovery section auto-linking every other agent-ready endpoint.
* **IndexNow** — non-blocking ping to Bing and Yandex on every post publish so search engines re-crawl within minutes.

= Presentation — format content for agents =

* **Markdown Negotiation** — when a request includes `Accept: text/markdown`, the page is served as clean Markdown with `X-Markdown-Tokens` for context budgeting. Browsers (which send `text/html`) are completely unaffected.
* **JSON-LD Schema** — Schema.org structured data: WebSite, Organization, Article, BreadcrumbList, and auto-detected FAQPage. Logo resolved from your theme's custom logo or site icon.
* **OpenAPI 3.0.3** — `/?format=openapi` returns a complete spec generated dynamically from `rest_get_server()`, including plugin-registered REST routes.
* **WebMCP Tools** — registers four tools (search, posts, pages, site info) via `navigator.modelContext.provideContext()` for browsers that support the W3C WebMCP draft.

= Declarations =

* **Content-Signals** — appends a `Content-Signal: ai-train=no, search=yes, ai-input=no` directive to robots.txt declaring your AI-usage preferences (per contentsignals.org). Composes with Yoast/Rank Math/AIOSEO — their additions are preserved, our line lands at the very end.

= Why use it =

* **No conflicts with your SEO plugin.** JSON-LD auto-suppresses when Yoast SEO, Rank Math, All in One SEO, SEOPress, The SEO Framework, Slim SEO, Squirrly SEO, Schema Pro, or SASWP is active.
* **Multisite-aware.** Every endpoint also resolves at `/{subsite}/...` paths automatically.
* **Cached.** OpenAPI is cached for a day, llms.txt for an hour, with proper invalidation on plugin activation, theme switch, post changes, and setting toggles.
* **Performance-conscious.** Markdown handler runs at `PHP_INT_MAX` priority so it doesn't break object-cache flushes or Query Monitor. IndexNow pings are non-blocking.
* **Extensible.** Nine filter hooks let you customize skills, schemas, capabilities, and content. See "For Developers" below.
* **Accessible.** Score-card SVG has dynamic `aria-label`, copy buttons announce success via `aria-live`, decorative arrows hidden from screen readers via CSS pseudo-elements.

= For developers =

The plugin exposes nine filter hooks for extension. Examples:

`add_filter( 'agent_ready_required_capability', function () { return 'edit_posts'; } );`
Delegate plugin access to a non-admin role.

`add_filter( 'agent_ready_skill_definitions', function ( $skills ) { return $skills + [ 'products' => [ 'type' => 'information-retrieval', 'description' => 'WooCommerce products', 'endpoint' => rest_url( 'wc/v3/products' ) ] ]; } );`
Register custom skills that ship in the Agent Skills Index and are served as SKILL.md artifacts with verifiable sha256 digests.

`add_filter( 'agent_ready_content_signal', function () { return 'ai-train=yes, search=yes, ai-input=yes'; } );`
Customize the Content-Signal directive (e.g. permit AI training).

Other hooks: `agent_ready_api_catalog_linkset`, `agent_ready_mcp_server_card`, `agent_ready_json_ld_graph`, `agent_ready_openapi_spec`, `agent_ready_llms_txt_content`, `agent_ready_active_seo_plugin`. The settings page's Help tab → For Developers lists all of them with descriptions.

== Installation ==

= From the WordPress plugin directory =

1. In your WP admin, go to **Plugins → Add New** and search for "Agent-Ready".
2. Click **Install Now**, then **Activate**.
3. The Quick Setup wizard runs automatically on first activation — review the recommended toggles and click **Apply**.
4. Adjust any toggle later from **Settings → Agent-Ready**.

= Manual install =

1. Download the plugin zip.
2. Upload the `agent-ready` folder to `/wp-content/plugins/`.
3. Activate **Agent-Ready** from the **Plugins** screen.
4. Visit **Settings → Agent-Ready** and run the wizard or configure manually.

= IndexNow setup (optional) =

If you want Bing and Yandex to re-crawl your content within minutes of publish:

1. Generate a key at https://www.bing.com/webmasters/indexnow.
2. Paste it into the **IndexNow API Key** field on the settings page.
3. Enable the IndexNow toggle. The plugin hosts the key file at `/{key}.txt` for ownership verification automatically.

== Frequently Asked Questions ==

= Will this conflict with my SEO plugin? =

No. JSON-LD output auto-suppresses when Yoast SEO, Rank Math, All in One SEO, SEOPress, The SEO Framework, Slim SEO, Squirrly SEO, Schema Pro, or SASWP is active. The settings page shows a clear notice when this happens. Our `robots_txt` filter runs at `PHP_INT_MAX` priority, so any rules your SEO plugin adds (sitemap URLs, custom Disallow, etc.) are preserved — our `Content-Signal` line is appended last.

= Does this change anything for my regular site visitors? =

No. Browsers send `Accept: text/html` and get the normal HTML response. The `.well-known/...`, `/llms.txt`, and `?format=openapi` endpoints are paths a normal user never visits. The only thing added to the page HTML is a single JSON-LD `<script>` block (when no SEO plugin is active) and a small WebMCP script tag (which no-ops in browsers without the experimental flag).

= My site is multisite. Does it work? =

Yes. Every endpoint resolves at both the root and per-subsite paths automatically — `/llms.txt`, `/travelwithpurpose/llms.txt`, `/.well-known/api-catalog`, `/travelwithpurpose/.well-known/api-catalog`, etc. Each subsite has its own settings.

= How do I delegate plugin access to a non-admin role? =

Use the `agent_ready_required_capability` filter. The plugin defaults to `manage_options` (admin-only); change it to any capability of your choice, e.g. `edit_pages` for editors:

`add_filter( 'agent_ready_required_capability', function () { return 'edit_pages'; } );`

= I enabled IndexNow but I'm not seeing pings to Bing or Yandex. =

Check three things: (1) the IndexNow API Key field is filled in, (2) you're publishing a post of a public post type — revisions and autosaves are skipped, (3) you're on production. Pings are non-blocking, so failures are silent. Tail your server log for outbound requests to `api.indexnow.org`.

= Can I add custom skills, schemas, or sections to llms.txt? =

Yes. Use the relevant filter:

* `agent_ready_skill_definitions` — register custom Agent Skills.
* `agent_ready_json_ld_graph` — add custom Schema.org entries (Product, Recipe, Event, etc.).
* `agent_ready_llms_txt_content` — append sections to or replace the llms.txt body.
* `agent_ready_openapi_spec` — add `securitySchemes`, custom tags, additional servers.
* `agent_ready_api_catalog_linkset` — add anchors or rels (e.g. for a GraphQL endpoint).
* `agent_ready_mcp_server_card` — override transport / capabilities for a real MCP implementation.

The Help tab's "For Developers" section on the settings page lists every hook.

= How do I undo everything and start over? =

Use the **Reset to Defaults** button under the Save Changes button on the settings page. It clears every plugin option, wipes cached endpoint outputs, and shows a confirmation dialog before wiping anything.

= Performance impact? =

Minimal. The OpenAPI document is cached for a day in a transient and only regenerates on plugin activation/deactivation or theme switch. The llms.txt body is cached for an hour and invalidates on post edits, site name/description changes, and setting toggles. Endpoint handlers run on `init` only when the request path matches; on every other request they early-return after one regex check. The Markdown handler runs at `PHP_INT_MAX` priority on shutdown so it never breaks object-cache writes or other shutdown hooks.

= How do I see if AI agents are actually using my site? =

The plugin doesn't include a built-in access log. To verify activity, check your server access log for User-Agents like `GPTBot`, `ClaudeBot`, `OAI-SearchBot`, `Google-Extended`, `PerplexityBot`, `CCBot`, `Bytespider`, etc. hitting any of the agent-ready endpoints.

= Can I disable a feature without deactivating the plugin? =

Yes. Every feature is an independent toggle. Uncheck what you don't want and Save Changes. The corresponding behavior reverts immediately on the next request — no rewrite flush, no cache flush needed (the plugin handles cache invalidation itself).

== Screenshots ==

1. Settings page — score card, three grouped sections (Discovery / Presentation / Declarations), Read More links to inline details.
2. Quick Setup wizard on first activation — environment-aware recommendations.
3. Testing section — one-click curl commands, View output and Open in [validator] links per endpoint.
4. Help tab → For Developers — built-in API reference for the nine filter hooks.

== Changelog ==

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
* Activation notice + plugins-row Settings link.
* Multisite-aware path matching for every endpoint.
* Transient caching: 1 day for OpenAPI, 1 hour for llms.txt, with smart invalidation.
* Nine filter hooks for extending or overriding plugin behavior.
* Translation-ready (.pot file shipped) and accessibility-ready (screen-reader h1, dynamic SVG aria-label, aria-live success announcements).

== Upgrade Notice ==

= 1.0.0 =
Initial release.

== Privacy ==

Agent-Ready stores data **only on your own server** — there is no telemetry, no analytics, and no third-party logging. Specifically:

= Local data =

* Plugin **option rows** in the WordPress options table store toggle states and the IndexNow API Key (stored as plain text — keep your database secure).
* **Transients** cache the OpenAPI document and llms.txt body. These are deleted on plugin uninstall.
* No request data, IP addresses, User-Agents, or visitor information is recorded by the plugin.

= Outbound network calls =

The plugin makes exactly **one** outbound HTTP request, and only when explicitly enabled:

* **IndexNow** — when the IndexNow toggle is on and a key is configured, the plugin sends a non-blocking POST to `https://api.indexnow.org/indexnow` on every post publish. The payload contains the site host, the IndexNow key, and the URL of the published post (no visitor data).

If the IndexNow toggle is off (the default), the plugin makes zero outbound network requests. All other features (manifests, JSON-LD, robots.txt) only respond to incoming HTTP requests; they never call out.

= Cookies =

The plugin does not set any cookies.

= Uninstall =

When the plugin is **deleted** (not just deactivated), all plugin options, the IndexNow key, and all transients are removed from the database. Multisite networks have every site cleaned in turn.
