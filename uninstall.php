<?php
/**
 * AJ Agent Crawl Optimizer uninstall handler.
 *
 * Runs only when the plugin is uninstalled (deleted from the Plugins screen),
 * not on deactivation. Removes every option the plugin created so the database
 * is left clean.
 *
 * @package Ajaco
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$options = array(
	'ajaco_markdown_enabled',
	'ajaco_content_signals_enabled',
	'ajaco_api_catalog_enabled',
	'ajaco_mcp_server_card_enabled',
	'ajaco_agent_skills_index_enabled',
	'ajaco_webmcp_enabled',
	'ajaco_json_ld_enabled',
	'ajaco_openapi_enabled',
	'ajaco_indexnow_enabled',
	'ajaco_llms_txt_enabled',
	'ajaco_ai_bot_rules_enabled',
	'ajaco_auth_md_enabled',
	'ajaco_ai_bot_policy',
	'ajaco_content_signal_prefs',
	'ajaco_indexnow_key',
	// Wizard state (persistent options since 1.0.1).
	'ajaco_show_wizard',
	'ajaco_wizard_done',
	// v2: the stored self-scan result.
	'ajaco_last_scan',
	// v2: the llms.txt curation config (sections, intro, custom markdown).
	'ajaco_llms_config',
);

// v2: per-post llms.txt curation meta. Uninstall (unlike Reset) removes every
// trace of the plugin, including the meta rows it wrote on posts.
$post_meta_keys = array(
	'_ajaco_llms_exclude',
	'_ajaco_llms_summary',
);

$transients = array(
	'ajaco_reset_notice',
	'ajaco_openapi_cache',
	'ajaco_llms_txt_cache',
	'ajaco_llms_full_txt_cache',
	// Pre-1.0.1 installs stored wizard state in transients.
	'ajaco_show_wizard',
	'ajaco_wizard_applied',
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
		foreach ( $post_meta_keys as $post_meta_key ) {
			delete_post_meta_by_key( $post_meta_key );
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
	foreach ( $post_meta_keys as $post_meta_key ) {
		delete_post_meta_by_key( $post_meta_key );
	}
}
