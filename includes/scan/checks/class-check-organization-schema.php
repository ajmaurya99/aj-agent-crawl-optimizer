<?php
/**
 * Scan check: Organization schema completeness (id `organizationSchema`,
 * discoverability).
 *
 * Reads the homepage's JSON-LD and looks for an Organization node that
 * links the site to its official profiles (sameAs) and gives agents a way
 * to reach it (contactPoint or a postal address). Hosted scanners reward
 * both; an Organization with only a name earns nothing.
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
 * Organization schema check.
 */
class Check_Organization_Schema extends Check {

	/**
	 * Check id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'organizationSchema';
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
		return 'Organization schema (sameAs, contact)';
	}

	/**
	 * Run the check.
	 *
	 * @return Check_Result
	 */
	public function run(): Check_Result {
		$evidence = array();
		$res      = $this->http_get( $this->origin() . '/', 'GET /', $evidence );

		if ( '' !== $res['error'] && 0 === $res['code'] ) {
			return $this->unable( 'Could not reach the homepage — ' . $res['error'], $evidence );
		}
		if ( 200 !== $res['code'] ) {
			return $this->fail( 'Homepage returned HTTP ' . $res['code'], $evidence );
		}

		$blocks = array();
		if ( preg_match_all( '#<script\b[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $res['body'], $m ) ) {
			$blocks = $m[1];
		}

		$org = null;
		foreach ( $blocks as $raw ) {
			$json = json_decode( html_entity_decode( trim( $raw ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ), true );
			if ( ! is_array( $json ) ) {
				$json = json_decode( trim( $raw ), true );
			}
			if ( ! is_array( $json ) ) {
				continue;
			}
			$org = $this->find_organization( $json );
			if ( null !== $org ) {
				break;
			}
		}

		if ( null === $org ) {
			$evidence[] = Evidence::parse(
				'Find an Organization node in JSON-LD',
				'negative',
				count( $blocks ) . ' JSON-LD block(s) on the homepage, none describing an Organization.'
			);
			return $this->fail( 'No Organization JSON-LD on the homepage', $evidence, array( 'blocks' => count( $blocks ) ) );
		}

		$same_as = isset( $org['sameAs'] ) ? (array) $org['sameAs'] : array();
		$same_as = array_values( array_filter( $same_as, 'is_string' ) );
		$contact = ! empty( $org['contactPoint'] ) || ! empty( $org['email'] ) || ! empty( $org['telephone'] );
		$address = ! empty( $org['address'] );
		$logo    = ! empty( $org['logo'] );

		$details = array(
			'name'    => isset( $org['name'] ) ? $org['name'] : '',
			'sameAs'  => count( $same_as ),
			'contact' => $contact,
			'address' => $address,
			'logo'    => $logo,
		);

		$evidence[] = Evidence::parse(
			'Inspect the Organization node',
			( count( $same_as ) > 0 && ( $contact || $address ) ) ? 'positive' : 'negative',
			sprintf(
				'sameAs: %d link(s); contact point: %s; address: %s; logo: %s.',
				count( $same_as ),
				$contact ? 'yes' : 'no',
				$address ? 'yes' : 'no',
				$logo ? 'yes' : 'no'
			)
		);

		$missing = array();
		if ( 0 === count( $same_as ) ) {
			$missing[] = 'sameAs links to official profiles';
		}
		if ( ! $contact && ! $address ) {
			$missing[] = 'a contactPoint or postal address';
		}

		if ( ! empty( $missing ) ) {
			return $this->fail( 'Organization schema is missing ' . implode( ' and ', $missing ), $evidence, $details );
		}

		return $this->pass(
			sprintf( 'Organization schema links %d official profile(s) and gives agents a way to make contact', count( $same_as ) ),
			$evidence,
			$details
		);
	}

	/**
	 * Depth-first search for a node whose @type is (or includes) Organization
	 * or a subtype.
	 *
	 * @param array $node JSON-LD node or document.
	 * @param int   $depth Recursion guard.
	 * @return array|null
	 */
	private function find_organization( array $node, int $depth = 0 ): ?array {
		if ( $depth > 6 ) {
			return null;
		}
		if ( isset( $node['@type'] ) ) {
			$types = array_map( 'strval', (array) $node['@type'] );
			foreach ( $types as $type ) {
				if ( preg_match( '/Organization$|^Corporation$|^LocalBusiness$|Business$|^NewsMediaOrganization$/', $type ) ) {
					return $node;
				}
			}
		}
		foreach ( $node as $key => $value ) {
			if ( is_array( $value ) ) {
				$found = $this->find_organization( $value, $depth + 1 );
				if ( null !== $found ) {
					return $found;
				}
			}
		}
		return null;
	}
}
