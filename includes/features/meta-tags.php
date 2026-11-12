<?php
/**
 * Feature: Identity meta tags — canonical + Open Graph basics.
 *
 * Agents (and the scanners that grade for them) look for four identity
 * signals in the raw HTML: a declared language, a canonical URL, og:type and
 * og:image. WordPress core already prints the language attribute and a
 * canonical link on singular views; this fills the rest in: a canonical link
 * on non-singular views (home, archives) and the Open Graph basics
 * everywhere. Auto-suppressed when an SEO plugin is active, exactly like the
 * JSON-LD feature, so there is never a duplicate tag.
 *
 * @package Ajaco
 */

namespace Ajaco;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_head', __NAMESPACE__ . '\\output_meta_tags', 2 );

/**
 * Print the tags.
 *
 * @return void
 */
function output_meta_tags(): void {
	if ( ! is_feature_enabled( 'meta_tags' ) ) {
		return;
	}
	if ( null !== active_seo_plugin() ) {
		return;
	}
	if ( is_admin() || is_feed() || is_404() ) {
		return;
	}

	$url = current_canonical_url();

	// Core's rel_canonical() covers singular views; add one for the rest so
	// the homepage and archives identify themselves too.
	if ( ! is_singular() && '' !== $url ) {
		echo '<link rel="canonical" href="' . esc_url( $url ) . '" />' . "\n";
	}

	$type  = is_singular( 'post' ) ? 'article' : 'website';
	$title = wp_get_document_title();
	$image = site_identity_image();

	echo '<meta property="og:type" content="' . esc_attr( $type ) . '" />' . "\n";
	echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '" />' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
	if ( '' !== $url ) {
		echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";
	}
	if ( '' !== $image ) {
		echo '<meta property="og:image" content="' . esc_url( $image ) . '" />' . "\n";
	}
	$description = is_singular() ? get_the_excerpt() : get_bloginfo( 'description' );
	$description = markdown_safe_text( (string) $description );
	if ( '' !== $description ) {
		echo '<meta property="og:description" content="' . esc_attr( $description ) . '" />' . "\n";
	}
}

/**
 * Canonical URL of the current view ('' when it cannot be determined).
 *
 * @return string
 */
function current_canonical_url(): string {
	if ( is_front_page() ) {
		return home_url( '/' );
	}
	if ( is_singular() ) {
		$permalink = get_permalink();
		return is_string( $permalink ) ? $permalink : '';
	}
	if ( is_home() ) {
		$posts_page = (int) get_option( 'page_for_posts' );
		$link       = $posts_page > 0 ? get_permalink( $posts_page ) : home_url( '/' );
		return is_string( $link ) ? $link : '';
	}
	if ( is_category() || is_tag() || is_tax() ) {
		$link = get_term_link( get_queried_object() );
		return is_string( $link ) ? $link : '';
	}
	if ( is_post_type_archive() ) {
		$link = get_post_type_archive_link( (string) get_query_var( 'post_type' ) );
		return is_string( $link ) ? $link : '';
	}
	if ( is_author() ) {
		$link = get_author_posts_url( (int) get_query_var( 'author' ) );
		return is_string( $link ) ? $link : '';
	}
	return '';
}

/**
 * Best available image for the current view: featured image on singular,
 * else the theme's custom logo, else the site icon.
 *
 * @return string
 */
function site_identity_image(): string {
	if ( is_singular() ) {
		$thumb = get_the_post_thumbnail_url( null, 'large' );
		if ( is_string( $thumb ) && '' !== $thumb ) {
			return $thumb;
		}
	}
	$logo_id = (int) get_theme_mod( 'custom_logo' );
	if ( $logo_id > 0 ) {
		$logo = wp_get_attachment_image_url( $logo_id, 'full' );
		if ( is_string( $logo ) && '' !== $logo ) {
			return $logo;
		}
	}
	$icon = get_site_icon_url( 512 );
	return is_string( $icon ) ? $icon : '';
}
