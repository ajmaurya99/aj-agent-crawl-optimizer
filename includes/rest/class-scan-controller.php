<?php
/**
 * REST API: scan, fix, and health endpoints (namespace ajaco/v1).
 *
 * Drives the dashboard's scan → fix → re-verify loop. The health endpoint is
 * public and doubles as the RFC 9727 `status` target for the API catalog.
 *
 * @package Ajaco
 */

namespace Ajaco\Rest;

use Ajaco\Scan\Fix_Registry;
use Ajaco\Scan\Scanner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', array( __NAMESPACE__ . '\\Scan_Controller', 'register_routes' ) );

/**
 * Route registration + callbacks.
 */
class Scan_Controller {

	const REST_NAMESPACE = 'ajaco/v1';

	/**
	 * Register all routes.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/scan',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_latest_scan' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'run_scan' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
					'args'                => array(
						'checks' => array(
							'type'     => 'array',
							'items'    => array( 'type' => 'string' ),
							'required' => false,
						),
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/scan/check',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'rescan_check' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array(
					'check' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/fix',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'apply_fix' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array(
					'check' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/health',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'health' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Permission: same capability that guards the settings screen.
	 *
	 * @return bool
	 */
	public static function can_manage(): bool {
		return current_user_can( \Ajaco\required_capability() );
	}

	/**
	 * GET /scan — latest stored scan (or null).
	 *
	 * @return \WP_REST_Response
	 */
	public static function get_latest_scan(): \WP_REST_Response {
		return rest_ensure_response(
			array(
				'scan' => Scanner::get_last_scan(),
			)
		);
	}

	/**
	 * POST /scan — run a fresh scan.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function run_scan( \WP_REST_Request $request ) {
		$checks  = $request->get_param( 'checks' );
		$enabled = null;

		if ( is_array( $checks ) && ! empty( $checks ) ) {
			$known     = array_keys( Scanner::check_classes() );
			$sanitized = array_values( array_filter( array_map( 'sanitize_text_field', $checks ) ) );
			$invalid   = array_diff( $sanitized, $known );
			if ( ! empty( $invalid ) ) {
				return new \WP_Error(
					'ajaco_invalid_checks',
					sprintf( 'Invalid check names: %s', implode( ', ', $invalid ) ),
					array( 'status' => 400 )
				);
			}
			$enabled = $sanitized;
		}

		$scanner = new Scanner();
		$scan    = $scanner->run( $enabled );

		return rest_ensure_response( array( 'scan' => $scan ) );
	}

	/**
	 * POST /scan/check — re-run a single check and merge into the stored scan.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rescan_check( \WP_REST_Request $request ) {
		$check_id = sanitize_text_field( (string) $request->get_param( 'check' ) );

		$scanner = new Scanner();
		$result  = $scanner->run_one( $check_id );

		if ( null === $result ) {
			return new \WP_Error( 'ajaco_unknown_check', sprintf( 'Unknown check: %s', $check_id ), array( 'status' => 400 ) );
		}

		return rest_ensure_response( $result );
	}

	/**
	 * POST /fix — apply the one-click fix for a check, then re-scan it.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function apply_fix( \WP_REST_Request $request ) {
		$check_id = sanitize_text_field( (string) $request->get_param( 'check' ) );

		if ( ! isset( Scanner::check_classes()[ $check_id ] ) ) {
			return new \WP_Error( 'ajaco_unknown_check', sprintf( 'Unknown check: %s', $check_id ), array( 'status' => 400 ) );
		}

		$fix = Fix_Registry::apply( $check_id );
		if ( ! $fix['applied'] ) {
			return new \WP_Error( 'ajaco_no_fix', $fix['message'], array( 'status' => 422 ) );
		}

		// Verify: re-run just this check — the SKILL.md "Validate" pattern.
		$scanner = new Scanner();
		$result  = $scanner->run_one( $check_id );

		return rest_ensure_response(
			array(
				'fix'   => $fix,
				'check' => null !== $result ? $result['check'] : null,
				'scan'  => null !== $result ? $result['scan'] : null,
			)
		);
	}

	/**
	 * GET /health — public liveness endpoint (RFC 9727 `status` relation target).
	 *
	 * @return \WP_REST_Response
	 */
	public static function health(): \WP_REST_Response {
		return rest_ensure_response(
			array(
				'status'    => 'ok',
				'timestamp' => gmdate( 'c' ),
			)
		);
	}
}
