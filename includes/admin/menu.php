<?php
/**
 * Admin: register the settings page under Settings → Agent-Ready.
 *
 * @package AgentReady
 */

namespace AgentReady;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', __NAMESPACE__ . '\\add_admin_menu' );

/**
 * Add the Agent-Ready submenu under Settings.
 *
 * @return void
 */
function add_admin_menu(): void {
	add_options_page(
		__( 'Agent-Ready', 'agent-ready' ),
		__( 'Agent-Ready', 'agent-ready' ),
		required_capability(),
		'agent-ready',
		__NAMESPACE__ . '\\render_settings_page'
	);
}
