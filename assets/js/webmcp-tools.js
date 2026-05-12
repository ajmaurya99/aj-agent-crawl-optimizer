/**
 * WebMCP Tools for AJ Agent Crawl Optimizer
 *
 * Exposes site tools to AI agents via the browser's navigator.modelContext API.
 * @see https://webmachinelearning.github.io/webmcp/
 */

(function () {
    'use strict';

    // Check if WebMCP API is available (Chrome experimental feature).
    if (!navigator.modelContext) {
        return;
    }

    // Get WordPress REST API root from the localized data PHP exposes via
    // wp_localize_script(). Falls back to the same-origin root if missing.
    const apiRoot = (window.AjacoWebMCP && window.AjacoWebMCP.apiUrl) || '/wp-json/';

    // Define available tools.
    const tools = [
        {
            name: 'search_content',
            description: 'Search posts, pages, and media on the site',
            inputSchema: {
                type: 'object',
                properties: {
                    search: {
                        type: 'string',
                        description: 'Search query'
                    },
                    type: {
                        type: 'string',
                        enum: ['post', 'page', 'any'],
                        default: 'any',
                        description: 'Content type to search'
                    }
                },
                required: ['search']
            },
            execute: async function (params) {
                const type = params.type || 'any';
                const url = apiRoot + 'wp/v2/search?search=' + encodeURIComponent(params.search) + '&type=' + type;

                try {
                    const response = await fetch(url, {
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        credentials: 'include'
                    });

                    if (!response.ok) {
                        throw new Error('Search failed: ' + response.status);
                    }

                    const data = await response.json();
                    return {
                        success: true,
                        results: data.map(function (item) {
                            return {
                                id: item.id,
                                title: item.title,
                                type: item.type,
                                url: item.url
                            };
                        })
                    };
                } catch (error) {
                    return {
                        success: false,
                        error: error.message
                    };
                }
            }
        },
        {
            name: 'get_posts',
            description: 'Get recent posts from the site',
            inputSchema: {
                type: 'object',
                properties: {
                    per_page: {
                        type: 'integer',
                        default: 10,
                        description: 'Number of posts to retrieve (max 100)'
                    },
                    page: {
                        type: 'integer',
                        default: 1,
                        description: 'Page number'
                    }
                }
            },
            execute: async function (params) {
                const perPage = Math.min(params.per_page || 10, 100);
                const page = params.page || 1;
                const url = apiRoot + 'wp/v2/posts?per_page=' + perPage + '&page=' + page + '&_embed';

                try {
                    const response = await fetch(url, {
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        credentials: 'include'
                    });

                    if (!response.ok) {
                        throw new Error('Failed to fetch posts: ' + response.status);
                    }

                    const posts = await response.json();
                    return {
                        success: true,
                        posts: posts.map(function (post) {
                            return {
                                id: post.id,
                                title: post.title.rendered,
                                excerpt: post.excerpt.rendered,
                                date: post.date,
                                link: post.link
                            };
                        })
                    };
                } catch (error) {
                    return {
                        success: false,
                        error: error.message
                    };
                }
            }
        },
        {
            name: 'get_pages',
            description: 'Get site pages',
            inputSchema: {
                type: 'object',
                properties: {
                    per_page: {
                        type: 'integer',
                        default: 10,
                        description: 'Number of pages to retrieve (max 100)'
                    }
                }
            },
            execute: async function (params) {
                const perPage = Math.min(params.per_page || 10, 100);
                const url = apiRoot + 'wp/v2/pages?per_page=' + perPage;

                try {
                    const response = await fetch(url, {
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        credentials: 'include'
                    });

                    if (!response.ok) {
                        throw new Error('Failed to fetch pages: ' + response.status);
                    }

                    const pages = await response.json();
                    return {
                        success: true,
                        pages: pages.map(function (page) {
                            return {
                                id: page.id,
                                title: page.title.rendered,
                                status: page.status,
                                link: page.link
                            };
                        })
                    };
                } catch (error) {
                    return {
                        success: false,
                        error: error.message
                    };
                }
            }
        },
        {
            name: 'get_site_info',
            description: 'Get general site information',
            inputSchema: {
                type: 'object',
                properties: {}
            },
            execute: async function () {
                const url = apiRoot;

                try {
                    const response = await fetch(url, {
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        credentials: 'include'
                    });

                    if (!response.ok) {
                        throw new Error('Failed to fetch site info: ' + response.status);
                    }

                    const info = await response.json();
                    return {
                        success: true,
                        site: {
                            name: info.name,
                            description: info.description,
                            url: info.url,
                            home: info.home,
                            gmtOffset: info.gmt_offset
                        }
                    };
                } catch (error) {
                    return {
                        success: false,
                        error: error.message
                    };
                }
            }
        }
    ];

    // Register tools with WebMCP.
    navigator.modelContext.provideContext({
        tools: tools
    });

})();
