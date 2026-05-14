<?php
/**
 * Feature: JSON-LD Schema.org structured data.
 *
 * Outputs `<script type="application/ld+json">` in <head> with WebSite,
 * Organization, Article (singular posts/pages), BreadcrumbList (non-front
 * singular), and FAQPage (auto-detected from heading/Q&A patterns).
 *
 * Auto-suppresses when an SEO plugin is active (see active_seo_plugin())
 * to prevent duplicate structured-data warnings.
 *
 * @package Ajaco
 */

namespace Ajaco;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_head', __NAMESPACE__ . '\\output_json_ld_schema', 1 );

/**
 * Extract Q&A pairs from post content for FAQPage schema.
 *
 * Walks the DOM looking first for <dl><dt>/<dd> definition lists, then for
 * question-shaped headings (ending in `?` or starting with what/why/how/...)
 * followed by sibling content up to the next heading.
 *
 * @param string $html Raw post_content HTML.
 * @return array<int, array{q: string, a: string}>
 */
function extract_faq_pairs( string $html ): array {
	$faqs = array();
	if ( trim( $html ) === '' ) {
		return $faqs;
	}

	libxml_use_internal_errors( true );
	$doc = new \DOMDocument();
	$doc->loadHTML(
		'<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>',
		LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
	);
	libxml_clear_errors();

	$xpath = new \DOMXPath( $doc );

	// Strategy 1: <dt>/<dd> definition-list pairs.
	foreach ( $xpath->query( '//dt' ) as $dt ) {
		$dd = $dt->nextSibling;
		while ( $dd && $dd->nodeType !== XML_ELEMENT_NODE ) {
			$dd = $dd->nextSibling;
		}
		if ( $dd && strtolower( $dd->nodeName ) === 'dd' ) {
			$q = trim( $dt->textContent );
			$a = trim( preg_replace( '/\s+/', ' ', $dd->textContent ) );
			if ( $q !== '' && strlen( $a ) > 10 ) {
				$faqs[] = array(
					'q' => $q,
					'a' => $a,
				);
			}
		}
	}
	if ( ! empty( $faqs ) ) {
		return $faqs;
	}

	// Strategy 2: question-shaped headings followed by sibling content up to next heading.
	$heading_levels = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' );
	foreach ( $xpath->query( '//h2 | //h3 | //h4' ) as $heading ) {
		$q = trim( preg_replace( '/\s+/', ' ', $heading->textContent ) );
		if ( $q === '' ) {
			continue;
		}
		$looks_like_question = (bool) preg_match( '/\?\s*$/u', $q )
			|| (bool) preg_match( '/^(what|why|how|when|where|who|is|are|can|do|does)\b/i', $q );
		if ( ! $looks_like_question ) {
			continue;
		}

		$a       = '';
		$sibling = $heading->nextSibling;
		while ( $sibling ) {
			if ( $sibling->nodeType === XML_ELEMENT_NODE
				&& in_array( strtolower( $sibling->nodeName ), $heading_levels, true ) ) {
				break;
			}
			if ( $sibling->nodeType === XML_ELEMENT_NODE ) {
				$a .= ' ' . $sibling->textContent;
			}
			$sibling = $sibling->nextSibling;
		}
		$a = trim( preg_replace( '/\s+/', ' ', $a ) );

		if ( strlen( $a ) > 10 ) {
			$faqs[] = array(
				'q' => $q,
				'a' => $a,
			);
		}
	}

	return $faqs;
}

/**
 * Output JSON-LD structured data schema.
 *
 * @return void
 */
