<?php
/**
 * REST API: llms.txt live preview (namespace ajaco/v1).
 *
 * Powers the curation screen's preview pane: the admin POSTs the *unsaved*
 * config from the form and gets back the exact Markdown body that /llms.txt
 * would serve, plus the size numbers that matter to an agent's context window
 * (bytes, rough tokens, entry count).
 *
 * Nothing here writes: the option is saved the normal way through options.php.
 *
 * @package Ajaco
 */

namespace Ajaco\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', array( __NAMESPACE__ . '\\Llms_Controller', 'register_routes' ) );

/**
 * Route registration + callbacks for the llms.txt curation preview.
 */
class Llms_Controller {

	const REST_NAMESPACE = 'ajaco/v1';

	/**
	 * Register the preview route.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/llms/preview',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'preview' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array(
					'config' => array(
						'type'        => 'object',
						'required'    => false,
						'description' => 'Unsaved llms.txt curation config to preview. Omit to preview the saved config.',
					),
				),
			)
		);
	}

	/**
	 * Permission: same capability that guards the curation screen.
	 *
	 * @return bool
	 */
	public static function can_manage(): bool {
		return current_user_can( \Ajaco\required_capability() );
	}

	/**
	 * POST /llms/preview — render the llms.txt body for a candidate config.
	 *
	 * The config is passed through untouched: build_llms_txt() runs it through
	 * sanitize_llms_config() itself, which is the single source of truth for
	 * what a valid config is (unknown post types dropped, counts clamped,
	 * text stripped). Duplicating that here would let the preview drift from
	 * what actually gets served.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function preview( \WP_REST_Request $request ): \WP_REST_Response {
		$config = $request->get_param( 'config' );

		// A missing (or malformed) param means "preview what's saved".
		$markdown = \Ajaco\build_llms_txt( is_array( $config ) ? $config : null );

		$bytes   = strlen( $markdown );
		$entries = preg_match_all( '/^- \[/m', $markdown );

		return rest_ensure_response(
			array(
				'markdown'   => $markdown,
				'bytes'      => $bytes,
				// Rough context-window math: ~4 bytes per token is the usual
				// English-prose approximation. Good enough to warn "this file is
				// huge", which is all the number is for.
				'tokens'     => (int) ceil( $bytes / 4 ),
				'entryCount' => is_int( $entries ) ? $entries : 0,
			)
		);
	}
}
