<?php
/**
 * Feature: Markdown Negotiation.
 *
 * Returns a clean Markdown version of any page when an agent requests it via
 * `Accept: text/markdown`. Browsers (which send `text/html`) are unaffected.
 *
 * @package Ajaco
 */

namespace Ajaco;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'template_redirect', __NAMESPACE__ . '\\handle_markdown_request', 1 );
add_action( 'send_headers', __NAMESPACE__ . '\\send_markdown_vary_header' );
add_action( 'wp_head', __NAMESPACE__ . '\\output_markdown_alternate_link', 3 );

/**
 * Advertise the markdown representation of the current page: a
 * `<link rel="alternate" type="text/markdown">` in the head (AgentReady
 * AR-READ-09) and the matching Link header. The href is the page's own URL —
 * the same URL serves markdown on `Accept: text/markdown`.
 *
 * @return void
 */
function output_markdown_alternate_link(): void {
	if ( ! is_feature_enabled( 'markdown' ) || is_admin() || is_feed() || is_404() ) {
		return;
	}
	$url = markdown_alternate_url();
	if ( '' === $url ) {
		return;
	}
	echo '<link rel="alternate" type="text/markdown" href="' . esc_url( $url ) . '" />' . "\n";
}

/**
 * URL of the current view for the alternate link ('' when unknown).
 *
 * @return string
 */
function markdown_alternate_url(): string {
	if ( is_front_page() ) {
		return home_url( '/' );
	}
	if ( is_singular() ) {
		$permalink = get_permalink();
		return is_string( $permalink ) ? $permalink : '';
	}
	global $wp;
	if ( $wp instanceof \WP && is_string( $wp->request ) ) {
		return home_url( user_trailingslashit( $wp->request ) );
	}
	return '';
}

/**
 * YAML frontmatter block for the markdown representation: title, URL, site,
 * modification date, and the HTTP status for error pages. Lets an agent
 * identify what it fetched without parsing the body.
 *
 * @return string
 */
function markdown_frontmatter(): string {
	$yaml = static function ( string $value ): string {
		return '"' . str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), markdown_safe_text( $value ) ) . '"';
	};

	$lines   = array( '---' );
	$lines[] = 'title: ' . $yaml( is_404() ? __( 'Not found', 'aj-agent-crawl-optimizer' ) : wp_get_document_title() );
	$url     = markdown_alternate_url();
	if ( '' !== $url ) {
		$lines[] = 'url: ' . esc_url_raw( $url );
	}
	$lines[] = 'site: ' . $yaml( get_bloginfo( 'name' ) );
	if ( is_singular() ) {
		$modified = get_post_modified_time( 'c', true );
		if ( is_string( $modified ) ) {
			$lines[] = 'modified: ' . $modified;
		}
	}
	if ( is_404() ) {
		$lines[] = 'status: 404';
	}
	$lines[] = '---';

	return implode( "\n", $lines ) . "\n\n";
}

/**
 * Check if the current request accepts Markdown.
 *
 * @return bool
 */
function accepts_markdown(): bool {
	if ( ! isset( $_SERVER['HTTP_ACCEPT'] ) ) {
		return false;
	}

	$accept = sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) );
	// strpos, not str_contains — the WP polyfill only exists since WP 5.9 and
	// this plugin supports 5.5+.
	return strpos( $accept, 'text/markdown' ) !== false;
}

/**
 * Emit `Vary: Accept` on every frontend response while markdown negotiation
 * is enabled — the representation varies on the Accept header, so caches and
 * CDNs must key on it. Without this, a page cache either serves cached HTML
 * to agents (breaking negotiation) or caches the markdown variant for humans.
 *
 * @return void
 */
function send_markdown_vary_header(): void {
	if ( ! is_feature_enabled( 'markdown' ) ) {
		return;
	}
	if ( is_admin() ) {
		return;
	}

	header( 'Vary: Accept', false );

	if ( ! is_feed() && ! is_404() ) {
		$url = markdown_alternate_url();
		if ( '' !== $url ) {
			header( 'Link: <' . esc_url_raw( $url ) . '>; rel="alternate"; type="text/markdown"', false );
		}
	}
}

/**
 * Convert HTML to Markdown.
 *
 * @param string $html HTML string.
 * @return string
 */
