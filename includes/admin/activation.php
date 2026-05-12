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
	// Trigger the one-time Quick Setup wizard on the next visit to the
	// settings page. 5-minute window is enough for the user to navigate over;
	// after that the wizard quietly drops away and the normal page renders.
	set_transient( WIZARD_TRANSIENT, 1, 5 * MINUTE_IN_SECONDS );

	// Flush cached endpoint outputs so the first request after (re)activation
	// always reflects the current plugin code, not a stale pre-update body.
	delete_transient( 'ajaco_openapi_cache' );
	delete_transient( 'ajaco_llms_txt_cache' );
}

/**
 * Add a Settings link to the plugin's row on the Plugins screen.
 *
 * @param array $links Existing action links.
 * @return array
 */
function plugin_action_links( array $links ): array {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'options-general.php?page=aj-agent-crawl-optimizer' ) ),
		esc_html__( 'Settings', 'aj-agent-crawl-optimizer' )
	);
	array_unshift( $links, $settings_link );
	return $links;
}
