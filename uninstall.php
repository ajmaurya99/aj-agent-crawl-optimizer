<?php
/**
 * Agent-Ready uninstall handler.
 *
 * Runs only when the plugin is uninstalled (deleted from the Plugins screen),
 * not on deactivation. Removes every option the plugin created so the database
 * is left clean.
 *
 * @package AgentReady
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$options = array(
	'agent_ready_markdown_enabled',
	'agent_ready_content_signals_enabled',
	'agent_ready_api_catalog_enabled',
	'agent_ready_mcp_server_card_enabled',
	'agent_ready_agent_skills_index_enabled',
	'agent_ready_webmcp_enabled',
	'agent_ready_json_ld_enabled',
	'agent_ready_openapi_enabled',
	'agent_ready_indexnow_enabled',
	'agent_ready_llms_txt_enabled',
	'agent_ready_indexnow_key',
);

$transients = array(
	'agent_ready_activation_notice',
	'agent_ready_reset_notice',
	'agent_ready_openapi_cache',
	'agent_ready_llms_txt_cache',
	'agent_ready_show_wizard',
	'agent_ready_wizard_applied',
);

if ( is_multisite() ) {
	$sites = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $sites as $site_id ) {
		switch_to_blog( $site_id );
		foreach ( $options as $option ) {
			delete_option( $option );
		}
		foreach ( $transients as $transient ) {
			delete_transient( $transient );
		}
		restore_current_blog();
	}
} else {
	foreach ( $options as $option ) {
		delete_option( $option );
	}
	foreach ( $transients as $transient ) {
		delete_transient( $transient );
	}
}