function html_to_markdown( string $html ): string {
	$markdown = $html;

	// Remove script, style, noscript, and comments.
	$markdown = preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', $markdown );
	$markdown = preg_replace( '/<style\b[^>]*>.*?<\/style>/is', '', $markdown );
	$markdown = preg_replace( '/<noscript\b[^>]*>.*?<\/noscript>/is', '', $markdown );
	$markdown = preg_replace( '/<!--.*?-->/s', '', $markdown );
	$markdown = preg_replace( '/<[^>]*hidden[^>]*>/i', '', $markdown );

	// Headings.
	$markdown = preg_replace( '/<h1[^>]*>(.*?)<\/h1>/is', "# $1\n\n", $markdown );
	$markdown = preg_replace( '/<h2[^>]*>(.*?)<\/h2>/is', "## $1\n\n", $markdown );
	$markdown = preg_replace( '/<h3[^>]*>(.*?)<\/h3>/is', "### $1\n\n", $markdown );
	$markdown = preg_replace( '/<h4[^>]*>(.*?)<\/h4>/is', "#### $1\n\n", $markdown );
	$markdown = preg_replace( '/<h5[^>]*>(.*?)<\/h5>/is', "##### $1\n\n", $markdown );
	$markdown = preg_replace( '/<h6[^>]*>(.*?)<\/h6>/is', "###### $1\n\n", $markdown );

	// Paragraphs and line breaks.
	$markdown = preg_replace( '/<br\s*\/?>/i', "\n", $markdown );
	$markdown = preg_replace( '/<\/p>/i', "\n\n", $markdown );
	$markdown = preg_replace( '/<p[^>]*>/i', '', $markdown );

	// Bold and italic.
	$markdown = preg_replace( '/<strong[^>]*>(.*?)<\/strong>/is', '**$1**', $markdown );
	$markdown = preg_replace( '/<b[^>]*>(.*?)<\/b>/is', '**$1**', $markdown );
	$markdown = preg_replace( '/<em[^>]*>(.*?)<\/em>/is', '*$1*', $markdown );
	$markdown = preg_replace( '/<i[^>]*>(.*?)<\/i>/is', '*$1*', $markdown );

	// Links.
	$markdown = preg_replace( '/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', '[$2]($1)', $markdown );

	// Images.
	$markdown = preg_replace( '/<img\s+[^>]*src=["\']([^"\']+)["\'][^>]*(?:\/?)>/is', '![]($1)', $markdown );

	// Lists.
	$markdown = preg_replace( '/<ul[^>]*>/i', "\n", $markdown );
	$markdown = preg_replace( '/<\/ul>/i', "\n", $markdown );
	$markdown = preg_replace( '/<ol[^>]*>/i', "\n", $markdown );
	$markdown = preg_replace( '/<\/ol>/i', "\n", $markdown );
	$markdown = preg_replace( '/<li[^>]*>(.*?)<\/li>/is', "- $1\n", $markdown );

	// Blockquotes.
	$markdown = preg_replace( '/<blockquote[^>]*>(.*?)<\/blockquote>/is', "> $1\n\n", $markdown );

	// Code.
	$markdown = preg_replace( '/<code[^>]*>(.*?)<\/code>/is', '`$1`', $markdown );
	$markdown = preg_replace( '/<pre[^>]*>(.*?)<\/pre>/is', "```\n$1\n```\n\n", $markdown );

	// Horizontal rules.
	$markdown = preg_replace( '/<hr\s*\/?>/i', "\n---\n\n", $markdown );

	// Remove remaining HTML tags and decode entities.
	$markdown = wp_strip_all_tags( $markdown );
	$markdown = html_entity_decode( $markdown, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

	// Clean up whitespace.
	$markdown = preg_replace( "/\n{3,}/", "\n\n", $markdown );
	$markdown = trim( $markdown );

	return $markdown;
}

/**
 * Extract main content region from a full HTML document.
 *
 * @param string $html HTML string.
 * @return string
 */
function extract_main_content( string $html ): string {
	$doc = new \DOMDocument();
	libxml_use_internal_errors( true );

	$wrapped = '<html><body>' . $html . '</body></html>';
	$doc->loadHTML( $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

	$xpath = new \DOMXPath( $doc );

	$selectors = array(
		'//article[contains(@class, "post")]',
		'//article[contains(@class, "entry")]',
		'//main[contains(@class, "content")]',
		'//main//article',
		'//div[contains(@class, "entry-content")]',
		'//div[contains(@class, "post-content")]',
		'//div[contains(@class, "content")]',
		'//article',
		'//main',
	);

	foreach ( $selectors as $selector ) {
		$nodes = $xpath->query( $selector );
		if ( $nodes && $nodes->length > 0 ) {
			$node  = $nodes->item( 0 );
			$inner = '';
			foreach ( $node->childNodes as $child ) {
				$inner .= $node->ownerDocument->saveHTML( $child );
			}
			return $inner;
		}
	}

	// Fallback to body.
	$body = $xpath->query( '//body' );
	if ( $body && $body->length > 0 ) {
		$inner = '';
		foreach ( $body->item( 0 )->childNodes as $child ) {
			$inner .= $body->item( 0 )->ownerDocument->saveHTML( $child );
		}
		return $inner;
	}

	return $html;
}

/**
 * Handle a request that accepts Markdown.
 *
 * @return void
 */
function handle_markdown_request(): void {
	if ( ! accepts_markdown() ) {
		return;
	}

	if ( is_admin() || is_feed() || is_robots() || is_favicon() ) {
		return;
	}

	if ( ! is_feature_enabled( 'markdown' ) ) {
		return;
	}

	nocache_headers();
	header( 'Content-Type: text/markdown; charset=UTF-8' );
	header( 'Vary: Accept', false );

	// Capture the rendered page in an output buffer whose callback rewrites
	// the body as Markdown when the buffer is flushed. The conversion has to
	// live in the buffer callback, not in a late `shutdown` action: core
	// flushes every open buffer in wp_ob_end_flush_all() at shutdown
	// priority 1, so by the time a later action ran the HTML had already
	// gone to the client under a text/markdown Content-Type.
	ob_start( __NAMESPACE__ . '\\markdown_output_callback' );
}

/**
 * Output-buffer callback: turn the buffered HTML page into Markdown.
 *
 * @param string $content Buffered HTML.
 * @return string Markdown body.
 */
function markdown_output_callback( string $content ): string {
	if ( '' === trim( $content ) ) {
		return $content;
	}

	$markdown = markdown_frontmatter() . html_to_markdown( extract_main_content( $content ) );

	if ( ! headers_sent() ) {
		header( 'X-Markdown-Tokens: ' . (int) ceil( strlen( $markdown ) / 4 ) );
	}

	return $markdown;
}
