<?php
/**
 * Admin: register all plugin settings with the WP Settings API.
 *
 * Settings group: `agent_ready_settings`. All toggles default to false
 * (opt-in). The IndexNow API key is sanitized via sanitize_indexnow_key().
 *
 * @package AgentReady
 */

namespace AgentReady;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_init', __NAMESPACE__ . '\\register_settings' );

/**
 * Register all settings with the Settings API.
 *
 * @return void
 */
function register_settings(): void {
	$boolean_options = array(
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
	);

	foreach ( $boolean_options as $option ) {
		register_setting(
			'agent_ready_settings',
			$option,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);
	}

	register_setting(
		'agent_ready_settings',
		'agent_ready_indexnow_key',
		array(
			'type'              => 'string',
			'sanitize_callback' => __NAMESPACE__ . '\\sanitize_indexnow_key',
			'default'           => '',
		)
	);
}
