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
 * Which option fields the submitted form actually rendered.
 *
 * WordPress (wp-admin/options.php) force-updates EVERY registered option of a
 * submitted group, using null for any option missing from the POST. For a checkbox,
 * "missing" normally means "the user unchecked it" — but it also means "this
 * form never had that field", which happens whenever the open page was
 * rendered by an older version of the plugin (or the POST was truncated by
 * PHP's max_input_vars). In that case WordPress would silently switch off
 * features the user never touched, and wipe array options wholesale.
 *
 * Our forms therefore declare what they rendered, in a hidden
 * `ajaco_rendered_fields` input. Anything not on that list is left alone.
 *
 * @return string[]|null Rendered option names, or null when this is not one of
 *                       our settings-form saves (REST, WP-CLI, programmatic).
 */
function submitted_form_fields(): ?array {
	// Nonce verification is options.php's job; it runs before any sanitize
	// callback fires. We only read the POST to decide what NOT to overwrite.
	// phpcs:disable WordPress.Security.NonceVerification.Missing
	if ( empty( $_POST['option_page'] ) ) {
		return null;
	}

	$page = sanitize_text_field( wp_unslash( $_POST['option_page'] ) );
	if ( ! in_array( $page, array( 'ajaco_settings', 'ajaco_llms_settings' ), true ) ) {
		return null;
	}

	if ( ! isset( $_POST['ajaco_rendered_fields'] ) ) {
		// One of our groups, but the form predates this marker — we cannot tell
		// which fields it had, so treat every absent option as "not rendered"
		// and preserve it. Losing an unintended un-check on one stale save is
		// far better than silently disabling features.
		return array();
	}

	$raw = sanitize_text_field( wp_unslash( $_POST['ajaco_rendered_fields'] ) );
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	return array_values( array_filter( array_map( 'trim', explode( ',', $raw ) ) ) );
}

/**
 * Whether the submitted form rendered a field for this option.
 *
 * @param string $option Option name.
 * @return bool True when the option may be written from this request.
 */
function field_was_rendered( string $option ): bool {
	$fields = submitted_form_fields();
	if ( null === $fields ) {
		// Not a settings-form save (REST, WP-CLI, Fix now) — normal behavior.
		return true;
	}
	return in_array( $option, $fields, true );
}

/**
 * Wrap a sanitize callback so an option the submitted form never rendered
 * keeps its stored value instead of being reset.
 *
 * @param string   $option    Option name.
 * @param callable $sanitizer Real sanitize callback.
 * @return callable
 */
function guard_sanitizer( string $option, callable $sanitizer ): callable {
	return function ( $value ) use ( $option, $sanitizer ) {
		if ( ! field_was_rendered( $option ) ) {
			return get_option( $option );
		}
		return call_user_func( $sanitizer, $value );
	};
}

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
				'sanitize_callback' => guard_sanitizer( $option, 'rest_sanitize_boolean' ),
				'default'           => false,
			)
		);
	}

	register_setting(
		'ajaco_settings',
		'ajaco_indexnow_key',
		array(
			'type'              => 'string',
			'sanitize_callback' => guard_sanitizer( 'ajaco_indexnow_key', __NAMESPACE__ . '\\sanitize_indexnow_key' ),
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
			'sanitize_callback' => guard_sanitizer( 'ajaco_ai_bot_policy', __NAMESPACE__ . '\\sanitize_ai_bot_policy' ),
			'default'           => array(),
		)
	);

	register_setting(
		'ajaco_settings',
		'ajaco_content_signal_prefs',
		array(
			'type'              => 'array',
			'sanitize_callback' => guard_sanitizer( 'ajaco_content_signal_prefs', __NAMESPACE__ . '\\sanitize_content_signal_prefs' ),
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
			'sanitize_callback' => guard_sanitizer( 'ajaco_llms_config', __NAMESPACE__ . '\\sanitize_llms_config' ),
			'default'           => array(),
		)
	);
}
