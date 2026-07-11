<?php
/**
 * Scan check: DNS for AI Discovery / DNS-AID (id `dnsAid`).
 *
 * Queries DNS-over-HTTPS at the well-known agent entrypoints: SVCB + HTTPS
 * records at `_index._agents.<host>`, `_a2a._agents.<host>`,
 * `_mcp._agents.<host>`, plus TXT only at `_index._agents.<host>` (7 queries
 * per domain; repeated against the apex for www. hosts). Primary resolver is
 * Cloudflare DoH with automatic fallback to Google DoH. Passes when any
 * SVCB/HTTPS answer exists at a well-known entrypoint. Mirrors the
 * isitagentready.com `dnsAid` check (draft-mozleywilliams-dnsop-dnsaid,
 * RFC 9460).
 *
 * These are external requests by design — DNS-AID lives in the DNS, not on
 * the origin.
 *
 * @package Ajaco
 */

namespace Ajaco\Scan\Checks;

use Ajaco\Scan\Check;
use Ajaco\Scan\Check_Result;
use Ajaco\Scan\Evidence;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * DNS-AID check.
 */
class Check_Dns_Aid extends Check {

	/**
	 * Primary DoH resolver (JSON API).
	 */
	const RESOLVER_CLOUDFLARE = 'https://cloudflare-dns.com/dns-query';

	/**
	 * Fallback DoH resolver (JSON API).
	 */
	const RESOLVER_GOOGLE = 'https://dns.google/resolve';

	/**
	 * Once the primary resolver fails, stick with the fallback for the rest
	 * of the run instead of re-failing on every query.
	 *
	 * @var bool
	 */
	private $use_fallback_resolver = false;

	/**
	 * Total resolver HTTP attempts made.
	 *
	 * @var int
	 */
	private $resolver_attempts = 0;

	/**
	 * Resolver HTTP attempts that failed at the network level.
	 *
	 * @var int
	 */
	private $resolver_network_failures = 0;

	/**
	 * Last resolver error message seen.
	 *
	 * @var string
	 */
	private $last_error = '';

	/**
	 * Check id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'dnsAid';
	}

	/**
	 * Category.
	 *
	 * @return string
	 */
	public function get_category(): string {
		return self::CATEGORY_DISCOVERABILITY;
	}

	/**
	 * Display name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'DNS for AI Discovery (DNS-AID)';
	}

	/**
	 * Run the check.
	 *
	 * @return Check_Result
	 */
	public function run(): Check_Result {
		$evidence = array();

		$host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		if ( '' === $host ) {
			return $this->unable( 'Could not determine the site host for DNS queries', $evidence );
		}

		$domains = array( $host );
		// The apex fallback applies to www. hosts only, not other subdomains.
		if ( 0 === strpos( $host, 'www.' ) && '' !== substr( $host, 4 ) ) {
			$domains[] = substr( $host, 4 );
		}

		$evidence[] = Evidence::parse(
			'Derive DNS-AID entrypoints',
			'neutral',
			sprintf(
				'Querying SVCB + HTTPS at _index/_a2a/_mcp._agents (TXT at _index only) for: %s.',
				implode( ', ', $domains )
			)
		);

		$queries_attempted = array();
		$dnssec_validated  = false;
		$service_records   = 0;
		$alias_records     = 0;
		$txt_entries       = array();
		$records           = array();
		$answered          = 0;

		foreach ( $domains as $domain ) {
			$entrypoints = array(
				'_index._agents.' . $domain,
				'_a2a._agents.' . $domain,
				'_mcp._agents.' . $domain,
			);

			foreach ( $entrypoints as $name ) {
				$types = array( 'SVCB', 'HTTPS' );
				if ( 0 === strpos( $name, '_index.' ) ) {
					$types[] = 'TXT';
				}

				foreach ( $types as $type ) {
					$queries_attempted[] = $type . ' ' . $name;

					$doc = $this->doh_query( $name, $type, $evidence );

					if ( null === $doc ) {
						// Both resolvers unreachable so far — no point burning
						// through the remaining queries offline.
						if ( 0 === $answered
							&& $this->resolver_attempts >= 2
							&& $this->resolver_attempts === $this->resolver_network_failures ) {
							return $this->unable( 'Could not reach DNS-over-HTTPS resolvers — ' . $this->last_error, $evidence );
						}
						continue;
					}

					$answered++;

					if ( ! empty( $doc['AD'] ) ) {
						$dnssec_validated = true;
					}

					$answers = isset( $doc['Answer'] ) && is_array( $doc['Answer'] ) ? $doc['Answer'] : array();
					foreach ( $answers as $answer ) {
						if ( ! is_array( $answer ) ) {
							continue;
						}
						$answer_type = isset( $answer['type'] ) ? (int) $answer['type'] : 0;
						$data        = isset( $answer['data'] ) ? (string) $answer['data'] : '';

						if ( 64 === $answer_type || 65 === $answer_type ) {
							// RFC 9460: SvcPriority 0 = AliasMode, >0 = ServiceMode.
							$tokens = explode( ' ', trim( $data ) );
							if ( '0' === $tokens[0] ) {
								$alias_records++;
							} else {
								$service_records++;
							}
							$records[] = $this->record_entry( $answer, 64 === $answer_type ? 'SVCB' : 'HTTPS' );
						} elseif ( 16 === $answer_type && 'TXT' === $type ) {
							$txt_entries[] = trim( $data, '" ' );
							$records[]     = $this->record_entry( $answer, 'TXT' );
						}
					}
				}
			}
		}

		if ( 0 === $answered ) {
			return $this->unable( 'Could not reach DNS-over-HTTPS resolvers — ' . $this->last_error, $evidence );
		}

		$details = array(
			'domainsChecked'     => $domains,
			'queriesAttempted'   => $queries_attempted,
			'dnssecValidated'    => $dnssec_validated,
			'serviceRecordCount' => $service_records,
			'aliasRecordCount'   => $alias_records,
			'txtIndexEntryCount' => count( $txt_entries ),
			'txtIndexEntries'    => $txt_entries,
			'records'            => $records,
		);

		$svcb_https_total = $service_records + $alias_records;

		if ( $svcb_https_total > 0 ) {
			$evidence[] = Evidence::parse(
				'Evaluate DNS-AID records',
				'positive',
				sprintf(
					'%d SVCB/HTTPS record(s) found at well-known _agents entrypoints%s.',
					$svcb_https_total,
					$dnssec_validated ? ' (DNSSEC validated)' : ''
				)
			);
			return $this->pass(
				sprintf( 'DNS-AID records found: %d SVCB/HTTPS record(s) at _agents entrypoints', $svcb_https_total ),
				$evidence,
				$details
			);
		}

		$evidence[] = Evidence::parse(
			'Evaluate DNS-AID records',
			'negative',
			sprintf( 'No SVCB/HTTPS answers across %d well-known entrypoint queries.', count( $queries_attempted ) )
		);

		return $this->fail( 'No DNS-AID records found at _index/_a2a/_mcp._agents entrypoints', $evidence, $details );
	}

