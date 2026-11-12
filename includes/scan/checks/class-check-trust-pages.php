<?php
/**
 * Scan check: Trust anchor pages (id `trustPages`, discoverability).
 *
 * Agents weigh whether a site has a real privacy policy and a way to reach
 * a human before recommending it. Resolves the pages WordPress knows about
 * (the Privacy Policy setting, about/contact pages by slug) and confirms the
 * privacy page actually serves over HTTP.
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
 * Trust pages check.
 */
class Check_Trust_Pages extends Check {

	/**
	 * Check id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'trustPages';
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
		return 'Trust pages (privacy, about, contact)';
	}

	/**
	 * Run the check.
	 *
	 * @return Check_Result
	 */
	public function run(): Check_Result {
		$evidence = array();

		$privacy_id  = (int) get_option( 'wp_page_for_privacy_policy' );
		$privacy_url = '';
		if ( $privacy_id > 0 && 'publish' === get_post_status( $privacy_id ) ) {
			$privacy_url = (string) get_permalink( $privacy_id );
		}
		$evidence[] = Evidence::parse(
			'Resolve the Privacy Policy page (Settings → Privacy)',
			'' !== $privacy_url ? 'positive' : 'negative',
			'' !== $privacy_url ? 'Published at ' . $privacy_url : 'No published Privacy Policy page is set.'
		);

		$about      = $this->first_published_page( array( 'about', 'about-us', 'about-me', 'company' ) );
		$contact    = $this->first_published_page( array( 'contact', 'contact-us', 'get-in-touch', 'support' ) );
		$evidence[] = Evidence::parse(
			'Look for about / contact pages by slug',
			( $about || $contact ) ? 'positive' : 'negative',
			( $about ? 'About: ' . get_permalink( $about ) . ' ' : 'No about page. ' ) . ( $contact ? 'Contact: ' . get_permalink( $contact ) : 'No contact page.' )
		);

		$privacy_live = false;
		if ( '' !== $privacy_url ) {
			$res          = $this->http_get( $privacy_url, 'GET ' . wp_parse_url( $privacy_url, PHP_URL_PATH ), $evidence );
			$privacy_live = 200 === $res['code'];
		}

		$details = array(
			'privacy' => $privacy_url,
			'about'   => $about ? (string) get_permalink( $about ) : '',
			'contact' => $contact ? (string) get_permalink( $contact ) : '',
		);

		$missing = array();
		if ( ! $privacy_live ) {
			$missing[] = '' === $privacy_url ? 'a published Privacy Policy page' : 'a reachable Privacy Policy page';
		}
		if ( ! $about && ! $contact ) {
			$missing[] = 'an about or contact page';
		}

		if ( empty( $missing ) ) {
			return $this->pass( 'Privacy policy is live and the site has ' . ( $contact ? 'a contact page' : 'an about page' ), $evidence, $details );
		}

		return $this->fail( 'Missing ' . implode( ' and ', $missing ), $evidence, $details );
	}

	/**
	 * First published page matching one of the slugs, or null.
	 *
	 * @param string[] $slugs Candidate slugs.
	 * @return \WP_Post|null
	 */
	private function first_published_page( array $slugs ): ?\WP_Post {
		foreach ( $slugs as $slug ) {
			$page = get_page_by_path( $slug, OBJECT, 'page' );
			if ( $page instanceof \WP_Post && 'publish' === $page->post_status ) {
				return $page;
			}
		}
		return null;
	}
}
