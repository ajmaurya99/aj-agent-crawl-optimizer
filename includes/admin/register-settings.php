<?php
/**
 * Admin: register all plugin settings with the WP Settings API.
 *
 * Settings groups: `ajaco_settings` (feature toggles, saved from the Settings
 * screen) and `ajaco_llms_settings` (the llms.txt curation config, saved from
 * its own screen). All toggles default to false (opt-in). The IndexNow API key
 * is sanitized via sanitize_indexnow_key().
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

	// Array options in the `ajaco_settings` group are safe to register ONLY
	// because the settings page ALWAYS renders their fields (options.php
	// force-updates every registered option of a submitted group — an option
	// without form fields would be wiped on each Save).
	register_setting(
		'ajaco_settings',
		'ajaco_ai_bot_policy',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'Ajaco\\sanitize_ai_bot_policy',
			'default'           => array(),
		)
	);

	register_setting(
		'ajaco_settings',
		'ajaco_content_signal_prefs',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'Ajaco\\sanitize_content_signal_prefs',
			'default'           => array(),
		)
	);

	// The llms.txt curation config lives in its OWN group, saved from its own
	// screen. It must NOT join `ajaco_settings`: options.php force-updates every
	// registered option in a submitted group, so a shared group would let the
	// Settings screen (which renders no curation fields) wipe the whole config
	// on every save — and vice versa.
	register_setting(
		'ajaco_llms_settings',
		'ajaco_llms_config',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'Ajaco\\sanitize_llms_config',
			'default'           => array(),
		)
	);
}