	/**
	 * Run one DoH JSON query, falling back from Cloudflare to Google.
	 *
	 * @param string $name     Query name (e.g. _index._agents.example.com).
	 * @param string $type     Query type (SVCB|HTTPS|TXT).
	 * @param array  $evidence Evidence array, appended by reference.
	 * @return array|null Decoded DNS JSON document, or null on resolver failure.
	 */
	private function doh_query( string $name, string $type, array &$evidence ): ?array {
		$label = 'DoH ' . $type . ' ' . $name;
		$args  = array(
			'headers' => array(
				'accept' => 'application/dns-json',
			),
		);

		if ( ! $this->use_fallback_resolver ) {
			$url = self::RESOLVER_CLOUDFLARE . '?name=' . rawurlencode( $name ) . '&type=' . $type;
			$doc = $this->resolver_fetch( $url, $label, $evidence, $args );
			if ( null !== $doc ) {
				return $doc;
			}
			$this->use_fallback_resolver = true;
			$label                      .= ' (fallback resolver)';
		}

		$url = self::RESOLVER_GOOGLE . '?name=' . rawurlencode( $name ) . '&type=' . $type;
		return $this->resolver_fetch( $url, $label, $evidence, $args );
	}

	/**
	 * Fetch one resolver URL and decode the DNS JSON document.
	 *
	 * @param string $url      Resolver query URL.
	 * @param string $label    Evidence label.
	 * @param array  $evidence Evidence array, appended by reference.
	 * @param array  $args     Extra wp_remote_get args.
	 * @return array|null
	 */
	private function resolver_fetch( string $url, string $label, array &$evidence, array $args ): ?array {
		$this->resolver_attempts++;

		$response = $this->http_get( $url, $label, $evidence, $args );

		if ( '' !== $response['error'] && 0 === $response['code'] ) {
			$this->resolver_network_failures++;
			$this->last_error = $response['error'];
			return null;
		}

		if ( 200 !== $response['code'] || '' === $response['body'] ) {
			$this->last_error = 'HTTP ' . $response['code'] . ' from resolver';
			return null;
		}

		$doc = json_decode( $response['body'], true );
		if ( ! is_array( $doc ) || ! isset( $doc['Status'] ) ) {
			$this->last_error = 'Resolver returned an invalid DNS JSON document';
			return null;
		}

		return $doc;
	}

	/**
	 * Normalize a DoH answer into a details record entry.
	 *
	 * @param array  $answer DoH Answer element.
	 * @param string $type   Record type label (SVCB|HTTPS|TXT).
	 * @return array
	 */
	private function record_entry( array $answer, string $type ): array {
		return array(
			'name' => isset( $answer['name'] ) ? rtrim( (string) $answer['name'], '.' ) : '',
			'type' => $type,
			'ttl'  => isset( $answer['TTL'] ) ? (int) $answer['TTL'] : 0,
			'data' => isset( $answer['data'] ) ? (string) $answer['data'] : '',
		);
	}
}
