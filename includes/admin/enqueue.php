<?php
/**
 * Admin: enqueue assets for the Agent Ready screens.
 *
 * Settings screen keeps the legacy admin.css/js pair; the Dashboard gets the
 * v2 scanner app (dashboard.css/js) plus its localized data payload; the
 * llms.txt curation screen reuses admin.css and adds its own preview script.
 * Each is gated to its own hook suffix so nothing loads on unrelated admin
 * pages.
 *
 * @package Ajaco
 */

namespace Ajaco;

use Ajaco\Scan\Check_Info;
use Ajaco\Scan\Fix_Registry;
use Ajaco\Scan\Scanner;
use Ajaco\Scan\Score;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_admin_assets' );

/**
 * Enqueue admin CSS + JS on our screens only.
 *
 * @param string $hook_suffix Current admin page hook suffix.
 * @return void
 */
function enqueue_admin_assets( string $hook_suffix ): void {
	if ( admin_page_hook( 'settings' ) === $hook_suffix ) {
		enqueue_settings_assets();
		return;
	}

	if ( admin_page_hook( 'dashboard' ) === $hook_suffix ) {
		enqueue_dashboard_assets();
		return;
	}

	if ( admin_page_hook( 'llms' ) === $hook_suffix ) {
		enqueue_llms_assets();
	}
}

/**
 * Legacy settings-screen assets.
 *
 * @return void
 */
function enqueue_settings_assets(): void {
	$css_path = AJACO_DIR . 'assets/css/admin.css';
	$js_path  = AJACO_DIR . 'assets/js/admin.js';

	wp_enqueue_style(
		'ajaco-admin',
		AJACO_URL . 'assets/css/admin.css',
		array(),
		file_exists( $css_path ) ? filemtime( $css_path ) : AJACO_VERSION
	);

	wp_enqueue_script(
		'ajaco-admin',
		AJACO_URL . 'assets/js/admin.js',
		array(),
		file_exists( $js_path ) ? filemtime( $js_path ) : AJACO_VERSION,
		true
	);

	wp_localize_script(
		'ajaco-admin',
		'AjacoAdmin',
		array(
			'i18n' => array(
				'copied' => __( 'Copied!', 'aj-agent-crawl-optimizer' ),
			),
		)
	);
}

/**
 * Dashboard (scanner) app assets + data payload.
 *
 * @return void
 */