function output_json_ld_schema(): void {
	if ( ! is_feature_enabled( 'json_ld' ) ) {
		return;
	}

	// Auto-suppress when a major SEO plugin is active — they emit their own
	// WebSite/Organization/Article schemas, and shipping ours alongside causes
	// duplicate-schema warnings in Google Search Console.
	if ( active_seo_plugin() !== null ) {
		return;
	}

	$site_url         = home_url( '/' );
	$site_name        = get_bloginfo( 'name' );
	$site_description = get_bloginfo( 'description' );

	// Resolve logo: theme custom logo first, then site icon, then nothing.
	$logo_block     = null;
	$custom_logo_id = (int) get_theme_mod( 'custom_logo' );
	if ( $custom_logo_id ) {
		$img = wp_get_attachment_image_src( $custom_logo_id, 'full' );
		if ( $img ) {
			$logo_block = array(
				'@type'  => 'ImageObject',
				'url'    => $img[0],
				'width'  => (int) $img[1],
				'height' => (int) $img[2],
			);
		}
	}
	if ( ! $logo_block ) {
		$icon = get_site_icon_url( 512 );
		if ( $icon ) {
			$logo_block = array(
				'@type'  => 'ImageObject',
				'url'    => $icon,
				'width'  => 512,
				'height' => 512,
			);
		}
	}

	$organization = array(
		'@type'         => 'Organization',
		'@id'           => $site_url . '#organization',
		'name'          => $site_name,
		'alternateName' => $site_name,
		'url'           => $site_url,
	);
	if ( $logo_block ) {
		$organization['logo'] = $logo_block;
	}

	$schema = array(
		'@context' => 'https://schema.org',
		'@graph'   => array(
			// WebSite schema.
			array(
				'@type'           => 'WebSite',
				'@id'             => $site_url . '#website',
				'url'             => $site_url,
				'name'            => $site_name,
				'description'     => $site_description,
				'inLanguage'      => get_bloginfo( 'language' ) ?: 'en-US',
				'publisher'       => array(
					'@id' => $site_url . '#organization',
				),
				'potentialAction' => array(
					'@type'       => 'SearchAction',
					'target'      => array(
						'@type'       => 'EntryPoint',
						'urlTemplate' => $site_url . '?s={search_term_string}',
					),
					'query-input' => array(
						'@type'         => 'PropertyValueSpecification',
						'valueRequired' => true,
						'valueName'     => 'search_term_string',
					),
				),
			),
			$organization,
		),
	);

	// Add Article schema for singular posts/pages of any public post type
	// that opts into the REST API (per spec).
	if ( is_singular() ) {
		$post_type    = get_post_type();
		$pt_object    = $post_type ? get_post_type_object( $post_type ) : null;
		$is_supported = $pt_object && ! empty( $pt_object->public ) && ! empty( $pt_object->show_in_rest );

		if ( $is_supported ) {
			global $post;
			if ( $post ) {
				setup_postdata( $post );

				$raw_desc    = has_excerpt() ? get_the_excerpt() : wp_strip_all_tags( get_the_content() );
				$description = trim(
					preg_replace(
						'/\s+/',
						' ',
						html_entity_decode( wp_strip_all_tags( $raw_desc ), ENT_QUOTES | ENT_HTML5, 'UTF-8' )
					)
				);
				$description = preg_replace( '/\s*\[(\.\.\.|…)\]\s*$/u', '', $description );

				$article_schema = array(
					'@type'            => 'Article',
					'@id'              => get_permalink() . '#article',
					'headline'         => get_the_title(),
					'description'      => $description,
					'datePublished'    => get_the_date( 'c' ),
					'dateModified'     => get_the_modified_date( 'c' ),
					'author'           => array(
						'@type' => 'Person',
						'name'  => get_the_author(),
					),
					'publisher'        => array(
						'@id' => $site_url . '#organization',
					),
					'image'            => has_post_thumbnail() ? get_the_post_thumbnail_url( get_the_ID(), 'large' ) : null,
					'url'              => get_permalink(),
					'mainEntityOfPage' => array(
						'@type' => 'WebPage',
						'@id'   => get_permalink(),
					),
				);

				$article_schema = array_filter(
					$article_schema,
					function ( $value ) {
						return $value !== null && $value !== '';
					}
				);

				$schema['@graph'][] = $article_schema;
				wp_reset_postdata();
			}
		}
	}

	// Add BreadcrumbList for singular pages and posts (not front page).
	if ( is_singular() && ! is_front_page() ) {
		global $post;
		if ( $post ) {
			$crumbs = array(
				'@type'           => 'BreadcrumbList',
				'itemListElement' => array(),
			);

			$crumbs['itemListElement'][] = array(
				'@type'    => 'ListItem',
				'position' => 1,
				'name'     => 'Home',
				'item'     => $site_url,
			);

			if ( is_single() ) {
				$cats = get_the_category( $post->ID );
				if ( ! empty( $cats ) ) {
					$cat = $cats[0];
					if ( $cat->parent ) {
						$parent = get_category( $cat->parent );
						if ( $parent && ! is_wp_error( $parent ) ) {
							$crumbs['itemListElement'][] = array(
								'@type'    => 'ListItem',
								'position' => 2,
								'name'     => $parent->name,
								'item'     => get_category_link( $parent->term_id ),
							);
						}
					}
					$crumbs['itemListElement'][] = array(
						'@type'    => 'ListItem',
						'position' => count( $crumbs['itemListElement'] ) + 1,
						'name'     => $cat->name,
						'item'     => get_category_link( $cat->term_id ),
					);
				}
			}

			$crumbs['itemListElement'][] = array(
				'@type'    => 'ListItem',
				'position' => count( $crumbs['itemListElement'] ) + 1,
				'name'     => get_the_title(),
				'item'     => get_permalink(),
			);

			$schema['@graph'][] = $crumbs;
		}
	}

	// FAQPage detection on singular content.
	if ( is_singular() ) {
		global $post;
		if ( $post ) {
			$plain        = strtolower( wp_strip_all_tags( $post->post_content ) );
			$faq_detected = (
				strpos( $plain, 'faq' ) !== false
				|| strpos( $plain, 'frequently asked' ) !== false
				|| preg_match( '/q:\s*.+\?\s*\n?\s*a:\s*.+\?/i', $plain )
				|| preg_match( '/what (is|are|do|does|can|will)/i', $plain )
			);

			if ( $faq_detected ) {
				$pairs = extract_faq_pairs( $post->post_content );
				if ( ! empty( $pairs ) ) {
					$main_entity = array();
					foreach ( $pairs as $pair ) {
						$main_entity[] = array(
							'@type'          => 'Question',
							'name'           => $pair['q'],
							'acceptedAnswer' => array(
								'@type' => 'Answer',
								'text'  => $pair['a'],
							),
						);
					}
					$schema['@graph'][] = array(
						'@type'      => 'FAQPage',
						'@id'        => get_permalink() . '#faq',
						'mainEntity' => $main_entity,
					);
				}
			}
		}
	}

	/**
	 * Filter the JSON-LD @graph array before output.
	 *
	 * Allows plugins to add custom Schema.org entries (Product, Recipe, Event,
	 * VideoObject, etc.) or modify the existing WebSite/Organization/Article
	 * entries. The filter receives the full `@graph` array (without the
	 * `@context` wrapper).
	 *
	 * @param array $graph
	 */
	$schema['@graph'] = apply_filters( 'ajaco_json_ld_graph', $schema['@graph'] );

	// JSON-LD is embedded in an HTML <script> element. JSON_HEX_TAG escapes
	// every angle bracket to its \u00XX form so user-controlled values (post
	// titles, excerpts, author names, extracted FAQ text) cannot emit a literal
	// `</script>` and break out of the block. JSON_UNESCAPED_SLASHES is kept so
	// the many URLs in the graph stay readable.
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG ) . '</script>' . "\n";
}
