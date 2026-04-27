<?php
/**
 * Admin: enqueue settings-page styles and scripts.
 *
 * Gated to the Settings → Agent-Ready screen so we don't load assets on
 * every WP admin page.
 *
 * @package AgentReady
 */

namespace AgentReady;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_admin_assets' );

/**
 * Enqueue admin CSS + JS, only on our settings page.
 *
 * @param string $hook_suffix Current admin page hook suffix.
 * @return void
 */
function enqueue_admin_assets( string $hook_suffix ): void {
	// Hook suffix produced by the options-page registration is settings_page_agent-ready.
	if ( $hook_suffix !== 'settings_page_agent-ready' ) {
		return;
	}

	$css_path = AGENT_READY_DIR . 'assets/css/admin.css';
	$js_path  = AGENT_READY_DIR . 'assets/js/admin.js';

	wp_enqueue_style(
		'agent-ready-admin',
		AGENT_READY_URL . 'assets/css/admin.css',
		array(),
		file_exists( $css_path ) ? filemtime( $css_path ) : AGENT_READY_VERSION
	);

	wp_enqueue_script(
		'agent-ready-admin',
		AGENT_READY_URL . 'assets/js/admin.js',
		array(),
		file_exists( $js_path ) ? filemtime( $js_path ) : AGENT_READY_VERSION,
		true
	);

	// Localized strings + any other PHP-derived data the JS needs.
	wp_localize_script(
		'agent-ready-admin',
		'AgentReadyAdmin',
		array(
			'i18n' => array(
				'copied' => __( 'Copied!', 'agent-ready' ),
			),
		)
	);
}
