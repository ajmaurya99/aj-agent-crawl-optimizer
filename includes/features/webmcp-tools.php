<?php
/**
 * Feature: WebMCP Tools.
 *
 * Enqueues a frontend script that registers tools via
 * `navigator.modelContext.provideContext()` — the W3C WebMCP draft API.
 *
 * @see https://webmachinelearning.github.io/webmcp/
 *
 * @package AgentReady
 */

namespace AgentReady;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_webmcp_script' );

/**
 * Enqueue the WebMCP tools script.
 *
 * @return void
 */
function enqueue_webmcp_script(): void {
	if ( ! is_feature_enabled( 'webmcp' ) ) {
		return;
	}

	$asset_path = AGENT_READY_DIR . 'assets/js/webmcp-tools.js';
	if ( ! file_exists( $asset_path ) ) {
		return;
	}

	$version = filemtime( $asset_path );

	wp_enqueue_script(
		'agent-ready-webmcp',
		AGENT_READY_URL . 'assets/js/webmcp-tools.js',
		array(),
		$version,
		true
	);

	wp_localize_script(
		'agent-ready-webmcp',
		'AgentReadyWebMCP',
		array(
			'apiUrl' => rest_url( '/' ),
		)
	);
}
