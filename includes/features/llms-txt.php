<?php
/**
 * Feature: llms.txt — a curated, LLM-readable index of the site.
 *
 * Serves /llms.txt with site identity, a Discovery section auto-linking every
 * other plugin endpoint that's currently enabled, top-level pages, and
 * recent posts. Also serves /llms-full.txt (same toggle) with the full
 * Markdown-converted content of those pages/posts. Format follows
 * https://llmstxt.org/.
 *
 * @package Ajaco
 */

namespace Ajaco;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const LLMS_TXT_CACHE_KEY      = 'ajaco_llms_txt_cache';
const LLMS_FULL_TXT_CACHE_KEY = 'ajaco_llms_full_txt_cache';

add_action( 'init', __NAMESPACE__ . '\\handle_llms_txt_request' );
add_action( 'init', __NAMESPACE__ . '\\handle_llms_full_txt_request' );

// Invalidate when posts/pages change — the body contains their titles, URLs,
// and excerpts.
add_action( 'save_post', __NAMESPACE__ . '\\flush_llms_txt_cache' );
add_action( 'delete_post', __NAMESPACE__ . '\\flush_llms_txt_cache' );
add_action( 'trashed_post', __NAMESPACE__ . '\\flush_llms_txt_cache' );
add_action( 'untrashed_post', __NAMESPACE__ . '\\flush_llms_txt_cache' );

// Invalidate when site identity changes — used in the header.
add_action( 'update_option_blogname', __NAMESPACE__ . '\\flush_llms_txt_cache' );
add_action( 'update_option_blogdescription', __NAMESPACE__ . '\\flush_llms_txt_cache' );

// Invalidate when any AJ Agent Crawl Optimizer toggle changes — affects the Discovery section.
add_action( 'updated_option', __NAMESPACE__ . '\\maybe_flush_llms_txt_on_setting_change' );

/**
 * Delete the cached llms.txt and llms-full.txt bodies.
 *
 * @return void
 */
function flush_llms_txt_cache(): void {
	delete_transient( LLMS_TXT_CACHE_KEY );
	delete_transient( LLMS_FULL_TXT_CACHE_KEY );
}

/**
 * Flush the cache when an `ajaco_*_enabled` option is updated. The
 * Discovery section auto-includes only enabled features, so a toggle change
 * means the cached body is stale.
 *
 * @param string $option Option name.
 * @return void
 */
function maybe_flush_llms_txt_on_setting_change( string $option ): void {
	if ( strpos( $option, 'ajaco_' ) === 0 ) {
		flush_llms_txt_cache();
	}
}

/**
 * Serve /llms.txt at the root or any multisite subsite path.
 *
 * Cached for one hour in a transient. Invalidates automatically on post/page
 * changes, site name/description changes, and AJ Agent Crawl Optimizer setting toggles.
 *
 * @return void
 */
