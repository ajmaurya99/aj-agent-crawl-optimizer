<?php
/**
 * Scan engine: value object for a single check's outcome.
 *
 * Mirrors the result shape of Cloudflare's Agent Readiness scanner
 * (isitagentready.com) so results are directly comparable: status, message,
 * an evidence timeline (fetch/parse/conclude steps), an optional structured
 * details object, and the run duration.
 *
 * @package Ajaco
 */

namespace Ajaco\Scan;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Immutable-ish result of one check run.
 */
class Check_Result {

	const STATUS_PASS    = 'pass';
	const STATUS_FAIL    = 'fail';
	const STATUS_NEUTRAL = 'neutral';
	const STATUS_UNABLE  = 'unableToCheck';

	/**
	 * One of the STATUS_* constants.
	 *
	 * @var string
	 */
	public $status;

	/**
	 * Human-readable one-line outcome.
	 *
	 * @var string
	 */
	public $message;

	/**
	 * Evidence timeline steps (see Evidence).
	 *
	 * @var array<int, array>
	 */
	public $evidence;

	/**
	 * Check-specific structured data (e.g. checkedBots, skillCount).
	 *
	 * @var array|null
	 */
	public $details;

	/**
	 * Wall-clock duration of the check run, in milliseconds.
	 *
	 * @var int
	 */
	public $duration_ms = 0;

	/**
	 * Constructor.
	 *
	 * @param string     $status   One of the STATUS_* constants.
	 * @param string     $message  One-line outcome.
	 * @param array      $evidence Evidence steps.
	 * @param array|null $details  Structured details, or null.
	 */
	public function __construct( string $status, string $message, array $evidence = array(), $details = null ) {
		$this->status   = $status;
		$this->message  = $message;
		$this->evidence = $evidence;
		$this->details  = $details;
	}

	/**
	 * Serialize for storage / REST / JS consumption.
	 *
	 * @return array
	 */
	public function to_array(): array {
		$out = array(
			'status'     => $this->status,
			'message'    => $this->message,
			'evidence'   => $this->evidence,
			'durationMs' => $this->duration_ms,
		);
		if ( null !== $this->details ) {
			$out['details'] = $this->details;
		}
		return $out;
	}
}
