<?php
/**
 * Scan check: Markdown frontmatter (id `markdownFrontmatter`,
 * contentAccessibility).
 *
 * The Markdown representation of the homepage should open with a YAML
 * frontmatter block (title, url, …) so an agent can identify the document
 * without parsing the body.
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
 * Markdown frontmatter check.
 */
class Check_Markdown_Frontmatter extends Check {

	/**
	 * Check id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'markdownFrontmatter';
	}

	/**
	 * Category.
	 *
	 * @return string
	 */
	public function get_category(): string {
		return self::CATEGORY_CONTENT;
	}

	/**
	 * Display name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'Markdown frontmatter';
	}

	/**
	 * Run the check.
	 *
	 * @return Check_Result
	 */
	public function run(): Check_Result {
		$evidence = array();
		$res      = $this->http_get(
			$this->origin() . '/',
			'GET / (Accept: text/markdown)',
			$evidence,
			array( 'headers' => array( 'Accept' => 'text/markdown' ) )
		);

		if ( '' !== $res['error'] && 0 === $res['code'] ) {
			return $this->unable( 'Could not reach the homepage — ' . $res['error'], $evidence );
		}

		$ctype = isset( $res['headers']['content-type'] ) ? $res['headers']['content-type'] : '';
		if ( 0 !== stripos( $ctype, 'text/markdown' ) ) {
			return $this->fail( 'Homepage does not serve Markdown for Accept: text/markdown — enable Markdown Negotiation first', $evidence );
		}

		$body  = ltrim( $res['body'] );
		$lines = array_slice( preg_split( '/\r\n|\r|\n/', $body ), 0, 40 );
		$keys  = array();
		$ok    = false;

		if ( isset( $lines[0] ) && '---' === trim( $lines[0] ) ) {
			foreach ( array_slice( $lines, 1 ) as $line ) {
				if ( '---' === trim( $line ) ) {
					$ok = true;
					break;
				}
				if ( preg_match( '/^([A-Za-z_][\w-]*)\s*:/', $line, $k ) ) {
					$keys[] = $k[1];
				}
			}
		}

		if ( ! $ok || empty( $keys ) ) {
			$evidence[] = Evidence::parse( 'Inspect the first lines of the Markdown body', 'negative', 'No `---` … `---` frontmatter block with keys at the top of the document.' );
			return $this->fail( 'Markdown body has no frontmatter metadata block', $evidence, array( 'keys' => $keys ) );
		}

		$evidence[] = Evidence::parse( 'Inspect the first lines of the Markdown body', 'positive', 'Frontmatter keys: ' . implode( ', ', $keys ) . '.' );
		return $this->pass( 'Markdown body opens with frontmatter (' . implode( ', ', $keys ) . ')', $evidence, array( 'keys' => $keys ) );
	}
}