function enqueue_dashboard_assets(): void {
	$css_path = AJACO_DIR . 'assets/css/dashboard.css';
	$js_path  = AJACO_DIR . 'assets/js/dashboard.js';

	wp_enqueue_style(
		'ajaco-dashboard',
		AJACO_URL . 'assets/css/dashboard.css',
		array(),
		file_exists( $css_path ) ? filemtime( $css_path ) : AJACO_VERSION
	);

	wp_enqueue_script(
		'ajaco-dashboard',
		AJACO_URL . 'assets/js/dashboard.js',
		array( 'wp-i18n' ),
		file_exists( $js_path ) ? filemtime( $js_path ) : AJACO_VERSION,
		true
	);

	// The localized i18n payload is already translated server-side via __();
	// this makes the handle translation-aware for completeness and parity with
	// the block-editor script.
	if ( function_exists( 'wp_set_script_translations' ) ) {
		wp_set_script_translations( 'ajaco-dashboard', 'aj-agent-crawl-optimizer' );
	}

	// Per-check static metadata for card rendering + copy prompts.
	$meta   = array();
	$checks = Scanner::get_checks();
	foreach ( $checks as $id => $check ) {
		$info        = Check_Info::get( $id );
		$fixes       = Fix_Registry::all();
		$weight      = Score::weight( $id );
		$meta[ $id ] = array(
			'name'        => $check->get_name(),
			'category'    => $check->get_category(),
			'description' => $info['description'],
			'prompt'      => $info['prompt'],
			'specUrls'    => $info['specUrls'],
			'skillUrl'    => $info['skillUrl'],
			'fixable'     => isset( $fixes[ $id ] ),
			'fixLabel'    => isset( $fixes[ $id ] ) ? $fixes[ $id ]['label'] : '',
			'fixNote'     => isset( $fixes[ $id ] ) ? $fixes[ $id ]['note'] : '',
			'points'      => $weight['points'],
			'bonus'       => $weight['bonus'],
			'fixLink'     => Fix_Registry::link_fix( $id ),
		);
	}

	wp_localize_script(
		'ajaco-dashboard',
		'AjacoDash',
		array(
			'restUrl'        => esc_url_raw( rest_url( 'ajaco/v1' ) ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'homeUrl'        => home_url( '/' ),
			'settingsUrl'    => settings_page_url(),
			'scan'           => Scanner::get_last_scan(),
			'history'        => Scanner::get_history(),
			'badgeUrl'       => home_url( '/agent-ready-badge.svg' ),
			'badgePublic'    => is_feature_enabled( 'badge' ),
			'checkMeta'      => $meta,
			'defaultChecks'  => Scanner::default_check_ids(),
			'levelNames'     => \Ajaco\Scan\Level::names(),
			'grades'         => Score::grades(),
			'categoryLabels' => array(
				'discoverability'      => __( 'Discoverability', 'aj-agent-crawl-optimizer' ),
				'contentAccessibility' => __( 'Content Accessibility', 'aj-agent-crawl-optimizer' ),
				'botAccessControl'     => __( 'Bot Access Control', 'aj-agent-crawl-optimizer' ),
				'discovery'            => __( 'API, Auth, MCP & Skill Discovery', 'aj-agent-crawl-optimizer' ),
				'commerce'             => __( 'Commerce', 'aj-agent-crawl-optimizer' ),
			),
			'i18n'           => array(
				'scan'        => __( 'Run scan', 'aj-agent-crawl-optimizer' ),
				'scanning'    => __( 'Scanning…', 'aj-agent-crawl-optimizer' ),
				'rescan'      => __( 'Re-scan', 'aj-agent-crawl-optimizer' ),
				'fixNow'      => __( 'Fix now', 'aj-agent-crawl-optimizer' ),
				'fixing'      => __( 'Fixing…', 'aj-agent-crawl-optimizer' ),
				'verifying'   => __( 'Verifying…', 'aj-agent-crawl-optimizer' ),
				'copyPrompt'  => __( 'Copy agent prompt', 'aj-agent-crawl-optimizer' ),
				'copied'      => __( 'Copied!', 'aj-agent-crawl-optimizer' ),
				'evidence'    => __( 'Audit details', 'aj-agent-crawl-optimizer' ),
				'hideDetails' => __( 'Hide details', 'aj-agent-crawl-optimizer' ),
				'pass'        => __( 'Pass', 'aj-agent-crawl-optimizer' ),
				'fail'        => __( 'Fail', 'aj-agent-crawl-optimizer' ),
				'neutral'     => __( 'Not applicable', 'aj-agent-crawl-optimizer' ),
				'unable'      => __( 'Unable to check', 'aj-agent-crawl-optimizer' ),

				// Empty state.
				'emptyTitle'  => __( 'Is this site agent-ready?', 'aj-agent-crawl-optimizer' ),
				'emptyBody'   => __( 'Run the readiness scan — the same checks Cloudflare’s isitagentready.com runs — against this site, with full request/response evidence and one-click fixes.', 'aj-agent-crawl-optimizer' ),
				'runFirst'    => __( 'Run your first scan', 'aj-agent-crawl-optimizer' ),

				// Score hero.
				/* translators: 1: score 0-100, 2: letter grade. */
				'scoreOf100'   => __( 'Score %1$s of 100, grade %2$s', 'aj-agent-crawl-optimizer' ),
				'scoreTitle'   => __( 'Agent-readiness score', 'aj-agent-crawl-optimizer' ),
				'noScore'      => __( 'No scored checks ran', 'aj-agent-crawl-optimizer' ),
				'rescanScore'  => __( 'Re-scan to compute the score', 'aj-agent-crawl-optimizer' ),
				/* translators: %s: signed point change, e.g. +12. */
				'sinceLast'    => __( '%s since last scan', 'aj-agent-crawl-optimizer' ),
				'noChange'     => __( 'No change since last scan', 'aj-agent-crawl-optimizer' ),
				'firstScan'    => __( 'First scan — re-scan after fixes to track progress', 'aj-agent-crawl-optimizer' ),
				/* translators: %s: bonus points, e.g. 3. */
				'bonusPts'     => __( '+%s bonus', 'aj-agent-crawl-optimizer' ),
				'layersTitle'  => __( 'Score by layer', 'aj-agent-crawl-optimizer' ),
				/* translators: 1: earned points, 2: available points. */
				'ptsOf'        => __( '%1$s / %2$s pts', 'aj-agent-crawl-optimizer' ),
				'layerNa'      => __( 'Not applicable', 'aj-agent-crawl-optimizer' ),
				/* translators: %s: points a fix would add. */
				'plusPts'      => __( '+%s pts', 'aj-agent-crawl-optimizer' ),
				/* translators: %s: points a passing check earns. */
				'pts'          => __( '%s pts', 'aj-agent-crawl-optimizer' ),
				'bonusTag'     => __( 'Bonus', 'aj-agent-crawl-optimizer' ),
				'filterAll'    => __( 'All checks', 'aj-agent-crawl-optimizer' ),
				'filterFail'   => __( 'Failing', 'aj-agent-crawl-optimizer' ),
				'filterFix'    => __( 'One-click fixes', 'aj-agent-crawl-optimizer' ),
				'filterBonus'  => __( 'Bonus', 'aj-agent-crawl-optimizer' ),
				'filterEmpty'  => __( 'Nothing matches this filter.', 'aj-agent-crawl-optimizer' ),
				'sortedImpact' => __( 'Sorted by impact', 'aj-agent-crawl-optimizer' ),

				// Share & verify.
				'badgeTitle'       => __( 'Score badge', 'aj-agent-crawl-optimizer' ),
				'badgeBody'        => __( 'A static SVG of the last scan. Place it anywhere on this site with the shortcode.', 'aj-agent-crawl-optimizer' ),
				'copyShortcode'    => __( 'Copy shortcode', 'aj-agent-crawl-optimizer' ),
				'copyEmbed'        => __( 'Copy embed HTML', 'aj-agent-crawl-optimizer' ),
				'badgePrivate'     => __( 'To embed the badge on another site or a README, enable “Public score badge” in Settings.', 'aj-agent-crawl-optimizer' ),
				'badgeAlt'         => __( 'Agent-readiness score badge', 'aj-agent-crawl-optimizer' ),
				'externalTitle'    => __( 'Hosted scanners', 'aj-agent-crawl-optimizer' ),
				'externalBody'     => __( 'Cross-check this site on the two public agent-readiness scanners. Their APIs never start a scan — run one on their site first, then check here.', 'aj-agent-crawl-optimizer' ),
				'externalCheck'    => __( 'Check hosted scanners', 'aj-agent-crawl-optimizer' ),
				'externalRefresh'  => __( 'Refresh', 'aj-agent-crawl-optimizer' ),
				'externalChecking' => __( 'Checking…', 'aj-agent-crawl-optimizer' ),
				'externalNone'     => __( 'Not scanned yet', 'aj-agent-crawl-optimizer' ),
				'externalError'    => __( 'Could not reach', 'aj-agent-crawl-optimizer' ),
				'externalOpen'     => __( 'Open report', 'aj-agent-crawl-optimizer' ),
				/* translators: %s: scanner name. */
				'externalScanOn'   => __( 'Scan on %s', 'aj-agent-crawl-optimizer' ),
				/* translators: %s: date/time. */
				'externalChecked'  => __( 'Checked %s', 'aj-agent-crawl-optimizer' ),
				'externalLocal'    => __( 'Hosted scanners can only see public sites — this one looks local or private.', 'aj-agent-crawl-optimizer' ),

				// Gauge + next level.
				/* translators: 1: level number 0-5, 2: level name. */
				'levelOfName' => __( 'Level %1$s of 5 — %2$s', 'aj-agent-crawl-optimizer' ),
				'nextLevel'   => __( 'Next level', 'aj-agent-crawl-optimizer' ),
				'topLevel'    => __( 'Top level', 'aj-agent-crawl-optimizer' ),
				/* translators: 1: level number, 2: level name. */
				'levelDashName' => __( 'Level %1$s — %2$s', 'aj-agent-crawl-optimizer' ),
				'topLevelBody' => __( 'This site passes every ladder requirement. Keep an eye on spec churn — standards in this space move fast.', 'aj-agent-crawl-optimizer' ),

				// Hosting diagnosis.
				/* translators: %s: number of blocked endpoints. Singular|plural. */
				'hostingTitle' => __( 'Your host is blocking %1$s agent endpoint|Your host is blocking %1$s agent endpoints', 'aj-agent-crawl-optimizer' ),
				'hostingBody'  => __( 'These features are enabled and served by the plugin (no files involved), but the web server intercepts the request before WordPress runs. Add the matching rule to your server config — or send it to your hosting support — then re-scan.', 'aj-agent-crawl-optimizer' ),
				'copyNginx'    => __( 'Copy nginx fix', 'aj-agent-crawl-optimizer' ),
				'copyApache'   => __( 'Copy Apache .htaccess fix', 'aj-agent-crawl-optimizer' ),

				// Category section.
				'optional'         => __( 'Optional', 'aj-agent-crawl-optimizer' ),
				'notChecked'       => __( 'not checked', 'aj-agent-crawl-optimizer' ),
				'commerceNote'     => __( 'Commerce protocols are emerging standards — informational, never counted in the score.', 'aj-agent-crawl-optimizer' ),
				'commerceNoteNone' => __( 'No e-commerce signals detected on this site. Shown for information only; does not affect the score.', 'aj-agent-crawl-optimizer' ),

				// Check card detail labels.
				'goalLabel'   => __( 'Goal', 'aj-agent-crawl-optimizer' ),
				'resultLabel' => __( 'Result', 'aj-agent-crawl-optimizer' ),
				'issueLabel'  => __( 'Issue', 'aj-agent-crawl-optimizer' ),
				'noteLabel'   => __( 'Note', 'aj-agent-crawl-optimizer' ),
				'fixLabel'    => __( 'Fix', 'aj-agent-crawl-optimizer' ),
				/* translators: %s: duration in milliseconds. */
				'completedIn' => __( 'Completed in %s ms', 'aj-agent-crawl-optimizer' ),

				// Fix sheet.
				'improveReadiness' => __( 'Improve readiness', 'aj-agent-crawl-optimizer' ),
				/* translators: %s: number of failing checks. Singular|plural. */
				'issuesFound'      => __( '%1$s issue found|%1$s issues found', 'aj-agent-crawl-optimizer' ),
				'oneClickFix'      => __( '(one-click fix)', 'aj-agent-crawl-optimizer' ),
				/* translators: %s: number of one-click-fixable checks. */
				'fixAllSafe'       => __( 'Fix all safe items (%s)', 'aj-agent-crawl-optimizer' ),
				'copyAllPrompts'   => __( 'Copy all agent prompts', 'aj-agent-crawl-optimizer' ),
				'sheetIntro'       => __( 'Copied prompts paste into any coding agent (Cursor, Claude Code, Windsurf, Copilot). Fixes needing DNS or server access always go the prompt route.', 'aj-agent-crawl-optimizer' ),

				// Errors.
				'sessionExpired' => __( 'Your session expired — reload this page and try again.', 'aj-agent-crawl-optimizer' ),
				/* translators: %s: HTTP status code. */
				'requestFailed'  => __( 'Request failed (HTTP %s)', 'aj-agent-crawl-optimizer' ),
			),
		)
	);
}

/**
 * Curation screen (llms.txt): the shared admin.css plus its preview script.
 *
 * @return void
 */
function enqueue_llms_assets(): void {
	$css_path = AJACO_DIR . 'assets/css/admin.css';
	$js_path  = AJACO_DIR . 'assets/js/llms-admin.js';

	wp_enqueue_style(
		'ajaco-admin',
		AJACO_URL . 'assets/css/admin.css',
		array(),
		file_exists( $css_path ) ? filemtime( $css_path ) : AJACO_VERSION
	);

	wp_enqueue_script(
		'ajaco-llms-admin',
		AJACO_URL . 'assets/js/llms-admin.js',
		array(),
		file_exists( $js_path ) ? filemtime( $js_path ) : AJACO_VERSION,
		true
	);

	wp_localize_script(
		'ajaco-llms-admin',
		'AjacoLlms',
		array(
			'restUrl' => esc_url_raw( rest_url( 'ajaco/v1' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'i18n'    => array(
				'loading'     => __( 'Refreshing preview…', 'aj-agent-crawl-optimizer' ),
				'failed'      => __( 'Could not build the preview.', 'aj-agent-crawl-optimizer' ),
				'expired'     => __( 'Your session expired — reload this page and try again.', 'aj-agent-crawl-optimizer' ),
				'badResponse' => __( 'The server returned an unexpected response. Check for a plugin conflict or a PHP error in your logs.', 'aj-agent-crawl-optimizer' ),
			),
		)
	);
}
