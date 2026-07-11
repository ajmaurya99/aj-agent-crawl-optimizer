<?php
/**
 * Admin: plugin lifecycle hooks — activation cache reset + plugins-row Settings link.
 *
 * @package Ajaco
 */

namespace Ajaco;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

register_activation_hook( AJACO_FILE, __NAMESPACE__ . '\\on_activation' );
add_filter( 'plugin_action_links_' . plugin_basename( AJACO_FILE ), __NAMESPACE__ . '\\plugin_action_links' );

/**
 * Activation tasks: arm the one-time Quick Setup wizard for the user's next
 * visit to the settings page, and flush cached endpoint bodies so the first
 * request after (re)activation reflects the current plugin code.
 *
 * @return void
 */
function on_activation(): void {
	// Arm the Quick Setup wizard persistently (an option, not a timed
	// transient) so WP-CLI/bulk activations and slow admins don't lose it.
	// Re-activations after the wizard has already run don't re-arm it — the
	// user's configuration stands.
	if ( ! get_option( WIZARD_DONE_OPTION ) ) {
		update_option( WIZARD_PENDING_OPTION, 1, false );
	}

	// Flush cached endpoint outputs so the first request after (re)activation
	// always reflects the current plugin code, not a stale pre-update body.
	delete_transient( 'ajaco_openapi_cache' );
	delete_transient( 'ajaco_llms_txt_cache' );
	delete_transient( 'ajaco_llms_full_txt_cache' );
}

/**
 * Add a Settings link to the plugin's row on the Plugins screen.
 *
 * @param array $links Existing action links.
 * @return array
 */
function plugin_action_links( array $links ): array {
	$dashboard_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( dashboard_page_url() ),
		esc_html__( 'Dashboard', 'aj-agent-crawl-optimizer' )
	);
	$settings_link  = sprintf(
		'<a href="%s">%s</a>',
		esc_url( settings_page_url() ),
		esc_html__( 'Settings', 'aj-agent-crawl-optimizer' )
	);
	array_unshift( $links, $dashboard_link, $settings_link );
	return $links;
}
