<?php
/**
 * Shared helpers used across feature handlers.
 *
 * @package Ajaco
 */

namespace Ajaco;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check if a specific feature is enabled.
 *
 * @param string $feature Feature slug (e.g. "markdown", "content_signals").
 * @return bool
 */
function is_feature_enabled( string $feature ): bool {
	return (bool) get_option( 'ajaco_' . $feature . '_enabled', false );
}

/**
 * Capability required to manage AJ Agent Crawl Optimizer settings.
 *
 * Filterable via `ajaco_required_capability` so site admins can delegate
 * access (e.g. give SEO managers control without granting full `manage_options`).
 * The filter must return a non-empty string capability; invalid returns fall
 * back to `manage_options`.
 *
 * @return string
 */
function required_capability(): string {
	/**
	 * Filter the capability required to view and modify AJ Agent Crawl Optimizer settings.
	 *
	 * @param string $capability Default `manage_options`.
	 */
	$cap = apply_filters( 'ajaco_required_capability', 'manage_options' );
	return is_string( $cap ) && $cap !== '' ? $cap : 'manage_options';
}

/**
 * Sanitize a text value for interpolation into a text/markdown body.
 *
 * Machine-readable markdown endpoints (llms.txt, SKILL.md) must NOT be
 * HTML-entity-escaped — agents would receive `Tom&#039;s Blog &amp; Café`.
 * Instead: strip tags, decode any entities WP stored, and collapse
 * newlines/whitespace so a value can't break the surrounding markdown or
 * YAML frontmatter structure.
 *
 * @param string $text Raw text value.
 * @return string
 */
function markdown_safe_text( string $text ): string {
	$text = wp_strip_all_tags( $text );
	$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$text = preg_replace( '/\s+/u', ' ', $text );
	return trim( (string) $text );
}

/**
 * Match the current request path against an expected path.
 *
 * Returns true for an exact match OR a multisite subsite-prefixed match
 * (one extra leading path segment). Allows endpoints like
 * `/.well-known/api-catalog` to also resolve at `/{subsite}/.well-known/api-catalog`.
 *
 * @param string $expected Expected path, including leading slash.
 * @return bool
 */
function request_path_is( string $expected ): bool {
	if ( empty( $_SERVER['REQUEST_URI'] ) ) {
		return false;
	}

	$path = wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
	if ( ! is_string( $path ) ) {
		return false;
	}

	$expected = '/' . ltrim( $expected, '/' );
	if ( $path === $expected ) {
		return true;
	}

	return (bool) preg_match( '#^/[^/]+' . preg_quote( $expected, '#' ) . '$#', $path );
}

/**
 * Detect a major SEO plugin that emits its own JSON-LD output.
 *
 * Used by the JSON-LD feature to auto-suppress our schema when one of these
 * plugins is active, avoiding duplicate WebSite/Organization/Article schemas
 * (which trigger Google Search Console warnings about duplicate structured
 * data).
 *
 * Detection is cached for the request. Site owners can extend or override
 * detection via the `ajaco_active_seo_plugin` filter — return a plugin
 * display name to force-suppress, or `false` to force our schema on.
 *
 * @return string|null Display name of the active SEO plugin, or null if none.
 */
function active_seo_plugin(): ?string {
	static $cached = false;
	if ( $cached !== false ) {
		return $cached === '' ? null : $cached;
	}

	// Each entry: display name => list of signal constants/classes. First
	// match wins. Covers the top SEO plugins on WordPress.org by install count.
	$signals = array(
		'Yoast SEO'                => array( 'WPSEO_VERSION' ),
		'Rank Math'                => array( 'RANK_MATH_VERSION', 'RankMath\\Plugin' ),
		'All in One SEO'           => array( 'AIOSEO_VERSION', 'AIOSEO\\Plugin\\AIOSEO' ),
		'SEOPress'                 => array( 'SEOPRESS_VERSION' ),
		'The SEO Framework'        => array( 'THE_SEO_FRAMEWORK_VERSION', 'The_SEO_Framework\\Load' ),
		'Slim SEO'                 => array( 'SLIM_SEO_VERSION', 'Slim_SEO\\Plugin' ),
		'Squirrly SEO'             => array( 'SQ_VERSION', 'SquirrlySEO' ),
		'Schema Pro'               => array( 'WPSP_VER', 'BSF_AIOSRS_Pro_Markup' ),
		'Schema & Structured Data' => array( 'SASWP_VERSION', 'Saswp_Schema' ),
	);

	$detected = null;
	foreach ( $signals as $name => $tokens ) {
		foreach ( $tokens as $token ) {
			if ( defined( $token ) || class_exists( $token ) ) {
				$detected = $name;
				break 2;
			}
		}
	}

	/**
	 * Filter the detected SEO plugin name.
	 *
	 * Return a non-empty string to declare a plugin we don't auto-detect,
	 * or boolean false to force our JSON-LD on regardless of detection.
	 *
	 * @param string|null $detected Plugin display name, or null if none found.
	 */
	$detected = apply_filters( 'ajaco_active_seo_plugin', $detected );
	if ( $detected === false || ! is_string( $detected ) ) {
		$detected = null;
	}

	$cached = $detected ?? '';
	return $detected;
}
