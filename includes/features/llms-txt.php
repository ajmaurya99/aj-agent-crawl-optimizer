<?php
/**
 * Feature: llms.txt — a curated, LLM-readable index of the site.
 *
 * Serves /llms.txt with site identity, a Discovery section auto-linking every
 * other agent-ready endpoint that's currently enabled, top-level pages, and
 * recent posts. Format follows https://llmstxt.org/.
 *
 * @package AgentReady
 */

namespace AgentReady;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const LLMS_TXT_CACHE_KEY = 'agent_ready_llms_txt_cache';

add_action( 'init', __NAMESPACE__ . '\\handle_llms_txt_request' );

// Invalidate when posts/pages change — the body contains their titles, URLs,
// and excerpts.
add_action( 'save_post', __NAMESPACE__ . '\\flush_llms_txt_cache' );
add_action( 'delete_post', __NAMESPACE__ . '\\flush_llms_txt_cache' );
add_action( 'trashed_post', __NAMESPACE__ . '\\flush_llms_txt_cache' );
add_action( 'untrashed_post', __NAMESPACE__ . '\\flush_llms_txt_cache' );

// Invalidate when site identity changes — used in the header.
add_action( 'update_option_blogname', __NAMESPACE__ . '\\flush_llms_txt_cache' );
add_action( 'update_option_blogdescription', __NAMESPACE__ . '\\flush_llms_txt_cache' );

// Invalidate when any Agent-Ready toggle changes — affects the Discovery section.
add_action( 'updated_option', __NAMESPACE__ . '\\maybe_flush_llms_txt_on_setting_change' );

/**
 * Delete the cached llms.txt body.
 *
 * @return void
 */
function flush_llms_txt_cache(): void {
	delete_transient( LLMS_TXT_CACHE_KEY );
}

/**
 * Flush the cache when an `agent_ready_*_enabled` option is updated. The
 * Discovery section auto-includes only enabled features, so a toggle change
 * means the cached body is stale.
 *
 * @param string $option Option name.
 * @return void
 */
function maybe_flush_llms_txt_on_setting_change( string $option ): void {
	if ( strpos( $option, 'agent_ready_' ) === 0 ) {
		flush_llms_txt_cache();
	}
}

/**
 * Serve /llms.txt at the root or any multisite subsite path.
 *
 * Cached for one hour in a transient. Invalidates automatically on post/page
 * changes, site name/description changes, and Agent-Ready setting toggles.
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
	if ( is_string( $cached ) && $cached !== '' ) {
		// Plain-text Markdown body served as text/markdown.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $cached;
		exit;
	}

	$body = build_llms_txt();
	set_transient( LLMS_TXT_CACHE_KEY, $body, HOUR_IN_SECONDS );

	// Plain-text Markdown body served as text/markdown.
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $body;
	exit;
}

/**
 * Build the llms.txt body.
 *
 * @return string
 */
function build_llms_txt(): string {
	$name        = get_bloginfo( 'name' );
	$description = get_bloginfo( 'description' );

	$out = "# {$name}\n\n";
	if ( $description !== '' ) {
		$out .= "> {$description}\n\n";
	}
	$out .= "An LLM-readable index of {$name}. Follow the links for the full content.\n\n";

	// Discovery endpoints — only list features that are currently enabled.
	$discovery = array();
	if ( is_feature_enabled( 'api_catalog' ) ) {
		$discovery[] = '[API Catalog](' . home_url( '/.well-known/api-catalog' )
			. '): RFC 9727 link set advertising REST endpoints, documentation, and health.';
	}
	if ( is_feature_enabled( 'openapi' ) ) {
		$discovery[] = '[OpenAPI Spec](' . home_url( '/?format=openapi' )
			. '): OpenAPI 3.0.3 specification of every REST route on this site.';
	}
	if ( is_feature_enabled( 'agent_skills_index' ) ) {
		$discovery[] = '[Agent Skills Index](' . home_url( '/.well-known/agent-skills/index.json' )
			. '): skills agents can use, each with a verifiable SKILL.md artifact.';
	}
	if ( is_feature_enabled( 'mcp_server_card' ) ) {
		$discovery[] = '[MCP Server Card](' . home_url( '/.well-known/mcp/server-card.json' )
			. '): SEP-1649 server descriptor.';
	}

	if ( ! empty( $discovery ) ) {
		$out .= "## Discovery\n\n";
		foreach ( $discovery as $line ) {
			$out .= "- {$line}\n";
		}
		$out .= "\n";
	}

	// Top-level pages.
	$pages = get_posts(
		array(
			'post_type'        => 'page',
			'post_status'      => 'publish',
			'numberposts'      => 20,
			'post_parent'      => 0,
			'orderby'          => 'menu_order title',
			'order'            => 'ASC',
			'suppress_filters' => false,
		)
	);
	if ( ! empty( $pages ) ) {
		$out .= "## Pages\n\n";
		foreach ( $pages as $page ) {
			$out .= '- ' . format_llms_txt_entry( $page ) . "\n";
		}
		$out .= "\n";
	}

	// Recent posts.
	$posts = get_posts(
		array(
			'post_type'        => 'post',
			'post_status'      => 'publish',
			'numberposts'      => 10,
			'orderby'          => 'date',
			'order'            => 'DESC',
			'suppress_filters' => false,
		)
	);
	if ( ! empty( $posts ) ) {
		$out .= "## Recent Posts\n\n";
		foreach ( $posts as $post ) {
			$out .= '- ' . format_llms_txt_entry( $post, true ) . "\n";
		}
		$out .= "\n";
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
	return (string) apply_filters( 'agent_ready_llms_txt_content', $out );
}

/**
 * Format a single post/page entry for llms.txt.
 *
 * @param \WP_Post $p
 * @param bool     $with_date Append the publish date in parentheses.
 * @return string
 */
function format_llms_txt_entry( \WP_Post $p, bool $with_date = false ): string {
	$title   = get_the_title( $p );
	$url     = get_permalink( $p );
	$excerpt = trim( wp_strip_all_tags( get_the_excerpt( $p ) ) );
	$excerpt = preg_replace( '/\s*\[(\.\.\.|…)\]\s*$/u', '', $excerpt );

	$line = "[{$title}]({$url})";
	if ( $with_date ) {
		$line .= ' (' . get_the_date( 'Y-m-d', $p ) . ')';
	}
	if ( $excerpt !== '' ) {
		$line .= ': ' . $excerpt;
	}
	return $line;
}
