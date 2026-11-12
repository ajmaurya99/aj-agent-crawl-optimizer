<?php
/**
 * Feature: score badge — an SVG of the last scan's score, grade and Level.
 *
 * Two ways to show it: the `[agent_ready_badge]` shortcode renders the SVG
 * inline wherever the site owner places it, and (opt-in) a public endpoint
 * at /agent-ready-badge.svg lets the badge be embedded elsewhere (a README,
 * another site). Administrators can always fetch the endpoint, so the
 * Dashboard preview works before the public toggle is on. The SVG is static:
 * no scripts, no external resources.
 *
 * @package Ajaco
 */

namespace Ajaco;

use Ajaco\Scan\Scanner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', __NAMESPACE__ . '\\handle_badge_request' );
add_shortcode( 'agent_ready_badge', __NAMESPACE__ . '\\badge_shortcode' );

/**
 * Serve /agent-ready-badge.svg.
 *
 * @return void
 */
function handle_badge_request(): void {
	if ( ! request_path_is( '/agent-ready-badge.svg' ) ) {
		return;
	}
	if ( ! is_feature_enabled( 'badge' ) && ! current_user_can( required_capability() ) ) {
		return;
	}

	nocache_headers();
	header( 'Content-Type: image/svg+xml; charset=utf-8' );
	header( 'Cache-Control: public, max-age=3600' );

	// SVG document assembled from escaped values in build_badge_svg().
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo build_badge_svg( Scanner::get_last_scan() );
	exit;
}

/**
 * `[agent_ready_badge]` — inline SVG linked to the dashboard-free homepage.
 *
 * @return string
 */
function badge_shortcode(): string {
	$svg = build_badge_svg( Scanner::get_last_scan() );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG built from escaped values.
	return '<span class="ajaco-badge" style="display:inline-block;line-height:0">' . $svg . '</span>';
}

/**
 * Grade colour.
 *
 * @param string $grade Letter grade.
 * @return string Hex colour.
 */
function badge_color( string $grade ): string {
	$colors = array(
		'A+' => '#22c55e',
		'A'  => '#22c55e',
		'B'  => '#3b82f6',
		'C'  => '#eab308',
		'D'  => '#f97316',
		'F'  => '#ef4444',
	);
	return isset( $colors[ $grade ] ) ? $colors[ $grade ] : '#8c8f94';
}

/**
 * Build the badge SVG (236×76, dark card).
 *
 * @param array|null $scan Last scan, or null when none is stored.
 * @return string
 */
function build_badge_svg( ?array $scan ): string {
	$has_score = is_array( $scan ) && isset( $scan['score']['grade'] ) && '' !== $scan['score']['grade'];
	$score     = $has_score ? (int) $scan['score']['score'] : null;
	$grade     = $has_score ? (string) $scan['score']['grade'] : '';
	$level     = is_array( $scan ) && isset( $scan['level'] ) ? (int) $scan['level'] : null;
	$color     = $has_score ? badge_color( $grade ) : '#8c8f94';
	$year      = is_array( $scan ) && ! empty( $scan['scannedAt'] ) ? gmdate( 'Y', strtotime( (string) $scan['scannedAt'] ) ) : gmdate( 'Y' );
	$label     = $has_score ? __( 'AGENT READY', 'aj-agent-crawl-optimizer' ) : __( 'NOT SCANNED', 'aj-agent-crawl-optimizer' );
	$sub       = null !== $level
		/* translators: %d: readiness level 0-5. */
		? sprintf( __( 'Level %d of 5', 'aj-agent-crawl-optimizer' ), $level )
		: __( 'Run a scan to score this site', 'aj-agent-crawl-optimizer' );
	$title = $has_score
		/* translators: 1: score, 2: grade. */
		? sprintf( __( 'Agent-readiness score %1$d of 100, grade %2$s', 'aj-agent-crawl-optimizer' ), $score, $grade )
		: __( 'Agent-readiness score: not scanned yet', 'aj-agent-crawl-optimizer' );

	$font = 'font-family="-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif"';

	$svg  = '<svg xmlns="http://www.w3.org/2000/svg" width="236" height="76" viewBox="0 0 236 76" role="img" aria-label="' . esc_attr( $title ) . '">';
	$svg .= '<title>' . esc_html( $title ) . '</title>';
	$svg .= '<rect x="0.5" y="0.5" width="235" height="75" rx="12" fill="#111111" stroke="#262626"/>';
	$svg .= '<path d="M0.5 46h235v17.5a12 12 0 0 1-12 12h-211a12 12 0 0 1-12-12z" fill="#1e1e1e"/>';
	$svg .= '<circle cx="24" cy="23" r="7" fill="none" stroke="' . esc_attr( $color ) . '" stroke-width="3"/>';
	$svg .= '<text x="40" y="28" ' . $font . ' font-size="13" font-weight="700" letter-spacing="1" fill="#f7f5ef">' . esc_html( $label ) . '</text>';
	if ( $has_score ) {
		$svg .= '<text x="190" y="31" ' . $font . ' font-size="26" font-weight="700" fill="#f7f5ef" text-anchor="end">' . (int) $score . '</text>';
		$svg .= '<text x="198" y="31" ' . $font . ' font-size="16" font-weight="700" fill="' . esc_attr( $color ) . '">' . esc_html( $grade ) . '</text>';
	}
	$svg .= '<text x="14" y="63" ' . $font . ' font-size="10.5" fill="#f7f5ef" fill-opacity="0.7">' . esc_html( $sub ) . '</text>';
	$svg .= '<text x="222" y="63" ' . $font . ' font-size="9" letter-spacing="1.5" fill="#f7f5ef" fill-opacity="0.5" text-anchor="end">' . esc_html( $year ) . '</text>';
	$svg .= '</svg>';

	return $svg;
}
