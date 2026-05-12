<?php
/**
 * Admin: register the settings page under Settings → AJ Agent Crawl Optimizer.
 *
 * @package Ajaco
 */

namespace Ajaco;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', __NAMESPACE__ . '\\add_admin_menu' );

/**
 * Add the AJ Agent Crawl Optimizer submenu under Settings.
 *
 * @return void
 */
function add_admin_menu(): void {
	add_options_page(
		__( 'AJ Agent Crawl Optimizer', 'aj-agent-crawl-optimizer' ),
		__( 'AJ Agent Crawl Optimizer', 'aj-agent-crawl-optimizer' ),
		required_capability(),
		'aj-agent-crawl-optimizer',
		__NAMESPACE__ . '\\render_settings_page'
	);
}
