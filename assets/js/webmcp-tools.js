/**
 * WebMCP Tools for AJ Agent Crawl Optimizer
 *
 * Registers read-only site tools for browser-based AI agents via the W3C
 * WebMCP draft (document.modelContext.registerTool). Browsers that only
 * ship the earlier preview surface (navigator.modelContext.provideContext)
 * are still served through a fallback. A silent no-op everywhere else.
 *
 * Every tool is a thin, read-only wrapper over a public WP REST route; none
 * of them change anything, so they are annotated readOnlyHint: true and never
 * consequentialHint. Results are returned as strings (the shape the draft's
 * executeTool resolves to) containing compact JSON.
 *
 * @see https://webmachinelearning.github.io/webmcp/
 */

(function () {
    'use strict';

    var cfg = window.AjacoWebMCP || {};
    var apiRoot = cfg.apiUrl || '/wp-json/';
    var siteName = cfg.siteName || '';

    function restGet(path) {
        return fetch(apiRoot + path, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin'
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status + ' from ' + path);
            }
            return response.json();
        });
    }

    // Plain text from a rendered-HTML REST field. DOMParser documents are
    // inert: nothing in the markup executes or loads.
    function text(html) {
        var doc = new DOMParser().parseFromString(html || '', 'text/html');
        return (doc.body.textContent || '').replace(/\s+/g, ' ').trim();
    }

    function clamp(n, lo, hi, dflt) {
        n = parseInt(n, 10);
        if (isNaN(n)) {
            return dflt;
        }
        return Math.max(lo, Math.min(hi, n));
    }

    function out(obj) {
        return JSON.stringify(obj);
    }

    var tools = [
        {
            name: 'search_site_content',
            title: 'Search ' + (siteName || 'this site'),
            description: 'Full-text search of the published posts and pages on this site. Returns up to 20 matches with id, title, type and URL. Use it to find the page that answers a question before reading it.',
            inputSchema: {
                type: 'object',
                properties: {
                    query: { type: 'string', description: 'Words to search for.' },
                    type: { type: 'string', enum: ['post', 'page', 'any'], description: 'Restrict to posts or pages; default any.' }
                },
                required: ['query']
            },
            annotations: { readOnlyHint: true },
            execute: function (input) {
                var type = input && input.type && input.type !== 'any' ? '&subtype=' + encodeURIComponent(input.type) : '';
                return restGet('wp/v2/search?per_page=20&search=' + encodeURIComponent(input.query) + type).then(function (items) {
                    return out({
                        results: items.map(function (item) {
                            return { id: item.id, title: text(item.title), type: item.subtype || item.type, url: item.url };
                        })
                    });
                });
            }
        },
        {
            name: 'list_recent_posts',
            title: 'List recent posts',
            description: 'List the most recently published posts, newest first, with id, title, excerpt, publish date and URL. Supports paging. Use list_site_pages for static pages.',
            inputSchema: {
                type: 'object',
                properties: {
                    per_page: { type: 'integer', minimum: 1, maximum: 50, description: 'Posts per page, 1 to 50; default 10.' },
                    page: { type: 'integer', minimum: 1, description: 'Page number; default 1.' }
                }
            },
            annotations: { readOnlyHint: true },
            execute: function (input) {
                var perPage = clamp(input && input.per_page, 1, 50, 10);
                var page = clamp(input && input.page, 1, 1000, 1);
                return restGet('wp/v2/posts?per_page=' + perPage + '&page=' + page + '&_fields=id,title,excerpt,date,link').then(function (posts) {
                    return out({
                        page: page,
                        posts: posts.map(function (post) {
                            return { id: post.id, title: text(post.title && post.title.rendered), excerpt: text(post.excerpt && post.excerpt.rendered), date: post.date, url: post.link };
                        })
                    });
                });
            }
        },
        {
            name: 'list_site_pages',
            title: 'List site pages',
            description: 'List the published static pages of this site (about, contact, pricing and so on) with id, title and URL. Use list_recent_posts for blog posts.',
            inputSchema: {
                type: 'object',
                properties: {
                    per_page: { type: 'integer', minimum: 1, maximum: 100, description: 'Pages to return, 1 to 100; default 20.' }
                }
            },
            annotations: { readOnlyHint: true },
            execute: function (input) {
                var perPage = clamp(input && input.per_page, 1, 100, 20);
                return restGet('wp/v2/pages?per_page=' + perPage + '&orderby=menu_order&order=asc&_fields=id,title,link').then(function (pages) {
                    return out({
                        pages: pages.map(function (page) {
                            return { id: page.id, title: text(page.title && page.title.rendered), url: page.link };
                        })
                    });
                });
            }
        },
        {
            name: 'get_site_info',
            title: 'Get site information',
            description: 'Return the site name, tagline, home URL, language and timezone, plus the URLs of its agent-facing resources (llms.txt, OpenAPI, REST API). Call this first to understand what the site is.',
            inputSchema: { type: 'object', properties: {} },
            annotations: { readOnlyHint: true },
            execute: function () {
                return restGet('').then(function (info) {
                    return out({
                        name: info.name,
                        description: info.description,
                        url: info.home || info.url,
                        language: cfg.language || '',
                        timezone: info.timezone_string || info.gmt_offset,
                        resources: cfg.resources || {}
                    });
                });
            }
        }
    ];

    function registerModern() {
        if (!window.isSecureContext && location.hostname !== 'localhost') {
            return false;
        }
        if (!('modelContext' in document) || typeof document.modelContext.registerTool !== 'function') {
            return false;
        }
        tools.forEach(function (tool) {
            try {
                var p = document.modelContext.registerTool(tool);
                if (p && typeof p.catch === 'function') {
                    p.catch(function () { /* NotAllowedError / InvalidStateError: nothing to do. */ });
                }
            } catch (e) {
                // Ignore — registration is best effort.
            }
        });
        return true;
    }

    function registerLegacy() {
        var nav = window.navigator;
        if (!nav || !nav.modelContext || typeof nav.modelContext.provideContext !== 'function') {
            return false;
        }
        try {
            nav.modelContext.provideContext({ tools: tools });
        } catch (e) {
            return false;
        }
        return true;
    }

    if (!registerModern()) {
        registerLegacy();
    }
})();
