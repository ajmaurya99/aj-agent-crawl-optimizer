<?php
/**
 * Admin: register all plugin settings with the WP Settings API.
 *
 * Settings group: `ajaco_settings`. All toggles default to false
 * (opt-in). The IndexNow API key is sanitized via sanitize_indexnow_key().
 *
 * @package Ajaco
 */

namespace Ajaco;

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
	);

	foreach ( $boolean_options as $option ) {
		register_setting(
			'ajaco_settings',
			$option,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);
	}

	register_setting(
		'ajaco_settings',
		'ajaco_indexnow_key',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'Ajaco\\sanitize_indexnow_key',
			'default'           => '',
		)
	);

	// NOTE: ajaco_ai_bot_policy (per-bot allow|block map) is deliberately NOT
	// registered in the `ajaco_settings` group — the settings form renders no
	// field for it, and options.php force-updates every registered option of a
	// submitted group, which would wipe the stored policy on every Save.
	// Writes to it are sanitized at the write sites via sanitize_ai_bot_policy().
}
