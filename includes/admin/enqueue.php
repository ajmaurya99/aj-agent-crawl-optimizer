<?php
/**
 * Admin: enqueue assets for the Agent Ready screens.
 *
 * Settings screen keeps the legacy admin.css/js pair; the Dashboard gets the
 * v2 scanner app (dashboard.css/js) plus its localized data payload. Both are
 * gated to their own hook suffix so nothing loads on unrelated admin pages.
 *
 * @package Ajaco
 */

namespace Ajaco;

use Ajaco\Scan\Check_Info;
use Ajaco\Scan\Fix_Registry;
use Ajaco\Scan\Scanner;

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
		array(),
		file_exists( $js_path ) ? filemtime( $js_path ) : AJACO_VERSION,
		true
	);

	// Per-check static metadata for card rendering + copy prompts.
	$meta   = array();
	$checks = Scanner::get_checks();
	foreach ( $checks as $id => $check ) {
		$info        = Check_Info::get( $id );
		$fixes       = Fix_Registry::all();
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
			'checkMeta'      => $meta,
			'defaultChecks'  => Scanner::default_check_ids(),
			'levelNames'     => \Ajaco\Scan\Level::names(),
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
			),
		)
	);
}
