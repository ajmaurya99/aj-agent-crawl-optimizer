<?php
/**
 * Feature: Content-Signals.
 *
 * Appends a `Content-Signal` directive to robots.txt declaring AI usage
 * preferences (per https://contentsignals.org/). Composes with SEO plugins:
 * Yoast/RankMath/AIOSEO additions are preserved, our line is appended last.
 *
 * @package Ajaco
 */

namespace Ajaco;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Filter runs at PHP_INT_MAX so it lands after every other plugin (Yoast
// registers at 99999) — our Content-Signal sits at the very end of robots.txt
// and the rest of the file is preserved intact.
add_filter( 'robots_txt', __NAMESPACE__ . '\\filter_robots_txt', PHP_INT_MAX, 2 );

// Intercept robots.txt at init so multisite subsite paths
// (`/{subsite}/robots.txt`) resolve regardless of rewrite-rule state. Output
// is delegated to WP's native do_robots() which fires the do_robotstxt action
// and applies the robots_txt filter, so SEO plugins compose normally.
add_action( 'init', __NAMESPACE__ . '\\handle_robots_request' );

/**
 * Effective Content-Signal preferences: stored option over conservative
 * defaults, every value coerced to yes|no.
 *
 * @return array{ai_train: string, search: string, ai_input: string}
 */
function content_signal_prefs(): array {
	$stored = get_option( 'ajaco_content_signal_prefs', array() );
	if ( ! is_array( $stored ) ) {
		$stored = array();
	}

	$defaults = array(
		'ai_train' => 'no',
		'search'   => 'yes',
		'ai_input' => 'no',
	);

	$prefs = array();
	foreach ( $defaults as $key => $default ) {
		$value         = isset( $stored[ $key ] ) ? $stored[ $key ] : $default;
		$prefs[ $key ] = ( 'yes' === $value ) ? 'yes' : 'no';
	}
	return $prefs;
}

/**
 * Sanitize the `ajaco_content_signal_prefs` option: known keys, yes|no values.
 *
 * @param mixed $value Raw option value.
 * @return array{ai_train: string, search: string, ai_input: string}
 */
function sanitize_content_signal_prefs( $value ): array {
	if ( ! is_array( $value ) ) {
		$value = array();
	}

	$clean = array();
	foreach ( array( 'ai_train', 'search', 'ai_input' ) as $key ) {
		$clean[ $key ] = ( isset( $value[ $key ] ) && 'yes' === $value[ $key ] ) ? 'yes' : 'no';
	}
	return $clean;
}

/**
 * Append `Content-Signal` to the robots.txt output.
 *
 * @param string $output    The robots.txt content WP is about to serve.
 * @param int    $is_public 1 if the site is public, 0 if search engines are discouraged.
 * @return string
 */
function filter_robots_txt( string $output, $is_public ): string {
	unset( $is_public ); // Argument required by WP filter signature; not used here.
	if ( ! is_feature_enabled( 'content_signals' ) ) {
		return $output;
	}

	$prefs   = content_signal_prefs();
	$default = sprintf( 'ai-train=%s, search=%s, ai-input=%s', $prefs['ai_train'], $prefs['search'], $prefs['ai_input'] );

	/**
	 * Filter the Content-Signal directive value.
	 *
	 * The default is built from the Content-Signal preferences configured on
	 * the settings page (falling back to `ai-train=no, search=yes,
	 * ai-input=no`). Return value should be the directive value only — the
	 * `Content-Signal:` prefix and trailing newline are added by the plugin.
	 *
	 * @param string $directive Directive value from the stored preferences.
	 */
	$directive = (string) apply_filters( 'ajaco_content_signal', $default );

	// Emit the directive inside an explicit `User-agent: *` group. Appending a
	// bare Content-Signal line after other plugins' output would attach it to
	// whatever User-agent group happens to be last under RFC 9309 group
	// semantics (e.g. an SEO plugin's AhrefsBot block) — scope would be
	// nondeterministic.
	$content_signal = "User-agent: *\nContent-Signal: {$directive}\n";

	if ( trim( $output ) === '' ) {
		return "User-agent: *\nAllow: /\nContent-Signal: {$directive}\n";
	}

	return rtrim( $output ) . "\n\n" . $content_signal;
}

/**
 * Serve robots.txt early so multisite subsite paths resolve, but delegate the
 * body to WP's native do_robots() so the robots_txt filter (and therefore
 * Yoast/Rank Math/etc.) runs normally.
 *
 * @return void
 */
function handle_robots_request(): void {
	if ( ! is_feature_enabled( 'content_signals' ) ) {
		return;
	}

	if ( empty( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}

	$path = wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
	if ( ! is_string( $path ) || ! preg_match( '#(^|/)robots\.txt$#', $path ) ) {
		return;
	}

	nocache_headers();
	do_robots();
	exit;
}
