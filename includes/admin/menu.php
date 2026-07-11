<?php
/**
 * Admin: top-level "Agent Ready" menu — Dashboard (scanner) + Settings.
 *
 * @package Ajaco
 */

namespace Ajaco;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', __NAMESPACE__ . '\\add_admin_menu' );

/**
 * Get/set registered admin page hook suffixes (used to gate asset loading).
 *
 * @param string      $key   'dashboard' or 'settings'.
 * @param string|null $value Hook suffix to store, or null to read.
 * @return string
 */
function admin_page_hook( string $key, ?string $value = null ): string {
	static $hooks = array();
	if ( null !== $value ) {
		$hooks[ $key ] = $value;
	}
	return isset( $hooks[ $key ] ) ? $hooks[ $key ] : '';
}

/**
 * Register the Agent Ready menu: Dashboard (default) + Settings.
 *
 * The settings page keeps its historical slug (aj-agent-crawl-optimizer) so
 * bookmarks and the plugins-row link keep working — it just lives under the
 * new top-level menu now.
 *
 * @return void
 */
function add_admin_menu(): void {
	$cap = required_capability();

	$dashboard_hook = add_menu_page(
		__( 'Agent Ready', 'aj-agent-crawl-optimizer' ),
		__( 'Agent Ready', 'aj-agent-crawl-optimizer' ),
		$cap,
		'ajaco-dashboard',
		__NAMESPACE__ . '\\render_dashboard_page',
		'dashicons-shield',
		81
	);
	admin_page_hook( 'dashboard', (string) $dashboard_hook );

	add_submenu_page(
		'ajaco-dashboard',
		__( 'Agent Ready Dashboard', 'aj-agent-crawl-optimizer' ),
		__( 'Dashboard', 'aj-agent-crawl-optimizer' ),
		$cap,
		'ajaco-dashboard',
		__NAMESPACE__ . '\\render_dashboard_page'
	);

	$settings_hook = add_submenu_page(
		'ajaco-dashboard',
		__( 'Agent Ready Settings', 'aj-agent-crawl-optimizer' ),
		__( 'Settings', 'aj-agent-crawl-optimizer' ),
		$cap,
		'aj-agent-crawl-optimizer',
		__NAMESPACE__ . '\\render_settings_page'
	);
	admin_page_hook( 'settings', (string) $settings_hook );
}

/**
 * Admin URL of the settings screen.
 *
 * @return string
 */
function settings_page_url(): string {
	return admin_url( 'admin.php?page=aj-agent-crawl-optimizer' );
}

/**
 * Admin URL of the dashboard screen.
 *
 * @return string
 */
function dashboard_page_url(): string {
	return admin_url( 'admin.php?page=ajaco-dashboard' );
}