function handle_llms_txt_request(): void {
	if ( ! is_feature_enabled( 'llms_txt' ) ) {
		return;
	}

	if ( empty( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}

	$path = wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
	if ( ! is_string( $path ) || ! preg_match( '#(^|/)llms\.txt$#', $path ) ) {
		return;
	}

	nocache_headers();
	header( 'Content-Type: text/markdown; charset=utf-8' );

	$cached = get_transient( LLMS_TXT_CACHE_KEY );
	if ( is_string( $cached ) && '' !== $cached ) {
		// Plain-text Markdown served as text/markdown (never rendered as HTML).
		// Values are sanitized for the markdown context in build_llms_txt().
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $cached;
		exit;
	}

	$body = build_llms_txt();
	set_transient( LLMS_TXT_CACHE_KEY, $body, HOUR_IN_SECONDS );

	// Plain-text Markdown served as text/markdown (never rendered as HTML).
	// Values are sanitized for the markdown context in build_llms_txt().
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $body;
	exit;
}

/**
 * Build the llms.txt body from the curation config.
 *
 * @param array|null $config Config override (used by the admin live preview);
 *                           null reads the saved config.
 * @return string
 */
function build_llms_txt( ?array $config = null ): string {
	$config = null === $config ? llms_config() : sanitize_llms_config( $config );

	// text/markdown body — markdown_safe_text() (not esc_html) so agents
	// receive `Tom's Blog & Café`, not `Tom&#039;s Blog &amp; Café`.
	$name        = markdown_safe_text( get_bloginfo( 'name' ) );
	$description = markdown_safe_text( get_bloginfo( 'description' ) );

	$out = "# {$name}\n\n";
	if ( '' !== $description ) {
		$out .= "> {$description}\n\n";
	}

	// The owner's intro wins over the boilerplate line — it's the one place
	// they can tell an agent what this site is actually for.
	if ( '' !== $config['intro'] ) {
		$out .= $config['intro'] . "\n\n";
	} else {
		$out .= "An LLM-readable index of {$name}. Follow the links for the full content.\n\n";
	}

	$out .= 'Full content: ' . esc_url_raw( home_url( '/llms-full.txt' ) ) . "\n\n";

	if ( 'top' === $config['custom_position'] && '' !== $config['custom_md'] ) {
		$out .= $config['custom_md'] . "\n\n";
	}

	// Discovery endpoints — only list features that are currently enabled.
	// esc_url_raw (not esc_url) — display escaping would entity-encode
	// ampersands inside a text/markdown body.
	$discovery = array();
	if ( is_feature_enabled( 'api_catalog' ) ) {
		$discovery[] = '[API Catalog](' . esc_url_raw( home_url( '/.well-known/api-catalog' ) )
			. '): RFC 9727 link set advertising REST endpoints, documentation, and health.';
	}
	if ( is_feature_enabled( 'openapi' ) ) {
		$discovery[] = '[OpenAPI Spec](' . esc_url_raw( home_url( '/openapi.json' ) )
			. '): OpenAPI 3.0.3 specification of every REST route on this site.';
	}
	if ( is_feature_enabled( 'agent_skills_index' ) ) {
		$discovery[] = '[Agent Skills Index](' . esc_url_raw( home_url( '/.well-known/agent-skills/index.json' ) )
			. '): skills agents can use, each with a verifiable SKILL.md artifact.';
	}
	if ( is_feature_enabled( 'mcp_server_card' ) ) {
		$discovery[] = '[MCP Server Card](' . esc_url_raw( home_url( '/.well-known/mcp/server-card.json' ) )
			. '): SEP-1649 server descriptor.';
	}

	if ( ! empty( $discovery ) ) {
		$out .= "## Discovery\n\n";
		foreach ( $discovery as $line ) {
			$out .= "- {$line}\n";
		}
		$out .= "\n";
	}

	// Curated sections: one per configured post type, honoring per-post
	// exclusions and password protection.
	foreach ( $config['sections'] as $post_type => $section ) {
		if ( empty( $section['enabled'] ) || ! post_type_exists( $post_type ) ) {
			continue;
		}

		$entries = llms_section_posts( $post_type, $section );
		if ( empty( $entries ) ) {
			continue;
		}

		$out .= '## ' . $section['heading'] . "\n\n";
		foreach ( $entries as $entry ) {
			$out .= '- ' . format_llms_txt_entry( $entry, ! empty( $section['show_date'] ) ) . "\n";
		}
		$out .= "\n";
	}

	if ( 'bottom' === $config['custom_position'] && '' !== $config['custom_md'] ) {
		$out .= $config['custom_md'] . "\n\n";
	}

	/**
	 * Filter the final llms.txt body.
	 *
	 * Themes and plugins can append their own sections (e.g. featured products,
	 * upcoming events, custom-post-type listings) or replace the body wholesale.
	 * Return value is served verbatim with `Content-Type: text/markdown`.
	 *
	 * @param string $out The Markdown body about to be served.
	 */
	return (string) apply_filters( 'ajaco_llms_txt_content', $out );
}

/**
 * Format a single post/page entry for llms.txt.
 *
 * @param \WP_Post $p
 * @param bool     $with_date Append the publish date in parentheses.
 * @return string
 */
function format_llms_txt_entry( \WP_Post $p, bool $with_date = false ): string {
	// markdown_safe_text/esc_url_raw — this is a text/markdown body; HTML
	// entity escaping would corrupt titles and summaries for agents.
	$title = markdown_safe_text( get_the_title( $p ) );
	// Escape closing brackets so a title can't break out of the [label](url) syntax.
	$title = str_replace( ']', '\\]', $title );
	$url   = esc_url_raw( get_permalink( $p ) );

	// The author's LLM summary override wins over the excerpt (see
	// llms_post_summary()) — excerpts are written for humans skimming, this
	// line is written for a model deciding whether to fetch the page.
	$summary = llms_post_summary( $p );

	$line = "[{$title}]({$url})";
	if ( $with_date ) {
		$line .= ' (' . get_the_date( 'Y-m-d', $p ) . ')';
	}
	if ( '' !== $summary ) {
		$line .= ': ' . $summary;
	}
	return $line;
}

/**
 * Serve /llms-full.txt at the root or any multisite subsite path.
 *
 * Rides the same `llms_txt` toggle as /llms.txt. Cached for one hour in its
 * own transient; invalidated by the same hooks via flush_llms_txt_cache().
 *
 * @return void
 */
function handle_llms_full_txt_request(): void {
	if ( ! is_feature_enabled( 'llms_txt' ) ) {
		return;
	}

	if ( empty( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}

	$path = wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
	if ( ! is_string( $path ) || ! preg_match( '#(^|/)llms-full\.txt$#', $path ) ) {
		return;
	}

	nocache_headers();
	header( 'Content-Type: text/markdown; charset=utf-8' );

	$cached = get_transient( LLMS_FULL_TXT_CACHE_KEY );
	if ( is_string( $cached ) && '' !== $cached ) {
		// Plain-text Markdown served as text/markdown (never rendered as HTML).
		// Values are sanitized for the markdown context in build_llms_full_txt().
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $cached;
		exit;
	}

	$body = build_llms_full_txt();
	set_transient( LLMS_FULL_TXT_CACHE_KEY, $body, HOUR_IN_SECONDS );

	// Plain-text Markdown served as text/markdown (never rendered as HTML).
	// Values are sanitized for the markdown context in build_llms_full_txt().
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $body;
	exit;
}

/**
 * Build the llms-full.txt body: the full content of top-level pages and the
 * 15 most recent posts, converted to Markdown.
 *
 * @return string
 */
function build_llms_full_txt(): string {
	// text/markdown body — markdown_safe_text() (not esc_html) so agents
	// receive `Tom's Blog & Café`, not `Tom&#039;s Blog &amp; Café`.
	$name        = markdown_safe_text( get_bloginfo( 'name' ) );
	$description = markdown_safe_text( get_bloginfo( 'description' ) );

	$out = "# {$name}\n\n";
	if ( '' !== $description ) {
		$out .= "> {$description}\n\n";
	}
	$out .= "The full content of {$name} in Markdown, for LLM consumption. "
		. 'A shorter index is available at ' . esc_url_raw( home_url( '/llms.txt' ) ) . ".\n\n";

	// Same curated sections as llms.txt — the owner's inclusion choices and
	// per-post exclusions apply to the full-content file too. llms_section_posts()
	// already drops excluded and password-protected entries (this endpoint
	// serves FULL content, and applying `the_content` to raw post_content
	// bypasses the password gate in get_the_content()).
	//
	// Full content is heavy, so each section is capped tighter than in the index.
	$config = llms_config();
	foreach ( $config['sections'] as $post_type => $section ) {
		if ( empty( $section['enabled'] ) || ! post_type_exists( $post_type ) ) {
			continue;
		}

		$section['count'] = min( (int) $section['count'], 15 );

		foreach ( llms_section_posts( $post_type, $section ) as $p ) {
			$out .= format_llms_full_txt_entry( $p );
		}
	}

	/**
	 * Filter the final llms-full.txt body.
	 *
	 * Themes and plugins can append custom-post-type content or replace the
	 * body wholesale. Return value is served verbatim with
	 * `Content-Type: text/markdown`.
	 *
	 * @param string $out The Markdown body about to be served.
	 */
	return (string) apply_filters( 'ajaco_llms_full_txt_content', $out );
}

/**
 * Format a single post/page entry for llms-full.txt: heading, source URL,
 * publish date, then the full rendered content converted to Markdown
 * (capped at ~20000 characters).
 *
 * @param \WP_Post $p
 * @return string
 */
function format_llms_full_txt_entry( \WP_Post $p ): string {
	$title = markdown_safe_text( get_the_title( $p ) );
	$url   = esc_url_raw( get_permalink( $p ) );

	// Render shortcodes/blocks via `the_content`, then convert the HTML to
	// Markdown with the shared converter from markdown-negotiation.php.
	// Truncate the rendered HTML BEFORE conversion — html_to_markdown runs
	// ~25 full-string regex passes, so feeding it a megabyte page-builder
	// document on a cold cache is a CPU trap.
	$html = (string) apply_filters( 'the_content', $p->post_content );
	if ( mb_strlen( $html ) > 100000 ) {
		$html = mb_substr( $html, 0, 100000 );
	}
	$markdown = trim( html_to_markdown( $html ) );
	if ( ! is_string( $markdown ) || '' === $markdown ) {
		$markdown = trim( wp_strip_all_tags( $html ) );
	}
	if ( mb_strlen( $markdown ) > 20000 ) {
		$markdown = mb_substr( $markdown, 0, 20000 ) . "\n\n…(truncated)";
	}

	$entry  = "## {$title}\n\n";
	$entry .= "Source: {$url}\n";
	$entry .= 'Published: ' . get_the_date( 'Y-m-d', $p ) . "\n\n";
	$entry .= $markdown . "\n\n";
	return $entry;
}
