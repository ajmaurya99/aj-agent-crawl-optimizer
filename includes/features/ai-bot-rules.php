<?php
/**
 * Feature: AI bot rules — per-bot AI crawler policy in robots.txt.
 *
 * Appends one RFC 9309 group per known AI crawler (the 15 bots checked by
 * isitagentready.com) to robots.txt, each explicitly allowed or blocked
 * according to a stored policy. Composes with SEO plugins: their output is
 * preserved, our groups are appended after it but before the Content-Signals
 * feature's `Content-Signal` group.
 *
 * @package Ajaco
 */

namespace Ajaco;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Priority PHP_INT_MAX - 5 so our groups land after every SEO plugin (Yoast
// registers at 99999) but BEFORE content-signals' PHP_INT_MAX append — the
// Content-Signal group must stay last in robots.txt.
add_filter( 'robots_txt', __NAMESPACE__ . '\\append_ai_bot_rules', PHP_INT_MAX - 5, 2 );

/**
 * Registry of AI crawlers: canonical User-agent token => purpose label.
 *
 * Tokens and set match the 15 bots checked by the isitagentready.com
 * `robotsTxtAiRules` scan. Purpose labels: `training` (model training),
 * `search` (AI search/answer engines), `user-action` (fetches on behalf of a
 * live user request).
 *
 * @return array<string,string>
 */
function ai_bot_list(): array {
	$bots = array(
		'GPTBot'             => 'training',
		'ChatGPT-User'       => 'user-action',
		'Google-Extended'    => 'training',
		'CCBot'              => 'training',
		'anthropic-ai'       => 'training',
		'Claude-Web'         => 'user-action',
		'Bytespider'         => 'training',
		'PerplexityBot'      => 'search',
		'cohere-ai'          => 'training',
		'Applebot-Extended'  => 'training',
		'Amazonbot'          => 'search',
		'meta-externalagent' => 'training',
		'FacebookBot'        => 'search',
		'omgilibot'          => 'training',
		'Diffbot'            => 'search',
	);

	/**
	 * Filter the AI crawler registry.
	 *
	 * Keys are canonical User-agent tokens as they appear in robots.txt; values
	 * are purpose labels (`training`, `search`, `user-action`). Add entries to
	 * manage additional crawlers, or remove entries to leave a bot unmanaged.
	 *
	 * @param array<string,string> $bots User-agent token => purpose label.
	 */
	$bots = apply_filters( 'ajaco_ai_bot_list', $bots );

	return is_array( $bots ) ? $bots : array();
}

/**
 * Resolve the effective per-bot policy: User-agent token => allow|block.
 *
 * Reads the `ajaco_ai_bot_policy` option; every registered bot missing from
 * the stored option (or holding an unknown value) defaults to `allow`.
 *
 * @return array<string,string>
 */
function ai_bot_policy(): array {
	$stored = get_option( 'ajaco_ai_bot_policy', array() );
	if ( ! is_array( $stored ) ) {
		$stored = array();
	}

	$policy = array();
	foreach ( ai_bot_list() as $token => $purpose ) {
		$value            = isset( $stored[ $token ] ) ? $stored[ $token ] : 'allow';
		$policy[ $token ] = ( 'block' === $value ) ? 'block' : 'allow';
	}

	/**
	 * Filter the resolved per-bot AI crawler policy.
	 *
	 * @param array<string,string> $policy User-agent token => `allow`|`block`.
	 */
	$policy = apply_filters( 'ajaco_ai_bot_policy', $policy );

	return is_array( $policy ) ? $policy : array();
}

/**
 * Sanitize the `ajaco_ai_bot_policy` option: keep only known bot tokens with
 * an `allow` or `block` value.
 *
 * @param mixed $value Raw option value.
 * @return array<string,string>
 */
function sanitize_ai_bot_policy( $value ): array {
	if ( ! is_array( $value ) ) {
		return array();
	}

	$known = ai_bot_list();
	$clean = array();
	foreach ( $value as $token => $rule ) {
		if ( ! isset( $known[ $token ] ) ) {
			continue;
		}
		if ( 'allow' !== $rule && 'block' !== $rule ) {
			continue;
		}
		$clean[ $token ] = $rule;
	}

	return $clean;
}

/**
 * Append one RFC 9309 group per AI crawler to the robots.txt output.
 *
 * @param string $output    The robots.txt content WP is about to serve.
 * @param int    $is_public 1 if the site is public, 0 if search engines are discouraged.
 * @return string
 */
function append_ai_bot_rules( string $output, $is_public ): string {
	if ( ! is_feature_enabled( 'ai_bot_rules' ) ) {
		return $output;
	}

	$policy = ai_bot_policy();
	if ( empty( $policy ) ) {
		return $output;
	}

	// RFC 9309 most-specific-match: a dedicated `User-agent: <bot>` group
	// DETACHES that bot from the `User-agent: *` group entirely, so each group
	// must be self-contained: honor "Discourage search engines" (blog_public),
	// and for allowed bots replicate WordPress core's default * protections
	// instead of a blanket `Allow: /`.
	$discouraged = ! $is_public;

	$site_url  = wp_parse_url( site_url() );
	$site_path = isset( $site_url['path'] ) ? $site_url['path'] : '';

	$allow_rules = "Disallow: {$site_path}/wp-admin/\nAllow: {$site_path}/wp-admin/admin-ajax.php";

	$groups = array();
	foreach ( $policy as $token => $rule ) {
		if ( $discouraged || 'block' === $rule ) {
			$groups[] = "User-agent: {$token}\nDisallow: /";
		} else {
			$groups[] = "User-agent: {$token}\n{$allow_rules}";
		}
	}

	$block = "# AI crawler rules managed by AJ Agent Crawl Optimizer\n"
		. implode( "\n\n", $groups ) . "\n";

	if ( '' === trim( $output ) ) {
		return $block;
	}

	return rtrim( $output ) . "\n\n" . $block;
}
